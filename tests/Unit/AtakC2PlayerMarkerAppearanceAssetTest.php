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
        self::assertStringContainsString('getSize', $manager);
        self::assertStringContainsString('size.x < 8', $manager);

        self::assertStringContainsString('export function applyDisplaySize', $lod);
        self::assertStringContainsString('showCallsign: true', $lod);

        self::assertStringContainsString('markerHoverLines', $symbol);
        self::assertStringContainsString('En liaison', $symbol);
        self::assertStringNotContainsString('tac-marker__status', $symbol);

        self::assertStringContainsString('var(--atak-unit-label-size, 11px)', $css);
        self::assertStringContainsString('overflow: visible !important', $css);

        self::assertStringContainsString('c2Ready', $map);
        self::assertStringContainsString('dessiner les effectifs en secours', $map);
        self::assertStringContainsString('showLabel: true', $map);
        self::assertStringContainsString('refreshMarkersAgainstUnits', $map);

        self::assertStringContainsString('ORIGIN_EPS', $bridge);
        self::assertStringContainsString('grid_ref', $bridge);
        self::assertStringContainsString("e.status === 'LOST' && !e.keepLastKnown", $bridge);
        self::assertStringContainsString('&& !e.isPlayer', $bridge);
        self::assertStringContainsString('isPlayer: !isAi', $bridge);
        self::assertStringContainsString('keepLastKnown', $bridge);
        self::assertStringContainsString('display_call_sign', $bridge);
        self::assertStringContainsString("else live = 'ONLINE'", $bridge);

        self::assertStringContainsString('labelW', $manager);
        self::assertStringContainsString('tac-marker-wrap', $manager);
        self::assertStringContainsString('zIndexOffset: 850', $manager);

        self::assertStringContainsString('tac-marker-wrap', $css);
        self::assertStringContainsString('overflow: visible', $css);
        self::assertStringContainsString('width: max-content', $css);
        self::assertMatchesRegularExpression('/\.tac-marker-wrap\s*\{[^}]*isolation:\s*isolate;/s', $css);
        self::assertMatchesRegularExpression('/\.tac-marker__callsign\s*\{[^}]*position:\s*relative;[^}]*z-index:\s*3;[^}]*display:\s*block;/s', $css);
        self::assertStringContainsString('iconSize: 20', $map);
        self::assertStringContainsString('clampNum(src.labelSize, 9, 16', $map);
        self::assertStringContainsString('une position joueur deja recue', $map);

        self::assertStringContainsString('Libellés des unités', $view);
        self::assertStringContainsString('id="atak-settings-look-icon-size"', $view);
        self::assertStringContainsString('max="28"', $view);
        self::assertStringNotContainsString('Libellés infobulle', $view);
    }
}
