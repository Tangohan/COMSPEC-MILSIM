<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakGeoNetworkSchema;
use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakGeoPlaceRepository
{
    use LazyDatabaseConnection;

    /** @var list<string> */
    private const PLACE_TYPES = ['CITY', 'TOWN', 'VILLAGE', 'LANDMARK', 'INTERSECTION', 'OTHER'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        AtakGeoNetworkSchema::ensure();
    }

    /** @return list<string> */
    public static function placeTypes(): array
    {
        return self::PLACE_TYPES;
    }

    /**
     * @param list<array<string, mixed>> $places
     */
    public function upsertBatch(int $tenantId, int $mapId, array $places): int
    {
        if ($tenantId < 1 || $mapId < 1 || $places === []) {
            return 0;
        }

        $sql = 'INSERT INTO atak_geo_places
                (tenant_id, map_id, source_id, place_type, name, pos_x, pos_y, pos_z, radius_m, meta_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  place_type = VALUES(place_type),
                  name = VALUES(name),
                  pos_x = VALUES(pos_x),
                  pos_y = VALUES(pos_y),
                  pos_z = VALUES(pos_z),
                  radius_m = VALUES(radius_m),
                  meta_json = VALUES(meta_json),
                  updated_at = CURRENT_TIMESTAMP';
        $st = $this->pdo()->prepare($sql);
        $count = 0;

        foreach (array_slice($places, 0, 5000) as $index => $place) {
            if (!is_array($place)) {
                continue;
            }
            $x = $place['x'] ?? $place['pos_x'] ?? null;
            $y = $place['y'] ?? $place['pos_y'] ?? null;
            if (!is_numeric($x) || !is_numeric($y)) {
                continue;
            }
            $sourceId = substr(trim((string) ($place['id'] ?? $place['source_id'] ?? 'place-' . $index)), 0, 128);
            if ($sourceId === '') {
                continue;
            }
            $type = $this->enum($place['type'] ?? $place['place_type'] ?? null, self::PLACE_TYPES, 'OTHER');
            $name = substr(trim((string) ($place['name'] ?? $place['label'] ?? '')), 0, 200);
            $meta = $place['meta'] ?? $place['meta_json'] ?? null;
            $metaJson = null;
            if (is_array($meta)) {
                $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
            } elseif (is_string($meta) && $meta !== '') {
                $metaJson = substr($meta, 0, 4000);
            }

            $st->execute([
                $tenantId,
                $mapId,
                $sourceId,
                $type,
                $name,
                (float) $x,
                (float) $y,
                isset($place['z']) && is_numeric($place['z'])
                    ? (float) $place['z']
                    : (isset($place['pos_z']) && is_numeric($place['pos_z']) ? (float) $place['pos_z'] : null),
                isset($place['radius_m']) && is_numeric($place['radius_m'])
                    ? max(0, min(5000, (int) $place['radius_m'])) : null,
                $metaJson,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inBbox(int $tenantId, int $mapId, float $minX, float $minY, float $maxX, float $maxY, int $limit = 500): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return [];
        }
        $limit = max(1, min(2000, $limit));
        $st = $this->pdo()->prepare(
            'SELECT source_id, place_type, name, pos_x, pos_y, pos_z, radius_m, meta_json, updated_at
             FROM atak_geo_places
             WHERE tenant_id = ? AND map_id = ?
               AND pos_x BETWEEN ? AND ? AND pos_y BETWEEN ? AND ?
             ORDER BY place_type, name
             LIMIT ' . $limit
        );
        $st->execute([$tenantId, $mapId, min($minX, $maxX), max($minX, $maxX), min($minY, $maxY), max($minY, $maxY)]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['source_id'] ?? ''),
                'type' => (string) ($row['place_type'] ?? 'OTHER'),
                'name' => (string) ($row['name'] ?? ''),
                'x' => (float) ($row['pos_x'] ?? 0),
                'y' => (float) ($row['pos_y'] ?? 0),
                'z' => isset($row['pos_z']) && $row['pos_z'] !== null ? (float) $row['pos_z'] : null,
                'radius_m' => isset($row['radius_m']) ? (int) $row['radius_m'] : null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows);
    }

    /** @return array{places: int, last_updated_at: ?string} */
    public function summary(int $tenantId, int $mapId): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return ['places' => 0, 'last_updated_at' => null];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT COUNT(*) AS c, MAX(updated_at) AS last_at
                 FROM atak_geo_places WHERE tenant_id = ? AND map_id = ?'
            );
            $st->execute([$tenantId, $mapId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'places' => (int) ($row['c'] ?? 0),
                'last_updated_at' => isset($row['last_at']) && $row['last_at'] !== '' ? (string) $row['last_at'] : null,
            ];
        } catch (\Throwable) {
            return ['places' => 0, 'last_updated_at' => null];
        }
    }

    /**
     * @param list<string>|null $types
     * @return list<array<string, mixed>>
     */
    public function searchByName(int $tenantId, int $mapId, string $query, ?array $types = null, int $limit = 20): array
    {
        $query = trim($query);
        if ($tenantId < 1 || $mapId < 1 || $query === '') {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $where = ['tenant_id = ?', 'map_id = ?', 'name LIKE ?'];
        $params = [$tenantId, $mapId, '%' . $query . '%'];
        if (is_array($types) && $types !== []) {
            $valid = array_values(array_intersect($types, self::PLACE_TYPES));
            if ($valid !== []) {
                $placeholders = implode(',', array_fill(0, count($valid), '?'));
                $where[] = 'place_type IN (' . $placeholders . ')';
                array_push($params, ...$valid);
            }
        }
        $st = $this->pdo()->prepare(
            'SELECT source_id, place_type, name, pos_x, pos_y, pos_z
             FROM atak_geo_places
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY CHAR_LENGTH(name), name
             LIMIT ' . $limit
        );
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row): array => [
            'id' => (string) ($row['source_id'] ?? ''),
            'type' => (string) ($row['place_type'] ?? 'OTHER'),
            'name' => (string) ($row['name'] ?? ''),
            'x' => (float) ($row['pos_x'] ?? 0),
            'y' => (float) ($row['pos_y'] ?? 0),
            'z' => isset($row['pos_z']) && $row['pos_z'] !== null ? (float) $row['pos_z'] : null,
        ], $rows);
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, ?string $default): ?string
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $v = strtoupper(trim((string) $value));

        return in_array($v, $allowed, true) ? $v : $default;
    }
}
