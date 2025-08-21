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
    public function student(int $user_id)
    {
        // Find the user manually
        $user = User::findOrFail($user_id);

        // Eager load subject & room for performance
        $lessons = $user->lessons()->with(['subject', 'teachers' ,'room'])->get();

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->name,
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
                'extendedProps' => [
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->name,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                ],
            ];
        });

        return view('schedule.index.student', [
            'events' => $events,
            'user' => $user,
        ]);
    }

    public function teachers(?int $teacher_id = null)
    {
        $teacher = $teacher_id ? Teacher::findOrFail($teacher_id) : null;

        return view('schedule.index.teachers', [
            'teacher' => $teacher,
        ]);
    }

    public function teachersData(string $start, ?int $teacher_id = null)
    {
        $startDate = Carbon::parse($start)->startOfWeek();
        $endDate   = (clone $startDate)->endOfWeek();

        if ($teacher_id) {
            $teacher = Teacher::with('subjects')->findOrFail($teacher_id);

            $lessons = $teacher->lessons()
                ->with(['subject', 'room', 'teachers.user'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $subjects = $teacher->subjects->map(function ($subject) use ($teacher, $startDate, $endDate) {
                $lessonsCount = $teacher->lessons()
                    ->where('subject_id', $subject->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count();

                $remaining = max(0, ($subject->pivot->quantity ?? 0) - $lessonsCount);

                return [
                    'id'       => $subject->id,
                    'name'     => $subject->name,
                    'code'     => $subject->code,
                    'color'    => $subject->color,
                    'quantity' => $remaining,
                ];
            })->filter(fn ($row) => $row['quantity'] > 0)->values();
        } else {
            $lessons = Lesson::with(['subject', 'room', 'teachers.user'])
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $subjects = Subject::with('teachers')->get()->map(function ($subject) use ($startDate, $endDate) {
                $lessonsCount = Lesson::where('subject_id', $subject->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count();

                $totalQty = $subject->teachers->sum(fn ($t) => $t->pivot->quantity ?? 0);
                $remaining = max(0, $totalQty - $lessonsCount);

                return [
                    'id'       => $subject->id,
                    'name'     => $subject->name,
                    'code'     => $subject->code,
                    'color'    => $subject->color,
                    'quantity' => $remaining,
                ];
            })->filter(fn ($row) => $row['quantity'] > 0)->values();
        }

        $events = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'title' => $lesson->subject->code,
                'color' => $lesson->subject->color,
                'start' => $lesson->date . 'T' . $lesson->start_time,
                'end' => $lesson->date . 'T' . $lesson->end_time,
                'date' => $lesson->date,
                'period' => $lesson->period,
                'fixed' => $lesson->fixed,
                'extendedProps' => [
                    'reason' => $lesson->reason,
                    'room' => $lesson->room->code,
                    'teachers' => $lesson->teachers
                        ->map(fn($teacher) => $teacher->user->name)
                        ->join(', '),
                ],
            ];
        });

        return response()->json([
            'events' => $events,
            'subjects' => $subjects,
        ]);
    }

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

    public function optimize($user_id)
    {
        $student = User::with('subjects')->findOrFail($user_id);

        $rooms = Room::all();
        $existingSchedule = Lesson::with(['teachers', 'users', 'room'])->get();

        $students = collect([$student])->map(function ($s) {
            return [
                'id'       => $s->id,
                'subjects' => $s->subjects->map(fn($sub) => [
                    'id'       => $sub->id,
                    'quantity' => $sub->pivot->quantity ?? 0,
                ])->toArray(),
            ];
        })->values()->toArray();

        $scheduler = new ScheduleGenerator(
            $existingSchedule->toArray(),
            $rooms->toArray(),
            [],
            $students
        );

        $newSchedule = $scheduler->generate();
        $newSchedule = $newSchedule['lessons'];

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
                $lesson->subject_id = $lessonData['subject_id'];
                $lesson->room_id = $lessonData['room_id'];
                $lesson->date = $lessonData['date'];
                $lesson->start_time = $lessonData['start_time'];
                $lesson->end_time = $lessonData['end_time'];
                $lesson->save();

                // Attach teachers
                if (!empty($lessonData['teacher_ids'])) {
                    $teacherIds = collect($lessonData['teacher_ids'])
                        ->map(fn($userId) => Teacher::firstOrCreate(['user_id' => $userId])->id)
                        ->toArray();

                    $lesson->teachers()->sync($teacherIds);
                }
            }
        });

        return redirect('/schedule/index/student/user_id/'.$user_id)->with('message', 'Schedule optimized and saved!');
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
