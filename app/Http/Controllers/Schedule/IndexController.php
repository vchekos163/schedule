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

    public function optimizeTeachers(Request $request, string $start = null)
    {
        $start = $request->input('start', $start);
        $prompt = $request->input('prompt', '');
        $jobId = (string) Str::uuid();

        if ($prompt !== '') {
            Cache::put("optimize_teachers_prompt_{$jobId}", $prompt, now()->addHour());
        }

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

    public function saveOptimizedTeachers(string $jobId)
    {
        $data = Cache::get("optimize_teachers_{$jobId}");
        $newSchedule = $data['lessons'];

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
                $lesson->period = $lessonData['period'];
                $lesson->save();

                $studentIds = array_unique(array_map('intval', $lessonData['student_ids'] ?? []));

                if (empty($studentIds)) {
                    $lesson->users()->sync([]);
                } else {
                    $validIds = User::query()
                        ->whereIn('id', $studentIds)
                        ->pluck('id')
                        ->all();

                    $lesson->users()->sync($validIds);
                }
            }
        });

        return response()->json(['status' => 'saved']);
    }

    public function optimize($user_id)
    {
        $student = User::with('subjects')->findOrFail($user_id);

        $rooms = Room::all();
        $existingSchedule = Lesson::with(['teachers', 'users', 'room'])->get();

        $students = collect([$student])->map(function ($s) {
            return [
                'id'       => $s->id,
                'subjects' => $s->subjects->map(fn($sub) => [
                    'id'       => $sub->id,
                    'quantity' => $sub->pivot->quantity ?? 0,
                ])->toArray(),
            ];
        })->values()->toArray();

        $scheduler = new ScheduleGenerator(
            $existingSchedule->toArray(),
            $rooms->toArray(),
            [],
            $students
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
}
