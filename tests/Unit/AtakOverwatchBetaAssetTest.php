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

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertStringContainsString("'/-ATAK-OVERWATCH-Beta'", $routes);
        self::assertStringContainsString('data-panel="mission"', $view);
        self::assertStringContainsString('data-panel="layers"', $view);
        self::assertStringContainsString('id="command-palette"', $view);
        self::assertStringContainsString('id="map-context-menu"', $view);
        self::assertStringContainsString('id="mission-timeline"', $view);
        self::assertStringContainsString('event.ctrlKey || event.metaKey', $view);
    }
}
