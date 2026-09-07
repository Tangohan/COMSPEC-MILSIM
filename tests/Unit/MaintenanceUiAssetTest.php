<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MaintenanceUiAssetTest extends TestCase
{
    public function testPublicMaintenancePageUsesAthenaLayoutAndHumanCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/errors/maintenance.php');

        self::assertStringContainsString('Maintenance', $view);
        self::assertStringContainsString('opérationnelle', $view);
        self::assertStringContainsString('intervention de fond', $view);
        self::assertStringContainsString('Tous les accès au site', $view);
        self::assertStringContainsString('les API, restent fermés', $view);
        self::assertStringContainsString('Réessayer', $view);
        self::assertStringContainsString('Athena', $view);
        self::assertStringContainsString('COMSPEC-MILSIM', $view);
        self::assertStringContainsString('fog-team.jpg', $view);
        self::assertStringNotContainsString('Code maintenance', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
    }

    public function testMaintenanceLayoutCannotOverflowANarrowViewport(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/errors/maintenance.php');

        self::assertStringContainsString('overflow-x:hidden', $view);
        self::assertStringContainsString('grid-template-columns:minmax(0,1fr)', $view);
        self::assertStringContainsString('.layout > * { min-width:0; }', $view);
        self::assertStringContainsString('.panel { position:relative; width:100%; max-width:100%; min-width:0;', $view);
        self::assertStringContainsString('font-size:clamp(2.65rem,12.7vw,4rem)', $view);
    }
}
