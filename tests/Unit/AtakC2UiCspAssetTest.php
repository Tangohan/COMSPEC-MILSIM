<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakC2UiCspAssetTest extends TestCase
{
    public function testAtakPageVendorsThreeWithImportMap(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $init = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/terrain3d/initTerrain3D.js');

        self::assertStringContainsString('type="importmap"', $view);
        self::assertStringContainsString('assets/vendor/three/build/three.module.js', $view);
        self::assertStringContainsString('"three/addons/"', $view);
        self::assertStringContainsString('window.ATAK_THREE_BASE', $view);
        self::assertStringContainsString('resolveThreeBase', $init);
        self::assertStringContainsString("window.ATAK_THREE_BASE", $init);
        self::assertStringContainsString("import('three')", $init);
        self::assertStringContainsString("import('three/addons/controls/OrbitControls.js')", $init);
        self::assertStringContainsString('Multiple instances of Three.js', $init);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/vendor/three/build/three.module.js');
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/vendor/three/examples/jsm/controls/OrbitControls.js');
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/vendor/three/examples/jsm/renderers/CSS2DRenderer.js');
    }

    public function testC2RailButtonsExposeVisibleLabels(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-map-c2-v2.css');
        $controls = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/map/MapControls.js');

        self::assertStringContainsString('tac-c2-rail__label', $view);
        self::assertStringContainsString('>Mesure</span>', $view);
        self::assertStringContainsString('>Route</span>', $view);
        self::assertStringContainsString('.tac-c2-rail__label', $css);
        self::assertStringContainsString("btn('toggle-2d'", $controls);
        self::assertStringContainsString("btn('toggle-3d'", $controls);
        self::assertStringContainsString('tac-map-controls__label', $controls);
    }
}
