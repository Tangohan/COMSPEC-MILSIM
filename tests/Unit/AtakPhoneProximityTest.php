<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakPhoneProximity;
use PHPUnit\Framework\TestCase;

final class AtakPhoneProximityTest extends TestCase
{
    public function testNormalizeSnapsToClosedPresets(): void
    {
        self::assertSame(0, AtakPhoneProximity::normalize(-12));
        self::assertSame(200, AtakPhoneProximity::normalize(180));
        self::assertSame(1000, AtakPhoneProximity::normalize(900));
        self::assertSame(2000, AtakPhoneProximity::normalize(2500));
        self::assertSame(200, AtakPhoneProximity::normalize(200));
    }

    public function testLabelsAreHumanReadable(): void
    {
        self::assertSame('Désactivée', AtakPhoneProximity::label(0));
        self::assertSame('200 mètres', AtakPhoneProximity::label(200));
        self::assertSame('1 kilomètre', AtakPhoneProximity::label(1000));
    }

    public function testEvaluateAlertsOnEnterThenHoldsUntilExitBand(): void
    {
        $enter = AtakPhoneProximity::evaluate(false, 180.0, 200);
        self::assertTrue($enter['inside']);
        self::assertTrue($enter['alert']);

        $stay = AtakPhoneProximity::evaluate(true, 190.0, 200);
        self::assertTrue($stay['inside']);
        self::assertFalse($stay['alert']);

        $hysteresis = AtakPhoneProximity::evaluate(true, 220.0, 200);
        self::assertTrue($hysteresis['inside']);
        self::assertFalse($hysteresis['alert']);

        $exit = AtakPhoneProximity::evaluate(true, 250.0, 200);
        self::assertFalse($exit['inside']);
        self::assertFalse($exit['alert']);

        $reenter = AtakPhoneProximity::evaluate(false, 199.0, 200);
        self::assertTrue($reenter['alert']);
    }

    public function testDisabledRadiusNeverAlerts(): void
    {
        $off = AtakPhoneProximity::evaluate(false, 10.0, 0);
        self::assertFalse($off['inside']);
        self::assertFalse($off['alert']);
    }

    public function testToastAndDistanceFormatting(): void
    {
        self::assertSame('96 m', AtakPhoneProximity::formatDistance(96.2));
        self::assertSame('1,2 km', AtakPhoneProximity::formatDistance(1180.0));
        self::assertSame(
            'Téléphone proche — Tél. Dimitris (180 m)',
            AtakPhoneProximity::toastMessage('Tél. Dimitris', 180.0)
        );
    }
}
