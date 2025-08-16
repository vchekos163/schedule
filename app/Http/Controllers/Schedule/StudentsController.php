<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class StudentsController extends Controller
{
    public function index()
    {
        $startDate = Carbon::now()->startOfWeek();
        $endDate   = (clone $startDate)->addDays(4);

        $days = [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
        ];

        $periods = Config::get('periods');

        $lessons = Lesson::with(['subject', 'users'])
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $studentLessons       = [];
        $studentsWithConflict = [];

        foreach ($lessons as $lesson) {
            foreach ($lesson->users as $user) {
                $studentLessons[$user->id][$lesson->date][$lesson->period][] = $lesson;

                if (
                    count($studentLessons[$user->id][$lesson->date][$lesson->period]) > 1
                ) {
                    $studentsWithConflict[$user->id] = true;
                }
            }
        }

        $students = User::role('student')->orderBy('name')->get();

        return view('schedule.students.index', [
            'students'            => $students,
            'days'                => $days,
            'periods'             => $periods,
            'startDate'           => $startDate,
            'studentLessons'      => $studentLessons,
            'studentsWithConflict' => $studentsWithConflict,
        ]);
    }
}

