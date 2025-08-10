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

    public function autoFillTeacher(int $teacher_id, string $start)
    {
        // constants to match your calendar
        $dayStart = '09:00';
        $dayEnd   = '15:00';       // wrap when start time reaches >= 15:00
        $blockMin = 45;            // lesson duration
        $gapMin   = 15;            // gap
        $stepMin  = $blockMin + $gapMin; // 60 min between lesson starts

        $room = Room::firstOrFail();

        // next Monday from the passed date
        $weekStartDate = Carbon::parse($start)
            ->startOfWeek(Carbon::MONDAY)
            ->addWeek()
            ->toDateString();

        if ($teacher_id === 0) {
            // all teachers
            Teacher::with('subjects')->chunkById(100, function ($teachers) use ($weekStartDate, $dayStart, $dayEnd, $blockMin, $stepMin, $room) {
                foreach ($teachers as $teacher) {
                    $this->fillSimpleForTeacher($teacher, $weekStartDate, $dayStart, $dayEnd, $blockMin, $stepMin, $room);
                }
            });
        } else {
            // single teacher
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);
            $this->fillSimpleForTeacher($teacher, $weekStartDate, $dayStart, $dayEnd, $blockMin, $stepMin, $room);
        }

        return redirect()->back()->with('message', 'Lessons generated.');
    }

    /**
     * Generate 45-min lessons with 15-min gaps, Mon–Fri 09:00–15:00, no availability checks.
     */
    private function fillSimpleForTeacher(Teacher $teacher, string $weekStartDate, string $dayStart, string $dayEnd, int $blockMin, int $stepMin, Room $room): void
    {
        // start Monday 09:00 for this teacher
        $cursorDate = $weekStartDate;
        $cursorTime = Carbon::createFromFormat('H:i', $dayStart);

        foreach ($teacher->subjects as $subject) {
            $qty = max(1, (int) ($subject->pivot->quantity ?? 1));

            for ($i = 0; $i < $qty; $i++) {
                // wrap to next weekday if past end of day
                if ($cursorTime->format('H:i') >= $dayEnd) {
                    $cursorDate = Carbon::parse($cursorDate)->addDay()->toDateString();
                    // skip weekends
                    while (in_array(Carbon::parse($cursorDate)->dayOfWeekIso, [6, 7])) {
                        $cursorDate = Carbon::parse($cursorDate)->addDay()->toDateString();
                    }
                    $cursorTime = Carbon::createFromFormat('H:i', $dayStart);
                }

                $slotStart = $cursorTime->copy();
                $slotEnd   = $cursorTime->copy()->addMinutes($blockMin);

                $lesson = Lesson::create([
                    'subject_id' => $subject->id,
                    'room_id'    => $room->id,
                    'date'       => $cursorDate,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time'   => $slotEnd->format('H:i'),
                ]);

                $lesson->teachers()->attach($teacher->id);

                // advance by duration + gap (45 + 15)
                $cursorTime->addMinutes($stepMin);
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
