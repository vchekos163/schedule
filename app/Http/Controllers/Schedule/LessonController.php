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

        // Optional: return a composed title so the frontend can update it
        $title = $subject->name . ' - ' . $subject->teachers
                ->filter(fn($t) => $t->user)
                ->map(fn($t) => $t->user->name)
                ->join(', ');

        return response()->json([
            'id' => $lesson->id,
            'title' => $title,
        ]);
    }

    public function createForTeacher(int $teacher_id, int $subject_id, string $date, string $start_time)
    {
        $teacher = Teacher::with('user')->findOrFail($teacher_id);
        $subject = Subject::findOrFail($subject_id);
        $room    = Room::first();

        $end_time = Carbon::createFromFormat('H:i', $start_time)
            ->addMinutes(45)
            ->format('H:i');

        $lesson = Lesson::create([
            'subject_id' => $subject->id,
            'room_id'    => $room->id,
            'date'       => $date,
            'start_time' => $start_time,
            'end_time'   => $end_time,
        ]);

        $lesson->teachers()->attach($teacher->id);

        return response()->json([
            'id'       => $lesson->id,
            'title'    => $subject->code ?? $subject->name,
            'room'     => $room->code ?? $room->name,
            'teachers' => $teacher->user?->name,
        ]);
    }

    public function autoFillTeacher(int $teacher_id, string $start)
    {
        $periods = config('periods');
        $room    = Room::firstOrFail();

        // next Monday from the passed date
        $weekStartDate = Carbon::parse($start)
            ->startOfWeek(Carbon::MONDAY)
            ->addWeek()
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
                    'start_time' => $slotStart,
                    'end_time'   => $slotEnd,
                ]);

                $lesson->teachers()->attach($teacher->id);
                $periodIndex++;
            }
        }
    }

    public function update(int $lesson_id, $date, $start_time, $end_time)
    {
        $lesson = Lesson::findOrFail($lesson_id);

        $lesson->update([
            'date' => $date,
            'start_time' => $start_time,
            'end_time' => $end_time,
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
}
