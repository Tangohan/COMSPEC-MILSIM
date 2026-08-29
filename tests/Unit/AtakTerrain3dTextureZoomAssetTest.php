<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrain3dTextureZoomAssetTest extends TestCase
{
    public function testOverviewNeverFallsBackToHillshadeDiffuse(): void
    {
        $premium = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain3d-premium.js');
        $overview = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/MapOverviewTexture.js');
        $loader = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TextureLoader.js');
        $camera = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TerrainCameraControls.js');
        $renderer = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/Terrain3DRenderer.js');
        $bridge = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/map/atak-c2-bridge.js');

        self::assertStringContainsString('stitchTileOverview', $premium);
        self::assertStringContainsString('createFallbackMapCanvas', $premium);
        self::assertStringContainsString('applyMapTexture', $premium);
        self::assertStringNotContainsString("apiBase() + '/api/atak/terrain/hillshade", $premium);
        self::assertStringContainsString('export async function stitchTileOverview', $overview);
        self::assertStringContainsString('setCrossOrigin', $loader);
        self::assertStringContainsString('syncToWorld', $camera);
        self::assertStringContainsString('dolly(factor)', $camera);
        self::assertStringContainsString('syncCameraToWorld', $renderer);
        self::assertStringContainsString('setTextureFromCanvas', $renderer);
        self::assertStringContainsString('ATAKTerrainThree.dolly', $bridge);

        $material = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/TerrainMaterial.js');
        self::assertStringContainsString('fogDensityForWorld', $material);
        self::assertStringContainsString('syncFogToWorld', $material);
        self::assertStringContainsString('syncLightingToWorld', $material);
        self::assertStringContainsString('syncFogToWorld', $renderer);
        self::assertStringContainsString('syncLightingToWorld', $renderer);
        self::assertStringContainsString('syncCameraToWorld(size, size)', $premium);
    }
}
