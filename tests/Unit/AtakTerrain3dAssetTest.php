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
}
