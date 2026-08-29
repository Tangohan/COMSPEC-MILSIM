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
        self::assertStringContainsString('Topo premium 3D', $view);
        self::assertStringContainsString('atak-geo-network.js', $view);
        self::assertStringContainsString('atak-geo-live.js', $view);
        self::assertStringContainsString('atak-route-planner.js', $view);
    }

    public function testPremiumBridgeDecodesHeightsAndTakesOverView3d(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain3d-premium.js');

        self::assertStringContainsString("import { initTerrain3D } from './terrain3d/initTerrain3D.js'", $js);
        self::assertStringContainsString('include=heights', $js);
        self::assertStringContainsString("encoding !== 'int16le_b64'", $js);
        self::assertStringContainsString('setHeightData', $js);
        self::assertStringContainsString("getElementById('atak-view-3d')", $js);
        self::assertStringContainsString("addEventListener('atak:units-updated'", $js);
        self::assertStringContainsString('ATAKTerrain3DPremium', $js);
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
        self::assertStringContainsString('ATAKGeoLive', $js);
        self::assertStringContainsString('maybeSnapRoadRoute', $tools);
        self::assertStringContainsString('ATAKGeoLive.planRoadRoute', $tools);
    }
}
