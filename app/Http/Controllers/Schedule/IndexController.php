<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Services\ScheduleGenerator;
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
                'title' => $lesson->subject->code . ' - ' . $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
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
                'title' => $lesson->subject->code . ' - ' . $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
            ];
        });

        return view('schedule.index.teachers', [
            'teacher' => $teacher,
            'events' => $events,
            'subjects' => $teacher->subjects()->with('teachers.user')->get(),
        ]);
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
}
