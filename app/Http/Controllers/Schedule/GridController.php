<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Room;
use App\Models\Version;
use Illuminate\Support\Facades\Config;

class GridController extends Controller
{
    public function teachers(?int $teacher_id = null)
    {
        $teacher  = $teacher_id ? Teacher::findOrFail($teacher_id) : null;
        $periods  = Config::get('periods');
        $versions = Version::all();

        return view('schedule.grid.teachers', [
            'teacher'  => $teacher,
            'periods'  => $periods,
            'versions' => $versions,
        ]);
    }

    public function teachersData(int $version_id, ?int $teacher_id = null)
    {
        if ($teacher_id) {
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);

            $lessons = $teacher->lessons()
                ->with(['subject', 'room', 'teachers.user', 'users'])
                ->where('version_id', $version_id)
                ->get();

            $subjects = $teacher->subjects->map(function ($subject) use ($teacher, $version_id) {
                $lessonsCount = $teacher->lessons()
                    ->where('subject_id', $subject->id)
                    ->where('version_id', $version_id)
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
                ->where('version_id', $version_id)
                ->get()
                ->filter(fn($lesson) => optional($lesson->subject)->code !== 'IND');

            $subjects = Subject::with('teachers')->get()->map(function ($subject) use ($version_id) {
                $lessonsCount = Lesson::where('subject_id', $subject->id)
                    ->where('version_id', $version_id)
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
                'day' => $lesson->day,
                'period' => $lesson->period,
                'reason' => $lesson->reason,
                'room' => $lesson->room->code,
                'fixed' => $lesson->fixed,
                'teachers' => $lesson->teachers
                    ->map(fn($teacher) => $teacher->user->name)
                    ->join(', '),
                'students' => $lesson->users
                    ->map(fn($user) => [
                        'id' => $user->id,
                        'name' => $user->name . ($user->class ? ' (' . $user->class . ')' : ''),
                    ])->values()->all(),
            ];
        });

        $students = User::role('student')
            ->orderBy('class')
            ->orderBy('name')
            ->get(['id', 'name', 'class']);

        $periodKeys = array_keys(Config::get('periods'));

        $weekLessons = Lesson::with('users')
            ->where('version_id', $version_id)
            ->get();

        $busy = [];
        foreach ($weekLessons as $lesson) {
            foreach ($lesson->users as $user) {
                $busy[$lesson->day][$lesson->period][] = $user->id;
            }
        }

        $free = [];
        for ($day = 1; $day <= 5; $day++) {
            foreach ($periodKeys as $p) {
                $busyIds = $busy[$day][$p] ?? [];
                $free[$day][$p] = $students
                    ->filter(fn($s) => !in_array($s->id, $busyIds))
                    ->map(fn($s) => [
                        'id' => $s->id,
                        'name' => $s->name . ($s->class ? ' (' . $s->class . ')' : ''),
                    ])->values()->all();
            }
        }

        return response()->json([
            'events' => $events,
            'subjects' => $subjects,
            'free' => $free,
        ]);
    }

    public function rooms(?int $room_id = null)
    {
        $room     = $room_id ? Room::findOrFail($room_id) : null;
        $periods  = Config::get('periods');
        $versions = Version::all();

        return view('schedule.grid.rooms', [
            'room'     => $room,
            'periods'  => $periods,
            'versions' => $versions,
        ]);
    }

    public function roomsData(int $version_id, ?int $room_id = null)
    {
        if ($room_id) {
            $room    = Room::findOrFail($room_id);
            $lessons = $room->lessons()
                ->with(['subject', 'room', 'teachers.user'])
                ->where('version_id', $version_id)
                ->get();
        } else {
            $lessons = Lesson::with(['subject', 'room', 'teachers.user'])
                ->where('version_id', $version_id)
                ->get();
        }

        $events = $lessons->map(function ($lesson) {
            return [
                'id'      => $lesson->id,
                'title'   => $lesson->subject->code,
                'color'   => $lesson->subject->color,
                'day'     => $lesson->day,
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
        $user     = User::findOrFail($user_id);
        $periods  = Config::get('periods');
        $versions = \App\Models\Version::all();

        return view('schedule.grid.student', [
            'user'     => $user,
            'periods'  => $periods,
            'versions' => $versions,
        ]);
    }

    public function studentData(int $version_id, int $user_id)
    {
        $user = User::with('subjects')->findOrFail($user_id);

        $lessons = $user->lessons()
            ->with(['subject', 'room', 'teachers.user'])
            ->where('version_id', $version_id)
            ->get();

        $subjects = $user->subjects
            ->map(function ($subject) use ($user, $version_id) {
                $lessonsCount = $user->lessons()
                    ->where('subject_id', $subject->id)
                    ->where('version_id', $version_id)
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
                'day' => $lesson->day,
                'period' => $lesson->period,
                'reason' => $lesson->reason,
                'room' => $lesson->room->code,
                'teachers' => $lesson->teachers
                    ->map(fn($teacher) => $teacher->user->name)
                    ->join(', '),
                'subject_id' => $lesson->subject_id,
            ];
        });

        $subjectIds = $subjects->pluck('id');

        $unassigned = Lesson::with(['subject', 'room', 'teachers.user'])
            ->where('version_id', $version_id)
            ->whereIn('subject_id', $subjectIds)
            ->whereDoesntHave('users')
            ->get()
            ->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->subject->code,
                    'color' => $lesson->subject->color,
                    'day' => $lesson->day,
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
