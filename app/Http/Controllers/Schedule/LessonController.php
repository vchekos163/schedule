<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Subject;
use App\Models\Room;
use App\Models\Teacher;

class LessonController extends Controller
{
    public function createFromSubjectPeriod($subject_id, $day, $period, $version_id)
    {
        $subject = Subject::with('teachers.user')->findOrFail($subject_id);
        $room    = Room::first();

        $lesson = Lesson::create([
            'subject_id' => $subject_id,
            'room_id'    => $room->id,
            'day'        => $day,
            'period'     => $period,
            'version_id' => $version_id,
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
            'day' => $lesson->day,
            'period' => $lesson->period,
        ]);
    }
    public function createFromSubjectPeriodStudent($subject_id, $day, $period, $version_id, $user_id)
    {
        $subject = Subject::with('teachers.user')->findOrFail($subject_id);
        $user    = User::findOrFail($user_id);

        if ($subject->code === 'IND') {
            $room = Room::where('code', 'BIBL')->firstOrFail();
            $lesson = Lesson::create([
                'subject_id' => $subject_id,
                'room_id'    => $room->id,
                'day'        => $day,
                'period'     => $period,
                'version_id' => $version_id,
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
                'day'    => $lesson->day,
                'period' => $lesson->period,
                'reason' => $lesson->reason,
            ]);
        }

        $lesson = Lesson::with(['subject', 'room', 'teachers.user'])
            ->where('subject_id', $subject_id)
            ->where('day', $day)
            ->where('period', $period)
            ->where('version_id', $version_id)
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
            'day'   => $lesson->day,
            'period' => $lesson->period,
            'reason' => $lesson->reason,
            'fixed'  => $lesson->fixed,
        ]);
    }

    public function autoFillTeacher(int $teacher_id, int $version_id)
    {
        $periods = config('periods');
        $room    = Room::firstOrFail();

        if ($teacher_id === 0) {
            Teacher::with('subjects')->chunkById(100, function ($teachers) use ($version_id, $periods, $room) {
                foreach ($teachers as $teacher) {
                    $this->fillSimpleForTeacher($teacher, $version_id, $periods, $room);
                }
            });
        } else {
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);
            $this->fillSimpleForTeacher($teacher, $version_id, $periods, $room);
        }

        return redirect()->back()->with('message', 'Lessons generated.');
    }

    /**
     * Generate lessons based on fixed periods across days 1–5.
     */
    private function fillSimpleForTeacher(Teacher $teacher, int $version_id, array $periods, Room $room): void
    {
        $day         = 1;
        $periodKeys  = array_keys($periods);
        $periodIndex = 0;

        foreach ($teacher->subjects as $subject) {
            $qty = max(1, (int) ($subject->pivot->quantity ?? 1));

            for ($i = 0; $i < $qty; $i++) {
                if ($periodIndex >= count($periodKeys)) {
                    $day++;
                    $periodIndex = 0;
                    if ($day > 5) {
                        $day = 1;
                    }
                }

                $p = $periodKeys[$periodIndex];

                $lesson = Lesson::create([
                    'subject_id' => $subject->id,
                    'room_id'    => $room->id,
                    'day'        => $day,
                    'period'     => $p,
                    'version_id' => $version_id,
                ]);

                $lesson->teachers()->attach($teacher->id);
                $periodIndex++;
            }
        }
    }

    public function update(int $lesson_id, $day, $period, $version_id)
    {
        $lesson = Lesson::findOrFail($lesson_id);

        $lesson->update([
            'day'        => $day,
            'period'     => $period,
            'version_id' => $version_id,
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
            'day' => $lesson->day,
            'period' => $lesson->period,
            'reason' => $lesson->reason,
            'room' => $lesson->room->code,
            'teachers' => $lesson->teachers
                ->map(fn($teacher) => $teacher->user->name)
                ->join(', '),
            'subject_id' => $lesson->subject_id,
        ]);
    }

    public function assignStudentLessons(int $version_id, int $user_id = 0)
    {
        $usersQuery = User::with('subjects');

        if ($user_id) {
            $usersQuery->where('id', $user_id);
        } else {
            $usersQuery->role('student');
        }

        $users = $usersQuery->get();

        foreach ($users as $user) {
            $occupied = $user->lessons()
                ->where('version_id', $version_id)
                ->get(['day', 'period'])
                ->map(fn($l) => $l->day . '|' . $l->period)
                ->all();
            $occupied = array_fill_keys($occupied, true);

            foreach ($user->subjects as $subject) {
                $already = $user->lessons()
                    ->where('subject_id', $subject->id)
                    ->where('version_id', $version_id)
                    ->count();

                $needed = max(0, ($subject->pivot->quantity ?? 0) - $already);
                if ($needed <= 0) {
                    continue;
                }

                $candidates = Lesson::where('subject_id', $subject->id)
                    ->where('version_id', $version_id)
                    ->whereDoesntHave('users', fn($q) => $q->where('users.id', $user->id))
                    ->orderBy('day')
                    ->orderBy('period')
                    ->get(['id','day','period']);

                if ($candidates->isEmpty()) {
                    continue;
                }

                $toAttach = [];

                foreach ($candidates as $lesson) {
                    if ($needed <= 0) break;
                    $key = $lesson->day . '|' . $lesson->period;
                    if (!isset($occupied[$key])) {
                        $toAttach[] = $lesson->id;
                        $occupied[$key] = true;
                        $needed--;
                    }
                }

                if ($needed > 0) {
                    foreach ($candidates as $lesson) {
                        if ($needed <= 0) break;
                        if (in_array($lesson->id, $toAttach, true)) continue;
                        $toAttach[] = $lesson->id;
                        $needed--;
                    }
                }

                if (!empty($toAttach)) {
                    $user->lessons()->attach($toAttach);
                }
            }
        }

        return back()->with('message', 'Lessons assigned.');
    }

    public function unassignStudentLessons(int $version_id, int $user_id = 0)
    {
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
                ->where('version_id', $version_id)
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
                    $sorted   = $group->sortBy(fn($l) => $l->day . '|' . $l->period);
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
