<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakSceneMeshBake;
use PHPUnit\Framework\TestCase;

final class AtakSceneMeshBakeTest extends TestCase
{
    public function testLodMergesSmallNeighborsAndKeepsTallVolumes(): void
    {
        $rows = [
            ['id' => 'a', 'x' => 10.0, 'y' => 10.0, 'width' => 8.0, 'depth' => 8.0, 'height' => 4.0, 'base_z' => 2.0],
            ['id' => 'b', 'x' => 18.0, 'y' => 12.0, 'width' => 6.0, 'depth' => 6.0, 'height' => 3.0, 'base_z' => 2.0],
            ['id' => 'tower', 'x' => 14.0, 'y' => 11.0, 'width' => 10.0, 'depth' => 10.0, 'height' => 20.0, 'base_z' => 2.0],
        ];
        $out = AtakSceneMeshBake::lodClusters($rows, 120.0, 360.0);
        $ids = array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), $out);
        self::assertContains('tower', $ids);
        $clusters = array_values(array_filter($out, static fn (array $row): bool => !empty($row['cluster'])));
        self::assertCount(1, $clusters);
        self::assertContains('a', $clusters[0]['ids'] ?? []);
        self::assertContains('b', $clusters[0]['ids'] ?? []);
        self::assertCount(2, $out);
    }

    public function testNearLodDoesNotMergeIsolatedHouses(): void
    {
        $rows = [
            ['id' => 'west', 'x' => 0.0, 'y' => 0.0, 'width' => 8.0, 'depth' => 8.0, 'height' => 4.0, 'base_z' => 0.0],
            ['id' => 'east', 'x' => 400.0, 'y' => 400.0, 'width' => 8.0, 'depth' => 8.0, 'height' => 4.0, 'base_z' => 0.0],
        ];
        $out = AtakSceneMeshBake::lodClusters($rows, 55.0, 140.0);
        self::assertCount(2, $out);
        self::assertFalse(!empty($out[0]['cluster']));
        self::assertFalse(!empty($out[1]['cluster']));
    }

    public function testLod0MergesAWholeCell(): void
    {
        $rows = [
            ['id' => 'a', 'x' => 10.0, 'y' => 10.0, 'width' => 8.0, 'depth' => 8.0, 'height' => 4.0, 'base_z' => 2.0],
            ['id' => 'b', 'x' => 80.0, 'y' => 40.0, 'width' => 6.0, 'depth' => 6.0, 'height' => 3.0, 'base_z' => 2.0],
            ['id' => 'c', 'x' => 200.0, 'y' => 180.0, 'width' => 10.0, 'depth' => 10.0, 'height' => 20.0, 'base_z' => 2.0],
        ];
        $out = AtakSceneMeshBake::lodSettlements($rows, 400.0);
        self::assertCount(1, $out);
        self::assertTrue(!empty($out[0]['cluster']));
    }
}
