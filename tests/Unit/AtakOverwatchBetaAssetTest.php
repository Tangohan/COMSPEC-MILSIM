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
        self::assertStringNotContainsString("require base_path('views/atak.php')", $view);
        self::assertStringContainsString('class="ow-shell"', $view);
        self::assertStringContainsString('id="ow-map"', $view);
        self::assertStringContainsString("[AtakController::class, 'overwatchBeta']", $routes);

        $atakController = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');
        $atakApiController = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        $overwatchJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-beta.js');
        $realtimeJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-realtime.js');
        $p2Js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-p2.js');
        $recipe = file_get_contents(dirname(__DIR__, 2) . '/scripts/qa/atak-overwatch-beta-recipe.sh');
        self::assertIsString($atakController);
        self::assertIsString($atakApiController);
        self::assertIsString($overwatchJs);
        self::assertIsString($realtimeJs);
        self::assertIsString($p2Js);
        self::assertIsString($recipe);
        self::assertStringContainsString("\$params['_overwatch_beta'] = true", $atakController);
        self::assertStringContainsString("'atakOverwatchBeta' => !empty(\$params['_overwatch_beta'])", $atakController);
        self::assertStringContainsString('window.ATAK_OVERWATCH_BETA = true', $view);
        self::assertStringContainsString('atak-overwatch-beta.css', $view);
        self::assertStringContainsString("'/api/units?mapId='", $overwatchJs);
        self::assertStringContainsString('L.map(\'ow-map\'', $overwatchJs);
        self::assertStringContainsString('renderMap(); renderList(); syncStatus(true)', $overwatchJs);
        self::assertStringContainsString('window.__OVERWATCH_STANDALONE__', $overwatchJs);
        self::assertStringContainsString("'/api/atak/stream'", $routes);
        self::assertStringContainsString('text/event-stream', $atakApiController);
        self::assertStringContainsString("new EventSource(base + '/api/atak/stream", $realtimeJs);
        self::assertStringContainsString('setRealtimeActive(active)', $realtimeJs);
        self::assertSame('support', \App\Support\AtakChatChannel::normalizeKey('SUPPORT'));
        self::assertSame('Support technique', \App\Support\AtakChatChannel::labelFor('support'));
        self::assertStringContainsString("FORMAT = 'athena-overwatch-mission'", $p2Js);
        self::assertStringContainsString('MAX_IMPORT_SHAPES = 50', $p2Js);
        self::assertStringContainsString('ATAKMapShapes.createShape', $p2Js);
        self::assertStringContainsString('stream anonyme refusé', $recipe);
        self::assertStringContainsString("event: units", $recipe);
        self::assertStringNotContainsString('ALPHA 1-1', $view);
        self::assertStringNotContainsString('CONTACT C-018', $view);
    }
}
