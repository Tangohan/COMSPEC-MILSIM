<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EventRecurrence;
use PHPUnit\Framework\TestCase;

final class EventRecurrenceTest extends TestCase
{
    public function testWeeklyOnSeveralDaysWithCount(): void
    {
        // Mardi 6 octobre 2026, 21:00 → 23:00 ; mardi + jeudi, 4 occurrences.
        $out = EventRecurrence::expand('2026-10-06 21:00:00', '2026-10-06 23:00:00', 'weekly', null, 4, [2, 4]);
        self::assertSame(
            ['2026-10-06 21:00:00', '2026-10-08 21:00:00', '2026-10-13 21:00:00', '2026-10-15 21:00:00'],
            array_column($out, 'starts')
        );
        self::assertSame('2026-10-15 23:00:00', $out[3]['ends']);
    }

    public function testBiweeklyUntilDateIsInclusiveAndKeepsLocalTimeAcrossDst(): void
    {
        $out = EventRecurrence::expand('2026-10-17 20:30:00', null, 'biweekly', '2026-11-14', null);
        self::assertSame(
            ['2026-10-17 20:30:00', '2026-10-31 20:30:00', '2026-11-14 20:30:00'],
            array_column($out, 'starts')
        );
        self::assertNull($out[0]['ends']);
    }

    public function testMonthlySkipsMonthsWithoutTheDay(): void
    {
        $out = EventRecurrence::expand('2027-01-31 19:00:00', '2027-01-31 21:00:00', 'monthly', null, 3);
        self::assertSame(['2027-01-31 19:00:00', '2027-03-31 19:00:00', '2027-05-31 19:00:00'], array_column($out, 'starts'));
    }

    public function testUnknownFrequencyReturnsSingleSlotAndCountIsCapped(): void
    {
        self::assertCount(1, EventRecurrence::expand('2026-10-06 21:00:00', null, 'none', null, 10));
        self::assertCount(EventRecurrence::MAX_OCCURRENCES, EventRecurrence::expand('2026-10-06 21:00:00', null, 'weekly', null, 500));
        self::assertSame('chaque semaine (mardi, jeudi)', EventRecurrence::describe('weekly', [2, 4]));
    }
}
