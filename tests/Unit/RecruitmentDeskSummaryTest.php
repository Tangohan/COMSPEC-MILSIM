<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RecruitmentDeskSummary;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecruitmentDeskSummaryTest extends TestCase
{
    public function testEmptyDeskHasNoDelayAndZeroCounters(): void
    {
        $s = RecruitmentDeskSummary::compute([], new DateTimeImmutable('2026-11-23 10:00:00', new \DateTimeZone('UTC')));

        self::assertSame(0, $s['total']);
        self::assertSame(0, $s['to_process']);
        self::assertNull($s['avg_delay_days']);
    }

    public function testCountsFunnelMonthAndAverageDelay(): void
    {
        $now = new DateTimeImmutable('2026-11-23 10:00:00', new \DateTimeZone('UTC'));
        $rows = [
            ['status' => 'submitted', 'created_at' => '2026-11-20 08:00:00'],
            ['status' => 'submitted', 'pipeline_stage' => 'interview_scheduled', 'created_at' => '2026-11-10 08:00:00'],
            ['status' => 'reviewed', 'created_at' => '2026-11-10 00:00:00', 'reviewed_at' => '2026-11-14 00:00:00'],
            ['status' => 'reviewed', 'created_at' => '2026-09-01 00:00:00', 'reviewed_at' => '2026-09-03 00:00:00'],
            ['status' => 'rejected', 'created_at' => '2026-11-01 00:00:00', 'reviewed_at' => '2026-11-03 00:00:00'],
            ['status' => 'blocked', 'created_at' => '2026-11-01 00:00:00'],
        ];
        $s = RecruitmentDeskSummary::compute($rows, $now);

        self::assertSame(6, $s['total']);
        self::assertSame(1, $s['to_process']);
        self::assertSame(1, $s['in_interview']);
        self::assertSame(2, $s['funnel']['accepted']);
        self::assertSame(1, $s['accepted_this_month']);
        self::assertSame(1, $s['closed']['rejected']);
        self::assertSame(1, $s['closed']['blocked']);
        // (4 + 2 + 2) / 3 jours
        self::assertSame(2.7, $s['avg_delay_days']);
    }
}
