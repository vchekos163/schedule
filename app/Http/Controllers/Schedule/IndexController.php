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

    public function teachers(?int $teacher_id = null)
    {
        $teacher = $teacher_id ? Teacher::findOrFail($teacher_id) : null;

        return view('schedule.index.teachers', [
            'teacher' => $teacher,
        ]);
    }

    public function teachersData(string $start, ?int $teacher_id = null)
    {
        $startDate = Carbon::parse($start)->startOfWeek();
        $endDate   = (clone $startDate)->endOfWeek();

        if ($teacher_id) {
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);

            $lessons = $teacher->lessons()
                ->with(['subject', 'room', 'teachers.user'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $subjects = $teacher->subjects->map(function ($subject) use ($teacher, $startDate, $endDate) {
                $lessonsCount = $teacher->lessons()
                    ->where('subject_id', $subject->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count();

                $remaining = max(0, ($subject->pivot->quantity ?? 0) - $lessonsCount);

                return [
                    'id'       => $subject->id,
                    'name'     => $subject->name,
                    'code'     => $subject->code,
                    'color'    => $subject->color,
                    'quantity' => $remaining,
                ];
            })->filter(fn ($row) => $row['quantity'] > 0)->values();
        } else {
            $lessons = Lesson::with(['subject', 'room', 'teachers.user'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $subjects = Subject::with('teachers')->get()->map(function ($subject) use ($startDate, $endDate) {
                $lessonsCount = Lesson::where('subject_id', $subject->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count();

                $totalQty = $subject->teachers->sum(fn ($t) => $t->pivot->quantity ?? 0);
                $remaining = max(0, $totalQty - $lessonsCount);

                return [
                    'id'       => $subject->id,
                    'name'     => $subject->name,
                    'code'     => $subject->code,
                    'color'    => $subject->color,
                    'quantity' => $remaining,
                ];
            })->filter(fn ($row) => $row['quantity'] > 0)->values();
        }

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->code,
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
                'date' => $lesson->date,
                'period' => $lesson->period,
                'extendedProps' => [
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->code,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                ],
            ];
        });

        return response()->json([
            'events' => $events,
            'subjects' => $subjects,
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

            if (!is_array($slots)) continue;

            ksort($slots, SORT_NUMERIC);

            $currentState = null;
            $rangeStart   = null;
            $prevPeriod   = null;

            foreach ($slots as $period => $state) {
                $periodNumber = (int) $period;
                $state = strtoupper((string) $state);

                if ($state === 'UNAVAILABLE') {
                    if ($currentState !== null) {
                        $out[] = "{$shortDay}:{$rangeStart}-{$prevPeriod}:{$currentState}";
                        $currentState = null;
                        $rangeStart   = null;
                        $prevPeriod   = null;
                    }
                    continue;
                }

                if ($currentState === $state && $prevPeriod !== null && $periodNumber === $prevPeriod + 1) {
                    $prevPeriod = $periodNumber;
                    continue;
                }

                if ($currentState !== null) {
                    $out[] = "{$shortDay}:{$rangeStart}-{$prevPeriod}:{$currentState}";
                }

                $currentState = $state;
                $rangeStart   = $periodNumber;
                $prevPeriod   = $periodNumber;
            }

            if ($currentState !== null) {
                $out[] = "{$shortDay}:{$rangeStart}-{$prevPeriod}:{$currentState}";
            }
        }

        return $out;
    }

}
