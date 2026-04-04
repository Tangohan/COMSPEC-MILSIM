<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class MapShapeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function list(int $tenantId, int $mapId, ?string $missionId = null, ?string $since = null): array
    {
        $sql = 'SELECT * FROM atak_map_shapes WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if ($missionId !== null && $missionId !== '') {
            $sql .= ' AND (mission_id = ? OR mission_id IS NULL)';
            $params[] = $missionId;
        }
        if ($since !== null && $since !== '') {
            $sql .= ' AND (updated_at >= ? OR created_at >= ?)';
            $params[] = $since;
            $params[] = $since;
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'normalizeShape'], $rows);
    }

    public function get(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_map_shapes WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeShape($row) : null;
    }

    public function create(int $tenantId, int $mapId, array $payload): array
    {
        $shapeUid = $payload['shape_uid'] ?? $payload['id'] ?? 'shape_' . uniqid();
        $type = $payload['type'] ?? 'POINT';
        $label = $payload['label'] ?? null;
        $color = $payload['color'] ?? '#3388ff';
        $stroke = (int) ($payload['stroke'] ?? 2);
        $fillOpacity = (float) ($payload['fillOpacity'] ?? $payload['fill_opacity'] ?? 0.15);
        $createdBy = $payload['createdBy'] ?? $payload['created_by'] ?? null;
        $visibleTo = isset($payload['visibleTo']) ? json_encode($payload['visibleTo']) : (isset($payload['visible_to']) ? (is_string($payload['visible_to']) ? $payload['visible_to'] : json_encode($payload['visible_to'])) : null);
        $geometry = isset($payload['geometry']) ? (is_string($payload['geometry']) ? $payload['geometry'] : json_encode($payload['geometry'])) : '{}';
        $meta = isset($payload['meta']) ? (is_string($payload['meta']) ? $payload['meta'] : json_encode($payload['meta'])) : null;
        $missionId = $payload['mission_id'] ?? $payload['missionId'] ?? null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO atak_map_shapes (tenant_id, map_id, mission_id, shape_uid, type, label, color, stroke, fill_opacity, created_by, visible_to, geometry, meta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId, $mapId, $missionId, $shapeUid, $type, $label, $color, $stroke, $fillOpacity,
            $createdBy, $visibleTo, $geometry, $meta,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->get($tenantId, $id);
        return $row ?? [];
    }

    public function update(int $tenantId, int $id, array $payload): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM atak_map_shapes WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        if (!$stmt->fetch()) {
            return null;
        }
        $updates = [];
        $params = [];
        $allowed = ['label', 'color', 'stroke', 'fill_opacity', 'visible_to', 'geometry', 'meta'];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $payload)) {
                $v = $payload[$k];
                if (in_array($k, ['visible_to', 'geometry', 'meta'], true) && is_array($v)) {
                    $v = json_encode($v);
                }
                $updates[] = "`$k` = ?";
                $params[] = $v;
            }
        }
        if (isset($payload['fillOpacity'])) {
            $updates[] = 'fill_opacity = ?';
            $params[] = (float) $payload['fillOpacity'];
        }
        if (empty($updates)) {
            return $this->get($tenantId, $id);
        }
        $params[] = $tenantId;
        $params[] = $id;
        $this->pdo->prepare('UPDATE atak_map_shapes SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE tenant_id = ? AND id = ?')->execute($params);
        return $this->get($tenantId, $id);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM atak_map_shapes WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        return $stmt->rowCount() > 0;
    }

    private function normalizeShape(array $row): array
    {
        foreach (['visible_to', 'geometry', 'meta'] as $k) {
            if (isset($row[$k]) && is_string($row[$k])) {
                $decoded = json_decode($row[$k], true);
                $row[$k] = is_array($decoded) ? $decoded : $row[$k];
            }
        }
        $row['shapeUid'] = $row['shape_uid'] ?? null;
        return $row;
    }
}
