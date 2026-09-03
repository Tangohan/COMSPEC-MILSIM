<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AtakTrailVisibilityPreferencesAssetTest extends TestCase
{
    public function testTrailVisibilityCanBePersistedGloballyAndByType(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak.php');
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $layers = (string) file_get_contents($root . '/public/assets/js/atak-sse-layers.js');
        $bridge = (string) file_get_contents($root . '/public/assets/js/map/atak-c2-bridge.js');

        self::assertStringContainsString("var DISPLAY_PREFS_KEY = 'atak_map_display_prefs'", $map);
        self::assertStringContainsString("bindSseToggle('atak-show-unit-trails', 'showUnitTrails')", $map);

        foreach (['phone', 'vehicle', 'infantry', 'air'] as $kind) {
            self::assertStringContainsString('id="atak-show-unit-trail-<?= $trailKind ?>"', $view);
            self::assertStringContainsString("showUnitTrail_{$kind}: src.showUnitTrail_{$kind} !== false", $map);
        }

        self::assertStringContainsString("p['showUnitTrail_' + kind] === false", $layers);
        self::assertStringContainsString("prefs()['showUnitTrail_' + kind] === false", $layers);
        self::assertStringContainsString('trailPrefs.showUnitTrails === false ? []', $bridge);
        self::assertStringContainsString("trailPrefs['showUnitTrail_' + kind] !== false", $bridge);
    }
}
