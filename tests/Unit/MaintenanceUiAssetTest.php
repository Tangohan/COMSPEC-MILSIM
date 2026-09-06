<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MaintenanceUiAssetTest extends TestCase
{
    public function testPublicMaintenancePageUsesCalmAthenaLayoutAndHumanCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/errors/maintenance.php');

        self::assertStringContainsString('Nous revenons bientôt', $view);
        self::assertStringContainsString('momentanément fermé', $view);
        self::assertStringContainsString('Vos données restent en sécurité', $view);
        self::assertStringContainsString('Réessayer', $view);
        self::assertStringContainsString('Athena', $view);
        self::assertStringContainsString('fog-team.jpg', $view);
        self::assertStringContainsString('Instrument Serif', $view);
        self::assertStringNotContainsString('Space Mono', $view);
        self::assertStringNotContainsString('PORTAIL OPÉRATIONNEL', $view);
        self::assertStringNotContainsString('classification', $view);
        self::assertStringNotContainsString('ATH-', $view);
        self::assertStringNotContainsString('Code maintenance', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('les API', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
    }
}
