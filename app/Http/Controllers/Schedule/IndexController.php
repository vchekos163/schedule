<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Services\ScheduleGenerator;
use App\Jobs\OptimizeTeachers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Room;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Version;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class IndexController extends Controller
{
    public function optimizeTeachers(Request $request, string $version_id = null)
    {
        $version_id = $request->input('version_id', $version_id);
        $prompt = $request->input('prompt', '');
        $jobId = (string) Str::uuid();

        if ($prompt !== '') {
            Cache::put("optimize_teachers_prompt_{$jobId}", $prompt, now()->addHour());
        }

        OptimizeTeachers::dispatch($version_id, $jobId);

        return response()->json(['jobId' => $jobId]);
    }

    public function getOptimizedTeachers(string $jobId)
    {
        $data = Cache::get("optimize_teachers_{$jobId}");

        if (!$data) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json($data);
    }

    public function saveOptimizedTeachers(string $jobId)
    {
        $data = Cache::get("optimize_teachers_{$jobId}");
        $newSchedule = $data['lessons'];

        DB::transaction(function () use ($newSchedule) {
            foreach ($newSchedule as $lessonData) {
                $lesson = null;

                if (!empty($lessonData['lesson_id'])) {
                    $lesson = Lesson::find($lessonData['lesson_id']);
                }

                if (!$lesson) {
                    $lesson = new Lesson();
                }

                $lesson->reason = $lessonData['reason'];
                $lesson->room_id = $lessonData['room_id'];
                $lesson->day = $lessonData['day'];
                $lesson->period = $lessonData['period'];
                $lesson->version_id = $lessonData['version_id'] ?? null;
                $lesson->save();

                $studentIds = array_unique(array_map('intval', $lessonData['student_ids'] ?? []));

                if (empty($studentIds)) {
                    $lesson->users()->sync([]);
                } else {
                    $validIds = User::query()
                        ->whereIn('id', $studentIds)
                        ->pluck('id')
                        ->all();

                    $lesson->users()->sync($validIds);
                }
            }
        });

        return response()->json(['status' => 'saved']);
    }

    public function teachersExport(string $version_id, ?int $teacher_id = null)
    {
        $versionName = Version::find($version_id)?->name;
        $periods     = Config::get('periods');

        // Load lessons (for teacher or all)
        if ($teacher_id) {
            $teacher = Teacher::findOrFail($teacher_id);
            $lessons = $teacher->lessons()
                ->with(['subject', 'users'])
                ->where('version_id', $version_id)
                ->get();
        } else {
            $lessons = Lesson::with(['subject', 'users'])
                ->where('version_id', $version_id)
                ->get();
        }

        // group by day → period (assume lessons.day is 1=Mon ... 5=Fri)
        $byDate = $lessons->groupBy('day')->map(function ($day) {
            return $day->groupBy('period');
        });

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $dayNames    = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        // Loop 1–5 instead of 0–4
        for ($dayNumber = 1; $dayNumber <= 5; $dayNumber++) {
            $sheetIndex = $dayNumber - 1;
            $sheet = $dayNumber === 1
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet($sheetIndex);

            $sheet->setTitle($dayNames[$sheetIndex]);

            $row = 1;
            foreach ($periods as $p => $time) {
                // Column A = period label
                $sheet->setCellValue("A{$row}", "Lesson {$p}");
                $startRow = $row + 2;

                // Lessons for this day + period
                $periodLessons = $byDate[$dayNumber][$p] ?? collect();

                $colIndex  = 2; // start from B
                $maxHeight = 0;

                foreach ($periodLessons as $lesson) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                    // Subject info
                    $sheet->setCellValue("{$col}{$startRow}", $lesson->subject->name ?? '');
                    $sheet->setCellValue("{$col}" . ($startRow + 1), $lesson->subject->code ?? '');

                    // Students
                    $r = $startRow + 2;
                    foreach ($lesson->users as $student) {
                        $sheet->setCellValue("{$col}{$r}", $student->name . ($student->class ? " ({$student->class})" : ''));
                        $r++;
                    }

                    $lessonHeight = 2 + $lesson->users->count();
                    $maxHeight = max($maxHeight, $lessonHeight);

                    $colIndex++;
                }

                if ($maxHeight === 0) {
                    $maxHeight = 1;
                }

                // Autosize columns used this period
                for ($c = 2; $c < $colIndex; $c++) {
                    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
                }
                $sheet->getColumnDimension('A')->setAutoSize(true);

                // Move down by block height + label + spacer
                $row = $startRow + $maxHeight + 1;
            }
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, "schedule_{$versionName}.xlsx");
    }
}
