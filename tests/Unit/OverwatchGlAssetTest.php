<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchGlAssetTest extends TestCase
{
    public function testOverwatchBetaOffersVolumeReliefInsteadOfCssPitch(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        $map = (string) file_get_contents($root . '/public/assets/js/overwatch-gl/OverwatchGlMap.js');
        $proj = (string) file_get_contents($root . '/public/assets/js/overwatch-gl/TheaterProjection.js');
        $layers = (string) file_get_contents($root . '/public/assets/js/overwatch-gl/OverwatchGlLayers.js');

        self::assertStringContainsString('<option value="flat" selected>À plat (2D)</option>', $view);
        self::assertStringContainsString('<option value="immersive">2D immersif</option>', $view);
        self::assertStringContainsString('<option value="volume">Relief 3D</option>', $view);
        self::assertStringContainsString('<option value="tactical">Tactique 3D</option>', $view);
        self::assertStringContainsString('id="ow-cam-bar"', $view);
        self::assertStringContainsString('id="atak-scene-quality"', $view);
        self::assertStringContainsString('id="atak-terrain-inv-obstacles"', $view);
        self::assertStringContainsString('id="atak-symbol-occlusion"', $view);
        self::assertStringContainsString('id="atak-ghost-trails"', $view);
        self::assertStringContainsString('id="atak-scene-inspector"', $view);
        self::assertStringContainsString('id="atak-coverage-diag"', $view);
        self::assertStringContainsString('data-tool="viewshed"', $view);
        self::assertStringContainsString('data-tool="horizon"', $view);
        self::assertStringContainsString('data-tool="slice"', $view);
        self::assertStringContainsString('data-tool="compare"', $view);
        self::assertStringContainsString('OverwatchGlTactics.js', $view);
        self::assertStringNotContainsString('<option value="inclined">Relief 3D</option>', $view);
        self::assertStringContainsString('id="ow-gl-map"', $view);
        self::assertStringContainsString('id="atak-scene-buildings"', $view);
        self::assertStringContainsString('vendor/maplibre-gl/maplibre-gl.js', $view);
        self::assertStringContainsString('vendor/deck.gl/deck.min.js', $view);
        self::assertStringNotContainsString('Cesium', $view);
        self::assertStringNotContainsString('cesium', $view);
        self::assertStringNotContainsString('atak-scene-3d.js', $view);
        self::assertStringNotContainsString('atak-terrain-3d.js', $view);

        self::assertStringContainsString('offsetX', $proj);
        self::assertStringContainsString('offsetY', $proj);
        self::assertStringContainsString('worldSize', $proj);
        self::assertStringContainsString('111319.49079327358', $proj);
        self::assertStringContainsString('owtile', $map);
        self::assertStringContainsString('/api/atak/terrain/rgb/{z}/{x}/{y}', $map);
        self::assertStringContainsString('/api/atak/tiles', $proj);
        self::assertStringContainsString('proxiedTileUrl', $proj);
        self::assertStringContainsString('tile.x >= 0 && tile.y >= 0', $map);
        self::assertStringContainsString("v === 'flat' || v === 'immersive'", $map);
        self::assertStringContainsString("storedMode() === 'immersive'", $map);
        self::assertStringContainsString('destroyGl();', $map);
        self::assertStringContainsString('setTerrain', $map);

        self::assertStringContainsString("'&kind=' + encodeURIComponent(kind)", $layers);
        self::assertStringContainsString("fetchKind('building'", $layers);
        self::assertStringContainsString("fetchKind('forest'", $layers);
        self::assertStringContainsString('/api/atak/scene/mesh', $layers);
        self::assertStringContainsString('lodForZoom', $layers);
        self::assertStringContainsString("return '0'", $layers);
        self::assertStringContainsString("return '3'", $layers);
        self::assertStringContainsString('ow-gl-obstacles', $layers);
        self::assertStringContainsString('inspectSceneObject', $layers);
        self::assertStringContainsString('atak-scene-quality', $layers);
        self::assertStringContainsString('handleWorldClick', $layers);
        self::assertStringContainsString('pickable: true', $layers);
        self::assertStringContainsString('fill-extrusion', $layers);
        self::assertStringContainsString('clusterForests', $layers);
        self::assertStringContainsString('heightAt', $layers);
        self::assertStringContainsString('AGL', $layers);
        self::assertStringContainsString('bbox', $layers);
        self::assertStringContainsString('MapboxOverlay', $layers);
        self::assertStringContainsString('getUnits', $layers);
        self::assertStringContainsString('selectUnit', $layers);
        self::assertStringContainsString('setLight', $map);
        self::assertStringContainsString('overwatch:weather', $map);
        self::assertStringContainsString('daytime', $map);
        self::assertStringNotContainsString('Cesium', $layers);
        self::assertStringNotContainsString('model_class', $layers);
        self::assertFileExists($root . '/public/assets/vendor/maplibre-gl/maplibre-gl.js');
        self::assertFileExists($root . '/public/assets/vendor/deck.gl/deck.min.js');
        self::assertGreaterThan(100000, (int) filesize($root . '/public/assets/vendor/maplibre-gl/maplibre-gl.js'));
        self::assertGreaterThan(100000, (int) filesize($root . '/public/assets/vendor/deck.gl/deck.min.js'));
        $beta = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        self::assertStringContainsString('function inspectWorld', $beta);
        self::assertStringContainsString('function inspectSceneObject', $beta);
        self::assertStringContainsString('loadSceneFootprints', $beta);
        self::assertStringContainsString('tryInspectScene', $beta);
        self::assertStringContainsString('/api/atak/scene?mapId=', $beta);
        self::assertStringContainsString('data-scene-act="task"', $beta);
        self::assertStringContainsString('data-scene-act="plan"', $beta);
        self::assertStringContainsString('getSceneBuildings', $beta);
        self::assertStringContainsString('hitSceneAt(ll, force)', $beta);
        self::assertStringContainsString('data-scene-act="anchor"', $beta);
        self::assertStringContainsString('data-tool="viewshed"', $beta);
        self::assertStringContainsString('measure3d', $beta);
        $tactics = (string) file_get_contents($root . '/public/assets/js/overwatch-gl/OverwatchGlTactics.js');
        self::assertStringContainsString('/api/atak/terrain/viewshed', $tactics);
        self::assertStringContainsString('/api/atak/terrain/horizon', $tactics);
        self::assertStringContainsString('/api/atak/terrain/slice', $tactics);
        self::assertStringContainsString('setSplit', $tactics);
        self::assertStringContainsString('captureBookmark', $tactics);
        self::assertStringContainsString('terrain_gaps', $tactics);
        self::assertStringContainsString('indexedDB', $layers);
        self::assertStringContainsString('ow-scene-cache', $layers);
        self::assertStringContainsString('padE', $layers);
        self::assertStringContainsString('ow-gl-stacks', $layers);
        self::assertStringContainsString('occlusion', $layers);
        self::assertStringContainsString('HeatmapLayer', $layers);
        self::assertStringContainsString('setSplit', $map);
        self::assertStringContainsString('getCamera', $map);
        self::assertStringContainsString('trackResize: false', $map);
        self::assertStringContainsString('scheduleResize', $map);
        self::assertStringContainsString('already running', $map);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $terrain = (string) file_get_contents($root . '/app/Controllers/Api/AtakTerrainApiController.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakSceneApiController.php');
        self::assertStringContainsString("'/api/atak/terrain/viewshed'", $routes);
        self::assertStringContainsString("'/api/atak/scene/anchor'", $routes);
        self::assertStringContainsString('function viewshed', $terrain);
        self::assertStringContainsString('function horizon', $terrain);
        self::assertStringContainsString('function slice', $terrain);
        self::assertStringContainsString('function measure', $terrain);
        self::assertStringContainsString('function anchor', $ctrl);
        self::assertStringContainsString('terrain_gaps', $ctrl);
        self::assertStringContainsString('floor_labels', $ctrl);
        self::assertStringContainsString('function handleWorldClick', $beta);
        self::assertStringContainsString('applyCameraMode', $map);
        self::assertStringContainsString('data-ow-cam', $map);
        self::assertStringContainsString('hitDeletable', $beta);
        $weatherSqf = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeWeather.sqf');
        self::assertStringContainsString('daytime', $weatherSqf);
        $bake = (string) file_get_contents($root . '/app/Services/Tactical/AtakSceneMeshBake.php');
        self::assertStringContainsString('scene-mesh.json', $bake);
        self::assertStringContainsString('scene-anomalies.log', $bake);
        self::assertStringContainsString('function lodClusters', $bake);
        $bounds = (string) file_get_contents($root . '/app/Services/Tactical/AtakSceneBounds.php');
        self::assertStringContainsString('MAX_HEIGHT', $bounds);
    }

    public function testClassicAtakKeepsInclinedMeshAndIsUntouchedByOverwatchGl(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak.php');
        self::assertStringContainsString('<option value="inclined">Relief 3D</option>', $view);
        self::assertStringContainsString('atak-terrain-3d.js', $view);
        self::assertStringContainsString('atak-scene-3d.js', $view);
        self::assertStringNotContainsString('overwatch-gl/OverwatchGlMap.js', $view);
        self::assertStringNotContainsString('vendor/maplibre-gl/maplibre-gl.js', $view);
    }

    public function testSceneApiFiltersKindAndOmitsFileIdentifiers(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakSceneApiController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakSceneObjectRepository.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $terrain = (string) file_get_contents($root . '/app/Controllers/Api/AtakTerrainApiController.php');

        self::assertStringContainsString('normalizeKind', $ctrl);
        self::assertStringContainsString("\$limit = min(40000, \$limit)", $ctrl);
        self::assertStringNotContainsString("'model_class'", $ctrl);
        self::assertStringNotContainsString('model_class', substr($ctrl, (int) strpos($ctrl, 'function index')));
        self::assertStringContainsString('kindSql', $repo);
        self::assertStringContainsString("'/api/atak/terrain/rgb/{z}/{x}/{y}'", $routes);
        self::assertStringContainsString("'/api/atak/scene/mesh'", $routes);
        self::assertStringContainsString("'/api/atak/scene/object'", $routes);
        self::assertStringContainsString('function mesh', $ctrl);
        self::assertStringContainsString('function show', $ctrl);
        self::assertStringContainsString('AtakSceneMeshBake', $ctrl);
        self::assertStringContainsString('AtakSceneBounds', $ctrl);
        self::assertStringContainsString("'id' => (string) (\$item['id'] ?? '')", $ctrl);
        self::assertStringContainsString('function rgbTile', $terrain);
        self::assertStringContainsString('AtakTerrainRgb', $terrain);
    }
}
