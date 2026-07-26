<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakModulesSchema;

/**
 * Repository pour les zones tactiques (LZ, DZ, Objectives, Danger Zones)
 */
class AtakTacticalZoneRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        AtakModulesSchema::ensure();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée une nouvelle zone tactique
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO atak_tactical_zones (
            tenant_id, context_id, zone_name, zone_code, zone_type, subtype,
            description, purpose,
            geometry_type, center_x, center_y, center_z,
            radius, radius_major, radius_minor, rotation, width, height, polygon_points,
            status, priority, threat_level,
            active_from, active_until, is_temporary,
            alert_on_entry, alert_on_exit, alert_sound, alert_message,
            roe, restrictions,
            fill_color, border_color, opacity, border_width, is_visible,
            icon_type, show_label, label_size, visibility_level, restricted_to_units,
            properties, parent_zone_id, related_poi_id, created_by_user_id
        ) VALUES (
            :tenant_id, :context_id, :zone_name, :zone_code, :zone_type, :subtype,
            :description, :purpose,
            :geometry_type, :center_x, :center_y, :center_z,
            :radius, :radius_major, :radius_minor, :rotation, :width, :height, :polygon_points,
            :status, :priority, :threat_level,
            :active_from, :active_until, :is_temporary,
            :alert_on_entry, :alert_on_exit, :alert_sound, :alert_message,
            :roe, :restrictions,
            :fill_color, :border_color, :opacity, :border_width, :is_visible,
            :icon_type, :show_label, :label_size, :visibility_level, :restricted_to_units,
            :properties, :parent_zone_id, :related_poi_id, :created_by_user_id
        )";

        $params = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'zone_name' => $data['zone_name'] ?? 'Zone',
            'zone_code' => $data['zone_code'] ?? null,
            'zone_type' => $data['zone_type'] ?? 'OTHER',
            'subtype' => $data['subtype'] ?? null,
            'description' => $data['description'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'geometry_type' => $data['geometry_type'] ?? 'CIRCLE',
            'center_x' => $data['center_x'] ?? null,
            'center_y' => $data['center_y'] ?? null,
            'center_z' => $data['center_z'] ?? null,
            'radius' => $data['radius'] ?? null,
            'radius_major' => $data['radius_major'] ?? null,
            'radius_minor' => $data['radius_minor'] ?? null,
            'rotation' => $data['rotation'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'polygon_points' => isset($data['polygon_points']) ? json_encode($data['polygon_points']) : null,
            'status' => $data['status'] ?? 'PLANNED',
            'priority' => $data['priority'] ?? 'MEDIUM',
            'threat_level' => $data['threat_level'] ?? 'NONE',
            'active_from' => $data['active_from'] ?? null,
            'active_until' => $data['active_until'] ?? null,
            'is_temporary' => $data['is_temporary'] ?? false,
            'alert_on_entry' => $data['alert_on_entry'] ?? false,
            'alert_on_exit' => $data['alert_on_exit'] ?? false,
            'alert_sound' => $data['alert_sound'] ?? null,
            'alert_message' => $data['alert_message'] ?? null,
            'roe' => isset($data['roe']) ? json_encode($data['roe']) : null,
            'restrictions' => $data['restrictions'] ?? null,
            'fill_color' => $data['fill_color'] ?? $this->getDefaultColor($data['zone_type'] ?? 'OTHER'),
            'border_color' => $data['border_color'] ?? null,
            'opacity' => $data['opacity'] ?? 0.30,
            'border_width' => $data['border_width'] ?? 2,
            'is_visible' => $data['is_visible'] ?? true,
            'icon_type' => $data['icon_type'] ?? null,
            'show_label' => $data['show_label'] ?? true,
            'label_size' => $data['label_size'] ?? 'MEDIUM',
            'visibility_level' => $data['visibility_level'] ?? 'ALL',
            'restricted_to_units' => isset($data['restricted_to_units']) ? json_encode($data['restricted_to_units']) : null,
            'properties' => isset($data['properties']) ? json_encode($data['properties']) : null,
            'parent_zone_id' => $data['parent_zone_id'] ?? null,
            'related_poi_id' => $data['related_poi_id'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Retourne une couleur par défaut selon le type de zone
     */
    private function getDefaultColor(string $zoneType): string
    {
        return match ($zoneType) {
            'LZ', 'DZ', 'EXTRACT_POINT' => '#0088ff',
            'OBJECTIVE' => '#ffaa00',
            'DANGER_ZONE' => '#ff0000',
            'NO_GO_AREA', 'RESTRICTED_AREA' => '#ff0000',
            'FREE_FIRE_ZONE' => '#ff6600',
            'SAFE_ZONE' => '#00ff00',
            'RALLY_POINT' => '#00ffaa',
            'SECTOR', 'AO' => '#8800ff',
            default => '#888888',
        };
    }

    /**
     * Liste les zones pour un contexte
     */
    public function listForContext(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['z.tenant_id = :tenant_id', 'z.context_id = :context_id', 'z.deleted_at IS NULL'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['zone_type'])) {
            if (is_array($filters['zone_type'])) {
                $placeholders = [];
                foreach ($filters['zone_type'] as $i => $type) {
                    $key = "zone_type_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $type;
                }
                $where[] = 'z.zone_type IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'z.zone_type = :zone_type';
                $params['zone_type'] = $filters['zone_type'];
            }
        }

        if (!empty($filters['status'])) {
            $where[] = 'z.status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['is_visible'])) {
            $where[] = 'z.is_visible = :is_visible';
            $params['is_visible'] = (bool) $filters['is_visible'];
        }

        if (isset($filters['only_active'])) {
            $where[] = 'is_currently_active = TRUE';
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'z.priority DESC, z.created_at DESC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 200;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM v_atak_active_zones z 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as &$row) {
            if (!empty($row['polygon_points'])) {
                $row['polygon_points'] = json_decode($row['polygon_points'], true);
            }
            if (!empty($row['roe'])) {
                $row['roe'] = json_decode($row['roe'], true);
            }
            if (!empty($row['restricted_to_units'])) {
                $row['restricted_to_units'] = json_decode($row['restricted_to_units'], true);
            }
            if (!empty($row['properties'])) {
                $row['properties'] = json_decode($row['properties'], true);
            }
        }

        return $results;
    }

    /**
     * Récupère une zone par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM v_atak_active_zones WHERE id = :id AND deleted_at IS NULL";
        $zone = $this->db->fetchOne($sql, ['id' => $id]);

        if ($zone) {
            if (!empty($zone['polygon_points'])) {
                $zone['polygon_points'] = json_decode($zone['polygon_points'], true);
            }
            if (!empty($zone['roe'])) {
                $zone['roe'] = json_decode($zone['roe'], true);
            }
            if (!empty($zone['restricted_to_units'])) {
                $zone['restricted_to_units'] = json_decode($zone['restricted_to_units'], true);
            }
            if (!empty($zone['properties'])) {
                $zone['properties'] = json_decode($zone['properties'], true);
            }
        }

        return $zone ?: null;
    }

    /**
     * Met à jour une zone
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'zone_name', 'zone_code', 'zone_type', 'subtype', 'description', 'purpose',
            'geometry_type', 'center_x', 'center_y', 'center_z',
            'radius', 'radius_major', 'radius_minor', 'rotation', 'width', 'height',
            'status', 'priority', 'threat_level',
            'active_from', 'active_until', 'is_temporary',
            'alert_on_entry', 'alert_on_exit', 'alert_sound', 'alert_message',
            'restrictions', 'fill_color', 'border_color', 'opacity', 'border_width',
            'is_visible', 'icon_type', 'show_label', 'label_size', 'visibility_level',
            'parent_zone_id', 'related_poi_id', 'updated_by_user_id'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        $jsonFields = ['polygon_points', 'roe', 'restricted_to_units', 'properties'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = json_encode($data[$field]);
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE atak_tactical_zones SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Supprime logiquement une zone
     */
    public function softDelete(int $id): bool
    {
        $sql = "UPDATE atak_tactical_zones SET deleted_at = NOW() WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Vérifie si une position est dans une zone
     */
    public function isPositionInZone(int $zoneId, float $posX, float $posY): bool
    {
        $zone = $this->findById($zoneId);
        if (!$zone) {
            return false;
        }

        return match ($zone['geometry_type']) {
            'CIRCLE' => $this->isInCircle($posX, $posY, $zone),
            'RECTANGLE' => $this->isInRectangle($posX, $posY, $zone),
            'POLYGON' => $this->isInPolygon($posX, $posY, $zone),
            default => false,
        };
    }

    private function isInCircle(float $x, float $y, array $zone): bool
    {
        $dx = $x - (float) $zone['center_x'];
        $dy = $y - (float) $zone['center_y'];
        $distance = sqrt($dx * $dx + $dy * $dy);
        return $distance <= (float) $zone['radius'];
    }

    private function isInRectangle(float $x, float $y, array $zone): bool
    {
        $cx = (float) $zone['center_x'];
        $cy = (float) $zone['center_y'];
        $width = (float) $zone['width'];
        $height = (float) $zone['height'];
        
        return ($x >= $cx - $width / 2) && ($x <= $cx + $width / 2) &&
               ($y >= $cy - $height / 2) && ($y <= $cy + $height / 2);
    }

    private function isInPolygon(float $x, float $y, array $zone): bool
    {
        $points = $zone['polygon_points'] ?? [];
        if (empty($points)) {
            return false;
        }

        $inside = false;
        $count = count($points);
        
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $points[$i][0];
            $yi = $points[$i][1];
            $xj = $points[$j][0];
            $yj = $points[$j][1];
            
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
        
        return $inside;
    }

    /**
     * Récupère les zones contenant une position
     */
    public function findZonesContainingPosition(
        int $tenantId,
        int $contextId,
        float $posX,
        float $posY
    ): array {
        $zones = $this->listForContext($tenantId, $contextId, ['only_active' => true]);
        $containingZones = [];

        foreach ($zones as $zone) {
            if ($this->isPositionInZone((int) $zone['id'], $posX, $posY)) {
                $containingZones[] = $zone;
            }
        }

        return $containingZones;
    }

    /**
     * Crée une alerte de zone
     */
    public function createAlert(int $zoneId, array $data): int
    {
        $sql = "INSERT INTO atak_zone_alerts (
            zone_id, alert_type, alerted_at,
            unit_user_id, unit_callsign, unit_steam_id,
            unit_pos_x, unit_pos_y, properties
        ) VALUES (
            :zone_id, :alert_type, :alerted_at,
            :unit_user_id, :unit_callsign, :unit_steam_id,
            :unit_pos_x, :unit_pos_y, :properties
        )";

        $params = [
            'zone_id' => $zoneId,
            'alert_type' => $data['alert_type'] ?? 'ENTRY',
            'alerted_at' => $data['alerted_at'] ?? date('Y-m-d H:i:s'),
            'unit_user_id' => $data['unit_user_id'] ?? null,
            'unit_callsign' => $data['unit_callsign'] ?? null,
            'unit_steam_id' => $data['unit_steam_id'] ?? null,
            'unit_pos_x' => $data['unit_pos_x'] ?? null,
            'unit_pos_y' => $data['unit_pos_y'] ?? null,
            'properties' => isset($data['properties']) ? json_encode($data['properties']) : null,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Récupère les alertes non acquittées pour un contexte
     */
    public function listUnacknowledgedAlerts(int $tenantId, int $contextId): array
    {
        $sql = "SELECT a.*, z.zone_name, z.zone_type, z.alert_message
                FROM atak_zone_alerts a
                INNER JOIN atak_tactical_zones z ON a.zone_id = z.id
                WHERE z.tenant_id = :tenant_id
                  AND z.context_id = :context_id
                  AND a.acknowledged = FALSE
                  AND z.deleted_at IS NULL
                ORDER BY a.alerted_at DESC";

        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }
}
