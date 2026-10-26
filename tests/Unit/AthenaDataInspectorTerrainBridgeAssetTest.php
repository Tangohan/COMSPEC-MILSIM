<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AthenaDataInspectorTerrainBridgeAssetTest extends TestCase
{
    public function testInspectorUsesTheExistingAtakTerrainAndSceneInventory(): void
    {
        $root = dirname(__DIR__, 2);
        $repository = (string) file_get_contents($root . '/app/Repositories/AthenaDataRepository.php');
        self::assertStringContainsString('new AtakTerrainRepository($this->pdo())', $repository);
        self::assertStringContainsString('new AtakSceneObjectRepository($this->pdo())', $repository);
        self::assertStringContainsString("'terrain_inventory'=>\$existingTerrain", $repository);
        self::assertStringContainsString("'scene_objects'=>\$existingTerrain['objects']", $repository);
        // Depuis la refonte au format back-office (0df0fcaa, /back-office/atak/data-inspector), la page affiche des
        // métriques et des tableaux, sans la carte de relief : l'inventaire terrain reste servi par le dépôt.
        $view = (string) file_get_contents($root . '/views/atak/data_inspector.php');
        self::assertStringContainsString("data-api=\"<?= \$h(url('api/athena/inspector/metrics')) ?>\"", $view);
        self::assertStringContainsString('athena-data-inspector.js', $view);
    }
}
