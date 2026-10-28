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

    public function testClassicModulesPortedToOverwatch(): void
    {
        $beta = $this->read('public/assets/js/atak-overwatch-beta.js');
        self::assertStringContainsString("'/api/atak/medevac/' : '/api/cas/'", $beta);
        self::assertStringContainsString('data-ow-medevac-delete', $beta);
        self::assertStringContainsString("'/api/atak/mission-plan?mapId='", $beta);
        self::assertStringContainsString('window.ATAKMissionPlan = {', $beta);

        $telemetry = $this->read('public/assets/js/atak-overwatch-telemetry.js');
        self::assertStringContainsString("'/api/atak/logistics/resupply/status'", $telemetry);
        self::assertStringContainsString("meta.kind !== 'resupply_request'", $telemetry);

        $tools = $this->read('public/assets/js/atak-overwatch-tools.js');
        self::assertStringContainsString("'/api/atak/session-workspace'", $tools);
    }

    public function testTeamsReplayCamsAndTerminalsArePorted(): void
    {
        $view = $this->read('views/atak-overwatch-beta.php');
        self::assertStringContainsString('data-view="teams"', $view);
        self::assertStringContainsString('atak-overwatch-teams.js', $view);
        self::assertStringContainsString('id="ow-replay-session"', $view);
        self::assertStringContainsString('id="ow-replay-events"', $view);

        $teams = $this->read('public/assets/js/atak-overwatch-teams.js');
        self::assertStringContainsString("'/api/atak/fire-teams'", $teams);
        self::assertStringContainsString("'/api/atak/assignments'", $teams);
        self::assertStringContainsString('consumeMapClick: consumeMapClick', $teams);

        $beta = $this->read('public/assets/js/atak-overwatch-beta.js');
        self::assertStringContainsString('window.OverwatchTeams.consumeMapClick(ll)', $beta);
        self::assertStringContainsString("'/api/atak/personnel'", $beta);
        self::assertStringContainsString('link_user_id: Number(userId)', $beta);
        self::assertStringContainsString("'/certificate/regenerate'", $beta);
        self::assertStringContainsString("'/api/atak/terminals/compromise'", $beta);
        self::assertStringContainsString("'/api/atak/video-feeds?mapId='", $beta);

        $ops = $this->read('public/assets/js/atak-overwatch-ops.js');
        self::assertStringContainsString("'/api/replay/sessions/'", $ops);
        self::assertStringContainsString("'/api/replay/events/'", $ops);
    }
}
