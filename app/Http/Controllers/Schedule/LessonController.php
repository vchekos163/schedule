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
            'teachers' => $subject->teachers
                ->map(fn($teacher) => $teacher->user->name)
                ->join(', '),
            'date' => $lesson->date,
            'period' => $lesson->period,
        ]);
    }

    public function createFromSubjectPeriodStudent($subject_id, $date, $period, $user_id)
    {
        $subject = Subject::with('teachers.user')->findOrFail($subject_id);
        $room = Room::where('code', 'BIBL')->firstOrFail();
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

        $teacherIds = $subject->teachers->pluck('id')->unique()->values()->all();
        $lesson->teachers()->syncWithoutDetaching($teacherIds);
        $lesson->users()->syncWithoutDetaching([$user_id]);

        return response()->json([
            'id' => $lesson->id,
            'title' => $lesson->subject->code,
            'color' => $lesson->subject->color,
            'room' => $room->code,
            'teachers' => $subject->teachers
                ->map(fn($teacher) => $teacher->user->name)
                ->join(', '),
            'date' => $lesson->date,
            'period' => $lesson->period,
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

    public function delete($lesson_id)
    {
        $lesson = Lesson::findOrFail($lesson_id);
        $lesson->delete();

        return response()->json(['success' => true]);
    }

    public function assignStudentLessons(int $user_id, string $start)
    {
        $startDate = Carbon::parse($start)->startOfWeek(Carbon::MONDAY);
        $endDate   = (clone $startDate)->endOfWeek(Carbon::SUNDAY);

        /** @var \App\Models\User $user */
        $user = User::with('subjects')->findOrFail($user_id);

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
                ->whereDoesntHave('users', fn($q) => $q->where('users.id', $user_id))
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

        return back()->with('message', 'Lessons assigned.');
    }

/*
    public function createFromSubject($subject_id, $date, $start_time)
    {
        // Load teachers + their users for the title
        $subject = Subject::with('teachers.user')->findOrFail($subject_id);

        $room = Room::first();

        $end_time = Carbon::createFromFormat('H:i', $start_time)
            ->addMinutes(45)
            ->format('H:i');

        $lesson = Lesson::create([
            'subject_id' => $subject_id,
            'room_id' => $room->id,
            'date' => $date,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);

        // Subject->teachers are Teacher models → just pluck their IDs
        $teacherIds = $subject->teachers->pluck('id')->unique()->values()->all();

        // Avoid duplicates if lesson already has some teachers
        $lesson->teachers()->syncWithoutDetaching($teacherIds);

        return response()->json(        [
            'id' => $lesson->id,
            'title' => $lesson->subject->code,
            'color' => $lesson->subject->color,
            'room' => $room->code,
            'teachers' => $subject->teachers
                ->map(fn($teacher) => $teacher->user->name)
                ->join(', '),
        ]);
    }
*/
}
