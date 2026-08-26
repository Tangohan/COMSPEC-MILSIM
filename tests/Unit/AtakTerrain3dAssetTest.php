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

    public function testTerrainMeshOffersPersistentVerticalExaggeration(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertStringContainsString('verticalExaggeration: 2.5', $javascript);
        self::assertStringContainsString('function reliefOffset(z)', $javascript);
        self::assertStringContainsString('(Number(z) - minZ) / (maxZ - minZ)', $javascript);
        self::assertStringContainsString('normalizedHeight * 110 * state.verticalExaggeration', $javascript);
        self::assertStringNotContainsString('Math.min(550, displacement)', $javascript);
        self::assertStringContainsString('id="atak-terrain-exaggeration"', $view);
        self::assertStringContainsString('Exagération Z', $view);
        self::assertStringContainsString('id="atak-terrain-pitch"', $view);
        self::assertStringContainsString('Inclinaison', $view);
        self::assertStringContainsString('id="atak-terrain-3d-mode"', $view);
        self::assertStringContainsString('Vue de la carte', $view);
        self::assertStringContainsString('<option value="flat" selected>À plat</option>', $view);
        self::assertStringContainsString('<option value="inclined">Inclinée</option>', $view);
        self::assertDoesNotMatchRegularExpression('/id="atak-terrain-3d-settings"[^>]*\bhidden\b/', $view);
        self::assertStringContainsString("modeSelect.value = state.enabled ? 'inclined' : 'flat'", $javascript);
        self::assertStringContainsString('settings.hidden = false', $javascript);
        self::assertStringNotContainsString('settings.hidden = !state.enabled', $javascript);
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

    public function testMapToolsKeepThe3dButtonInTheViewCluster(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map-tools.js');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertStringContainsString("{ id: 'view3d', label: 'Vue 3D' }", $javascript);
        self::assertStringContainsString("view: ['zoom', 'view3d', 'nvg', 'cop']", $javascript);
        self::assertStringContainsString('id="atak-view-3d"', $view);
        self::assertStringContainsString('data-tool-slot="view3d"', $view);
    }

    public function testInclinedViewBillboardsMarkerGlyphsTowardTheScreen(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $sizes = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-marker-sizes.js');
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');
        $nato = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/nato-sidc-icons.js');

        self::assertStringContainsString('--atak-billboard: rotateZ(calc(-1 * var(--atak-map-bearing, 0deg))) rotateX(calc(-1 * var(--atak-map-pitch, 48deg)))', $css);
        self::assertStringContainsString('.atak-marker-billboard', $css);
        self::assertStringContainsString("atak-marker-glyph atak-marker-billboard", $sizes);
        self::assertStringContainsString('function inclinedView()', $map);
        self::assertStringContainsString('showLabel: inclinedView()', $map);
        self::assertStringContainsString("window.addEventListener('atak:terrain3dchange', refreshInclinedMarkers)", $map);
        self::assertStringContainsString('opts.showLabel = opts.showLabel === true', $nato);
        self::assertStringNotContainsString('Cesium', $map);
    }
}
