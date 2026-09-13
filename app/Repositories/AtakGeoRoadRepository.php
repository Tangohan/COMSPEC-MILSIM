<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakGeoNetworkSchema;
use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakGeoRoadRepository
{
    use LazyDatabaseConnection;

    /** @var list<string> */
    private const ROAD_CLASSES = ['HIGHWAY', 'PRIMARY', 'SECONDARY', 'TRACK', 'OTHER'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        AtakGeoNetworkSchema::ensure();
    }

    /** @return list<string> */
    public static function roadClasses(): array
    {
        return self::ROAD_CLASSES;
    }

    /**
     * @param list<array<string, mixed>> $segments
     */
    public function upsertBatch(int $tenantId, int $mapId, array $segments): int
    {
        if ($tenantId < 1 || $mapId < 1 || $segments === []) {
            return 0;
        }

        // Ne jamais écraser operator_label à l’ingest (libellé saisi au poste).
        $sql = 'INSERT INTO atak_geo_road_segments
                (tenant_id, map_id, source_id, node_a_x, node_a_y, node_b_x, node_b_y, length_m, road_class, one_way)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  node_a_x = VALUES(node_a_x),
                  node_a_y = VALUES(node_a_y),
                  node_b_x = VALUES(node_b_x),
                  node_b_y = VALUES(node_b_y),
                  length_m = VALUES(length_m),
                  road_class = VALUES(road_class),
                  one_way = VALUES(one_way),
                  updated_at = CURRENT_TIMESTAMP';
        $st = $this->pdo()->prepare($sql);
        $count = 0;

        foreach (array_slice($segments, 0, 10000) as $index => $seg) {
            if (!is_array($seg)) {
                continue;
            }
            $ax = $seg['ax'] ?? $seg['node_a_x'] ?? ($seg['a'][0] ?? null);
            $ay = $seg['ay'] ?? $seg['node_a_y'] ?? ($seg['a'][1] ?? null);
            $bx = $seg['bx'] ?? $seg['node_b_x'] ?? ($seg['b'][0] ?? null);
            $by = $seg['by'] ?? $seg['node_b_y'] ?? ($seg['b'][1] ?? null);
            if (!is_numeric($ax) || !is_numeric($ay) || !is_numeric($bx) || !is_numeric($by)) {
                continue;
            }
            $axF = (float) $ax;
            $ayF = (float) $ay;
            $bxF = (float) $bx;
            $byF = (float) $by;
            if ($axF === $bxF && $ayF === $byF) {
                continue;
            }
            $sourceId = substr(trim((string) ($seg['id'] ?? $seg['source_id'] ?? 'road-' . $index)), 0, 128);
            if ($sourceId === '') {
                continue;
            }
            $length = isset($seg['length_m']) && is_numeric($seg['length_m'])
                ? (float) $seg['length_m']
                : self::dist2d($axF, $ayF, $bxF, $byF);
            $class = $this->enum($seg['class'] ?? $seg['road_class'] ?? null, self::ROAD_CLASSES, 'OTHER');
            $oneWay = !empty($seg['one_way']) ? 1 : 0;

            $st->execute([
                $tenantId, $mapId, $sourceId,
                $axF, $ayF, $bxF, $byF,
                round($length, 2),
                $class,
                $oneWay,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Segments intersectant une bbox (extrémité A ou B dans la zone).
     *
     * @return list<array<string, mixed>>
     */
    public function inBbox(int $tenantId, int $mapId, float $minX, float $minY, float $maxX, float $maxY, int $limit = 2000): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return [];
        }
        $limit = max(1, min(5000, $limit));
        $loX = min($minX, $maxX);
        $hiX = max($minX, $maxX);
        $loY = min($minY, $maxY);
        $hiY = max($minY, $maxY);

        $st = $this->pdo()->prepare(
            'SELECT id, source_id, node_a_x, node_a_y, node_b_x, node_b_y, length_m, road_class, one_way, operator_label
             FROM atak_geo_road_segments
             WHERE tenant_id = ? AND map_id = ?
               AND (
                 (node_a_x BETWEEN ? AND ? AND node_a_y BETWEEN ? AND ?)
                 OR (node_b_x BETWEEN ? AND ? AND node_b_y BETWEEN ? AND ?)
               )
             LIMIT ' . $limit
        );
        $st->execute([
            $tenantId, $mapId,
            $loX, $hiX, $loY, $hiY,
            $loX, $hiX, $loY, $hiY,
        ]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            $label = isset($row['operator_label']) && $row['operator_label'] !== ''
                ? (string) $row['operator_label']
                : null;

            return [
                'id' => (string) ($row['source_id'] ?? ''),
                'db_id' => (int) ($row['id'] ?? 0),
                'a' => [(float) ($row['node_a_x'] ?? 0), (float) ($row['node_a_y'] ?? 0)],
                'b' => [(float) ($row['node_b_x'] ?? 0), (float) ($row['node_b_y'] ?? 0)],
                'length_m' => (float) ($row['length_m'] ?? 0),
                'class' => (string) ($row['road_class'] ?? 'OTHER'),
                'one_way' => (bool) ($row['one_way'] ?? false),
                'label' => $label,
            ];
        }, $rows);
    }

    /**
     * Met à jour le libellé opérateur d’un segment (source_id ou chaîne vide pour effacer).
     */
    public function updateOperatorLabel(int $tenantId, int $mapId, string $sourceId, ?string $label): bool
    {
        if ($tenantId < 1 || $mapId < 1) {
            return false;
        }
        $sourceId = substr(trim($sourceId), 0, 128);
        if ($sourceId === '') {
            return false;
        }
        $normalized = $label === null ? null : trim($label);
        if ($normalized === '') {
            $normalized = null;
        } else {
            $normalized = substr($normalized, 0, 120);
        }

        $st = $this->pdo()->prepare(
            'UPDATE atak_geo_road_segments
             SET operator_label = ?, updated_at = CURRENT_TIMESTAMP
             WHERE tenant_id = ? AND map_id = ? AND source_id = ?
             LIMIT 1'
        );
        $st->execute([$normalized, $tenantId, $mapId, $sourceId]);
        if ($st->rowCount() > 0) {
            return true;
        }

        // MySQL peut renvoyer 0 si la valeur est inchangée.
        return $this->findBySourceId($tenantId, $mapId, $sourceId) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySourceId(int $tenantId, int $mapId, string $sourceId): ?array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return null;
        }
        $sourceId = substr(trim($sourceId), 0, 128);
        if ($sourceId === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT id, source_id, node_a_x, node_a_y, node_b_x, node_b_y, length_m, road_class, one_way, operator_label
             FROM atak_geo_road_segments
             WHERE tenant_id = ? AND map_id = ? AND source_id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $mapId, $sourceId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $label = isset($row['operator_label']) && $row['operator_label'] !== ''
            ? (string) $row['operator_label']
            : null;

        return [
            'id' => (string) ($row['source_id'] ?? ''),
            'db_id' => (int) ($row['id'] ?? 0),
            'a' => [(float) ($row['node_a_x'] ?? 0), (float) ($row['node_a_y'] ?? 0)],
            'b' => [(float) ($row['node_b_x'] ?? 0), (float) ($row['node_b_y'] ?? 0)],
            'length_m' => (float) ($row['length_m'] ?? 0),
            'class' => (string) ($row['road_class'] ?? 'OTHER'),
            'one_way' => (bool) ($row['one_way'] ?? false),
            'label' => $label,
        ];
    }

    /**
     * Charge les segments dans une bbox élargie pour la planification.
     *
     * @return list<array{a: array{0: float, 1: float}, b: array{0: float, 1: float}, one_way: bool}>
     */
    public function segmentsForPlanning(int $tenantId, int $mapId, float $minX, float $minY, float $maxX, float $maxY, int $limit = 8000): array
    {
        $raw = $this->inBbox($tenantId, $mapId, $minX, $minY, $maxX, $maxY, $limit);
        $out = [];
        foreach ($raw as $row) {
            $out[] = [
                'a' => [(float) $row['a'][0], (float) $row['a'][1]],
                'b' => [(float) $row['b'][0], (float) $row['b'][1]],
                'one_way' => (bool) ($row['one_way'] ?? false),
            ];
        }

        return $out;
    }

    /** @return array{roads: int, last_updated_at: ?string} */
    public function summary(int $tenantId, int $mapId): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return ['roads' => 0, 'last_updated_at' => null];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT COUNT(*) AS c, MAX(updated_at) AS last_at
                 FROM atak_geo_road_segments WHERE tenant_id = ? AND map_id = ?'
            );
            $st->execute([$tenantId, $mapId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'roads' => (int) ($row['c'] ?? 0),
                'last_updated_at' => isset($row['last_at']) && $row['last_at'] !== '' ? (string) $row['last_at'] : null,
            ];
        } catch (\Throwable) {
            return ['roads' => 0, 'last_updated_at' => null];
        }
    }

    private static function dist2d(float $ax, float $ay, float $bx, float $by): float
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;

        return sqrt($dx * $dx + $dy * $dy);
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
