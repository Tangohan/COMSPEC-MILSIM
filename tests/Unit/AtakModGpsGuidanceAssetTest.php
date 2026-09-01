<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakModGpsGuidanceAssetTest extends TestCase
{
    public function testGpsPollCreatesNumberedMarkersAndReadsSequence(): void
    {
        $root = dirname(__DIR__, 2);
        $gps = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollGpsNavigation.sqf'
        );
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $pos = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf'
        );

        self::assertStringContainsString('GetWaypoints', $gps);
        self::assertStringContainsString('"all"', $gps);
        self::assertStringContainsString('count _cols) >= 8', $gps);
        self::assertStringContainsString('COMSPEC_GPS_WP_', $gps);
        self::assertStringContainsString('createMarkerLocal', $gps);
        self::assertStringContainsString('setMarkerPolylineLocal', $gps);
        self::assertStringContainsString('COMSPEC_GPS_RT_', $gps);
        self::assertStringContainsString('ColorGrey', $gps);
        self::assertStringContainsString('MarkWaypointReached', $gps);
        self::assertStringContainsString('BIS_fnc_sortBy', $gps);
        self::assertStringContainsString('id\\troute_id\\tlabel\\tpos_x\\tpos_y\\tradius_m\\treached\\tsequence', $ext);
        self::assertStringContainsString('eta_seconds', $pos);
        self::assertStringContainsString('distance_to_destination_m', $pos);
        self::assertStringContainsString('active_route_id', $pos);
        self::assertStringContainsString('active_waypoint_id', $pos);
    }
}
