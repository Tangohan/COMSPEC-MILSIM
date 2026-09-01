<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakC2PlayerMarkerAppearanceAssetTest extends TestCase
{
    public function testLiveMarkersHonorDisplayPrefsAndStayStable(): void
    {
        $root = dirname(__DIR__, 2);
        $bridge = (string) file_get_contents($root . '/public/assets/js/map/atak-c2-bridge.js');
        $manager = (string) file_get_contents($root . '/public/assets/js/map/MarkerManager.js');
        $lod = (string) file_get_contents($root . '/public/assets/js/map/MarkerLOD.js');
        $symbol = (string) file_get_contents($root . '/public/assets/js/map/TacticalSymbol.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-map-c2-v2.css');
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $view = (string) file_get_contents($root . '/views/atak.php');

        self::assertStringContainsString("new MarkerManager({ map: map, clustering: false })", $bridge);
        self::assertStringContainsString("addEventListener('atak:display-prefs-changed'", $bridge);
        self::assertStringContainsString('headingRounded', $bridge);
        self::assertStringNotContainsString('_setUnitsMarkersC2Wrapped', $bridge);

        self::assertStringContainsString('applyDisplaySize', $manager);
        self::assertStringContainsString('this.map.on(\'zoomend\'', $manager);
        self::assertStringNotContainsString("map.on('zoomend moveend'", $manager);
        self::assertStringContainsString('marker._tacSig === sig', $manager);
        self::assertStringContainsString('getDisplayPrefs', $manager);

        self::assertStringContainsString('export function applyDisplaySize', $lod);
        self::assertStringContainsString('showCallsign: true', $lod);

        self::assertStringContainsString('markerHoverLines', $symbol);
        self::assertStringContainsString('En liaison', $symbol);
        self::assertStringNotContainsString('tac-marker__status', $symbol);

        self::assertStringContainsString('var(--atak-unit-label-size, 11px)', $css);
        self::assertStringContainsString('overflow: visible !important', $css);

        self::assertStringContainsString('labelSize: 11', $map);
        self::assertStringContainsString('iconSize: 20', $map);
        self::assertStringContainsString('clampNum(src.labelSize, 9, 16', $map);

        self::assertStringContainsString('Libellés des unités', $view);
        self::assertStringContainsString('id="atak-settings-look-icon-size"', $view);
        self::assertStringContainsString('max="28"', $view);
        self::assertStringNotContainsString('Libellés infobulle', $view);
    }
}
