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
        $dates = $weekStart->format('d-m-Y') . ' - ' . $weekEnd->format('d-m-Y');

        $existingSchedule = Lesson::select('id','date','room_id','subject_id')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with([
                'subject:id,priority',
                'subject.rooms:id',
                'teachers:id,availability,max_lessons,max_days,max_gaps',
            ])
            ->get();

        $subjectIds = $existingSchedule->pluck('subject_id')->filter()->unique()->values();

        $subjectToStudents = DB::table('subject_user')
            ->whereIn('subject_id', $subjectIds)
            ->select('subject_id', 'user_id')
            ->get()
            ->groupBy('subject_id')
            ->map(fn($rows) => $rows->pluck('user_id')->unique()->values()->all());

        $lessons = $existingSchedule->map(function ($l) use ($subjectToStudents) {
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
                'student_ids'    => $subjectToStudents->get($l->subject_id, []),
            ];
        })->values();

        $rooms = Room::select('id', 'purpose', 'capacity')->get();

        $newSchedule = [];
        $events = collect();

        if (count($existingSchedule->toArray())) {
            $scheduler = new ScheduleGenerator(
                $lessons->toArray(),
                $rooms->toArray(),
                $dates
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

            if (!is_array($slots)) continue;

            ksort($slots, SORT_NUMERIC);

            $currentState = null;
            $rangeStart   = null;
            $prevPeriod   = null;

            foreach ($slots as $period => $state) {
                $periodNumber = (int) $period;
                $state = strtoupper((string) $state);

                if ($state === 'UNAVAILABLE') {
                    if ($currentState !== null) {
                        $out[] = "{$shortDay}:{$rangeStart}-{$prevPeriod}:{$currentState}";
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
                    $out[] = "{$shortDay}:{$rangeStart}-{$prevPeriod}:{$currentState}";
                }

                $currentState = $state;
                $rangeStart   = $periodNumber;
                $prevPeriod   = $periodNumber;
            }

            if ($currentState !== null) {
                $out[] = "{$shortDay}:{$rangeStart}-{$prevPeriod}:{$currentState}";
            }
        }

        return $out;
    }
}
