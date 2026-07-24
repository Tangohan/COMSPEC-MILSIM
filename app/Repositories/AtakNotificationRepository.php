<?php

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour la gestion des notifications temps réel
 */
class AtakNotificationRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée une notification temps réel
     */
    public function create(int $tenantId, int $contextId, array $data): int
    {
        $this->db->execute(
            "INSERT INTO atak_realtime_notifications 
             (tenant_id, context_id, notification_type, priority, title, message,
              source_entity_type, source_entity_id, target_roles, target_users, target_units,
              sound_alert, show_on_map, map_pos_x, map_pos_y, expires_at, properties)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $contextId,
                $data['notification_type'],
                $data['priority'] ?? 'INFO',
                $data['title'],
                $data['message'],
                $data['source_entity_type'] ?? null,
                $data['source_entity_id'] ?? null,
                $data['target_roles'] ?? null,
                $data['target_users'] ?? null,
                $data['target_units'] ?? null,
                $data['sound_alert'] ?? null,
                $data['show_on_map'] ?? false,
                $data['map_pos_x'] ?? null,
                $data['map_pos_y'] ?? null,
                $data['expires_at'] ?? null,
                $data['properties'] ? json_encode($data['properties']) : null
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Liste les notifications actives pour un contexte
     */
    public function listActive(int $tenantId, int $contextId, array $filters = []): array
    {
        $conditions = [
            "tenant_id = ?",
            "context_id = ?",
            "is_active = TRUE",
            "(expires_at IS NULL OR expires_at > NOW())"
        ];
        $params = [$tenantId, $contextId];

        if (isset($filters['notification_type'])) {
            $conditions[] = "notification_type = ?";
            $params[] = $filters['notification_type'];
        }

        if (isset($filters['priority'])) {
            $conditions[] = "priority = ?";
            $params[] = $filters['priority'];
        }

        if (isset($filters['min_priority'])) {
            $priorityOrder = ['INFO' => 1, 'LOW' => 2, 'MEDIUM' => 3, 'HIGH' => 4, 'CRITICAL' => 5];
            $minLevel = $priorityOrder[$filters['min_priority']] ?? 1;
            $conditions[] = "CASE priority 
                            WHEN 'INFO' THEN 1 
                            WHEN 'LOW' THEN 2 
                            WHEN 'MEDIUM' THEN 3 
                            WHEN 'HIGH' THEN 4 
                            WHEN 'CRITICAL' THEN 5 
                            END >= ?";
            $params[] = $minLevel;
        }

        // Filtrage par rôle/utilisateur
        if (isset($filters['for_role'])) {
            $conditions[] = "JSON_CONTAINS(target_roles, ?)";
            $params[] = json_encode($filters['for_role']);
        }

        if (isset($filters['for_user_id'])) {
            $conditions[] = "JSON_CONTAINS(target_users, ?)";
            $params[] = json_encode($filters['for_user_id']);
        }

        $sql = "SELECT * FROM atak_realtime_notifications 
                WHERE " . implode(" AND ", $conditions) . "
                ORDER BY priority DESC, created_at DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Marque une notification comme inactive
     */
    public function dismiss(int $id): bool
    {
        return $this->db->execute(
            "UPDATE atak_realtime_notifications SET is_active = FALSE WHERE id = ?",
            [$id]
        );
    }

    /**
     * Nettoie les notifications expirées
     */
    public function cleanupExpired(int $tenantId, int $contextId): int
    {
        $stmt = $this->db->execute(
            "UPDATE atak_realtime_notifications 
             SET is_active = FALSE 
             WHERE tenant_id = ? AND context_id = ?
               AND is_active = TRUE 
               AND expires_at IS NOT NULL 
               AND expires_at <= NOW()",
            [$tenantId, $contextId]
        );

        return $stmt->rowCount();
    }

    /**
     * Compte les notifications actives par priorité
     */
    public function countByPriority(int $tenantId, int $contextId): array
    {
        $result = $this->db->query(
            "SELECT priority, COUNT(*) as count 
             FROM atak_realtime_notifications 
             WHERE tenant_id = ? AND context_id = ?
               AND is_active = TRUE
               AND (expires_at IS NULL OR expires_at > NOW())
             GROUP BY priority",
            [$tenantId, $contextId]
        )->fetchAll();

        $counts = [
            'INFO' => 0,
            'LOW' => 0,
            'MEDIUM' => 0,
            'HIGH' => 0,
            'CRITICAL' => 0
        ];

        foreach ($result as $row) {
            $counts[$row['priority']] = $row['count'];
        }

        return $counts;
    }

    /**
     * Crée une notification MEDEVAC golden hour
     */
    public function createGoldenHourWarning(int $medevacId, int $tenantId, int $contextId, array $medevacData): int
    {
        $minutesRemaining = $medevacData['golden_hour_minutes_remaining'] ?? 0;
        
        $priority = $minutesRemaining <= 5 ? 'CRITICAL' : 'HIGH';
        $title = $minutesRemaining <= 5 ? '🚨 GOLDEN HOUR CRITIQUE' : '⚠️ Golden Hour Warning';
        
        $message = sprintf(
            "MEDEVAC %s : Golden hour expire dans %d minutes. Patient T1 urgent nécessite évacuation immédiate.",
            $medevacData['medevac_number'],
            $minutesRemaining
        );

        return $this->create($tenantId, $contextId, [
            'notification_type' => 'GOLDEN_HOUR_WARNING',
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'source_entity_type' => 'MEDEVAC',
            'source_entity_id' => $medevacId,
            'target_roles' => json_encode(['COMMAND', 'MEDICAL', 'AVIATION']),
            'sound_alert' => $priority === 'CRITICAL' ? 'ALERT_CRITICAL' : 'ALERT_WARNING',
            'show_on_map' => true,
            'map_pos_x' => $medevacData['pickup_pos_x'],
            'map_pos_y' => $medevacData['pickup_pos_y'],
            'expires_at' => $medevacData['golden_hour_expires_at']
        ]);
    }

    /**
     * Crée une notification véhicule critique
     */
    public function createVehicleCriticalWarning(int $vehicleId, int $tenantId, int $contextId, array $vehicleData): int
    {
        $issues = [];
        if ($vehicleData['is_fuel_critical']) $issues[] = 'carburant critique';
        if ($vehicleData['is_ammo_critical']) $issues[] = 'munitions critiques';
        if ($vehicleData['is_damaged']) $issues[] = 'endommagé';

        $message = sprintf(
            "Véhicule %s : %s. Position: %.0f, %.0f. Maintenance immédiate recommandée.",
            $vehicleData['vehicle_callsign'],
            implode(', ', $issues),
            $vehicleData['pos_x'],
            $vehicleData['pos_y']
        );

        return $this->create($tenantId, $contextId, [
            'notification_type' => 'VEHICLE_CRITICAL',
            'priority' => 'HIGH',
            'title' => "⚠️ Véhicule {$vehicleData['vehicle_callsign']}",
            'message' => $message,
            'source_entity_type' => 'VEHICLE',
            'source_entity_id' => $vehicleId,
            'target_roles' => json_encode(['COMMAND', 'LOGISTICS']),
            'target_units' => json_encode([$vehicleData['unit_assigned']]),
            'sound_alert' => 'ALERT_WARNING',
            'show_on_map' => true,
            'map_pos_x' => $vehicleData['pos_x'],
            'map_pos_y' => $vehicleData['pos_y'],
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes'))
        ]);
    }

    /**
     * Polling pour récupérer nouvelles notifications depuis timestamp
     */
    public function pollSince(int $tenantId, int $contextId, string $sinceTimestamp, array $filters = []): array
    {
        $filters['created_after'] = $sinceTimestamp;
        
        $conditions = [
            "tenant_id = ?",
            "context_id = ?",
            "is_active = TRUE",
            "created_at > ?"
        ];
        $params = [$tenantId, $contextId, $sinceTimestamp];

        // Filtres additionnels
        if (isset($filters['for_role'])) {
            $conditions[] = "JSON_CONTAINS(target_roles, ?)";
            $params[] = json_encode($filters['for_role']);
        }

        $sql = "SELECT * FROM atak_realtime_notifications 
                WHERE " . implode(" AND ", $conditions) . "
                ORDER BY priority DESC, created_at ASC
                LIMIT 50";

        return $this->db->query($sql, $params)->fetchAll();
    }
}
