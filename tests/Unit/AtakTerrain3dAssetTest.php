<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrain3dAssetTest extends TestCase
{
    public function testTerrainMeshUsesTheImageLoadStateSupportedByLeaflet(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');

        self::assertStringContainsString('function tileImageReady(tile)', $javascript);
        self::assertStringContainsString('image.complete && Number(image.naturalWidth || image.width) > 0', $javascript);
        self::assertStringNotContainsString('!tile.loaded', $javascript);
    }

    public function testTerrainMeshRepaintsWhenTilesFinishLoading(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');

        self::assertStringContainsString("bindMapEvent(layer, 'tileload')", $javascript);
        self::assertStringContainsString("bindMapEvent(layer, 'tileunload')", $javascript);
    }

    public function testTerrainMeshKeepsTheLeafletTextureAsFallback(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString(
            '.atak-map-stage.atak-map-stage--3d.atak-terrain-mesh-ready #atak-map .leaflet-tile-pane { opacity: 1; }',
            $css
        );
        self::assertStringNotContainsString(
            '.atak-map-stage.atak-map-stage--3d.atak-terrain-mesh-ready #atak-map .leaflet-tile-pane { opacity: 0; }',
            $css
        );
    }

    public function testHillshadeUsesMultiplyBlendingToPreserveTheMapTexture(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString("style.mixBlendMode = 'multiply'", $javascript);
        self::assertStringContainsString('.atakHillshade-pane { mix-blend-mode: multiply; }', $css);
    }
}
