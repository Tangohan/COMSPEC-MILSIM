<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\SeniorityEngine;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SeniorityEngineTest extends TestCase
{
    public function testComputeFromStartUsesOldestPeriod(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'from_start'],
            [
                ['start_date' => '2025-01-01'],
                ['start_date' => '2023-05-10'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(1071, $result['days']);
        self::assertSame('2 a 11 m 11 j', $result['formatted']);
        self::assertSame('2026-04-15', $result['reference_date']);
    }

    public function testComputeSumPeriodsMergesOverlaps(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'sum_periods'],
            [
                ['start_date' => '2024-01-01', 'end_date' => '2024-02-01'],
                ['start_date' => '2024-01-15', 'end_date' => '2024-03-01'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(60, $result['days']);
        self::assertSame('0 a 2 m 0 j', $result['formatted']);
    }

    public function testComputeActiveOnlyFiltersInactiveRows(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'active_only'],
            [
                ['start_date' => '2024-01-01', 'end_date' => '2024-01-31', 'status' => 'active'],
                ['start_date' => '2024-02-01', 'end_date' => '2024-03-01', 'status' => 'inactive'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(30, $result['days']);
        self::assertSame('0 a 1 m 0 j', $result['formatted']);
    }
}
