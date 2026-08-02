<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakModulesSchema;

/**
 * Repository pour les demandes MEDEVAC étendues avec triage TCCC
 */
class AtakMedevacRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        AtakModulesSchema::ensure();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée une nouvelle demande MEDEVAC
     */
    public function create(array $data): int
    {
        // Calcul du golden hour pour patients T1
        $goldenHourExpires = null;
        if (($data['patients_t1_urgent'] ?? 0) > 0) {
            $requestedAt = $data['requested_at'] ?? date('Y-m-d H:i:s');
            $goldenHourExpires = date('Y-m-d H:i:s', strtotime($requestedAt . ' +60 minutes'));
        }

        $sql = "INSERT INTO atak_medevac_requests (
            tenant_id, context_id, medevac_number, priority,
            pickup_grid, pickup_pos_x, pickup_pos_y, pickup_elevation,
            radio_frequency, radio_callsign,
            patients_t1_urgent, patients_t2_urgent, patients_t3_delayed, patients_t4_expectant,
            equipment_needed, patients_litter, patients_ambulatory,
            security_status, enemy_description,
            lz_marking, lz_marking_color, lz_marking_details,
            patient_nationality, patient_status,
            nbc_contamination, nbc_details,
            terrain_description, obstacles, approach_direction, remarks,
            requested_by_user_id, requested_by_callsign, requested_by_unit, requested_at,
            golden_hour_expires_at
        ) VALUES (
            :tenant_id, :context_id, :medevac_number, :priority,
            :pickup_grid, :pickup_pos_x, :pickup_pos_y, :pickup_elevation,
            :radio_frequency, :radio_callsign,
            :patients_t1_urgent, :patients_t2_urgent, :patients_t3_delayed, :patients_t4_expectant,
            :equipment_needed, :patients_litter, :patients_ambulatory,
            :security_status, :enemy_description,
            :lz_marking, :lz_marking_color, :lz_marking_details,
            :patient_nationality, :patient_status,
            :nbc_contamination, :nbc_details,
            :terrain_description, :obstacles, :approach_direction, :remarks,
            :requested_by_user_id, :requested_by_callsign, :requested_by_unit, :requested_at,
            :golden_hour_expires_at
        )";

        $params = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'medevac_number' => $data['medevac_number'] ?? null,
            'priority' => $data['priority'] ?? 'URGENT',
            'pickup_grid' => $data['pickup_grid'] ?? null,
            'pickup_pos_x' => $data['pickup_pos_x'] ?? null,
            'pickup_pos_y' => $data['pickup_pos_y'] ?? null,
            'pickup_elevation' => $data['pickup_elevation'] ?? null,
            'radio_frequency' => $data['radio_frequency'] ?? null,
            'radio_callsign' => $data['radio_callsign'] ?? null,
            'patients_t1_urgent' => $data['patients_t1_urgent'] ?? 0,
            'patients_t2_urgent' => $data['patients_t2_urgent'] ?? 0,
            'patients_t3_delayed' => $data['patients_t3_delayed'] ?? 0,
            'patients_t4_expectant' => $data['patients_t4_expectant'] ?? 0,
            'equipment_needed' => isset($data['equipment_needed']) ? json_encode($data['equipment_needed']) : null,
            'patients_litter' => $data['patients_litter'] ?? 0,
            'patients_ambulatory' => $data['patients_ambulatory'] ?? 0,
            'security_status' => $data['security_status'] ?? 'NO_ENEMY',
            'enemy_description' => $data['enemy_description'] ?? null,
            'lz_marking' => $data['lz_marking'] ?? 'NONE',
            'lz_marking_color' => $data['lz_marking_color'] ?? null,
            'lz_marking_details' => $data['lz_marking_details'] ?? null,
            'patient_nationality' => $data['patient_nationality'] ?? 'FRIENDLY',
            'patient_status' => $data['patient_status'] ?? 'MILITARY',
            'nbc_contamination' => $data['nbc_contamination'] ?? 'NONE',
            'nbc_details' => $data['nbc_details'] ?? null,
            'terrain_description' => $data['terrain_description'] ?? null,
            'obstacles' => $data['obstacles'] ?? null,
            'approach_direction' => $data['approach_direction'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'requested_by_user_id' => $data['requested_by_user_id'] ?? null,
            'requested_by_callsign' => $data['requested_by_callsign'] ?? null,
            'requested_by_unit' => $data['requested_by_unit'] ?? null,
            'requested_at' => $data['requested_at'] ?? date('Y-m-d H:i:s'),
            'golden_hour_expires_at' => $goldenHourExpires,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Liste les demandes MEDEVAC
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

        if (isset($filters['golden_hour_critical'])) {
            $where[] = 'is_golden_hour_critical = :golden_hour_critical';
            $params['golden_hour_critical'] = (bool) $filters['golden_hour_critical'];
        }

        if (isset($filters['only_active'])) {
            $where[] = "status IN ('REQUESTED', 'ACKNOWLEDGED', 'ASSIGNED', 'INBOUND', 'ON_SITE', 'EVACUATING')";
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'priority DESC, requested_at ASC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM v_atak_active_medevac 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as &$row) {
            if (!empty($row['equipment_needed'])) {
                $row['equipment_needed'] = json_decode($row['equipment_needed'], true);
            }
        }

        return $results;
    }

    /**
     * Récupère une demande par ID
     */
    /**
     * @param int|null $tenantId Communauté propriétaire. Sans ce filtre, un
     *   identifiant deviné suffit à lire la demande d'évacuation d'une autre
     *   communauté — position du blessé comprise.
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = "SELECT * FROM v_atak_active_medevac WHERE id = :id";
        $params = ['id' => $id];
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        $medevac = $this->db->fetchOne($sql, $params);

        if ($medevac) {
            if (!empty($medevac['equipment_needed'])) {
                $medevac['equipment_needed'] = json_decode($medevac['equipment_needed'], true);
            }
        }

        return $medevac ?: null;
    }

    /**
     * Met à jour le statut d'une demande
     */
    public function updateStatus(int $id, string $newStatus, ?string $updateMessage = null): bool
    {
        $timestampFields = [
            'ACKNOWLEDGED' => 'acknowledged_at',
            'ON_SITE' => 'arrived_at',
            'COMPLETED' => 'completed_at',
            'CANCELLED' => 'cancelled_at',
        ];

        $updates = ['status = :status'];
        $params = ['id' => $id, 'status' => $newStatus];

        if (isset($timestampFields[$newStatus])) {
            $field = $timestampFields[$newStatus];
            $updates[] = "{$field} = NOW()";
        }

        $sql = "UPDATE atak_medevac_requests SET " . implode(', ', $updates) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Assigne un asset à une demande MEDEVAC
     */
    public function assignAsset(int $id, string $assetCallsign, ?int $pilotUserId = null): bool
    {
        $sql = "UPDATE atak_medevac_requests 
                SET assigned_asset_callsign = :asset_callsign,
                    assigned_pilot_user_id = :pilot_user_id,
                    assigned_at = NOW(),
                    status = CASE WHEN status = 'REQUESTED' THEN 'ASSIGNED' ELSE status END
                WHERE id = :id";

        return $this->db->execute($sql, [
            'id' => $id,
            'asset_callsign' => $assetCallsign,
            'pilot_user_id' => $pilotUserId
        ]) > 0;
    }

    /**
     * Ajoute un patient à une demande MEDEVAC
     */
    public function addPatient(int $medevacId, array $patientData): int
    {
        $sql = "INSERT INTO atak_medevac_patients (
            medevac_request_id, patient_name, patient_callsign, patient_unit, patient_steam_id,
            triage_category, triaged_by_callsign,
            consciousness, breathing, circulation,
            injuries, primary_injury, treatments_given, medications_given,
            is_stabilized, requires_litter, can_walk
        ) VALUES (
            :medevac_request_id, :patient_name, :patient_callsign, :patient_unit, :patient_steam_id,
            :triage_category, :triaged_by_callsign,
            :consciousness, :breathing, :circulation,
            :injuries, :primary_injury, :treatments_given, :medications_given,
            :is_stabilized, :requires_litter, :can_walk
        )";

        $params = [
            'medevac_request_id' => $medevacId,
            'patient_name' => $patientData['patient_name'] ?? null,
            'patient_callsign' => $patientData['patient_callsign'] ?? null,
            'patient_unit' => $patientData['patient_unit'] ?? null,
            'patient_steam_id' => $patientData['patient_steam_id'] ?? null,
            'triage_category' => $patientData['triage_category'] ?? 'T3',
            'triaged_by_callsign' => $patientData['triaged_by_callsign'] ?? null,
            'consciousness' => $patientData['consciousness'] ?? 'ALERT',
            'breathing' => $patientData['breathing'] ?? 'NORMAL',
            'circulation' => $patientData['circulation'] ?? 'NORMAL',
            'injuries' => isset($patientData['injuries']) ? json_encode($patientData['injuries']) : null,
            'primary_injury' => $patientData['primary_injury'] ?? null,
            'treatments_given' => isset($patientData['treatments_given']) ? json_encode($patientData['treatments_given']) : null,
            'medications_given' => isset($patientData['medications_given']) ? json_encode($patientData['medications_given']) : null,
            'is_stabilized' => $patientData['is_stabilized'] ?? false,
            'requires_litter' => $patientData['requires_litter'] ?? false,
            'can_walk' => $patientData['can_walk'] ?? false,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Récupère les patients d'une demande MEDEVAC
     */
    public function getPatients(int $medevacId): array
    {
        $sql = "SELECT * FROM atak_medevac_patients WHERE medevac_request_id = :medevac_id ORDER BY triage_category ASC";
        $patients = $this->db->fetchAll($sql, ['medevac_id' => $medevacId]);

        foreach ($patients as &$patient) {
            if (!empty($patient['injuries'])) {
                $patient['injuries'] = json_decode($patient['injuries'], true);
            }
            if (!empty($patient['treatments_given'])) {
                $patient['treatments_given'] = json_decode($patient['treatments_given'], true);
            }
            if (!empty($patient['medications_given'])) {
                $patient['medications_given'] = json_decode($patient['medications_given'], true);
            }
        }

        return $patients;
    }

    /**
     * Génère le prochain numéro MEDEVAC
     */
    public function generateMedevacNumber(int $tenantId, int $contextId): string
    {
        $sql = "SELECT COUNT(*) as count 
                FROM atak_medevac_requests 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND DATE(requested_at) = CURDATE()";

        $result = $this->db->fetchOne($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);

        $count = (int) ($result['count'] ?? 0) + 1;
        $date = date('Ymd');

        return sprintf('MEDEVAC-%s-%03d', $date, $count);
    }

    /**
     * Récupère les MEDEVAC avec golden hour critique
     */
    public function listGoldenHourCritical(int $tenantId, int $contextId): array
    {
        return $this->listForContext($tenantId, $contextId, [
            'golden_hour_critical' => true,
            'only_active' => true
        ]);
    }
}
