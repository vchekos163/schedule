<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Subject;
use App\Models\Room;
use App\Models\Teacher;
use Carbon\Carbon;

class LessonController extends Controller
{
    public function createFromSubjectPeriod($subject_id, $date, $period)
    {
        // Load teachers + their users for the title
        $subject = Subject::with('teachers.user')->findOrFail($subject_id);

        $room = Room::first();

        $slot = config("periods.$period");
        $start_time = $slot['start'];
        $end_time   = $slot['end'];

        $lesson = Lesson::create([
            'subject_id' => $subject_id,
            'room_id' => $room->id,
            'date' => $date,
            'period' => $period,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);

        // Subject->teachers are Teacher models → just pluck their IDs
        $teacherIds = $subject->teachers->pluck('id')->unique()->values()->all();

        // Avoid duplicates if lesson already has some teachers
        $lesson->teachers()->syncWithoutDetaching($teacherIds);

        return response()->json([
            'id' => $lesson->id,
            'title' => $lesson->subject->code,
            'color' => $lesson->subject->color,
            'room' => $room->code,
            'fixed' => $lesson->fixed,
            'teachers' => $subject->teachers
                ->map(fn($teacher) => $teacher->user->name)
                ->join(', '),
            'students' => [],
            'date' => $lesson->date,
            'period' => $lesson->period,
        ]);
    }

    public function createFromSubjectPeriodStudent($subject_id, $date, $period, $user_id)
    {
        $subject = Subject::with('teachers.user')->findOrFail($subject_id);
        $slot    = config("periods.$period");
        $user    = User::findOrFail($user_id);

        // If the subject is IND, always create a brand new lesson
        if ($subject->code === 'IND') {
            $room       = Room::where('code', 'BIBL')->firstOrFail();
            $start_time = $slot['start'];
            $end_time   = $slot['end'];

            $lesson = Lesson::create([
                'subject_id' => $subject_id,
                'room_id'    => $room->id,
                'date'       => $date,
                'period'     => $period,
                'start_time' => $start_time,
                'end_time'   => $end_time,
            ]);

            $teacherIds = $subject->teachers->pluck('id')->unique()->values()->all();
            $lesson->teachers()->syncWithoutDetaching($teacherIds);
            $lesson->users()->syncWithoutDetaching([$user_id]);

            return response()->json([
                'id'     => $lesson->id,
                'title'  => $lesson->subject->code,
                'color'  => $lesson->subject->color,
                'room'   => $room->code,
                'fixed'  => $lesson->fixed,
                'teachers' => $subject->teachers
                    ->map(fn($teacher) => $teacher->user->name)
                    ->join(', '),
                'date'   => $lesson->date,
                'period' => $lesson->period,
                'reason' => $lesson->reason,
            ]);
        }

        // For non-IND subjects, attach the student to an existing lesson
        $lesson = Lesson::with(['subject', 'room', 'teachers.user'])
            ->where('subject_id', $subject_id)
            ->where('date', $date)
            ->where('period', $period)
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        $lesson->users()->syncWithoutDetaching([$user_id]);

        return response()->json([
            'id'     => $lesson->id,
            'title'  => $lesson->subject->code,
            'color'  => $lesson->subject->color,
            'room'   => $lesson->room->code,
            'teachers' => $lesson->teachers
                ->map(fn($teacher) => $teacher->user->name)
                ->join(', '),
            'date'   => $lesson->date,
            'period' => $lesson->period,
            'reason' => $lesson->reason,
            'fixed'  => $lesson->fixed,
        ]);
    }

    public function autoFillTeacher(int $teacher_id, string $start)
    {
        $periods = config('periods');
        $room    = Room::firstOrFail();

        // next Monday from the passed date
        $weekStartDate = Carbon::parse($start)
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();

        if ($teacher_id === 0) {
            Teacher::with('subjects')->chunkById(100, function ($teachers) use ($weekStartDate, $periods, $room) {
                foreach ($teachers as $teacher) {
                    $this->fillSimpleForTeacher($teacher, $weekStartDate, $periods, $room);
                }
            });
        } else {
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);
            $this->fillSimpleForTeacher($teacher, $weekStartDate, $periods, $room);
        }

        return redirect()->back()->with('message', 'Lessons generated.');
    }

    /**
     * Generate lessons based on fixed periods, Mon–Fri, no availability checks.
     */
    private function fillSimpleForTeacher(Teacher $teacher, string $weekStartDate, array $periods, Room $room): void
    {
        $cursorDate  = $weekStartDate;
        $periodKeys  = array_keys($periods);
        $periodIndex = 0;

        foreach ($teacher->subjects as $subject) {
            $qty = max(1, (int) ($subject->pivot->quantity ?? 1));

            for ($i = 0; $i < $qty; $i++) {
                if ($periodIndex >= count($periodKeys)) {
                    $cursorDate = Carbon::parse($cursorDate)->addDay()->toDateString();
                    while (in_array(Carbon::parse($cursorDate)->dayOfWeekIso, [6, 7])) {
                        $cursorDate = Carbon::parse($cursorDate)->addDay()->toDateString();
                    }
                    $periodIndex = 0;
                }

                $p        = $periodKeys[$periodIndex];
                $slotStart = $periods[$p]['start'];
                $slotEnd   = $periods[$p]['end'];

                $lesson = Lesson::create([
                    'subject_id' => $subject->id,
                    'room_id'    => $room->id,
                    'date'       => $cursorDate,
                    'period'     => $p,
                    'start_time' => $slotStart,
                    'end_time'   => $slotEnd,
                ]);

                $lesson->teachers()->attach($teacher->id);
                $periodIndex++;
            }
        }
    }

    public function update(int $lesson_id, $date, $period)
    {
        $lesson = Lesson::findOrFail($lesson_id);

        $slot = config("periods.$period");

        $lesson->update([
            'date' => $date,
            'period' => $period,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
        ]);

        return response()->json([
            'status' => 'success',
            'lesson' => $lesson,
        ]);
    }

    public function setFixed(int $lesson_id, int $fixed)
    {
        $lesson = Lesson::findOrFail($lesson_id);
        $lesson->fixed = (bool) $fixed;
        $lesson->save();

        return response()->json([
            'status' => 'success',
            'fixed' => $lesson->fixed,
        ]);
    }

    public function delete(int $lesson_id, ?int $user_id = null)
    {
        $lesson = Lesson::findOrFail($lesson_id);

        // If a user id is provided, detach the student first
        if ($user_id !== null) {
            $lesson->users()->detach($user_id);

            // remove the lesson entirely when no students remain
            if ($lesson->users()->count() === 0) {
                $lesson->delete();
            }
        } else {
            $lesson->delete();
        }

        return response()->json(['success' => true]);
    }

    public function assignToStudent(int $lesson_id, int $user_id)
    {
        $lesson = Lesson::with(['subject', 'room', 'teachers.user'])->findOrFail($lesson_id);
        $lesson->users()->syncWithoutDetaching([$user_id]);

        return response()->json([
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
        ]);
    }

    public function assignStudentLessons(string $start, int $user_id = 0)
    {
        $startDate = Carbon::parse($start)->startOfWeek(Carbon::MONDAY);
        $endDate   = (clone $startDate)->endOfWeek(Carbon::SUNDAY);

        $usersQuery = User::with('subjects');

        if ($user_id) {
            $usersQuery->where('id', $user_id);
        } else {
            $usersQuery->role('student');
        }

        $users = $usersQuery->get();

        foreach ($users as $user) {
            // Build a set of already occupied slots: "Y-m-d|period"
            $occupied = $user->lessons()
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get(['date', 'period'])
                ->map(fn($l) => $l->date . '|' . $l->period)
                ->all();
            $occupied = array_fill_keys($occupied, true);

            foreach ($user->subjects as $subject) {
                // How many we still need this week for this subject
                $already = $user->lessons()
                    ->where('subject_id', $subject->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count();

                $needed = max(0, ($subject->pivot->quantity ?? 0) - $already);
                if ($needed <= 0) {
                    continue;
                }

                // Candidate lessons for this subject this week, that the student isn’t already attached to
                $candidates = Lesson::where('subject_id', $subject->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->whereDoesntHave('users', fn($q) => $q->where('users.id', $user->id))
                    ->orderBy('date')
                    ->orderBy('period')
                    ->get(['id','date','period']);

                if ($candidates->isEmpty()) {
                    continue;
                }

                $toAttach = [];

                // Pass 1: pick only non-conflicting slots
                foreach ($candidates as $lesson) {
                    if ($needed <= 0) break;
                    $key = $lesson->date . '|' . $lesson->period;
                    if (!isset($occupied[$key])) {
                        $toAttach[] = $lesson->id;
                        $occupied[$key] = true; // block this slot for any subject
                        $needed--;
                    }
                }

                // Pass 2 (fallback): if still needed, allow conflicts
                if ($needed > 0) {
                    foreach ($candidates as $lesson) {
                        if ($needed <= 0) break;
                        // skip ones we already chose in pass 1
                        if (in_array($lesson->id, $toAttach, true)) continue;

                        $toAttach[] = $lesson->id;
                        // (we do NOT add to $occupied to allow same slot if unavoidable)
                        $needed--;
                    }
                }

                // Attach in bulk (avoids duplicates)
                if (!empty($toAttach)) {
                    $user->lessons()->attach($toAttach);
                }
            }
        }

        return back()->with('message', 'Lessons assigned.');
    }

    public function unassignStudentLessons(string $start, int $user_id = 0)
    {
        $startDate = Carbon::parse($start)->startOfWeek(Carbon::MONDAY);
        $endDate   = (clone $startDate)->endOfWeek(Carbon::SUNDAY);

        $usersQuery = User::with('subjects');
        if ($user_id > 0) {
            $usersQuery->where('id', $user_id);
        } else {
            $usersQuery->role('student');
        }

        $users = $usersQuery->get();

        foreach ($users as $user) {
            $required = $user->subjects->mapWithKeys(fn($s) => [$s->id => $s->pivot->quantity ?? 0]);

            $lessons = $user->lessons()
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->with('subject')
                ->get()
                ->groupBy('subject_id');

            foreach ($lessons as $subjectId => $group) {
                $needed = $required[$subjectId] ?? 0;

                if ($needed <= 0) {
                    $user->lessons()->detach($group->pluck('id'));
                    continue;
                }

                if ($group->count() > $needed) {
                    $sorted   = $group->sortBy(fn($l) => $l->date . '|' . $l->period);
                    $toDetach = $sorted->slice($needed)->pluck('id');
                    if ($toDetach->isNotEmpty()) {
                        $user->lessons()->detach($toDetach);
                    }
                }
            }
        }

        return back()->with('message', 'Excess lessons unassigned.');
    }
}
