<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakOverwatchBetaAssetTest extends TestCase
{
    public function testBetaWorkspaceRouteAndCoreInteractionsArePresent(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/views/atak-overwatch-beta.php');
        $dashboardAside = file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_aside.php');
        $sidebar = file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertIsString($dashboardAside);
        self::assertIsString($sidebar);
        self::assertStringContainsString("'/-ATAK-OVERWATCH-Beta'", $routes);
        self::assertStringContainsString("'/atak-overwatch-beta'", $routes);
        self::assertStringContainsString("url('-ATAK-OVERWATCH-Beta')", $dashboardAside);
        self::assertStringContainsString("'Overwatch Beta'", $sidebar);
        self::assertStringContainsString('data-panel="mission"', $view);
        self::assertStringContainsString('data-panel="layers"', $view);
        self::assertStringContainsString('id="command-palette"', $view);
        self::assertStringContainsString('id="map-context-menu"', $view);
        self::assertStringContainsString('id="mission-timeline"', $view);
        self::assertStringContainsString('event.ctrlKey || event.metaKey', $view);
    }
}
