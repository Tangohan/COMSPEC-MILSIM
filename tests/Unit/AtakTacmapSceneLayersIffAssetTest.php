<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTacmapSceneLayersIffAssetTest extends TestCase
{
    public function testTheaterBuildingsLayerPanelNetworkZonesAndIffAlert(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $mapJs = (string) file_get_contents($root . '/public/assets/js/comspec-operational-map.js');
        $tacmap = (string) file_get_contents($root . '/views/tacmap.php');
        $layers = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_createLayerPanel.sqf');
        $apply = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_applyMapLayers.sqf');
        $hud = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf');
        $zone = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_createRoleplayZone.sqf');
        $portal = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncRoleplayZonesFromPortal.sqf');
        $sync = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf');
        $tick = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_phoneProximityTick.sqf');
        $iffTick = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_iffProximityTick.sqf');
        $iffAlert = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_iffProximityAlert.sqf');

        self::assertStringContainsString('versionStr = "1.0.165"', $cfgA);
        self::assertStringContainsString('versionStr = "1.6.9"', $cfgC);
        self::assertStringContainsString('class athena_iffProximityTick {}', $cfgA);
        self::assertStringContainsString('class athena_iffProximityAlert {}', $cfgA);

        self::assertStringContainsString('createSceneBuildingOverlay', $mapJs);
        self::assertStringContainsString('/atak/scene', $mapJs);
        self::assertStringContainsString('kind=building', $mapJs);
        self::assertStringContainsString('refreshNetworkZones', $mapJs);
        self::assertStringContainsString('/atak/roleplay-stats', $mapJs);
        self::assertStringContainsString('tacmap-layer-buildings', $tacmap);
        self::assertStringContainsString('Zones réseau', $tacmap);
        self::assertStringContainsString('Bâtiments', $tacmap);

        self::assertStringContainsString('Contacts ennemis', $layers);
        self::assertStringContainsString('Alliés suivis', $layers);
        self::assertStringContainsString('Zones réseau', $layers);
        self::assertStringContainsString('Rapports SIGINT', $layers);
        self::assertStringContainsString('createLayerPanel', $hud);
        self::assertStringContainsString('applyMapLayers', $hud);
        self::assertStringContainsString('comspec_ai_local_', $apply);
        self::assertStringContainsString('network_zones', $apply);
        self::assertStringNotContainsString('COMSPEC_AtakShowEnemyAi', $apply);

        self::assertStringContainsString('Solid', $zone);
        self::assertStringContainsString('comspec_roleplay_zone_', $portal);
        self::assertStringContainsString('network_zone', $sync);
        self::assertStringNotContainsString('"comspec_roleplay_zone_", "comspec_tabletmk_"', $sync);

        self::assertStringContainsString('athena_iffProximityTick', $tick);
        self::assertStringContainsString('Contact non identifié', $iffAlert);
        self::assertStringContainsString('COMSPEC_AtakIffProximityM', $iffTick);
        self::assertStringContainsString('45', $iffTick);
    }
}
