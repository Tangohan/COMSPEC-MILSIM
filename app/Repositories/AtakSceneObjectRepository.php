<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Services\Tactical\AtakSceneBounds;
use App\Services\Tactical\AtakSceneKind;
use PDO;

final class AtakSceneObjectRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** @return list<array<string, mixed>> */
    public function visible(int $tenantId, int $mapId, float $minX, float $minY, float $maxX, float $maxY, int $limit = 5000, ?string $kind = null): array
    {
        $limit = max(1, min(8000, $limit));
        $kindFilter = $this->kindSql($kind);
        $params = [$mapId, $minX, $maxX, $minY, $maxY];
        $kindWhere = '';
        if ($kindFilter !== null) {
            $kindWhere = ' AND kind IN (' . $kindFilter['placeholders'] . ')';
            foreach ($kindFilter['values'] as $value) {
                $params[] = $value;
            }
        }
        $st = $this->pdo()->prepare(
            'SELECT o.source_id AS id, o.kind, o.world_x AS x, o.world_y AS y, o.world_z AS z,
                    o.bearing, o.width_m AS width, o.depth_m AS depth, o.height_m AS height, o.density
             FROM atak_scene_objects o
             INNER JOIN (
                SELECT MIN(id) AS id
                FROM atak_scene_objects
                WHERE map_id = ? AND world_x BETWEEN ? AND ? AND world_y BETWEEN ? AND ?' . $kindWhere . '
                GROUP BY source_id
             ) t ON t.id = o.id
             ORDER BY o.kind, o.id LIMIT ' . $limit
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{placeholders: string, values: list<string>}|null
     */
    private function kindSql(?string $kind): ?array
    {
        $kind = strtolower(trim((string) $kind));
        if ($kind === '' || $kind === 'all') {
            return null;
        }
        if ($kind === 'building' || $kind === 'buildings') {
            return ['placeholders' => '?, ?', 'values' => ['building', 'buildings']];
        }
        if ($kind === 'forest' || $kind === 'forests' || $kind === 'tree' || $kind === 'trees') {
            return ['placeholders' => '?, ?, ?, ?', 'values' => ['forest', 'forests', 'tree', 'trees']];
        }
        if ($kind === 'obstacle' || $kind === 'obstacles' || $kind === 'linear') {
            $values = AtakSceneKind::obstacles();
            $placeholders = implode(', ', array_fill(0, count($values), '?'));

            return ['placeholders' => $placeholders, 'values' => $values];
        }

        return ['placeholders' => '?', 'values' => [$kind]];
    }

    /** @return array{building: int, forest: int} */
    public function countByKind(int $tenantId, int $mapId): array
    {
        $out = ['building' => 0, 'forest' => 0, 'obstacle' => 0];
        if ($tenantId < 1 || $mapId < 1) {
            return $out;
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT
                    COUNT(DISTINCT CASE WHEN kind IN ('building', 'buildings') THEN source_id END) AS building,
                    COUNT(DISTINCT CASE WHEN kind IN ('forest', 'forests') THEN source_id END) AS forest,
                    COUNT(DISTINCT CASE WHEN kind IN ('wall','fence','power','bridge','rock','pylon') THEN source_id END) AS obstacle
                 FROM atak_scene_objects
                 WHERE map_id = ?"
            );
            $st->execute([$mapId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['building'] = (int) ($row['building'] ?? $row['BUILDING'] ?? 0);
            $out['forest'] = (int) ($row['forest'] ?? $row['FOREST'] ?? 0);
            $out['obstacle'] = (int) ($row['obstacle'] ?? $row['OBSTACLE'] ?? 0);
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
                'SELECT MAX(`updated_at`) FROM atak_scene_objects WHERE map_id = ?'
            );
            $st->execute([$mapId]);
            $value = $st->fetchColumn();
            if ($value === false || $value === null || $value === '') {
                return null;
            }

            return (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public function allForBake(int $mapId, string $kind = 'building'): array
    {
        $kindFilter = $this->kindSql($kind);
        $sql = 'SELECT o.source_id AS id, o.kind, o.world_x AS x, o.world_y AS y,
                       o.bearing, o.width_m AS width, o.depth_m AS depth, o.height_m AS height,
                       o.model_class AS model, o.world_z AS z
                FROM atak_scene_objects o
                INNER JOIN (
                    SELECT MIN(id) AS id FROM atak_scene_objects WHERE map_id = ?';
        $params = [$mapId];
        if ($kindFilter !== null) {
            $sql .= ' AND kind IN (' . $kindFilter['placeholders'] . ')';
            foreach ($kindFilter['values'] as $value) {
                $params[] = $value;
            }
        }
        $sql .= ' GROUP BY source_id) t ON t.id = o.id ORDER BY o.id LIMIT 12000';
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (self::isMissingTable($e)) {
                return [];
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function findBySourceId(int $mapId, string $sourceId): ?array
    {
        $sourceId = substr(trim($sourceId), 0, 128);
        if ($sourceId === '' || $mapId < 1) {
            return null;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT source_id AS id, kind, model_class AS model, world_x AS x, world_y AS y, world_z AS z,
                        bearing, width_m AS width, depth_m AS depth, height_m AS height, density, extras
                 FROM atak_scene_objects WHERE map_id = ? AND source_id = ? ORDER BY id ASC LIMIT 1'
            );
            $st->execute([$mapId, $sourceId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            if (self::isMissingTable($e)) {
                return null;
            }
            try {
                $st = $this->pdo()->prepare(
                    'SELECT source_id AS id, kind, model_class AS model, world_x AS x, world_y AS y, world_z AS z,
                            bearing, width_m AS width, depth_m AS depth, height_m AS height, density
                     FROM atak_scene_objects WHERE map_id = ? AND source_id = ? ORDER BY id ASC LIMIT 1'
                );
                $st->execute([$mapId, $sourceId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);

                return is_array($row) ? $row : null;
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * Fusionne des métadonnées (ancrages, étage) sans écraser portes et sorties.
     *
     * @param array<string, mixed> $patch
     */
    public function mergeExtras(int $mapId, string $sourceId, array $patch): bool
    {
        $row = $this->findBySourceId($mapId, $sourceId);
        if ($row === null) {
            return false;
        }
        $extras = [];
        if (!empty($row['extras'])) {
            $decoded = json_decode((string) $row['extras'], true);
            $extras = is_array($decoded) ? $decoded : [];
        }
        foreach ($patch as $key => $value) {
            if ($value === null) {
                unset($extras[$key]);
            } else {
                $extras[$key] = $value;
            }
        }
        try {
            $st = $this->pdo()->prepare(
                'UPDATE atak_scene_objects SET extras = ? WHERE map_id = ? AND source_id = ?'
            );

            return $st->execute([json_encode($extras, JSON_UNESCAPED_UNICODE), $mapId, $sourceId]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param list<array<string, mixed>> $objects */
    public function upsertBatch(int $tenantId, int $mapId, array $objects): int
    {
        $sql = 'INSERT INTO atak_scene_objects
                (tenant_id, map_id, source_id, kind, model_class, world_x, world_y, world_z, bearing, width_m, depth_m, height_m, density, extras)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE kind=VALUES(kind), model_class=VALUES(model_class), world_x=VALUES(world_x),
                  world_y=VALUES(world_y), world_z=VALUES(world_z), bearing=VALUES(bearing), width_m=VALUES(width_m),
                  depth_m=VALUES(depth_m), height_m=VALUES(height_m), density=VALUES(density), extras=VALUES(extras), updated_at=CURRENT_TIMESTAMP';
        $sqlLegacy = 'INSERT INTO atak_scene_objects
                (tenant_id, map_id, source_id, kind, model_class, world_x, world_y, world_z, bearing, width_m, depth_m, height_m, density)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE kind=VALUES(kind), model_class=VALUES(model_class), world_x=VALUES(world_x),
                  world_y=VALUES(world_y), world_z=VALUES(world_z), bearing=VALUES(bearing), width_m=VALUES(width_m),
                  depth_m=VALUES(depth_m), height_m=VALUES(height_m), density=VALUES(density), updated_at=CURRENT_TIMESTAMP';
        $withExtras = true;
        try {
            $st = $this->pdo()->prepare($sql);
        } catch (\Throwable) {
            $withExtras = false;
            $st = $this->pdo()->prepare($sqlLegacy);
        }
        $count = 0;
        foreach (array_slice($objects, 0, 10000) as $index => $object) {
            if (!isset($object['x'], $object['y']) || !is_numeric($object['x']) || !is_numeric($object['y'])) {
                continue;
            }
            $kind = AtakSceneKind::normalize($object['kind'] ?? 'building');
            $sourceId = substr(trim((string) ($object['id'] ?? $object['source_id'] ?? 'object-' . $index)), 0, 128);
            $box = AtakSceneKind::isObstacle($kind)
                ? AtakSceneBounds::sanitizeObstacle($object)
                : AtakSceneBounds::sanitize($object);
            $extras = $this->encodeExtras($object);
            $row = [
                $tenantId, $mapId, $sourceId, $kind, substr((string) ($object['model'] ?? $object['model_class'] ?? ''), 0, 191),
                (float) $object['x'], (float) $object['y'], isset($object['z']) && is_numeric($object['z']) ? (float) $object['z'] : null,
                fmod((float) ($object['bearing'] ?? 0) + 360.0, 360.0), $box['width'], $box['depth'], $box['height'],
                max(0.05, min(1.0, (float) ($object['density'] ?? 1))),
            ];
            if ($withExtras) {
                $row[] = $extras;
            }
            try {
                $st->execute($row);
            } catch (\Throwable) {
                if ($withExtras) {
                    $withExtras = false;
                    $st = $this->pdo()->prepare($sqlLegacy);
                    array_pop($row);
                    $st->execute($row);
                } else {
                    continue;
                }
            }
            $count++;
        }

        return $count;
    }

    /** @param array<string, mixed> $object */
    private function encodeExtras(array $object): ?string
    {
        $doors = isset($object['doors']) && is_numeric($object['doors']) ? max(0, min(12, (int) $object['doors'])) : 0;
        $exits = [];
        if (isset($object['exits']) && is_array($object['exits'])) {
            foreach (array_slice($object['exits'], 0, 8) as $exit) {
                if (!is_array($exit) || !isset($exit['x'], $exit['y']) || !is_numeric($exit['x']) || !is_numeric($exit['y'])) {
                    continue;
                }
                $exits[] = [
                    'x' => round((float) $exit['x'], 1),
                    'y' => round((float) $exit['y'], 1),
                    'z' => isset($exit['z']) && is_numeric($exit['z']) ? round((float) $exit['z'], 1) : null,
                ];
            }
        }
        if ($doors < 1 && $exits === []) {
            return null;
        }

        return json_encode(['doors' => max($doors, count($exits)), 'exits' => $exits], JSON_UNESCAPED_UNICODE) ?: null;
    }
}
