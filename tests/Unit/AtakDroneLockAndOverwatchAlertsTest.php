<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakDroneLockAndOverwatchAlertsTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testLockAndFlirFunctionsAreRegisteredAndWired(): void
    {
        $main = 'mod/COMSPEC_ATAK_Native/Sources/addons/main';
        self::assertStringContainsString('class lockAction {}; class droneFlir {};', $this->read($main . '/config.cpp'));
        $client = $this->read($main . '/XEH_postInitClient.sqf');
        self::assertStringContainsString('"LockMark"', $client);
        self::assertStringContainsString('"DroneView"', $client);
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_lockAction', $client);
        $pre = $this->read($main . '/XEH_preInit.sqf');
        foreach (['enabled', 'live', 'grace', 'lost', 'keep', 'reacquire', 'range'] as $k) {
            self::assertStringContainsString('"comspec_atak_native_lock_' . $k . '"', $pre, $k);
        }
        self::assertStringContainsString('["build", [_vx, _vy, _vw, _vh], "FULL", false]', $this->read($main . '/functions/pages/fn_pageMap.sqf'));
        self::assertStringContainsString('"FEED", true] call comspec_atak_native_fnc_droneFlir', $this->read($main . '/functions/drone/fn_pageDrone.sqf'));
        self::assertStringContainsString('["mark", "PHONE"]', $this->read($main . '/functions/pages/fn_photoMode.sqf'));
    }

    public function testOverwatchAlertDismissAndSeenRoutes(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/api/atak/overwatch/alerts/dismiss', [\\App\\Controllers\\Api\\AtakOverwatchIntelController::class, 'alertDismiss']", $routes);
        self::assertStringContainsString("'/api/atak/overwatch/alerts/seen', [\\App\\Controllers\\Api\\AtakOverwatchIntelController::class, 'alertSeen']", $routes);
        $migration = $this->read('bootstrap/atak_overwatch_intel_migration.php');
        self::assertStringContainsString('dismissed_at', $migration);
        self::assertStringContainsString('atak_overwatch_alert_seen', $migration);
        $js = $this->read('public/assets/js/atak-overwatch-intel.js');
        self::assertStringContainsString('/api/atak/overwatch/alerts/dismiss', $js);
        self::assertStringContainsString('function receiptsOf(a)', $js);
    }

    public function testCommandsPanelCanBeHidden(): void
    {
        self::assertStringContainsString('.owc-panel[hidden]{display:none!important}', $this->read('public/assets/css/atak-overwatch-commands.css'));
    }
}
