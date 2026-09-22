<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakEcotiClearBuildingAssetTest extends TestCase
{
    public function testAceOffersCancelBuildingMark(): void
    {
        $root = dirname(__DIR__, 2);
        $ace = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf');
        $clear = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiClearBuilding.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('Annuler le pointage de b�timent', $ace);
        self::assertStringContainsString('COMSPEC_EcotiClearBuilding', $ace);
        self::assertStringContainsString('private _menuVer = 8', $ace);
        self::assertStringContainsString('COMSPEC_EcotiMarkedBuilding', $clear);
        self::assertStringContainsString('class ecotiClearBuilding {}', $cfg);
        self::assertStringContainsString('class ecotiBuildingMarkerName {}', $cfg);
        self::assertStringContainsString('versionStr = "1.6.9"', $cfg);
        $mark = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiMarkBuilding.sqf');
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        self::assertStringContainsString('syncMapMarker', $mark);
        self::assertStringContainsString('building_mark', $sync);
        self::assertStringContainsString('syncMapMarker', $clear);
    }
}
