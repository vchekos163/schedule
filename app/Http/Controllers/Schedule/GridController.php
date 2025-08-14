<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
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
}
