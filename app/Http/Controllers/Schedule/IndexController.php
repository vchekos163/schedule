<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Services\ScheduleGenerator;
use App\Jobs\OptimizeTeachers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Lesson;
use App\Models\User;
use App\Models\Room;
use App\Models\Teacher;
use App\Models\Subject;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class IndexController extends Controller
{
    public function optimizeTeachers(Request $request, string $start = null)
    {
        $start = $request->input('start', $start);
        $prompt = $request->input('prompt', '');
        $jobId = (string) Str::uuid();

        if ($prompt !== '') {
            Cache::put("optimize_teachers_prompt_{$jobId}", $prompt, now()->addHour());
        }

        OptimizeTeachers::dispatch($start, $jobId);

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
                $lesson->date = $lessonData['date'];
                $lesson->period = $lessonData['period'];
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

    public function teachersExport(string $start, ?int $teacher_id = null)
    {
        $startDate = Carbon::parse($start)->startOfWeek();
        $endDate   = (clone $startDate)->endOfWeek();
        $periods   = Config::get('periods');

        if ($teacher_id) {
            $teacher = Teacher::findOrFail($teacher_id);
            $lessons = $teacher->lessons()
                ->with(['subject', 'users'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();
        } else {
            $lessons = Lesson::with(['subject', 'users'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();
        }

        $byDate = $lessons->groupBy('date')->map(function ($day) {
            return $day->groupBy('period');
        });

        $spreadsheet = new Spreadsheet();
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        foreach (range(0, 4) as $idx) {
            $date = $startDate->copy()->addDays($idx)->toDateString();
            $sheet = $idx === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($idx);
            $sheet->setTitle($dayNames[$idx]);

            $row = 1;
            foreach ($periods as $p => $time) {
                // Column A: period label (and optionally time)
                $sheet->setCellValue("A{$row}", "Lesson {$p}");
                $startRow = $row + 2; // content starts 2 rows below the period label

                /** @var \Illuminate\Support\Collection $periodLessons */
                $periodLessons = $byDate[$date][$p] ?? collect();

                // Place each lesson in its own column starting at column B (index 2)
                $colIndex = 2; // B
                $maxHeight = 0;

                foreach ($periodLessons as $lesson) {
                    $col = Coordinate::stringFromColumnIndex($colIndex);

                    // Subject name and code
                    $sheet->setCellValue("{$col}{$startRow}", $lesson->subject->name ?? '');
                    $sheet->setCellValue("{$col}" . ($startRow + 1), $lesson->subject->code ?? '');

                    // Students under subject/code
                    $r = $startRow + 2;
                    foreach ($lesson->users as $student) {
                        $sheet->setCellValue("{$col}{$r}", $student->name . ($student->class ? " ({$student->class})" : ''));
                        $r++;
                    }

                    // Height of this lesson block = 2 header rows + students
                    $lessonHeight = 2 + $lesson->users->count();
                    if ($lessonHeight > $maxHeight) {
                        $maxHeight = $lessonHeight;
                    }

                    // Next lesson goes in the next column
                    $colIndex++;
                }

                // If no lessons, keep a small empty block height
                if ($maxHeight === 0) {
                    $maxHeight = 1;
                }

                // Optional: autosize the columns we used this period
                for ($c = 2; $c < $colIndex; $c++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
                }
                // Also autosize column A
                $sheet->getColumnDimension('A')->setAutoSize(true);

                // Advance to the next period block: max height + top label(2) + spacer(1)
                $row = $startRow + $maxHeight + 1;
            }
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, "schedule_{$start}.xlsx");
    }
}
