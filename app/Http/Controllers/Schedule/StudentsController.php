<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Version;
use Illuminate\Support\Facades\Config;

class StudentsController extends Controller
{
    public function index(?int $version_id = null)
    {
        $versions = Version::all();
        $versionId = $version_id ?? ($versions->first()->id ?? null);

        $days = [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
        ];

        $periods = Config::get('periods');

        $lessons = Lesson::with(['subject', 'users'])
            ->where('version_id', $versionId)
            ->get();

        $studentLessons       = [];
        $studentsWithConflict = [];

        foreach ($lessons as $lesson) {
            foreach ($lesson->users as $user) {
                $studentLessons[$user->id][$lesson->day][$lesson->period][] = $lesson;

                if (
                    count($studentLessons[$user->id][$lesson->day][$lesson->period]) > 1
                ) {
                    $studentsWithConflict[$user->id] = true;
                }
            }
        }

        $students = User::role('student')->orderBy('name')->get();

        return view('schedule.students.index', [
            'students'             => $students,
            'days'                 => $days,
            'periods'              => $periods,
            'studentLessons'       => $studentLessons,
            'studentsWithConflict' => $studentsWithConflict,
            'versions'             => $versions,
            'versionId'            => $versionId,
        ]);
    }
}

