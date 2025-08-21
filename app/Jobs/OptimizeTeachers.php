<?php

namespace App\Jobs;

use App\Models\Lesson;
use App\Models\Room;
use App\Services\ScheduleGenerator;
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

    public string $versionId;
    public string $jobId;
    public array $dates = [];

    /**
     * Create a new job instance.
     */
    public function __construct(string $versionId, string $jobId)
    {
        $this->versionId = $versionId;
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

        $existingSchedule = Lesson::select('id','date','room_id','subject_id','period','fixed')
            ->where('version_id', $this->versionId)
            ->whereHas('subject', function ($q) {
                $q->where('code', '!=', 'IND');
            })
            ->with([
                'subject:id,code,priority',
                'subject.rooms:id',
                'teachers:id,availability,max_lessons,max_days,max_gaps',
            ])
            ->get();

        $uniqueDates = $existingSchedule->pluck('date')->filter()->unique()->sort()->values();
        foreach ($uniqueDates as $index => $date) {
            $this->dates[$index + 1] = $date;
        }

        $subjectIds = $existingSchedule->pluck('subject_id')->filter()->unique()->values();

        $students = DB::table('subject_user')
            ->whereIn('subject_id', $subjectIds)
            ->select('user_id', 'subject_id', 'quantity')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows, $userId) {
                return [
                    'id'       => $userId,
                    'subjects' => $rows->map(fn($row) => [
                        'id'       => $row->subject_id,
                        'quantity' => $row->quantity,
                    ])->values()->all(),
                ];
            })->values();

        $lessons = $existingSchedule->map(function ($l) {
            $lesson = [
                'id'          => $l->id,
                'code'        => $l->subject->code,
                'subject_id'  => $l->subject->id,
                'priority'    => optional($l->subject)->priority,
                'teachers'    => $l->teachers->map(function ($t) {
                    $return = [
                        'id'           => $t->id,
                        'availability' => $this->compressAvailabilityNoGaps($t->availability ?? []),
                        'max_lessons_per_day'  => $t->max_lessons ?? null,
                        'max_gaps'     => $t->max_gaps ?? null,
                    ];
                    if ($t->max_days < 5){
                        $return['max_days'] = $t->max_days;
                    }
                    return $return;
                })->values()->all(),
                'rooms' => $l->subject
                    ? $l->subject->rooms
                        ->map(fn($r) => [
                            'id'       => $r->id,
                            'priority' => ($r->pivot->priority ?? 0),
                        ])
                        ->sortBy('priority')
                        ->values()
                        ->all()
                    : [],
            ];

            if ($l->fixed) {
                $dayNumber = array_search($l->date, $this->dates, true);
                $lesson['day'] = $dayNumber !== false ? $dayNumber : null;
                $lesson['period'] = $l->period;
            }

            return $lesson;
        })->values();

        $rooms = Room::select('id', 'capacity')->get();

        $newSchedule = [];
        $events = collect();

        if (count($existingSchedule->toArray())) {
            $prompt = Cache::get("optimize_teachers_prompt_{$this->jobId}", '');
            $scheduler = new ScheduleGenerator(
                $lessons->toArray(),
                $rooms->toArray(),
                $this->dates,
                $students->toArray(),
                $prompt
            );

            try {
                $newSchedule = $scheduler->generate();
            } catch (\Throwable $e) {
                Cache::put("optimize_teachers_{$this->jobId}", [
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ], now()->addHour());
                return;
            }

            if (isset($newSchedule['error'])) {
                Cache::put("optimize_teachers_{$this->jobId}", [
                    'status' => 'failed',
                    'error'  => $newSchedule['error'],
                ], now()->addHour());
                return;
            }

            $newSchedule = $newSchedule['lessons'] ?? [];

            foreach ($newSchedule as &$lessonData) {
                $lessonData['version_id'] = $this->versionId;
            }
            unset($lessonData);

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

        $out = [];
        foreach ($availability as $dayNumber => $slots) {
            $dayNumber = (int) $dayNumber;
            if ($dayNumber < 1 || $dayNumber > 5) continue;

            if (!is_array($slots)) continue;

            ksort($slots, SORT_NUMERIC);

            $currentState = null;
            $rangeStart   = null;
            $prevPeriod   = null;
            $ranges       = []; // collect ranges for this day

            foreach ($slots as $period => $state) {
                $periodNumber = (int) $period;
                $state = strtoupper((string) $state);

                if ($state === 'UNAVAILABLE') {
                    if ($currentState !== null) {
                        $ranges[] = "{$rangeStart}-{$prevPeriod}";
                        $currentState = null;
                        $rangeStart   = null;
                        $prevPeriod   = null;
                    }
                    continue;
                }

                if ($currentState === $state && $prevPeriod !== null && $periodNumber === $prevPeriod + 1) {
                    $prevPeriod = $periodNumber;
                    continue;
                }

                if ($currentState !== null) {
                    $ranges[] = "{$rangeStart}-{$prevPeriod}";
                }

                $currentState = $state;
                $rangeStart   = $periodNumber;
                $prevPeriod   = $periodNumber;
            }

            if ($currentState !== null) {
                $ranges[] = "{$rangeStart}-{$prevPeriod}";
            }

            if (!empty($ranges)) {
                $out[] = $dayNumber . ':' . implode(',', $ranges);
            }
        }

        return $out;
    }
}
