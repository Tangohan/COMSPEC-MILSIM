<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapUiArchitectureAssetTest extends TestCase
{
    public function testMapUiFunctionsAreRegisteredAndNamespaced(): void
    {
        $root = dirname(__DIR__, 2);
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $hud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        $bft = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_relabelBft.sqf'
        );
        $orders = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_orderCanTransition.sqf'
        );
        $cas = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_casRequestShow.sqf'
        );
        $ui = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui';

        self::assertStringContainsString('class map_ui', $cfg);
        self::assertStringContainsString('class mapUIInit', $cfg);
        self::assertStringContainsString('class collectMapState', $cfg);
        self::assertStringContainsString('class mapContextMenu', $cfg);
        self::assertStringContainsString('class formatUnitStatus', $cfg);
        self::assertStringContainsString('class applyMapLayers', $cfg);
        self::assertStringContainsString('class mapDrawOverlay', $cfg);
        self::assertStringContainsString('class mapPhotoIntel', $cfg);
        self::assertStringContainsString('class mapOrderAck', $cfg);
        self::assertStringContainsString('functions\\ui', $cfg);
        self::assertFileExists($ui . '/fn_mapUIInit.sqf');
        self::assertFileExists($ui . '/fn_collectMapState.sqf');
        self::assertFileExists($ui . '/fn_mapContextMenu.sqf');
        self::assertFileExists($ui . '/fn_mapReplay.sqf');
        self::assertFileExists($ui . '/fn_mapDebugOverlay.sqf');
        self::assertFileExists($ui . '/fn_applyMapLayers.sqf');
        self::assertFileExists($ui . '/fn_mapDrawOverlay.sqf');
        self::assertStringContainsString('88550', (string) file_get_contents($ui . '/fn_createTopBar.sqf'));
        self::assertStringContainsString('NOMINAL', (string) file_get_contents($ui . '/fn_formatUnitStatus.sqf'));
        self::assertStringContainsString('COMSPEC_PliAt', (string) file_get_contents($ui . '/fn_collectMapState.sqf'));
        self::assertStringContainsString('GOLD', $bft);
        self::assertStringContainsString('setMarkerAlphaLocal 0.4', $bft);
        self::assertStringContainsString('drawLine', (string) file_get_contents($ui . '/fn_mapDrawOverlay.sqf'));
        self::assertStringContainsString('ON STATION', (string) file_get_contents($ui . '/fn_collectMapState.sqf'));
        self::assertStringContainsString('COMSPEC_MapWorkspace', (string) file_get_contents($ui . '/fn_mapBookmarks.sqf'));
        self::assertStringContainsString('COMPLETE', (string) file_get_contents($ui . '/fn_mapOrderAck.sqf'));
        self::assertStringContainsString('case "DONE"', $orders);
        self::assertStringContainsString('_world', $cas);
        self::assertStringContainsString('mapUIUpdate', $hud);
        self::assertStringContainsString('mapUIDestroy', $hud);
        self::assertStringNotContainsString('createToolRail', $hud);
        $updUi = (string) file_get_contents($ui . '/fn_mapUIUpdate.sqf');
        self::assertStringContainsString('mapUIDestroy', $updUi);
        self::assertStringNotContainsString('mapContextMenu', $updUi);
        self::assertStringNotContainsString('createToolRail', $updUi);
        self::assertStringNotContainsString('createTopBar', $updUi);
        self::assertStringContainsString('88550', $updUi);
        self::assertStringContainsString('ctrlShow false', $updUi);
        self::assertStringContainsString('_barH', $hud);
        self::assertStringNotContainsString('displayCtrl 46600', $layout);
        self::assertStringNotContainsString('forEach [46600', $hud);
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        self::assertStringContainsString('idc = 9770', $page);
        self::assertStringContainsString('idc = 9773', $page);
        self::assertStringNotContainsString('class RscDisplayMainMap', $cfg);
    }
}
