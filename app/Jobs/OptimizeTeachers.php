<?php

namespace App\Jobs;

use App\Models\Lesson;
use App\Models\Room;
use App\Services\ScheduleGenerator;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizeTeachers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $start;
    public string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $start, string $jobId)
    {
        $this->start = $start;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        Log::info('OptimizeTeachers: handle', [
            'jobId'     => $this->jobId,
            'cache_key' => "optimize_teachers_{$this->jobId}",
        ]);

        $weekStart = Carbon::parse($this->start)->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->addDays(4);

        $existingSchedule = Lesson::select('id','date','room_id','subject_id')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with([
                'subject:id,priority',
                'room:id,capacity',
                'teachers:id,availability,max_lessons,max_days,max_gaps',
            ])
            ->get();

        $subjectIds = $existingSchedule->pluck('subject_id')->filter()->unique()->values();

        $subjectToStudentsQty = DB::table('subject_user')
            ->whereIn('subject_id', $subjectIds)
            ->select('subject_id', DB::raw('COUNT(DISTINCT user_id) AS students_quantity'))
            ->groupBy('subject_id')
            ->pluck('students_quantity', 'subject_id');

        $lessons = $existingSchedule->map(function ($l) use ($subjectToStudentsQty) {
            return [
                'id'          => $l->id,
                'priority'    => optional($l->subject)->priority,
                'teachers'    => $l->teachers->map(function ($t) {
                    return [
                        'id'           => $t->id,
                        'availability' => $this->compressAvailabilityNoGaps($t->availability ?? []),
                        'max_lessons'  => $t->max_lessons ?? null,
                        'max_gaps'     => $t->max_gaps ?? null,
                        'max_days_week' => optional($t->max_days < 5 ? $t->max_days : null),
                    ];
                })->values()->all(),
                'students_count' => (int) $subjectToStudentsQty->get($l->subject_id, 0),
            ];
        })->values();

        $rooms = Room::select('id', 'purpose', 'capacity')->get();

        $newSchedule = [];
        $events = collect();

        if (count($existingSchedule->toArray())) {
            $scheduler = new ScheduleGenerator(
                $lessons->toArray(),
                $rooms->toArray()
            );

            $newSchedule = $scheduler->generate();
            $newSchedule = $newSchedule['lessons'] ?? [];

            $events = collect($newSchedule)->map(function ($lessonData) {
                $id = $lessonData['lesson_id'] ?? null;
                $event = [
                    'id'    => $id,
                    'title' => '',
                    'color' => '#64748b',
                    'period' => $lessonData['period'],
                    'date' => $lessonData['date'],
                    'reason'   => $lessonData['reason'] ?? '',
                    'room'     => '',
                    'teachers' => '',
                ];

                if ($id) {
                    $lesson = Lesson::with(['subject', 'room', 'teachers.user'])->find($id);
                    if ($lesson) {
                        $event['title'] = $lesson->subject->code ?? ($lesson->subject->name ?? '');
                        $event['color'] = $lesson->subject->color ?? '#64748b';
                        $event['room'] = $lesson->room->code ?? ($lesson->room->name ?? '');
                        $event['teachers'] = $lesson->teachers
                            ->map(fn($t) => $t->user->name)
                            ->join(', ');
                    }
                }

                return $event;
            })->values();
        }

        Cache::put("optimize_teachers_{$this->jobId}", [
            'status'  => 'completed',
            'lessons' => $newSchedule,
            'events'  => $events,
        ], now()->addHour());
    }

    private function compressAvailabilityNoGaps($availability): array
    {
        if (!is_array($availability)) return [];
        $dayShort = [
            'monday'    => 'mon',
            'tuesday'   => 'tue',
            'wednesday' => 'wed',
            'thursday'  => 'thu',
            'friday'    => 'fri',
            'saturday'  => 'sat',
            'sunday'    => 'sun',
        ];

        $out = [];
        foreach ($availability as $day => $slots) {
            $shortDay = $dayShort[strtolower($day)] ?? strtolower(substr($day, 0, 3));
            $times = [];
            if (is_array($slots)) {
                foreach ($slots as $period => $state) {
                    if (strtoupper($state) !== 'UNAVAILABLE') {
                        $start = config("periods.$period.start");
                        if ($start) {
                            $times[] = $start;
                        }
                    }
                }
            }
            $out[$shortDay] = $this->glueDayNoGapsShort($times);
        }
        return $out;
    }

    private function glueDayNoGapsShort(array $times): ?string
    {
        if (empty($times)) return null;
        $times = array_values(array_unique(array_map(function ($t) {
            $t = trim((string)$t);
            if ($t === '') return null;
            if (preg_match('/^\d:\d{2}$/', $t)) $t = '0' . $t;
            return $t;
        }, $times)));
        $times = array_filter($times);
        if (!$times) return null;

        sort($times, SORT_STRING);
        $startHour = (int)substr(reset($times), 0, 2);
        $endHour   = (int)substr(end($times), 0, 2);
        return "{$startHour}-{$endHour}";
    }
}
