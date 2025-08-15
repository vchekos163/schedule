<?php

namespace Tests\Unit;

use App\Jobs\OptimizeTeachers;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OptimizeTeachersAvailabilityTest extends TestCase
{
    public function test_compress_availability_uses_periods_and_states(): void
    {
        $job = new OptimizeTeachers('2024-01-01', 'test-job');

        $ref = new ReflectionClass($job);
        $method = $ref->getMethod('compressAvailabilityNoGaps');
        $method->setAccessible(true);

        $availability = [
            'monday'  => [10 => 'CLASS', 11 => 'CLASS', 12 => 'CLASS'],
            'tuesday' => [4 => 'ONLINE', 5 => 'ONLINE'],
        ];

        $expected = [
            'mon:10-12:CLASS',
            'tue:4-5:ONLINE',
        ];

        $this->assertSame($expected, $method->invoke($job, $availability));
    }
}
