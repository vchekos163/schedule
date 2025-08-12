<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Services\ScheduleGenerator;
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
                'title' => $lesson->subject->codesubject->color,
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
        $weekStart = Carbon::parse($start)->startOfWeek(Carbon::MONDAY);//->addWeek();
        $weekEnd   = $weekStart->copy()->addDays(4);

        // TEACHERS: id, name, availability, limits, subjects (id, code, priority)
        $existingSchedule = Lesson::select('id','date','start_time','end_time','room_id','subject_id')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with([
                'subject:id,priority',
                'room:id,capacity',
                'teachers:id,availability,max_lessons,max_gaps',
            ])
            ->get();

// 2) Build subject_id -> [user_ids...] from subject_user
        $subjectIds = $existingSchedule->pluck('subject_id')->filter()->unique()->values();

        $subjectToStudents = DB::table('subject_user')
            ->whereIn('subject_id', $subjectIds)
            ->select('subject_id', 'user_id')
            ->get()
            ->groupBy('subject_id')
            ->map(fn($rows) => $rows->pluck('user_id')->unique()->values()->all());

// 3) Produce the single lessons structure

        $lessons = $existingSchedule->map(function ($l) use ($subjectToStudents) {
            return [
                'id'          => $l->id,
//                'date'        => $l->date,
//                'start_time'  => $l->start_time,
//                'end_time'    => $l->end_time,
/*
                'room'        => $l->room ? [
                    'id'       => $l->room->id,
                    'capacity' => $l->room->capacity,
                ] : null,
*/
                'priority'    => optional($l->subject)->priority,
                'teachers'    => $l->teachers->map(function ($t) {
                    return [
                        'id'           => $t->id,
                        'availability' => $this->compressAvailabilityNoGaps($t->availability ?? []),
                        'max_lessons'  => $t->max_lessons ?? null,
                        'max_gaps'     => $t->max_gaps ?? null,
                    ];
                })->values()->all(),
                'user_ids'    => $subjectToStudents->get($l->subject_id, []),
            ];
        })->values();

        $rooms = Room::select('id', 'purpose', 'capacity')->get();

        // Pass trimmed arrays to the generator / LLM
        if (count($existingSchedule->toArray())) {
            $scheduler = new ScheduleGenerator(
                $lessons->toArray(),
                $rooms->toArray()
            );

            $newSchedule = $scheduler->generate();
            $newSchedule = $newSchedule['lessons'] ?? [];

            // Build event objects for front-end preview
            $events = collect($newSchedule)->map(function ($lessonData) {
                $id = $lessonData['lesson_id'] ?? null;
                $event = [
                    'id'    => $id,
                    'title' => '',
                    'color' => '#64748b',
                    'start' => $lessonData['date'] . 'T' . $lessonData['start_time'],
                    'end'   => $lessonData['date'] . 'T' . $lessonData['end_time'],
                    'extendedProps' => [
                        'reason'   => $lessonData['reason'] ?? '',
                        'room'     => '',
                        'teachers' => '',
                    ],
                ];

                if ($id) {
                    $lesson = Lesson::with(['subject', 'room', 'teachers.user'])->find($id);
                    if ($lesson) {
                        $event['title'] = $lesson->subject->code ?? ($lesson->subject->name ?? '');
                        $event['color'] = $lesson->subject->color ?? '#64748b';
                        $event['extendedProps']['room'] = $lesson->room->code ?? ($lesson->room->name ?? '');
                        $event['extendedProps']['teachers'] = $lesson->teachers
                            ->map(fn($t) => $t->user->name)
                            ->join(', ');
                    }
                }

                return $event;
            })->values();

            return response()->json([
                'lessons' => $newSchedule,
                'events'  => $events,
            ]);
        }

        return response()->json(['lessons' => [], 'events' => []]);
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
        foreach ($availability as $day => $times) {
            $shortDay = $dayShort[strtolower($day)] ?? strtolower(substr($day, 0, 3));
            $out[$shortDay] = $this->glueDayNoGapsShort(is_array($times) ? $times : []);
        }
        return $out;
    }

}
