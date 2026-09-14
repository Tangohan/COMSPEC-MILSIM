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
        $atakApiController = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        $atakView = file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $atakV2 = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-v2.js');
        $overwatchJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-beta.js');
        $realtimeJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-realtime.js');
        $p2Js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-p2.js');
        $recipe = file_get_contents(dirname(__DIR__, 2) . '/scripts/qa/atak-overwatch-beta-recipe.sh');
        self::assertIsString($atakController);
        self::assertIsString($atakApiController);
        self::assertIsString($atakView);
        self::assertIsString($atakV2);
        self::assertIsString($overwatchJs);
        self::assertIsString($realtimeJs);
        self::assertIsString($p2Js);
        self::assertIsString($recipe);
        self::assertStringContainsString("\$params['_overwatch_beta'] = true", $atakController);
        self::assertStringContainsString("'atakOverwatchBeta' => !empty(\$params['_overwatch_beta'])", $atakController);
        self::assertStringContainsString('window.ATAK_OVERWATCH_BETA', $atakView);
        self::assertStringContainsString('atak-overwatch-beta.css', $atakView);
        self::assertStringContainsString("if (window.ATAK_OVERWATCH_BETA) requested = 'v2'", $atakV2);
        self::assertStringContainsString("openTab(button.dataset.overwatchTab, button)", $overwatchJs);
        self::assertStringContainsString("window.addEventListener('atak:units-updated'", $overwatchJs);
        self::assertStringContainsString("label.textContent = offline ? 'RECONNEXION'", $overwatchJs);
        self::assertStringContainsString("window.__ATAK_OVERWATCH_BOOTSTRAPPED__", $overwatchJs);
        self::assertStringContainsString("window.ATAKPolling.setInterval(requested)", $overwatchJs);
        self::assertStringContainsString("'atak:poll-interval-changed'", $overwatchJs);
        self::assertStringContainsString('id="overwatch-refresh-rate"', $atakView);
        self::assertStringContainsString('setTacticalPollInterval', $atakView);
        self::assertStringContainsString("'/api/atak/stream'", $routes);
        self::assertStringContainsString('text/event-stream', $atakApiController);
        self::assertStringContainsString("new EventSource(base + '/api/atak/stream", $realtimeJs);
        self::assertStringContainsString('setRealtimeActive(active)', $realtimeJs);
        self::assertSame('support', \App\Support\AtakChatChannel::normalizeKey('SUPPORT'));
        self::assertSame('Support technique', \App\Support\AtakChatChannel::labelFor('support'));
        self::assertStringContainsString("FORMAT = 'athena-overwatch-mission'", $p2Js);
        self::assertStringContainsString('MAX_IMPORT_SHAPES = 50', $p2Js);
        self::assertStringContainsString('ATAKMapShapes.createShape', $p2Js);
        self::assertStringContainsString('data-overwatch-export', $atakView);
        self::assertStringContainsString('data-overwatch-print', $atakView);
        self::assertStringContainsString('stream anonyme refusé', $recipe);
        self::assertStringContainsString("event: units", $recipe);
        self::assertStringContainsString('data-overwatch-basemap="classic"', $overwatchJs);
        self::assertStringContainsString('data-overwatch-basemap="aerial"', $overwatchJs);
        self::assertStringContainsString('data-overwatch-basemap="mono"', $overwatchJs);
    }
}
