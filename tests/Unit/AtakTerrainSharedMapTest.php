<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakTerrainRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AtakTerrainSharedMapTest extends TestCase
{
    private function pdoWithGrids(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE atak_terrain_grids (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL,
                world_name TEXT NOT NULL DEFAULT "",
                world_size INTEGER NOT NULL DEFAULT 0,
                origin_x REAL NOT NULL DEFAULT 0,
                origin_y REAL NOT NULL DEFAULT 0,
                cell_m INTEGER NOT NULL DEFAULT 50,
                cols INTEGER NOT NULL DEFAULT 0,
                grid_rows INTEGER NOT NULL DEFAULT 0,
                heights BLOB,
                min_z INTEGER,
                max_z INTEGER,
                filled_cells INTEGER NOT NULL DEFAULT 0,
                ready INTEGER NOT NULL DEFAULT 0,
                sampled_at TEXT,
                updated_at TEXT
            )'
        );
        $pdo->exec(
            'CREATE TABLE atak_terrain_chunks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL,
                grid_id INTEGER NOT NULL,
                col0 INTEGER NOT NULL,
                row0 INTEGER NOT NULL,
                cw INTEGER NOT NULL,
                rh INTEGER NOT NULL,
                received_at TEXT
            )'
        );

        return $pdo;
    }

    public function testGetGridUsesRichestTheaterSurveyForAnyTenant(): void
    {
        $pdo = $this->pdoWithGrids();
        $ins = $pdo->prepare(
            'INSERT INTO atak_terrain_grids (tenant_id, map_id, world_name, cols, grid_rows, filled_cells, sampled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([7, 1, 'altis', 100, 100, 9900, '2026-09-01 12:52:00']);
        $ins->execute([8, 1, 'altis', 100, 100, 12, '2026-09-01 13:25:00']);
        $ins->execute([8, 2, 'stratis', 40, 40, 800, '2026-09-01 13:00:00']);

        $repo = new AtakTerrainRepository($pdo);
        $grid = $repo->getGrid(8, 1, false);

        self::assertNotNull($grid);
        self::assertSame(7, (int) $grid['tenant_id']);
        self::assertSame(9900, (int) $grid['filled_cells']);
        $own = (new \ReflectionMethod(AtakTerrainRepository::class, 'getOwnGrid'))
            ->invoke($repo, 8, 1, false);
        self::assertSame(12, (int) ($own['filled_cells'] ?? 0));

        $stratis = $repo->getGrid(7, 2, false);
        self::assertNotNull($stratis);
        self::assertSame(800, (int) $stratis['filled_cells']);
    }

    public function testCoverageSummaryFollowsSharedTheaterGrid(): void
    {
        $pdo = $this->pdoWithGrids();
        $pdo->exec(
            'INSERT INTO atak_terrain_grids (tenant_id, map_id, cols, grid_rows, filled_cells, sampled_at)
             VALUES (7, 1, 100, 100, 9900, "2026-09-01 12:52:00")'
        );
        $pdo->exec(
            'INSERT INTO atak_terrain_chunks (tenant_id, map_id, grid_id, col0, row0, cw, rh, received_at)
             VALUES (7, 1, 1, 0, 0, 8, 8, "2026-09-01 12:52:00")'
        );

        $repo = new AtakTerrainRepository($pdo);
        $sum = $repo->coverageSummary(8, 1);

        self::assertSame(9900, $sum['terrain_filled']);
        self::assertSame(99, $sum['terrain_coverage_pct']);
        self::assertSame(1, $sum['terrain_chunks']);
    }
}
