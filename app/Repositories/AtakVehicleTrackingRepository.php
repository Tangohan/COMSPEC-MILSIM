<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakModulesSchema;

/**
 * Repository pour le tracking enrichi des véhicules et assets lourds
 */
class AtakVehicleTrackingRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        AtakModulesSchema::ensure();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée ou met à jour un véhicule (upsert)
     */
    public function upsert(array $data): int
    {
        // Vérifier si le véhicule existe déjà
        $existing = $this->findByCallsign(
            (int) $data['tenant_id'],
            (int) $data['context_id'],
            (string) $data['vehicle_callsign']
        );

        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        return $this->create($data);
    }

    /**
     * Crée un nouveau véhicule
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO atak_vehicle_tracking (
            tenant_id, context_id, vehicle_callsign, vehicle_name,
            vehicle_class, vehicle_type, side, unit_assigned,
            crew_commander_callsign, crew_commander_user_id, crew_count, crew_max,
            passenger_count, passenger_max, passengers_json,
            pos_x, pos_y, pos_z, heading, speed,
            status, fuel_percent, fuel_capacity, fuel_consumption_rate,
            ammo_percent, weapons, engine_health, hull_health, tracks_wheels_health, turret_health,
            destination_pos_x, destination_pos_y, mission_type, mission_description,
            properties, is_visible, last_seen_at
        ) VALUES (
            :tenant_id, :context_id, :vehicle_callsign, :vehicle_name,
            :vehicle_class, :vehicle_type, :side, :unit_assigned,
            :crew_commander_callsign, :crew_commander_user_id, :crew_count, :crew_max,
            :passenger_count, :passenger_max, :passengers_json,
            :pos_x, :pos_y, :pos_z, :heading, :speed,
            :status, :fuel_percent, :fuel_capacity, :fuel_consumption_rate,
            :ammo_percent, :weapons, :engine_health, :hull_health, :tracks_wheels_health, :turret_health,
            :destination_pos_x, :destination_pos_y, :mission_type, :mission_description,
            :properties, :is_visible, :last_seen_at
        )";

        $params = $this->prepareParams($data);
        $vehicleId = (int) $this->db->insert($sql, $params);

        // Sauvegarder position initiale dans historique
        $this->savePositionHistory($vehicleId, $data);

        return $vehicleId;
    }

    /**
     * Met à jour un véhicule
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'vehicle_name', 'vehicle_class', 'vehicle_type', 'side', 'unit_assigned',
            'crew_commander_callsign', 'crew_commander_user_id', 'crew_count', 'crew_max',
            'passenger_count', 'passenger_max',
            'pos_x', 'pos_y', 'pos_z', 'heading', 'speed',
            'status', 'fuel_percent', 'fuel_capacity', 'fuel_consumption_rate',
            'ammo_percent', 'engine_health', 'hull_health', 'tracks_wheels_health', 'turret_health',
            'destination_pos_x', 'destination_pos_y', 'mission_type', 'mission_description',
            'is_visible'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        $jsonFields = ['passengers_json', 'weapons', 'properties'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = json_encode($data[$field]);
            }
        }

        // Toujours mettre à jour last_seen_at
        $fields[] = "last_seen_at = NOW()";

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE atak_vehicle_tracking SET " . implode(', ', $fields) . " WHERE id = :id";
        $success = $this->db->execute($sql, $params) > 0;

        // Sauvegarder dans historique si position changée
        if ($success && (isset($data['pos_x']) || isset($data['pos_y']))) {
            $this->savePositionHistory($id, $data);
        }

        return $success;
    }

    /**
     * Prépare les paramètres pour insertion
     */
    private function prepareParams(array $data): array
    {
        return [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'vehicle_callsign' => $data['vehicle_callsign'] ?? null,
            'vehicle_name' => $data['vehicle_name'] ?? null,
            'vehicle_class' => $data['vehicle_class'] ?? 'OTHER',
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'side' => $data['side'] ?? 'BLUFOR',
            'unit_assigned' => $data['unit_assigned'] ?? null,
            'crew_commander_callsign' => $data['crew_commander_callsign'] ?? null,
            'crew_commander_user_id' => $data['crew_commander_user_id'] ?? null,
            'crew_count' => $data['crew_count'] ?? 0,
            'crew_max' => $data['crew_max'] ?? null,
            'passenger_count' => $data['passenger_count'] ?? 0,
            'passenger_max' => $data['passenger_max'] ?? null,
            'passengers_json' => isset($data['passengers_json']) ? json_encode($data['passengers_json']) : null,
            'pos_x' => $data['pos_x'] ?? null,
            'pos_y' => $data['pos_y'] ?? null,
            'pos_z' => $data['pos_z'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'status' => $data['status'] ?? 'OPERATIONAL',
            'fuel_percent' => $data['fuel_percent'] ?? null,
            'fuel_capacity' => $data['fuel_capacity'] ?? null,
            'fuel_consumption_rate' => $data['fuel_consumption_rate'] ?? null,
            'ammo_percent' => $data['ammo_percent'] ?? null,
            'weapons' => isset($data['weapons']) ? json_encode($data['weapons']) : null,
            'engine_health' => $data['engine_health'] ?? null,
            'hull_health' => $data['hull_health'] ?? null,
            'tracks_wheels_health' => $data['tracks_wheels_health'] ?? null,
            'turret_health' => $data['turret_health'] ?? null,
            'destination_pos_x' => $data['destination_pos_x'] ?? null,
            'destination_pos_y' => $data['destination_pos_y'] ?? null,
            'mission_type' => $data['mission_type'] ?? null,
            'mission_description' => $data['mission_description'] ?? null,
            'properties' => isset($data['properties']) ? json_encode($data['properties']) : null,
            'is_visible' => $data['is_visible'] ?? true,
            'last_seen_at' => $data['last_seen_at'] ?? date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Sauvegarde position dans historique
     */
    private function savePositionHistory(int $vehicleId, array $data): void
    {
        if (!isset($data['pos_x']) || !isset($data['pos_y'])) {
            return;
        }

        $sql = "INSERT INTO atak_vehicle_position_history (
            vehicle_tracking_id, pos_x, pos_y, pos_z, heading, speed, fuel_percent, ammo_percent
        ) VALUES (
            :vehicle_id, :pos_x, :pos_y, :pos_z, :heading, :speed, :fuel_percent, :ammo_percent
        )";

        $this->db->insert($sql, [
            'vehicle_id' => $vehicleId,
            'pos_x' => $data['pos_x'],
            'pos_y' => $data['pos_y'],
            'pos_z' => $data['pos_z'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'fuel_percent' => $data['fuel_percent'] ?? null,
            'ammo_percent' => $data['ammo_percent'] ?? null,
        ]);
    }

    /**
     * Liste les véhicules actifs
     */
    public function listActive(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant_id', 'context_id = :context_id'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['vehicle_class'])) {
            if (is_array($filters['vehicle_class'])) {
                $placeholders = [];
                foreach ($filters['vehicle_class'] as $i => $class) {
                    $key = "vehicle_class_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $class;
                }
                $where[] = 'vehicle_class IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'vehicle_class = :vehicle_class';
                $params['vehicle_class'] = $filters['vehicle_class'];
            }
        }

        if (!empty($filters['side'])) {
            $where[] = 'side = :side';
            $params['side'] = $filters['side'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['fuel_critical'])) {
            $where[] = 'is_fuel_critical = :fuel_critical';
            $params['fuel_critical'] = (bool) $filters['fuel_critical'];
        }

        if (isset($filters['damaged'])) {
            $where[] = 'is_damaged = :damaged';
            $params['damaged'] = (bool) $filters['damaged'];
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'last_seen_at DESC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 200;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM v_atak_active_vehicles 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as &$row) {
            $this->decodeJsonFields($row);
        }

        return $results;
    }

    /**
     * Trouve un véhicule par callsign
     */
    public function findByCallsign(int $tenantId, int $contextId, string $callsign): ?array
    {
        $sql = "SELECT * FROM v_atak_active_vehicles 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND vehicle_callsign = :callsign";

        $vehicle = $this->db->fetchOne($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'callsign' => $callsign
        ]);

        if ($vehicle) {
            $this->decodeJsonFields($vehicle);
        }

        return $vehicle ?: null;
    }

    /**
     * Trouve un véhicule par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM v_atak_active_vehicles WHERE id = :id";
        $vehicle = $this->db->fetchOne($sql, ['id' => $id]);

        if ($vehicle) {
            $this->decodeJsonFields($vehicle);
        }

        return $vehicle ?: null;
    }

    /**
     * Décode les champs JSON
     */
    private function decodeJsonFields(array &$row): void
    {
        $jsonFields = ['passengers_json', 'weapons', 'properties'];
        foreach ($jsonFields as $field) {
            if (!empty($row[$field])) {
                $row[$field] = json_decode($row[$field], true);
            }
        }
    }

    /**
     * Crée un événement véhicule
     */
    public function createEvent(int $vehicleId, array $eventData): int
    {
        $sql = "INSERT INTO atak_vehicle_events (
            vehicle_tracking_id, event_type, event_description,
            event_pos_x, event_pos_y, actor_callsign, actor_user_id, event_data
        ) VALUES (
            :vehicle_id, :event_type, :event_description,
            :event_pos_x, :event_pos_y, :actor_callsign, :actor_user_id, :event_data
        )";

        return (int) $this->db->insert($sql, [
            'vehicle_id' => $vehicleId,
            'event_type' => $eventData['event_type'] ?? 'MOVED',
            'event_description' => $eventData['event_description'] ?? null,
            'event_pos_x' => $eventData['event_pos_x'] ?? null,
            'event_pos_y' => $eventData['event_pos_y'] ?? null,
            'actor_callsign' => $eventData['actor_callsign'] ?? null,
            'actor_user_id' => $eventData['actor_user_id'] ?? null,
            'event_data' => isset($eventData['event_data']) ? json_encode($eventData['event_data']) : null,
        ]);
    }

    /**
     * Crée une demande de service
     */
    public function createServiceRequest(int $vehicleId, array $requestData): int
    {
        $sql = "INSERT INTO atak_vehicle_service_requests (
            vehicle_tracking_id, request_type, priority, request_details,
            requested_by_callsign, service_pos_x, service_pos_y
        ) VALUES (
            :vehicle_id, :request_type, :priority, :request_details,
            :requested_by_callsign, :service_pos_x, :service_pos_y
        )";

        return (int) $this->db->insert($sql, [
            'vehicle_id' => $vehicleId,
            'request_type' => $requestData['request_type'] ?? 'REFUEL',
            'priority' => $requestData['priority'] ?? 'MEDIUM',
            'request_details' => $requestData['request_details'] ?? null,
            'requested_by_callsign' => $requestData['requested_by_callsign'] ?? null,
            'service_pos_x' => $requestData['service_pos_x'] ?? null,
            'service_pos_y' => $requestData['service_pos_y'] ?? null,
        ]);
    }

    /**
     * Liste les demandes de service en attente
     */
    public function listPendingServiceRequests(int $tenantId, int $contextId): array
    {
        $sql = "SELECT sr.*, v.vehicle_callsign, v.vehicle_class, v.pos_x, v.pos_y
                FROM atak_vehicle_service_requests sr
                INNER JOIN atak_vehicle_tracking v ON sr.vehicle_tracking_id = v.id
                WHERE v.tenant_id = :tenant_id
                  AND v.context_id = :context_id
                  AND sr.status IN ('REQUESTED', 'ACKNOWLEDGED', 'ENROUTE', 'IN_PROGRESS')
                ORDER BY sr.priority DESC, sr.requested_at ASC";

        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }

    /**
     * Compte véhicules par classe
     */
    public function countByClass(int $tenantId, int $contextId): array
    {
        $sql = "SELECT vehicle_class, COUNT(*) as count 
                FROM atak_vehicle_tracking 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND status != 'DESTROYED'
                  AND TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()) <= 30
                GROUP BY vehicle_class";

        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }
}
