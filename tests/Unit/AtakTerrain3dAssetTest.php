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
        self::assertStringContainsString('width >= 8 && height >= 8', $javascript);
        self::assertStringContainsString("src.indexOf('data:image/gif') === 0", $javascript);
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
        self::assertStringContainsString('normalizedHeight * 180 * state.verticalExaggeration', $javascript);
        self::assertStringNotContainsString('normalizedHeight * 110 * state.verticalExaggeration', $javascript);
        self::assertStringNotContainsString('Math.min(550, displacement)', $javascript);
        self::assertStringContainsString('id="atak-terrain-exaggeration"', $view);
        self::assertStringContainsString('Exagération Z', $view);
        self::assertStringContainsString('id="atak-terrain-pitch"', $view);
        self::assertStringContainsString('Inclinaison', $view);
        self::assertStringContainsString('id="atak-terrain-3d-mode"', $view);
        self::assertStringContainsString('Vue de la carte', $view);
        self::assertStringContainsString('<option value="flat" selected>À plat (2D)</option>', $view);
        self::assertStringContainsString('<option value="inclined">Topo premium 3D</option>', $view);
        self::assertDoesNotMatchRegularExpression('/id="atak-terrain-3d-settings"[^>]*\bhidden\b/', $view);
        self::assertStringContainsString("modeSelect.value = state.enabled ? 'inclined' : 'flat'", $javascript);
        self::assertStringContainsString('settings.hidden = false', $javascript);
        self::assertStringContainsString('settings.removeAttribute(\'hidden\')', $javascript);
        self::assertStringNotContainsString('settings.hidden = !state.enabled', $javascript);
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        self::assertStringContainsString('.atak-terrain-3d-settings[hidden] { display: block !important; }', $css);
        $tools = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map-tools.js');
        self::assertStringContainsString("getElementById('atak-terrain-3d-settings')", $tools);
        self::assertStringContainsString('terrain3d.hidden = false', $tools);
    }

    public function testReadyTerrainMeshUncoversTheReliefUnderLeafletTiles(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString(
            '.atak-map-stage.atak-map-stage--3d.atak-terrain-mesh-ready #atak-map .leaflet-tile-pane { opacity: 0; }',
            $css
        );
        self::assertStringNotContainsString(
            '.atak-map-stage.atak-map-stage--3d.atak-terrain-mesh-ready #atak-map .leaflet-tile-pane { opacity: 1; }',
            $css
        );
        self::assertStringContainsString('.leaflet-atakTerrainMesh-pane', $css);
        self::assertStringContainsString('.leaflet-atakScene3d-pane', $css);
    }

    public function testTerrainMeshMountsInsideALeafletPaneAboveTheTiles(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');

        self::assertStringContainsString("placeViewportCanvas(terrainCanvas, map, 'atakTerrainMeshPane', 250)", $javascript);
        self::assertStringContainsString('function placeViewportCanvas', $javascript);
        self::assertStringContainsString('containerPointToLayerPoint', $javascript);
        self::assertStringContainsString("mapEl.style.setProperty('--atak-map-pitch'", $javascript);
        self::assertStringNotContainsString('mapEl.appendChild(terrainCanvas)', $javascript);
        self::assertStringContainsString('if (state.enabled) scheduleTerrain()', $javascript);
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

    public function testInclinedViewStartsTheMeshWithoutTheMarkerDepthCheckbox(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');

        self::assertStringContainsString("window.addEventListener('atak:mapready'", $javascript);
        self::assertStringContainsString('if (state.enabled) startTerrain()', $javascript);
        self::assertStringContainsString('La case « Relief et profondeur » ne concerne que les pastilles', $javascript);
        self::assertStringNotContainsString('markerDepth', $javascript);
        self::assertStringNotContainsString('atak-map-look-depth', $javascript);
        self::assertStringNotContainsString('!data.ready', $javascript);
        self::assertStringContainsString("if (!data || !data.heights || data.encoding !== 'int16le_b64') return null", $javascript);
    }

    public function testTerrainMeshKeepsDrawingWhenTileTexturesAreBlocked(): void
    {
        $javascript = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain-3d.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');

        self::assertStringContainsString('function bindShadeTexture(gl)', $javascript);
        self::assertStringContainsString('vertices.push(sx, sy - relief)', $javascript);
        self::assertStringContainsString('var relief = reliefOffset(z)', $javascript);
        self::assertStringContainsString('drapedTiles += 1', $javascript);
        self::assertStringContainsString("terrainCanvas.classList.toggle('atak-terrain-mesh--draped', drapedTiles > 0)", $javascript);
        self::assertStringContainsString('.atak-terrain-mesh:not(.atak-terrain-mesh--draped) { mix-blend-mode: multiply; }', $css);
        self::assertStringContainsString('crossOrigin: true', $map);
    }
}
