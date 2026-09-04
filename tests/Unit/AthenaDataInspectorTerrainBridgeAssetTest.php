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
        $view = (string) file_get_contents($root . '/views/atak/data_inspector.php');
        $javascript = (string) file_get_contents($root . '/public/assets/js/athena-data-inspector.js');

        self::assertStringContainsString('new AtakTerrainRepository($this->pdo())', $repository);
        self::assertStringContainsString('new AtakSceneObjectRepository($this->pdo())', $repository);
        self::assertStringContainsString("'terrain_inventory'=>\$existingTerrain", $repository);
        self::assertStringContainsString("'scene_objects'=>\$existingTerrain['objects']", $repository);
        self::assertStringContainsString('data-hillshade-api=', $view);
        self::assertStringContainsString('Bâtiment', $view);
        self::assertStringContainsString('Forêt', $view);
        self::assertStringContainsString('requestTerrainImage', $javascript);
        self::assertStringContainsString('(data.scene_objects||[]).forEach', $javascript);
        self::assertStringContainsString('inv.buildings', $javascript);
        self::assertStringContainsString('inv.forests', $javascript);
    }
}
