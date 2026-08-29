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
            dirname(__DIR__, 2) . '/docs/technique/atak-mod-align-prompt.md'
        );

        self::assertStringContainsString('sampleGeoNetwork', $ace);
        self::assertStringContainsString('COMSPEC_GeoNetwork', $ace);
        self::assertStringContainsString('sampleGeoNetwork', $theater);
        self::assertStringContainsString('_roadClass', $geo);
        self::assertStringContainsString('HIGHWAY', $geo);
        self::assertStringContainsString('APC', $bridge);
        self::assertStringContainsString('IFV', $bridge);
        self::assertStringContainsString('geo/ingest', $prompt);
    }
}
