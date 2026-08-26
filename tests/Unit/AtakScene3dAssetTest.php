<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakScene3dAssetTest extends TestCase
{
    public function testSceneRendererLoadsVisibleGameObjectsAndExtrudesThem(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-scene-3d.js');
        self::assertStringContainsString('/api/atak/scene?mapId=', $js);
        self::assertStringContainsString("item.kind === 'forest'", $js);
        self::assertStringContainsString('var top = base.map', $js);
    }

    public function testSceneControlsAndAssetArePresent(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        self::assertStringContainsString('id="atak-scene-buildings"', $view);
        self::assertStringContainsString('assets/js/atak-scene-3d.js', $view);
    }

    public function testSceneRendererNeverFetchesPngPreviewsForObjectIds(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-scene-3d.js');
        self::assertStringNotContainsString("id + '.png'", $js);
        self::assertStringNotContainsString('id+".png"', $js);
        self::assertStringNotContainsString("item.id + '.png'", $js);
        self::assertStringContainsString('jamais d’image d’identifiant', $js);
        $markers = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/arma-map-markers.js');
        self::assertStringContainsString('function isBareNumericPng(rel)', $markers);
        self::assertStringContainsString('if (isBareNumericPng(raw)) return \'\';', $markers);
    }
}
