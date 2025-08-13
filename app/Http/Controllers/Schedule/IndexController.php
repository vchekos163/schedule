<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Services\ScheduleGenerator;
use App\Jobs\OptimizeTeachers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Room;
use App\Models\Teacher;
use App\Models\Subject;

class IndexController extends Controller
{
    public function student(int $user_id)
    {
        // Find the user manually
        $user = User::findOrFail($user_id);

        // Eager load subject & room for performance
        $lessons = $user->lessons()->with(['subject', 'teachers' ,'room'])->get();

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->name,
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
                'extendedProps' => [
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->name,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                ],
            ];
        });

        return view('schedule.index.student', [
            'events' => $events,
            'user' => $user,
        ]);
    }

    public function teachers()
    {
        $lessons = Lesson::with(['subject', 'teachers.user'])->get();

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->code,
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
                'extendedProps' => [
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->code,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                ],
            ];
        });

        return view('schedule.index.teachers', [
            'teacher' => null,
            'events' => $events,
            'subjects' => Subject::with('teachers.user')->get(),
        ]);
    }

    public function teacher(int $teacher_id)
    {
        $teacher = Teacher::findOrFail($teacher_id);

        $lessons = $teacher->lessons()
            ->with(['subject', 'room', 'teachers.user'])
            ->get();

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->code,
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
                'extendedProps' => [
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->code,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                ],
            ];
        });

        return view('schedule.index.teachers', [
            'teacher' => $teacher,
            'events' => $events,
            'subjects' => $teacher->subjects()->with('teachers.user')->get(),
        ]);
    }

    public function optimizeTeachers(string $start)
    {
        $jobId = (string) Str::uuid();
        OptimizeTeachers::dispatch($start, $jobId);

        return response()->json(['jobId' => $jobId]);
    }

    public function getOptimizedTeachers(string $jobId)
    {
        $data = Cache::get("optimize_teachers_{$jobId}");

        if (!$data) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json($data);
    }

    public function saveOptimizedTeachers(Request $request)
    {
        $newSchedule = $request->input('lessons', []);

        DB::transaction(function () use ($newSchedule) {
            foreach ($newSchedule as $lessonData) {
                $lesson = null;

                if (!empty($lessonData['lesson_id'])) {
                    $lesson = Lesson::find($lessonData['lesson_id']);
                }

                if (!$lesson) {
                    $lesson = new Lesson();
                }

                $lesson->reason = $lessonData['reason'];
                $lesson->room_id = $lessonData['room_id'];
                $lesson->date = $lessonData['date'];
                $lesson->start_time = $lessonData['start_time'];
                $lesson->end_time = $lessonData['end_time'];
                $lesson->save();
            }
        });

        return response()->json(['status' => 'saved']);
    }

    public function optimize($user_id)
    {
        $student = User::with('subjects')->findOrFail($user_id);

        $teachers = User::role('teacher')->with(['subjects', 'teacher', 'lessons'])->get();
        $students = collect([$student]);
        $rooms = Room::all();

        $existingSchedule = Lesson::with(['teachers', 'users', 'room'])->get();

        $scheduler = new ScheduleGenerator(
            $teachers->toArray(),
            $students->toArray(),
            $rooms->toArray(),
            $existingSchedule->toArray()
        );

        $newSchedule = $scheduler->generate();
        $newSchedule = $newSchedule['lessons'];

        DB::transaction(function () use ($newSchedule) {
            foreach ($newSchedule as $lessonData) {
                $lesson = null;

                if (!empty($lessonData['lesson_id'])) {
                    $lesson = Lesson::find($lessonData['lesson_id']);
                }

                if (!$lesson) {
                    $lesson = new Lesson();
                }

                $lesson->reason = $lessonData['reason'];
                $lesson->subject_id = $lessonData['subject_id'];
                $lesson->room_id = $lessonData['room_id'];
                $lesson->date = $lessonData['date'];
                $lesson->start_time = $lessonData['start_time'];
                $lesson->end_time = $lessonData['end_time'];
                $lesson->save();

                // Attach students
                if (!empty($lessonData['student_ids'])) {
                    $lesson->users()->sync($lessonData['student_ids']);
                }

                // Attach teachers
                if (!empty($lessonData['teacher_ids'])) {
                    $teacherIds = collect($lessonData['teacher_ids'])
                        ->map(fn($userId) => Teacher::firstOrCreate(['user_id' => $userId])->id)
                        ->toArray();

                    $lesson->teachers()->sync($teacherIds);
                }
            }
        });

        return redirect('/schedule/index/student/user_id/'.$user_id)->with('message', 'Schedule optimized and saved!');
    }

    // In the same class as optimizeTeachers()

    private function glueDayNoGapsShort(array $times): ?string
    {
        if (empty($times)) return null;

        // normalize -> unique -> sorted "HH:MM"
        $times = array_values(array_unique(array_map(function ($t) {
            $t = trim((string)$t);
            if ($t === '') return null;
            if (preg_match('/^\d:\d{2}$/', $t)) $t = '0' . $t; // pad
            return $t;
        }, $times)));
        $times = array_filter($times);
        if (!$times) return null;

        sort($times, SORT_STRING);
        $startHour = (int)substr(reset($times), 0, 2);
        $endHour   = (int)substr(end($times), 0, 2);

        return "{$startHour}-{$endHour}";
    }

    private function compressAvailabilityNoGaps($availability): array
    {
        if (!is_array($availability)) return [];

        // Map full day names to short names
        $dayShort = [
            'monday'    => 'mon',
            'tuesday'   => 'tue',
            'wednesday' => 'wed',
            'thursday'  => 'thu',
            'friday'    => 'fri',
            'saturday'  => 'sat',
            'sunday'    => 'sun',
        ];

        $out = [];
        foreach ($availability as $day => $slots) {
            $shortDay = $dayShort[strtolower($day)] ?? strtolower(substr($day, 0, 3));
            $times = [];
            if (is_array($slots)) {
                foreach ($slots as $period => $state) {
                    if (strtoupper($state) !== 'UNAVAILABLE') {
                        $start = config("periods.$period.start");
                        if ($start) {
                            $times[] = $start;
                        }
                    }
                }
            }
            $out[$shortDay] = $this->glueDayNoGapsShort($times);
        }
        return $out;
    }

}
