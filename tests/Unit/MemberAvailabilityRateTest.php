<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\MemberAvailabilityRate;
use PHPUnit\Framework\TestCase;

final class MemberAvailabilityRateTest extends TestCase
{
    public function testNoEventsYieldsEmptySampleNotZero(): void
    {
        $pack = MemberAvailabilityRate::fromCounts(0, 0, 0);

        self::assertFalse($pack['sample']);
        self::assertNull($pack['availability_pct']);
        self::assertNull($pack['label']);
        self::assertSame('Aucune activité sur les 90 derniers jours', $pack['hint']);
    }

    public function testAveragesParticipationAndValidatedPresence(): void
    {
        // 8 / 12 participations, 6 / 8 présences → (0,667 + 0,75) / 2 = 71 %
        $pack = MemberAvailabilityRate::fromCounts(12, 8, 6);

        self::assertTrue($pack['sample']);
        self::assertSame(71, $pack['availability_pct']);
        self::assertSame(67, $pack['participation_pct']);
        self::assertSame(75, $pack['presence_pct']);
        self::assertSame('71 %', $pack['label']);
        self::assertSame(85, $pack['hue']);
        self::assertSame(
            'Sur 90 jours : 8 participations annoncées sur 12 activités, 6 présences validées.',
            $pack['hint']
        );
    }

    public function testZeroYesYieldsZeroPresenceAndRedBar(): void
    {
        $pack = MemberAvailabilityRate::fromCounts(10, 0, 0);

        self::assertTrue($pack['sample']);
        self::assertSame(0, $pack['availability_pct']);
        self::assertSame(0, $pack['participation_pct']);
        self::assertNull($pack['presence_pct']);
        self::assertSame(0, $pack['hue']);
        self::assertSame('0 %', $pack['label']);
    }

    public function testFullAttendanceIsGreen(): void
    {
        $pack = MemberAvailabilityRate::fromCounts(4, 4, 4);

        self::assertSame(100, $pack['availability_pct']);
        self::assertSame(120, $pack['hue']);
        self::assertSame(
            'Sur 90 jours : 4 participations annoncées sur 4 activités, 4 présences validées.',
            $pack['hint']
        );
    }

    public function testSingularHintForOneEvent(): void
    {
        $pack = MemberAvailabilityRate::fromCounts(1, 1, 1);

        self::assertSame(
            'Sur 90 jours : 1 participation annoncée sur 1 activité, 1 présence validée.',
            $pack['hint']
        );
    }

    public function testBarHueClamps(): void
    {
        self::assertSame(0, MemberAvailabilityRate::barHue(-10));
        self::assertSame(120, MemberAvailabilityRate::barHue(200));
        self::assertSame(60, MemberAvailabilityRate::barHue(50));
    }
}
