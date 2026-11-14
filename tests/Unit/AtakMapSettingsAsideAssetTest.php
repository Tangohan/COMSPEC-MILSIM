<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapSettingsAsideAssetTest extends TestCase
{
    public function testAffichageOpensSettingsAndMapControlsExposeCarteShortcut(): void
    {
        $tools = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map-tools.js');
        $controls = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/map/MapControls.js');
        $premium = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terrain3d-premium.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-terrain3d-premium.css');

        self::assertStringContainsString('function openMapSettings()', $tools);
        self::assertStringContainsString('openMapSettings: openMapSettings', $tools);
        self::assertStringContainsString("getElementById('atak-settings-map')", $tools);
        self::assertStringContainsString("btn('map-settings'", $controls);
        self::assertStringContainsString('Réglages carte', $controls);
        self::assertStringContainsString('is-booting', $premium);
        self::assertStringContainsString('invalidateLeafletSoon', $premium);
        self::assertStringContainsString('.terrain3d-host.is-booting', $css);
        self::assertStringContainsString('isolation: isolate', $css);
        self::assertStringContainsString('visibility: hidden', $css);
        self::assertStringContainsString('#atak-map.atak-map-2d-fallback', $css);
    }
}
