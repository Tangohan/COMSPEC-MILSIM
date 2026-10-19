<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ModVersion;

final class AtakEcotiClearBuildingAssetTest extends TestCase
{
    public function testAceOffersCancelBuildingMark(): void
    {
        $root = dirname(__DIR__, 2);
        $ace = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf');
        $clear = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiClearBuilding.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('Annuler le pointage de bâtiment', $ace);
        self::assertStringContainsString('COMSPEC_EcotiClearBuilding', $ace);
        // Le menu ACE a été reversionné depuis (8 → 9…) : exiger au moins la version qui a ajouté l'entrée.
        ModVersion::assertAtLeast('8', ModVersion::match('/private _menuVer = (\d+);/', $ace));
        self::assertStringContainsString('COMSPEC_EcotiMarkedBuilding', $clear);
        self::assertStringContainsString('class ecotiClearBuilding {}', $cfg);
        self::assertStringContainsString('class ecotiBuildingMarkerName {}', $cfg);
        ModVersion::assertAtLeast('1.6.9', ModVersion::fromConfigCpp($cfg));
        $mark = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiMarkBuilding.sqf');
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        self::assertStringContainsString('syncMapMarker', $mark);
        self::assertStringContainsString('building_mark', $sync);
        self::assertStringContainsString('syncMapMarker', $clear);
    }
}
