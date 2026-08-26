<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakSceneObjectRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AtakSceneObjectCountTest extends TestCase
{
    private function pdoWithSceneTable(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for in-memory scene counts');
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE atak_scene_objects (
                tenant_id INTEGER NOT NULL,
                map_id INTEGER NOT NULL,
                kind TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        return $pdo;
    }

    private function insert(PDO $pdo, int $tenantId, int $mapId, string $kind, int $n, string $updatedAt = '2026-08-26 19:55:00'): void
    {
        $st = $pdo->prepare('INSERT INTO atak_scene_objects (tenant_id, map_id, kind, updated_at) VALUES (?, ?, ?, ?)');
        for ($i = 0; $i < $n; $i++) {
            $st->execute([$tenantId, $mapId, $kind, $updatedAt]);
        }
    }

    public function testCountByKindSplitsBuildingsAndForestsForTenantAndMap(): void
    {
        $pdo = $this->pdoWithSceneTable();
        $this->insert($pdo, 7, 1, 'building', 12);
        $this->insert($pdo, 7, 1, 'forest', 22);
        $this->insert($pdo, 7, 2, 'building', 9);
        $this->insert($pdo, 8, 1, 'forest', 4);
        $this->insert($pdo, 7, 1, 'buildings', 3);
        $this->insert($pdo, 7, 1, 'forests', 2);

        $repo = new AtakSceneObjectRepository($pdo);
        $counts = $repo->countByKind(7, 1);

        self::assertSame(15, $counts['building']);
        self::assertSame(24, $counts['forest']);
        self::assertSame(['building' => 9, 'forest' => 0], $repo->countByKind(7, 2));
        self::assertSame(['building' => 0, 'forest' => 4], $repo->countByKind(8, 1));
    }

    public function testCountByKindReturnsZerosWhenTableIsMissing(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for in-memory scene counts');
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $repo = new AtakSceneObjectRepository($pdo);

        self::assertSame(['building' => 0, 'forest' => 0], $repo->countByKind(7, 1));
    }

    public function testLastUpdatedAtUsesSceneStampForTenantAndMap(): void
    {
        $pdo = $this->pdoWithSceneTable();
        $this->insert($pdo, 7, 1, 'building', 1, '2026-08-26 19:53:00');
        $this->insert($pdo, 7, 1, 'forest', 1, '2026-08-26 20:00:00');
        $this->insert($pdo, 7, 2, 'building', 1, '2026-08-26 21:00:00');

        $repo = new AtakSceneObjectRepository($pdo);

        self::assertSame('2026-08-26 20:00:00', $repo->lastUpdatedAt(7, 1));
    }
}
