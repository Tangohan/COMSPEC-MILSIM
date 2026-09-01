<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapSettingsAsideAssetTest extends TestCase
{
    public function testSettingsAsideHostsMapReliefAndInventoryControls(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertStringContainsString('id="atak-settings-map"', $view);
        self::assertStringContainsString('Carte — relief et calques', $view);
        self::assertStringContainsString('id="atak-terrain-hillshade"', $view);
        self::assertStringContainsString('Ombrage', $view);
        self::assertStringContainsString('id="atak-terrain-3d-settings"', $view);
        self::assertStringContainsString('id="atak-terrain-inventory"', $view);
        self::assertStringContainsString('id="atak-settings-map-data"', $view);
        self::assertStringContainsString('Données carte sur ce poste', $view);
        self::assertStringContainsString('id="atak-map-look-motion-arrows"', $view);
        self::assertStringNotContainsString('geo_places', $view);
        self::assertStringNotContainsString('Three.js', $view);
        self::assertStringNotContainsString('CSS-pitch', $view);
        self::assertStringNotContainsString('atak-terrain-3d-hint', $view);
        // Contrôles terrain actifs dans Réglages, pas dupliqués dans le popup Affichage.
        self::assertSame(1, substr_count($view, 'id="atak-terrain-hillshade"'));
        self::assertSame(1, substr_count($view, 'id="atak-terrain-3d-settings"'));
        self::assertSame(1, substr_count($view, 'id="atak-terrain-inventory"'));
    }

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
    }
}
