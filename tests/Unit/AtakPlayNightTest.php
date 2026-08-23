<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakPlayNight;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AtakPlayNightTest extends TestCase
{
    public function testFridayEveningAndSaturdayDawnShareTheSameNight(): void
    {
        $tz = new DateTimeZone(AtakPlayNight::TIMEZONE);
        $friday = new DateTimeImmutable('2026-08-21 21:00:00', $tz);
        $saturdayDawn = new DateTimeImmutable('2026-08-22 02:00:00', $tz);

        self::assertSame('2026-08-21', AtakPlayNight::keyFromDateTime($friday));
        self::assertSame('2026-08-21', AtakPlayNight::keyFromDateTime($saturdayDawn));
        self::assertSame('Vendredi 21 août', AtakPlayNight::label('2026-08-21'));
    }

    public function testSaturdayEveningIsANewNight(): void
    {
        $tz = new DateTimeZone(AtakPlayNight::TIMEZONE);
        $saturdayEvening = new DateTimeImmutable('2026-08-22 21:00:00', $tz);

        self::assertSame('2026-08-22', AtakPlayNight::keyFromDateTime($saturdayEvening));
        self::assertSame('Samedi 22 août', AtakPlayNight::label('2026-08-22'));
    }

    public function testCutoffAtTenSplitsTheMornings(): void
    {
        $tz = new DateTimeZone(AtakPlayNight::TIMEZONE);
        $justBefore = new DateTimeImmutable('2026-08-22 09:59:00', $tz);
        $justAfter = new DateTimeImmutable('2026-08-22 10:00:00', $tz);

        self::assertSame('2026-08-21', AtakPlayNight::keyFromDateTime($justBefore));
        self::assertSame('2026-08-22', AtakPlayNight::keyFromDateTime($justAfter));
    }

    public function testSqlRangeCoversTwentyFourHoursFromTen(): void
    {
        [$from, $to] = AtakPlayNight::sqlRange('2026-08-21');

        self::assertSame('2026-08-21 10:00:00', $from);
        self::assertSame('2026-08-22 10:00:00', $to);
    }
}
