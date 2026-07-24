<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour les Points d'Intérêt (POI) tactiques
 */
class AtakPoiRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée un nouveau POI
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO atak_poi (
            tenant_id, context_id, poi_name, poi_code, category, affiliation, certainty,
            pos_x, pos_y, pos_z, grid_reference,
            description, observed_activity, threat_level, status, last_observed_at,
            source_type, source_reliability, reported_by_user_id, reported_by_callsign,
            properties, icon_type, marker_color,
            is_visible, visibility_level, parent_poi_id, related_report_id,
            created_by_user_id
        ) VALUES (
            :tenant_id, :context_id, :poi_name, :poi_code, :category, :affiliation, :certainty,
            :pos_x, :pos_y, :pos_z, :grid_reference,
            :description, :observed_activity, :threat_level, :status, :last_observed_at,
            :source_type, :source_reliability, :reported_by_user_id, :reported_by_callsign,
            :properties, :icon_type, :marker_color,
            :is_visible, :visibility_level, :parent_poi_id, :related_report_id,
            :created_by_user_id
        )";

        $params = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'poi_name' => $data['poi_name'] ?? 'POI',
            'poi_code' => $data['poi_code'] ?? null,
            'category' => $data['category'] ?? 'OTHER',
            'affiliation' => $data['affiliation'] ?? 'UNKNOWN',
            'certainty' => $data['certainty'] ?? 'TO_VERIFY',
            'pos_x' => $data['pos_x'] ?? null,
            'pos_y' => $data['pos_y'] ?? null,
            'pos_z' => $data['pos_z'] ?? null,
            'grid_reference' => $data['grid_reference'] ?? null,
            'description' => $data['description'] ?? null,
            'observed_activity' => $data['observed_activity'] ?? null,
            'threat_level' => $data['threat_level'] ?? 'NONE',
            'status' => $data['status'] ?? 'ACTIVE',
            'last_observed_at' => $data['last_observed_at'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_reliability' => $data['source_reliability'] ?? 'UNKNOWN',
            'reported_by_user_id' => $data['reported_by_user_id'] ?? null,
            'reported_by_callsign' => $data['reported_by_callsign'] ?? null,
            'properties' => isset($data['properties']) ? json_encode($data['properties']) : null,
            'icon_type' => $data['icon_type'] ?? null,
            'marker_color' => $data['marker_color'] ?? null,
            'is_visible' => $data['is_visible'] ?? true,
            'visibility_level' => $data['visibility_level'] ?? 'PUBLIC',
            'parent_poi_id' => $data['parent_poi_id'] ?? null,
            'related_report_id' => $data['related_report_id'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Liste les POI pour un contexte
     */
    public function listForContext(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['p.tenant_id = :tenant_id', 'p.context_id = :context_id', 'p.deleted_at IS NULL'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['category'])) {
            if (is_array($filters['category'])) {
                $placeholders = [];
                foreach ($filters['category'] as $i => $cat) {
                    $key = "category_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $cat;
                }
                $where[] = 'p.category IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'p.category = :category';
                $params['category'] = $filters['category'];
            }
        }

        if (!empty($filters['affiliation'])) {
            $where[] = 'p.affiliation = :affiliation';
            $params['affiliation'] = $filters['affiliation'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['threat_level'])) {
            $where[] = 'p.threat_level = :threat_level';
            $params['threat_level'] = $filters['threat_level'];
        }

        if (isset($filters['is_visible'])) {
            $where[] = 'p.is_visible = :is_visible';
            $params['is_visible'] = (bool) $filters['is_visible'];
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'p.created_at DESC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 200;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM v_atak_poi p 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as &$row) {
            if (!empty($row['properties'])) {
                $row['properties'] = json_decode($row['properties'], true);
            }
        }

        return $results;
    }

    /**
     * Récupère un POI par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM v_atak_poi WHERE id = :id AND deleted_at IS NULL";
        $poi = $this->db->fetchOne($sql, ['id' => $id]);

        if ($poi) {
            if (!empty($poi['properties'])) {
                $poi['properties'] = json_decode($poi['properties'], true);
            }
        }

        return $poi ?: null;
    }

    /**
     * Met à jour un POI
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'poi_name', 'poi_code', 'category', 'affiliation', 'certainty',
            'pos_x', 'pos_y', 'pos_z', 'grid_reference',
            'description', 'observed_activity', 'threat_level', 'status',
            'last_observed_at', 'source_type', 'source_reliability',
            'icon_type', 'marker_color', 'is_visible', 'visibility_level',
            'parent_poi_id', 'related_report_id', 'updated_by_user_id'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (isset($data['properties'])) {
            $fields[] = "properties = :properties";
            $params['properties'] = json_encode($data['properties']);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE atak_poi SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Supprime logiquement un POI
     */
    public function softDelete(int $id): bool
    {
        $sql = "UPDATE atak_poi SET deleted_at = NOW() WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Compte les POI par catégorie
     */
    public function countByCategory(int $tenantId, int $contextId): array
    {
        $sql = "SELECT category, COUNT(*) as count 
                FROM atak_poi 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND deleted_at IS NULL
                GROUP BY category";
        
        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }

    /**
     * Récupère les POI proches d'une position
     */
    public function findNearPosition(
        int $tenantId,
        int $contextId,
        float $posX,
        float $posY,
        float $radius = 1000.0
    ): array {
        $sql = "SELECT *,
                SQRT(POW(pos_x - :pos_x, 2) + POW(pos_y - :pos_y, 2)) AS distance
                FROM v_atak_poi
                WHERE tenant_id = :tenant_id
                  AND context_id = :context_id
                  AND deleted_at IS NULL
                  AND is_visible = TRUE
                HAVING distance <= :radius
                ORDER BY distance ASC";

        $results = $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'pos_x' => $posX,
            'pos_y' => $posY,
            'radius' => $radius
        ]);

        foreach ($results as &$row) {
            if (!empty($row['properties'])) {
                $row['properties'] = json_decode($row['properties'], true);
            }
        }

        return $results;
    }

    /**
     * Ajoute une observation à un POI
     */
    public function addObservation(int $poiId, array $data): int
    {
        $sql = "INSERT INTO atak_poi_observations (
            poi_id, observed_at, observer_user_id, observer_callsign,
            status_at_observation, observed_activity, threat_assessment, notes
        ) VALUES (
            :poi_id, :observed_at, :observer_user_id, :observer_callsign,
            :status_at_observation, :observed_activity, :threat_assessment, :notes
        )";

        $params = [
            'poi_id' => $poiId,
            'observed_at' => $data['observed_at'] ?? date('Y-m-d H:i:s'),
            'observer_user_id' => $data['observer_user_id'] ?? null,
            'observer_callsign' => $data['observer_callsign'] ?? null,
            'status_at_observation' => $data['status_at_observation'] ?? 'UNKNOWN',
            'observed_activity' => $data['observed_activity'] ?? null,
            'threat_assessment' => $data['threat_assessment'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $observationId = (int) $this->db->insert($sql, $params);

        // Mise à jour du POI avec la dernière observation
        $this->db->execute(
            "UPDATE atak_poi SET last_observed_at = :observed_at WHERE id = :poi_id",
            ['poi_id' => $poiId, 'observed_at' => $params['observed_at']]
        );

        return $observationId;
    }

    /**
     * Récupère les observations d'un POI
     */
    public function getObservations(int $poiId, int $limit = 50): array
    {
        $sql = "SELECT o.*, u.username as observer_username
                FROM atak_poi_observations o
                LEFT JOIN users u ON o.observer_user_id = u.id
                WHERE o.poi_id = :poi_id
                ORDER BY o.observed_at DESC
                LIMIT :limit";

        return $this->db->fetchAll($sql, ['poi_id' => $poiId, 'limit' => $limit]);
    }
}
