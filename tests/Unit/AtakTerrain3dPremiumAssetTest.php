<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrain3dPremiumAssetTest extends TestCase
{
    public function testAtakPageEnablesPremiumTerrain3d(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertStringContainsString('window.ATAK_TERRAIN3D_PREMIUM = true', $view);
        self::assertStringContainsString('id="terrain3d-container"', $view);
        self::assertStringContainsString('atak-terrain3d-premium.css', $view);
        self::assertStringContainsString('type="module" src="<?= $base ?>/assets/js/atak-terrain3d-premium.js', $view);
        self::assertStringContainsString('Relief 3D', $view);
        self::assertStringContainsString('"atak-terrain3d/initTerrain3D.js"', $view);
        self::assertStringContainsString('"atak-terrain3d/TerrainMaterial.js"', $view);
        self::assertStringContainsString('terrain3d/TerrainMaterial.js?v=', $view);
        self::assertStringContainsString('atak-geo-network.js', $view);
        self::assertStringContainsString('atak-geo-live.js', $view);
        self::assertStringContainsString('atak-route-planner.js', $view);
    }

    public function testPremiumBridgeDecodesHeightsAndTakesOverView3d(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain3d-premium.js');

        self::assertStringContainsString("import { initTerrain3D } from 'atak-terrain3d/initTerrain3D.js'", $js);
        self::assertStringContainsString('include=heights', $js);
        self::assertStringContainsString("encoding !== 'int16le_b64'", $js);
        self::assertStringContainsString('setHeightData', $js);
        self::assertStringContainsString("getElementById('atak-view-3d')", $js);
        self::assertStringContainsString("addEventListener('atak:units-updated'", $js);
        self::assertStringContainsString('ATAKTerrain3DPremium', $js);
        self::assertStringContainsString('setLoading', $js);
        self::assertStringContainsString('terrain3d-loader', $js);
        self::assertStringContainsString('resetView: false', $js);
        self::assertStringContainsString('mapTileSize', $js);
        self::assertStringContainsString('captureLeafletView', $js);
        self::assertStringContainsString('applyTacticalViewToRenderer', $js);
        self::assertStringContainsString('restoreLeafletFromRenderer', $js);
        self::assertStringContainsString('ATAKTacticalView', $js);
        self::assertStringContainsString("classList.add('atak-map-stage--premium-3d')", $js);
        self::assertStringNotContainsString("classList.add('atak-map-stage--premium-3d', 'atak-map-stage--3d')", $js);
        self::assertStringContainsString('refreshLeafletUnitMarkers', $js);
        self::assertStringContainsString('Ne pas retirer Leaflet du flux', $js);
        self::assertStringNotContainsString('mapEl.hidden = true', $js);
        self::assertStringContainsString("querySelectorAll('.atak-terrain-3d-hint, .atak-geo-live-hint')", $js);
        self::assertStringNotContainsString('Vue topo premium (relief Three.js)', $js);
        self::assertStringNotContainsString('CSS-pitch', $js);
    }

    public function testWebGlTerrainProjectsGridAndMarkersOnTheMesh(): void
    {
        $renderer = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/Terrain3DRenderer.js');
        $geom = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TerrainGeometryBuilder.js');
        $overlays = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TerrainOverlayManager.js');
        $camera = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TerrainCameraControls.js');
        $material = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TerrainMaterial.js');

        self::assertStringContainsString('static buildTacticalGrid', $geom);
        self::assertStringContainsString('gridLift', $geom);
        self::assertStringContainsString('_syncTacticalGrid', $renderer);
        self::assertStringContainsString('setTacticalView', $renderer);
        self::assertStringContainsString('getTacticalView', $renderer);
        self::assertStringContainsString('distanceFromZoom', $camera);
        self::assertStringContainsString('const MARKER_LIFT_M = 3', $overlays);
        self::assertStringContainsString('polygonOffset: true', $material);
    }

    public function testLegacyTerrain3dDelegatesWhenPremiumFlagIsSet(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');

        self::assertStringContainsString('if (window.ATAK_TERRAIN3D_PREMIUM)', $js);
        self::assertStringContainsString('premiumDelegated: true', $js);
    }

    public function testGeoLiveExposesRoadPlannerHook(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-geo-live.js');
        $tools = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-tools.js');

        self::assertStringContainsString('AtakGeoNetwork.create', $js);
        self::assertStringContainsString('AtakRoutePlanner.create', $js);
        self::assertStringContainsString('atak-geo-places', $js);
        self::assertStringContainsString('Villes et villages', $js);
        self::assertStringContainsString('>Routes</span>', $js);
        self::assertStringNotContainsString('geo_places', $js);
        self::assertStringNotContainsString('graphe routier', $js);
        self::assertStringNotContainsString('réseau geo', $js);
        self::assertStringContainsString('ATAKGeoLive', $js);
        self::assertStringContainsString('maybeSnapRoadRoute', $tools);
        self::assertStringContainsString('ATAKGeoLive.planRoadRoute', $tools);
    }
}
