<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour les demandes QRF (Quick Reaction Force)
 */
class AtakQrfRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée une nouvelle demande QRF
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO atak_qrf_requests (
            tenant_id, context_id, qrf_number, priority,
            contact_pos_x, contact_pos_y, grid_reference, location_description,
            threat_type, threat_description, enemy_strength, enemy_disposition,
            requesting_unit, requesting_callsign, requesting_user_id, requesting_steam_id,
            friendly_strength, friendly_casualties, friendly_status,
            support_requested, enemy_weapons, terrain_description, best_approach, hazards,
            requested_at, urgency_expires_at
        ) VALUES (
            :tenant_id, :context_id, :qrf_number, :priority,
            :contact_pos_x, :contact_pos_y, :grid_reference, :location_description,
            :threat_type, :threat_description, :enemy_strength, :enemy_disposition,
            :requesting_unit, :requesting_callsign, :requesting_user_id, :requesting_steam_id,
            :friendly_strength, :friendly_casualties, :friendly_status,
            :support_requested, :enemy_weapons, :terrain_description, :best_approach, :hazards,
            :requested_at, :urgency_expires_at
        )";

        $params = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'qrf_number' => $data['qrf_number'] ?? null,
            'priority' => $data['priority'] ?? 'IMMEDIATE',
            'contact_pos_x' => $data['contact_pos_x'] ?? null,
            'contact_pos_y' => $data['contact_pos_y'] ?? null,
            'grid_reference' => $data['grid_reference'] ?? null,
            'location_description' => $data['location_description'] ?? null,
            'threat_type' => $data['threat_type'] ?? 'OTHER',
            'threat_description' => $data['threat_description'] ?? null,
            'enemy_strength' => $data['enemy_strength'] ?? 'UNKNOWN',
            'enemy_disposition' => $data['enemy_disposition'] ?? null,
            'requesting_unit' => $data['requesting_unit'] ?? null,
            'requesting_callsign' => $data['requesting_callsign'] ?? null,
            'requesting_user_id' => $data['requesting_user_id'] ?? null,
            'requesting_steam_id' => $data['requesting_steam_id'] ?? null,
            'friendly_strength' => $data['friendly_strength'] ?? null,
            'friendly_casualties' => $data['friendly_casualties'] ?? 0,
            'friendly_status' => $data['friendly_status'] ?? 'PINNED',
            'support_requested' => isset($data['support_requested']) ? json_encode($data['support_requested']) : null,
            'enemy_weapons' => isset($data['enemy_weapons']) ? json_encode($data['enemy_weapons']) : null,
            'terrain_description' => $data['terrain_description'] ?? null,
            'best_approach' => $data['best_approach'] ?? null,
            'hazards' => $data['hazards'] ?? null,
            'requested_at' => $data['requested_at'] ?? date('Y-m-d H:i:s'),
            'urgency_expires_at' => $data['urgency_expires_at'] ?? null,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Liste les demandes QRF
     */
    public function listForContext(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant_id', 'context_id = :context_id'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $i => $st) {
                    $key = "status_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $st;
                }
                $where[] = 'status IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'status = :status';
                $params['status'] = $filters['status'];
            }
        }

        if (!empty($filters['priority'])) {
            $where[] = 'priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (isset($filters['only_active'])) {
            $where[] = "status IN ('REQUESTED', 'ACKNOWLEDGED', 'QRF_ASSIGNED', 'QRF_ENROUTE', 'QRF_ENGAGED')";
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'priority DESC, requested_at ASC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM v_atak_active_qrf 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as &$row) {
            if (!empty($row['support_requested'])) {
                $row['support_requested'] = json_decode($row['support_requested'], true);
            }
            if (!empty($row['enemy_weapons'])) {
                $row['enemy_weapons'] = json_decode($row['enemy_weapons'], true);
            }
        }

        return $results;
    }

    /**
     * Récupère une demande par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM v_atak_active_qrf WHERE id = :id";
        $qrf = $this->db->fetchOne($sql, ['id' => $id]);

        if ($qrf) {
            if (!empty($qrf['support_requested'])) {
                $qrf['support_requested'] = json_decode($qrf['support_requested'], true);
            }
            if (!empty($qrf['enemy_weapons'])) {
                $qrf['enemy_weapons'] = json_decode($qrf['enemy_weapons'], true);
            }
        }

        return $qrf ?: null;
    }

    /**
     * Assigne une QRF à une demande
     */
    public function assignQrf(int $id, string $qrfUnit, string $qrfCallsign, ?int $leaderUserId = null): bool
    {
        $sql = "UPDATE atak_qrf_requests 
                SET assigned_qrf_unit = :qrf_unit,
                    assigned_qrf_callsign = :qrf_callsign,
                    assigned_qrf_leader_user_id = :leader_user_id,
                    assigned_at = NOW(),
                    status = CASE WHEN status = 'REQUESTED' THEN 'QRF_ASSIGNED' ELSE status END
                WHERE id = :id";

        return $this->db->execute($sql, [
            'id' => $id,
            'qrf_unit' => $qrfUnit,
            'qrf_callsign' => $qrfCallsign,
            'leader_user_id' => $leaderUserId
        ]) > 0;
    }

    /**
     * Met à jour la position de la QRF
     */
    public function updateQrfPosition(int $id, float $posX, float $posY, ?string $eta = null): bool
    {
        $sql = "UPDATE atak_qrf_requests 
                SET qrf_current_pos_x = :pos_x,
                    qrf_current_pos_y = :pos_y";

        $params = ['id' => $id, 'pos_x' => $posX, 'pos_y' => $posY];

        if ($eta !== null) {
            $sql .= ", qrf_eta = :eta";
            $params['eta'] = $eta;
        }

        $sql .= " WHERE id = :id";

        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Met à jour le statut
     */
    public function updateStatus(int $id, string $newStatus): bool
    {
        $timestampFields = [
            'ACKNOWLEDGED' => 'acknowledged_at',
            'QRF_ENROUTE' => 'qrf_departed_at',
            'QRF_ENGAGED' => 'qrf_arrived_at',
            'SITUATION_STABILIZED' => 'situation_stabilized_at',
            'COMPLETED' => 'completed_at',
            'CANCELLED' => 'cancelled_at',
        ];

        $updates = ['status = :status'];
        $params = ['id' => $id, 'status' => $newStatus];

        if (isset($timestampFields[$newStatus])) {
            $field = $timestampFields[$newStatus];
            $updates[] = "{$field} = NOW()";
        }

        $sql = "UPDATE atak_qrf_requests SET " . implode(', ', $updates) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Ajoute une mise à jour SITREP
     */
    public function addSitrepUpdate(int $qrfId, array $updateData): int
    {
        $sql = "INSERT INTO atak_qrf_sitrep_updates (
            qrf_request_id, update_type, update_message,
            pos_x, pos_y, updated_by_callsign, updated_by_user_id, is_from_qrf
        ) VALUES (
            :qrf_request_id, :update_type, :update_message,
            :pos_x, :pos_y, :updated_by_callsign, :updated_by_user_id, :is_from_qrf
        )";

        $params = [
            'qrf_request_id' => $qrfId,
            'update_type' => $updateData['update_type'] ?? 'SITUATION_UPDATE',
            'update_message' => $updateData['update_message'] ?? '',
            'pos_x' => $updateData['pos_x'] ?? null,
            'pos_y' => $updateData['pos_y'] ?? null,
            'updated_by_callsign' => $updateData['updated_by_callsign'] ?? null,
            'updated_by_user_id' => $updateData['updated_by_user_id'] ?? null,
            'is_from_qrf' => $updateData['is_from_qrf'] ?? false,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Récupère les mises à jour SITREP
     */
    public function getSitrepUpdates(int $qrfId, int $limit = 50): array
    {
        $sql = "SELECT * FROM atak_qrf_sitrep_updates 
                WHERE qrf_request_id = :qrf_id 
                ORDER BY updated_at DESC 
                LIMIT :limit";

        return $this->db->fetchAll($sql, ['qrf_id' => $qrfId, 'limit' => $limit]);
    }

    /**
     * Ajoute un waypoint
     */
    public function addWaypoint(int $qrfId, array $waypointData): int
    {
        $sql = "INSERT INTO atak_qrf_waypoints (
            qrf_request_id, sequence_number, pos_x, pos_y,
            waypoint_type, waypoint_name, description
        ) VALUES (
            :qrf_request_id, :sequence_number, :pos_x, :pos_y,
            :waypoint_type, :waypoint_name, :description
        )";

        $params = [
            'qrf_request_id' => $qrfId,
            'sequence_number' => $waypointData['sequence_number'] ?? 1,
            'pos_x' => $waypointData['pos_x'] ?? null,
            'pos_y' => $waypointData['pos_y'] ?? null,
            'waypoint_type' => $waypointData['waypoint_type'] ?? 'CHECKPOINT',
            'waypoint_name' => $waypointData['waypoint_name'] ?? null,
            'description' => $waypointData['description'] ?? null,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Récupère les waypoints
     */
    public function getWaypoints(int $qrfId): array
    {
        $sql = "SELECT * FROM atak_qrf_waypoints 
                WHERE qrf_request_id = :qrf_id 
                ORDER BY sequence_number ASC";

        return $this->db->fetchAll($sql, ['qrf_id' => $qrfId]);
    }

    /**
     * Génère le prochain numéro QRF
     */
    public function generateQrfNumber(int $tenantId, int $contextId): string
    {
        $sql = "SELECT COUNT(*) as count 
                FROM atak_qrf_requests 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND DATE(requested_at) = CURDATE()";

        $result = $this->db->fetchOne($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);

        $count = (int) ($result['count'] ?? 0) + 1;
        $date = date('Ymd');

        return sprintf('QRF-%s-%03d', $date, $count);
    }
}
