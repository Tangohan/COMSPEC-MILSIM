<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAllyDistanceAssetTest extends TestCase
{
    public function testNearestReporterAndLastKnownStayOnMap(): void
    {
        $root = dirname(__DIR__, 2);
        $mod = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $beacons = (string) file_get_contents($mod . '/functions/fn_initGpsBeacons.sqf');
        $nearest = (string) file_get_contents($mod . '/functions/fn_isNearestAtakReporter.sqf');
        $set = (string) file_get_contents($mod . '/functions/fn_setAllyTrack.sqf');
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak.css');
        $cfg = (string) file_get_contents($mod . '/config.cpp');

        self::assertStringContainsString('isNearestAtakReporter', $beacons);
        self::assertStringContainsString('COMSPEC_AllyTrackSnapshots', $beacons);
        self::assertStringContainsString('objectFromNetId', $beacons);
        self::assertStringContainsString('distance2D', $nearest);
        self::assertStringContainsString('enableDynamicSimulation false', $set);
        self::assertStringContainsString('isTrackedAi', $map);
        self::assertStringContainsString('nato-sidc--last-known', $map);
        self::assertStringContainsString('nato-sidc--last-known', $css);
        self::assertStringContainsString('1.5.16', $cfg);
    }
}
