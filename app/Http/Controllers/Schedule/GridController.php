<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class GridController extends Controller
{
    public function teachers(?int $teacher_id = null)
    {
        $teacher = $teacher_id ? Teacher::findOrFail($teacher_id) : null;
        $periods = Config::get('periods');

        return view('schedule.grid.teachers', [
            'teacher' => $teacher,
            'periods' => $periods,
        ]);
    }

    public function teachersData(string $start, ?int $teacher_id = null)
    {
        $startDate = Carbon::parse($start)->startOfWeek();
        $endDate   = (clone $startDate)->endOfWeek();

        if ($teacher_id) {
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);

            $lessons = $teacher->lessons()
                ->with(['subject', 'room', 'teachers.user', 'users'])
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
            $lessons = Lesson::with(['subject', 'room', 'teachers.user', 'users'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get()
                ->filter(fn($lesson) => optional($lesson->subject)->code !== 'IND');

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
                'date' => $lesson->date,
                'period' => $lesson->period,
                'reason' => $lesson->reason,
                'room' => $lesson->room->code.' ('.$lesson->room->capacity.')',
                'teachers' => $lesson->teachers
                    ->map(fn($teacher) => $teacher->user->name)
                    ->join(', '),
                'students' => $lesson->users
                    ->map(fn($user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ])->values()->all(),
            ];
        });

        return response()->json([
            'events' => $events,
            'subjects' => $subjects,
        ]);
    }

    public function rooms(?int $room_id = null)
    {
        $room    = $room_id ? Room::findOrFail($room_id) : null;
        $periods = Config::get('periods');

        return view('schedule.grid.rooms', [
            'room'    => $room,
            'periods' => $periods,
        ]);
    }

    public function roomsData(string $start, ?int $room_id = null)
    {
        $startDate = Carbon::parse($start)->startOfWeek();
        $endDate   = (clone $startDate)->endOfWeek();

        if ($room_id) {
            $room    = Room::findOrFail($room_id);
            $lessons = $room->lessons()
                ->with(['subject', 'room', 'teachers.user'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();
        } else {
            $lessons = Lesson::with(['subject', 'room', 'teachers.user'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();
        }

        $events = $lessons->map(function ($lesson) {
            return [
                'id'      => $lesson->id,
                'title'   => $lesson->subject->code,
                'color'   => $lesson->subject->color,
                'date'    => $lesson->date,
                'period'  => $lesson->period,
                'reason'  => $lesson->reason,
                'room'    => $lesson->room->code,
                'teachers' => $lesson->teachers
                    ->map(fn($teacher) => $teacher->user->name)
                    ->join(', '),
            ];
        });

        return response()->json([
            'events' => $events,
        ]);
    }

    public function student(int $user_id)
    {
        $user = User::findOrFail($user_id);
        $periods = Config::get('periods');

        return view('schedule.grid.student', [
            'user' => $user,
            'periods' => $periods,
        ]);
    }

    public function studentData(string $start, int $user_id)
    {
        $startDate = Carbon::parse($start)->startOfWeek();
        $endDate   = (clone $startDate)->endOfWeek();

        $user = User::with('subjects')->findOrFail($user_id);

        $lessons = $user->lessons()
            ->with(['subject', 'room', 'teachers.user'])
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $subjects = $user->subjects
            ->filter(fn($s) => $s->code === 'IND')
            ->map(function ($subject) use ($user, $startDate, $endDate) {
                $lessonsCount = $user->lessons()
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

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->code,
                'color' => $lesson->subject->color,
                'date' => $lesson->date,
                'period' => $lesson->period,
                'reason' => $lesson->reason,
                'room' => $lesson->room->code,
                'teachers' => $lesson->teachers
                    ->map(fn($teacher) => $teacher->user->name)
                    ->join(', '),
            ];
        });

        $subjectIds = $subjects->pluck('id');

        $unassigned = Lesson::with(['subject', 'room', 'teachers.user'])
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('subject_id', $subjectIds)
            ->whereDoesntHave('users')
            ->get()
            ->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->subject->code,
                    'color' => $lesson->subject->color,
                    'date' => $lesson->date,
                    'period' => $lesson->period,
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->code,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                    'subject_id' => $lesson->subject_id,
                ];
            });

        return response()->json([
            'events' => $events,
            'subjects' => $subjects,
            'unassigned' => $unassigned,
        ]);
    }
}
