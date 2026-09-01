<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakModC2PlatformAssetTest extends TestCase
{
    public function testPlatformAndAffiliationStayStableForC2Bridge(): void
    {
        $root = dirname(__DIR__, 2);
        $platform = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_bftPlatform.sqf'
        );
        $pos = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf'
        );
        $bridge = (string) file_get_contents($root . '/public/assets/js/map/atak-c2-bridge.js');
        $theater = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf'
        );
        $terrain = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTerrain.sqf'
        );
        $geo = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf'
        );
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $coverage = (string) file_get_contents($root . '/app/Controllers/Api/AtakGeoNetworkApiController.php');

        self::assertStringContainsString('FIXED_WING', $platform);
        self::assertStringContainsString('APC', $platform);
        self::assertStringContainsString('IFV', $platform);
        self::assertStringContainsString('TRUCK', $platform);
        self::assertStringContainsString('"affiliation"', $pos);
        self::assertStringContainsString('"friend"', $pos);
        self::assertStringContainsString('"hostile"', $pos);
        self::assertStringContainsString('"neutral"', $pos);
        self::assertStringContainsString('"unknown"', $pos);
        self::assertStringContainsString('FIXED_WING', $bridge);
        self::assertStringContainsString("return 'AIR'", $bridge);
        self::assertStringContainsString("return 'VEHICLE'", $bridge);
        self::assertStringContainsString('normalizeAffiliation', $bridge);
        self::assertStringContainsString('worldSize', $theater);
        self::assertStringContainsString('_aoHalf = 4000', $terrain);
        self::assertStringContainsString('Geo.Ingest', $geo);
        self::assertStringContainsString('Geo.Ingest', $ext);
        self::assertStringContainsString('geo_ready', $coverage);
    }
}
