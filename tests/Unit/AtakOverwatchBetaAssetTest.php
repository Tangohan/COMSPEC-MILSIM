<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakOverwatchBetaAssetTest extends TestCase
{
    public function testBetaWorkspaceRouteAndCoreInteractionsArePresent(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $controller = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $betaView = file_get_contents(dirname(__DIR__, 2) . '/views/atak-overwatch-beta.php');
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-beta.js');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertIsString($controller);
        self::assertIsString($betaView);
        self::assertIsString($script);
        self::assertStringContainsString("'/-ATAK-OVERWATCH-Beta'", $routes);
        self::assertStringContainsString("? 'atak-overwatch-beta' : 'atak'", $controller);
        self::assertStringContainsString("require base_path('views/atak.php')", $betaView);
        self::assertStringContainsString('id="overwatch-commandbar"', $view);
        self::assertStringContainsString('data-overwatch-tab="mission"', $view);
        self::assertStringContainsString('data-overwatch-settings', $view);
        self::assertStringContainsString('id="overwatch-watchlist"', $view);
        self::assertStringContainsString('.atak-tab[data-tab="', $script);
        self::assertStringContainsString("new KeyboardEvent('keydown'", $script);
        self::assertStringContainsString('athena:overwatch-watchlist:', $script);
        self::assertStringContainsString('data-overwatch-watch', $script);
        self::assertStringContainsString('data-overwatch-tool="line"', $view);
        self::assertStringContainsString('data-overwatch-squad-lines', $view);
        self::assertStringContainsString('atak-geo-places', $script);
        self::assertStringContainsString('window.L.polyline([point, center]', $script);
        self::assertStringContainsString("document.getElementById('atak-view-3d')", $script);
    }
}
