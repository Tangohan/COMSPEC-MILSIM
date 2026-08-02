<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakModulesSchema;

/**
 * Repository pour les rapports tactiques structurés (SPOTREP, SITREP, SALUTE, CONTACT)
 */
class AtakTacticalReportRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        AtakModulesSchema::ensure();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Crée un nouveau rapport tactique
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO atak_tactical_reports (
            tenant_id, context_id, report_type, report_number, priority, classification,
            submitter_user_id, submitter_callsign, submitter_unit, submitter_steam_id,
            pos_x, pos_y, grid_reference, location_description,
            dtg, report_timestamp, event_timestamp,
            structured_data, summary, details, remarks,
            has_attachments, status, visibility, distributed_to
        ) VALUES (
            :tenant_id, :context_id, :report_type, :report_number, :priority, :classification,
            :submitter_user_id, :submitter_callsign, :submitter_unit, :submitter_steam_id,
            :pos_x, :pos_y, :grid_reference, :location_description,
            :dtg, :report_timestamp, :event_timestamp,
            :structured_data, :summary, :details, :remarks,
            :has_attachments, :status, :visibility, :distributed_to
        )";

        $params = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'report_type' => $data['report_type'] ?? 'OTHER',
            'report_number' => $data['report_number'] ?? null,
            'priority' => $data['priority'] ?? 'ROUTINE',
            'classification' => $data['classification'] ?? 'UNCLASSIFIED',
            'submitter_user_id' => $data['submitter_user_id'] ?? null,
            'submitter_callsign' => $data['submitter_callsign'] ?? null,
            'submitter_unit' => $data['submitter_unit'] ?? null,
            'submitter_steam_id' => $data['submitter_steam_id'] ?? null,
            'pos_x' => $data['pos_x'] ?? null,
            'pos_y' => $data['pos_y'] ?? null,
            'grid_reference' => $data['grid_reference'] ?? null,
            'location_description' => $data['location_description'] ?? null,
            'dtg' => $data['dtg'] ?? null,
            'report_timestamp' => $data['report_timestamp'] ?? date('Y-m-d H:i:s'),
            'event_timestamp' => $data['event_timestamp'] ?? null,
            'structured_data' => isset($data['structured_data']) ? json_encode($data['structured_data']) : null,
            'summary' => $data['summary'] ?? null,
            'details' => $data['details'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'has_attachments' => $data['has_attachments'] ?? false,
            'status' => $data['status'] ?? 'SUBMITTED',
            'visibility' => $data['visibility'] ?? 'ALL',
            'distributed_to' => isset($data['distributed_to']) ? json_encode($data['distributed_to']) : null,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    /**
     * Liste les rapports pour un tenant et contexte
     */
    public function listForContext(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['r.tenant_id = :tenant_id', 'r.context_id = :context_id', 'r.deleted_at IS NULL'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['report_type'])) {
            $where[] = 'r.report_type = :report_type';
            $params['report_type'] = $filters['report_type'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 'r.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['submitter_steam_id'])) {
            $where[] = 'r.submitter_steam_id = :submitter_steam_id';
            $params['submitter_steam_id'] = $filters['submitter_steam_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'r.report_timestamp >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'r.report_timestamp <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'r.report_timestamp DESC';
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM v_atak_tactical_reports r 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        $results = $this->db->fetchAll($sql, $params);

        foreach ($results as &$row) {
            if (!empty($row['structured_data'])) {
                $row['structured_data'] = json_decode($row['structured_data'], true);
            }
            if (!empty($row['distributed_to'])) {
                $row['distributed_to'] = json_decode($row['distributed_to'], true);
            }
        }

        return $results;
    }

    /**
     * Récupère un rapport par ID
     */
    /**
     * @param int|null $tenantId Communauté propriétaire. À `null` seulement pour un
     *   appel interne dont on sait qu'il porte déjà sur un rapport vérifié : sans
     *   ce filtre, un identifiant deviné suffit à lire le rapport d'une autre
     *   communauté.
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = "SELECT * FROM v_atak_tactical_reports WHERE id = :id AND deleted_at IS NULL";
        $params = ['id' => $id];
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }
        $report = $this->db->fetchOne($sql, $params);

        if ($report) {
            if (!empty($report['structured_data'])) {
                $report['structured_data'] = json_decode($report['structured_data'], true);
            }
            if (!empty($report['distributed_to'])) {
                $report['distributed_to'] = json_decode($report['distributed_to'], true);
            }
        }

        return $report ?: null;
    }

    /**
     * Met à jour un rapport
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'report_number', 'priority', 'classification', 'status',
            'summary', 'details', 'remarks', 'action_taken',
            'acknowledged_by_user_id', 'acknowledged_at', 'visibility'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (isset($data['structured_data'])) {
            $fields[] = "structured_data = :structured_data";
            $params['structured_data'] = json_encode($data['structured_data']);
        }

        if (isset($data['distributed_to'])) {
            $fields[] = "distributed_to = :distributed_to";
            $params['distributed_to'] = json_encode($data['distributed_to']);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE atak_tactical_reports SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Marque un rapport comme acquitté
     */
    public function acknowledge(int $id, int $tenantId, int $contextId, int $acknowledgedByUserId): bool
    {
        $sql = "UPDATE atak_tactical_reports 
                SET acknowledged_by_user_id = :user_id, 
                    acknowledged_at = NOW(),
                    status = CASE WHEN status = 'SUBMITTED' THEN 'ACKNOWLEDGED' ELSE status END
                WHERE id = :id AND tenant_id = :tenant_id AND context_id = :context_id";
        
        return $this->db->execute($sql, [
            'id' => $id,
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'user_id' => $acknowledgedByUserId
        ]) > 0;
    }

    /**
     * Supprime logiquement un rapport
     */
    public function softDelete(int $id): bool
    {
        $sql = "UPDATE atak_tactical_reports SET deleted_at = NOW() WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Compte les rapports par type pour un contexte
     */
    public function countByType(int $tenantId, int $contextId): array
    {
        $sql = "SELECT report_type, COUNT(*) as count 
                FROM atak_tactical_reports 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND deleted_at IS NULL
                GROUP BY report_type";
        
        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }

    /**
     * Récupère les rapports non acquittés
     */
    public function listUnacknowledged(int $tenantId, int $contextId, ?string $priority = null): array
    {
        $where = [
            'tenant_id = :tenant_id',
            'context_id = :context_id',
            'status = :status',
            'deleted_at IS NULL'
        ];
        $params = [
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'status' => 'SUBMITTED'
        ];

        if ($priority) {
            $where[] = 'priority = :priority';
            $params['priority'] = $priority;
        }

        $sql = "SELECT * FROM v_atak_tactical_reports 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY priority DESC, report_timestamp ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Génère le prochain numéro de rapport pour un type
     */
    public function generateReportNumber(int $tenantId, int $contextId, string $reportType): string
    {
        $sql = "SELECT COUNT(*) as count 
                FROM atak_tactical_reports 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND report_type = :report_type 
                  AND DATE(report_timestamp) = CURDATE()";
        
        $result = $this->db->fetchOne($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'report_type' => $reportType
        ]);

        $count = (int) ($result['count'] ?? 0) + 1;
        $date = date('Ymd');
        
        return sprintf('%s-%s-%03d', $reportType, $date, $count);
    }
}
