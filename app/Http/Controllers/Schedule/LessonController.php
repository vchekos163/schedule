<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\User;

class LessonController extends Controller
{
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
