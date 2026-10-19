<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Overwatch en production : les fonctions qui étaient affichées sans effet sont branchées sur l'API.
 */
final class AtakOverwatchWiringAssetTest extends TestCase
{
    private function read(string $path): string
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($raw);

        return $raw;
    }

    public function testOrbatActionsReachTheApi(): void
    {
        $orbat = $this->read('public/assets/js/atak-overwatch-orbat.js');
        self::assertStringContainsString("order_type: 'SITREP'", $orbat);
        self::assertStringContainsString('beta.openSquadTaskForm(', $orbat);
        self::assertStringContainsString('beta.setUnitsHidden(', $orbat);
        self::assertStringContainsString('window.ATAK_CAPS.phoneSession', $orbat);
    }

    public function testUnitCommandsLayersAndLiveAlertsAreWired(): void
    {
        $beta = $this->read('public/assets/js/atak-overwatch-beta.js');
        self::assertStringContainsString("'/request-helmet-media'", $beta);
        self::assertStringContainsString("'/disconnect'", $beta);
        self::assertStringContainsString('data-unit-cmd', $beta);
        self::assertStringContainsString("localStorage.setItem('athena:ow-layer-'", $beta);
        self::assertStringContainsString("new CustomEvent('atak:units-updated'", $beta);
        self::assertStringContainsString('ATAKSuperPing.ingest(list)', $beta);
        self::assertStringContainsString('function locateSelf()', $beta);
        self::assertStringNotContainsString('<span>Satellites</span><strong>0</strong>', $beta);

        $intel = $this->read('public/assets/js/atak-overwatch-intel.js');
        self::assertStringContainsString("addEventListener('atak:tactical-alerts-updated'", $intel);

        $ops = $this->read('public/assets/js/atak-overwatch-ops.js');
        self::assertStringContainsString("'/api/atak/realism/config'", $ops);
        self::assertStringNotContainsString('window.AtakRealismConfig.get(', $ops);

        $gl = $this->read('public/assets/js/overwatch-gl/OverwatchGlLayers.js');
        self::assertStringContainsString('ow.isUnitHidden', $gl);
    }

    public function testMapPickerAndStationNotices(): void
    {
        $controller = $this->read('app/Controllers/Web/AtakController.php');
        self::assertStringContainsString("\$_GET['mapId']", $controller);

        $view = $this->read('views/atak-overwatch-beta.php');
        self::assertStringContainsString('name="mapId"', $view);
        self::assertStringContainsString('id="ow-session-notices"', $view);
        self::assertStringContainsString("url('atak/premiere-liaison')", $view);
        self::assertStringContainsString('atak-overwatch-session.js', $view);

        $session = $this->read('public/assets/js/atak-overwatch-session.js');
        self::assertStringContainsString('data-request-url', $session);
    }
}
