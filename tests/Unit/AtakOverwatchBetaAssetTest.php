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
        self::assertStringContainsString("require base_path('views/atak.php')", $view);
        self::assertStringContainsString("[AtakController::class, 'overwatchBeta']", $routes);

        $atakController = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');
        $atakView = file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $atakV2 = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-v2.js');
        $overwatchJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-beta.js');
        self::assertIsString($atakController);
        self::assertIsString($atakView);
        self::assertIsString($atakV2);
        self::assertIsString($overwatchJs);
        self::assertStringContainsString("\$params['_overwatch_beta'] = true", $atakController);
        self::assertStringContainsString('window.ATAK_OVERWATCH_BETA', $atakView);
        self::assertStringContainsString('atak-overwatch-beta.css', $atakView);
        self::assertStringContainsString("if (window.ATAK_OVERWATCH_BETA) requested = 'v2'", $atakV2);
        self::assertStringContainsString("select('comms', 'chat')", $overwatchJs);
        self::assertStringContainsString("select('c2', 'mission')", $overwatchJs);
        self::assertStringContainsString('data-overwatch-basemap="classic"', $overwatchJs);
        self::assertStringContainsString('data-overwatch-basemap="aerial"', $overwatchJs);
        self::assertStringContainsString('data-overwatch-basemap="mono"', $overwatchJs);
    }
}
