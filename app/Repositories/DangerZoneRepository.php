<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DangerZoneRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listByMission(string $missionId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM danger_zones WHERE mission_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$missionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            if (!empty($r['geometry_json'])) {
                $r['geometry_json'] = json_decode($r['geometry_json'], true);
            }
            if (!empty($r['side_visibility_json'])) {
                $r['side_visibility_json'] = json_decode($r['side_visibility_json'], true);
            }
        }
        return $rows;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM danger_zones WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['geometry_json'])) {
            $row['geometry_json'] = json_decode($row['geometry_json'], true);
        }
        if (!empty($row['side_visibility_json'])) {
            $row['side_visibility_json'] = json_decode($row['side_visibility_json'], true);
        }
        return $row;
    }

    public function getByIdAndMission(int $id, string $missionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM danger_zones WHERE id = ? AND mission_id = ?');
        $stmt->execute([$id, $missionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!empty($row['geometry_json'])) {
            $row['geometry_json'] = json_decode($row['geometry_json'], true);
        }
        if (!empty($row['side_visibility_json'])) {
            $row['side_visibility_json'] = json_decode($row['side_visibility_json'], true);
        }
        return $row;
    }

    public function create(string $missionId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO danger_zones (mission_id, zone_type, label, color, fill_opacity, stroke_width, geometry_type, geometry_json, side_visibility_json, threat_level, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $geometryJson = is_string($data['geometry_json'] ?? null) ? $data['geometry_json'] : json_encode($data['geometry_json'] ?? []);
        $sideJson = isset($data['side_visibility_json']) ? (is_string($data['side_visibility_json']) ? $data['side_visibility_json'] : json_encode($data['side_visibility_json'])) : null;
        $stmt->execute([
            $missionId,
            $data['zone_type'] ?? 'RESTRICTED_AREA',
            $data['label'] ?? null,
            $data['color'] ?? '#ff0000',
            (float) ($data['fill_opacity'] ?? 0.25),
            (int) ($data['stroke_width'] ?? 2),
            $data['geometry_type'] ?? 'CIRCLE',
            $geometryJson,
            $sideJson,
            $data['threat_level'] ?? 'MEDIUM',
            isset($data['active']) ? (int) (bool) $data['active'] : 1,
            $data['created_by'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->getById($id);
        return $row ?? [];
    }

    public function update(int $id, string $missionId, array $data): bool
    {
        $allowed = ['zone_type', 'label', 'color', 'fill_opacity', 'stroke_width', 'geometry_type', 'geometry_json', 'side_visibility_json', 'threat_level', 'active'];
        $updates = [];
        $params = [];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $v = $data[$f];
            if ($f === 'geometry_json') {
                $v = is_string($v) ? $v : json_encode($v);
            }
            if ($f === 'side_visibility_json') {
                $v = $v === null ? null : (is_string($v) ? $v : json_encode($v));
            }
            if ($f === 'fill_opacity' || $f === 'stroke_width') {
                $v = $f === 'fill_opacity' ? (float) $v : (int) $v;
            }
            if ($f === 'active') {
                $v = (int) (bool) $v;
            }
            $updates[] = "{$f} = ?";
            $params[] = $v;
        }
        if ($updates === []) {
            return true;
        }
        $params[] = $id;
        $params[] = $missionId;
        $stmt = $this->pdo->prepare('UPDATE danger_zones SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ? AND mission_id = ?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $missionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM danger_zones WHERE id = ? AND mission_id = ?');
        $stmt->execute([$id, $missionId]);
        return $stmt->rowCount() > 0;
    }
}
