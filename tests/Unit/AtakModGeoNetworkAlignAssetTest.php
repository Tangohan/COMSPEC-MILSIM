<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakModGeoNetworkAlignAssetTest extends TestCase
{
    public function testGeoNetworkIsWiredFromAceAndTheaterSurvey(): void
    {
        $ace = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );
        $theater = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf'
        );
        $geo = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf'
        );
        $bridge = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/js/map/atak-c2-bridge.js'
        );
        $prompt = (string) file_get_contents(
            dirname(__DIR__, 2) . '/docs/archive/legacy-atak/technique-atak-mod-align-prompt.md'
        );

        self::assertStringContainsString('sampleGeoNetwork', $ace);
        self::assertStringContainsString('COMSPEC_GeoNetwork', $ace);
        self::assertStringContainsString('sampleGeoNetwork', $theater);
        self::assertStringContainsString('_roadClass', $geo);
        self::assertStringContainsString('HIGHWAY', $geo);
        self::assertStringContainsString('APC', $bridge);
        self::assertStringContainsString('IFV', $bridge);
        self::assertStringContainsString('FIXED_WING', $bridge);
        self::assertStringContainsString('normalizeAffiliation', $bridge);
        self::assertStringContainsString('geo/ingest', $prompt);
        self::assertStringContainsString('GetWaypoints', $prompt);
        self::assertStringContainsString('MarkWaypointReached', $prompt);

        $gps = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollGpsNavigation.sqf'
        );
        $loops = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'
        );
        $pos = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf'
        );
        $ext = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs'
        );
        $cfg = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );

        self::assertStringContainsString('GetWaypoints', $gps);
        self::assertStringContainsString('MarkWaypointReached', $gps);
        self::assertStringContainsString('gps_navigation', $gps);
        self::assertStringContainsString('count _cols) >= 8', $gps);
        self::assertStringContainsString('COMSPEC_GPS_WP_', $gps);
        self::assertStringContainsString('pollGpsNavigation', $loops);
        self::assertStringContainsString('eta_seconds', $pos);
        self::assertStringContainsString('distance_to_destination_m', $pos);
        self::assertStringContainsString('GetWaypoints', $ext);
        self::assertStringContainsString('MarkWaypointReached', $ext);
        self::assertStringContainsString('SimplifyWaypointsJson', $ext);
        self::assertStringContainsString('pollGpsNavigation', $cfg);
    }
}
