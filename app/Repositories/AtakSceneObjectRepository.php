<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakSceneObjectRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** @return list<array<string, mixed>> */
    public function visible(int $tenantId, int $mapId, float $minX, float $minY, float $maxX, float $maxY, int $limit = 5000): array
    {
        $limit = max(1, min(10000, $limit));
        $st = $this->pdo()->prepare(
            'SELECT source_id, kind, model_class, world_x AS x, world_y AS y, world_z AS z,
                    bearing, width_m AS width, depth_m AS depth, height_m AS height, density
             FROM atak_scene_objects
             WHERE tenant_id = ? AND map_id = ? AND world_x BETWEEN ? AND ? AND world_y BETWEEN ? AND ?
             ORDER BY kind, id LIMIT ' . $limit
        );
        $st->execute([$tenantId, $mapId, $minX, $maxX, $minY, $maxY]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{building: int, forest: int} */
    public function countByKind(int $tenantId, int $mapId): array
    {
        $out = ['building' => 0, 'forest' => 0];
        if ($tenantId < 1 || $mapId < 1) {
            return $out;
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN kind IN ('building', 'buildings') THEN 1 ELSE 0 END), 0) AS building,
                    COALESCE(SUM(CASE WHEN kind IN ('forest', 'forests') THEN 1 ELSE 0 END), 0) AS forest
                 FROM atak_scene_objects
                 WHERE tenant_id = ? AND map_id = ?"
            );
            $st->execute([$tenantId, $mapId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['building'] = (int) ($row['building'] ?? $row['BUILDING'] ?? 0);
            $out['forest'] = (int) ($row['forest'] ?? $row['FOREST'] ?? 0);
        } catch (\Throwable $e) {
            if (self::isMissingTable($e)) {
                return $out;
            }
            throw $e;
        }

        return $out;
    }

    private static function isMissingTable(\Throwable $e): bool
    {
        if ($e instanceof \PDOException) {
            $driver = (int) ($e->errorInfo[1] ?? 0);
            $state = (string) ($e->errorInfo[0] ?? '');
            if ($driver === 1146 || $state === '42S02') {
                return true;
            }
        }
        $msg = $e->getMessage();

        return str_contains($msg, '1146')
            || str_contains($msg, "doesn't exist")
            || str_contains($msg, 'no such table');
    }

    public function lastUpdatedAt(int $tenantId, int $mapId): ?string
    {
        if ($tenantId < 1 || $mapId < 1) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT MAX(`updated_at`) FROM atak_scene_objects WHERE tenant_id = ? AND map_id = ?'
            );
            $st->execute([$tenantId, $mapId]);
            $value = $st->fetchColumn();
            if ($value === false || $value === null || $value === '') {
                return null;
            }

            return (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param list<array<string, mixed>> $objects */
    public function upsertBatch(int $tenantId, int $mapId, array $objects): int
    {
        $sql = 'INSERT INTO atak_scene_objects
                (tenant_id, map_id, source_id, kind, model_class, world_x, world_y, world_z, bearing, width_m, depth_m, height_m, density)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE kind=VALUES(kind), model_class=VALUES(model_class), world_x=VALUES(world_x),
                  world_y=VALUES(world_y), world_z=VALUES(world_z), bearing=VALUES(bearing), width_m=VALUES(width_m),
                  depth_m=VALUES(depth_m), height_m=VALUES(height_m), density=VALUES(density), updated_at=CURRENT_TIMESTAMP';
        $st = $this->pdo()->prepare($sql);
        $count = 0;
        foreach (array_slice($objects, 0, 10000) as $index => $object) {
            if (!isset($object['x'], $object['y']) || !is_numeric($object['x']) || !is_numeric($object['y'])) {
                continue;
            }
            $kind = ($object['kind'] ?? '') === 'forest' ? 'forest' : 'building';
            $sourceId = substr(trim((string) ($object['id'] ?? $object['source_id'] ?? 'object-' . $index)), 0, 128);
            $st->execute([
                $tenantId, $mapId, $sourceId, $kind, substr((string) ($object['model'] ?? $object['model_class'] ?? ''), 0, 191),
                (float) $object['x'], (float) $object['y'], isset($object['z']) && is_numeric($object['z']) ? (float) $object['z'] : null,
                fmod((float) ($object['bearing'] ?? 0) + 360.0, 360.0), max(1.0, min(500.0, (float) ($object['width'] ?? 4))),
                max(1.0, min(500.0, (float) ($object['depth'] ?? 4))), max(1.0, min(100.0, (float) ($object['height'] ?? 3))),
                max(0.05, min(1.0, (float) ($object['density'] ?? 1))),
            ]);
            $count++;
        }

        return $count;
    }
}
