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
        self::assertStringContainsString('Arma 3 et le téléphone ATAK restent utilisables', $view);
        self::assertStringContainsString('Réessayer', $view);
        self::assertStringContainsString('Athena', $view);
        self::assertStringContainsString('COMSPEC-MILSIM', $view);
        self::assertStringContainsString('fog-team.jpg', $view);
        self::assertStringNotContainsString('Code maintenance', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
    }
}
