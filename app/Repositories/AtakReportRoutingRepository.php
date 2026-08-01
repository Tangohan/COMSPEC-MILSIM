<?php

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour le routage automatique et la distribution intelligente des rapports tactiques
 */
class AtakReportRoutingRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Évalue et applique les règles de routage pour un rapport
     */
    public function applyRoutingRules(int $reportId, int $tenantId, int $contextId): array
    {
        // Charger le rapport
        $report = $this->db->query(
            "SELECT * FROM atak_tactical_reports WHERE id = ? AND tenant_id = ? AND context_id = ?",
            [$reportId, $tenantId, $contextId]
        )->fetch();

        if (!$report) {
            return ['error' => 'Report not found'];
        }

        // Charger règles actives triées par priorité
        $rules = $this->db->query(
            "SELECT * FROM atak_report_routing_rules 
             WHERE tenant_id = ? AND is_active = TRUE 
             ORDER BY priority_order ASC",
            [$tenantId]
        )->fetchAll();

        $routedTo = [];
        $matchedRules = 0;
        
        foreach ($rules as $rule) {
            // Vérifier conditions
            if ($this->matchesConditions($report, $rule)) {
                $matchedRules++;
                // Appliquer routage
                $recipients = $this->routeReport($reportId, $rule);
                $routedTo = array_merge($routedTo, $recipients);
            }
        }

        return [
            'report_id' => $reportId,
            'routed_to' => $routedTo,
            'rules_applied' => $matchedRules,
            'routes_created' => count($routedTo),
        ];
    }

    /**
     * Vérifie si un rapport correspond aux conditions d'une règle
     */
    private function matchesConditions(array $report, array $rule): bool
    {
        $conditions = json_decode($rule['trigger_conditions'], true);
        
        // Type rapport
        if (isset($conditions['report_types']) && !empty($conditions['report_types'])) {
            if (!in_array($report['report_type'], $conditions['report_types'])) {
                return false;
            }
        }

        // Priorité
        if (isset($conditions['priorities']) && !empty($conditions['priorities'])) {
            if (!in_array($report['priority'], $conditions['priorities'])) {
                return false;
            }
        }

        // Mots-clés dans contenu
        if (isset($conditions['keywords']) && !empty($conditions['keywords'])) {
            $content = strtolower($report['summary'] . ' ' . $report['details']);
            $hasKeyword = false;
            foreach ($conditions['keywords'] as $keyword) {
                if (str_contains($content, strtolower($keyword))) {
                    $hasKeyword = true;
                    break;
                }
            }
            if (!$hasKeyword) {
                return false;
            }
        }

        // Zone géographique
        if (isset($conditions['within_zone_ids']) && !empty($conditions['within_zone_ids'])) {
            if ($report['pos_x'] && $report['pos_y']) {
                $isInZone = false;
                foreach ($conditions['within_zone_ids'] as $zoneId) {
                    // Vérifier si position dans zone (utiliser repository zones)
                    $zoneRepo = new AtakTacticalZoneRepository($this->db);
                    if ($zoneRepo->isPositionInZone($zoneId, $report['pos_x'], $report['pos_y'])) {
                        $isInZone = true;
                        break;
                    }
                }
                if (!$isInZone) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Route un rapport selon une règle
     */
    private function routeReport(int $reportId, array $rule): array
    {
        $recipients = [];

        // Router vers rôles
        if ($rule['auto_assign_to_roles']) {
            $roles = $this->jsonList($rule['auto_assign_to_roles']);
            foreach ($roles as $role) {
                if ($this->createRoutingEntry($reportId, (int) $rule['id'], 'ROLE', (string) $role, $rule)) {
                    $recipients[] = ['type' => 'ROLE', 'identifier' => $role];
                }
            }
        }

        // Router vers utilisateurs
        if ($rule['auto_assign_to_users']) {
            $users = $this->jsonList($rule['auto_assign_to_users']);
            foreach ($users as $userId) {
                if ($this->createRoutingEntry($reportId, (int) $rule['id'], 'USER', (string) $userId, $rule)) {
                    $recipients[] = ['type' => 'USER', 'identifier' => $userId];
                }
            }
        }

        // Router vers unités
        if ($rule['auto_assign_to_units']) {
            $units = $this->jsonList($rule['auto_assign_to_units']);
            foreach ($units as $unit) {
                if ($this->createRoutingEntry($reportId, (int) $rule['id'], 'UNIT', (string) $unit, $rule)) {
                    $recipients[] = ['type' => 'UNIT', 'identifier' => $unit];
                }
            }
        }

        return $recipients;
    }

    /** @return list<mixed> */
    private function jsonList(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    /**
     * Crée une entrée de routage
     */
    private function createRoutingEntry(int $reportId, int $ruleId, string $type, string $identifier, array $rule): bool
    {
        $channels = json_decode((string) ($rule['notification_channels'] ?? ''), true);
        $channel = is_array($channels) && isset($channels[0]) ? (string) $channels[0] : 'in-game';

        return $this->db->execute(
            "INSERT IGNORE INTO atak_report_routing_history
             (report_id, routing_rule_id, routed_to_type, routed_to_identifier, notification_sent, notification_channel) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $reportId,
                $ruleId,
                $type,
                $identifier,
                0,
                !empty($rule['send_notification']) ? $channel : null
            ]
        ) > 0;
    }

    /**
     * Vérifie et traite les escalades
     */
    public function processEscalations(int $tenantId, int $contextId): array
    {
        // Trouver rapports non acquittés avec règles escalade
        $stmt = $this->db->query(
            "SELECT r.*, rh.routing_rule_id, rr.escalate_after_minutes, rr.escalate_to_roles
             FROM atak_tactical_reports r
             JOIN atak_report_routing_history rh ON r.id = rh.report_id
             JOIN atak_report_routing_rules rr ON rh.routing_rule_id = rr.id
             WHERE r.tenant_id = ? AND r.context_id = ?
               AND r.status = 'SUBMITTED'
               AND rh.acknowledged = FALSE
               AND rr.is_active = TRUE
               AND rr.escalate_after_minutes IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, rh.routed_at, NOW()) >= rr.escalate_after_minutes",
            [$tenantId, $contextId]
        );

        $escalated = [];

        foreach ($stmt as $row) {
            // Escalader vers rôles supérieurs
            if ($row['escalate_to_roles']) {
                $roles = $this->jsonList($row['escalate_to_roles']);
                foreach ($roles as $role) {
                    $created = $this->createRoutingEntry((int) $row['id'], (int) $row['routing_rule_id'], 'ROLE', (string) $role, [
                        'send_notification' => true,
                        'notification_channels' => '["in-game"]',
                    ]);
                    if (!$created) {
                        continue;
                    }
                    $escalated[] = (int) $row['id'];
                }
            }
        }

        return array_values(array_unique($escalated));
    }

    /**
     * Liste les rapports routés pour un destinataire
     */
    public function listForRecipient(int $tenantId, int $contextId, string $recipientType, string $recipientIdentifier, array $filters = []): array
    {
        $conditions = [
            "r.tenant_id = ?",
            "r.context_id = ?",
            "r.deleted_at IS NULL",
            "rh.routed_to_type = ?",
            "rh.routed_to_identifier = ?"
        ];
        $params = [$tenantId, $contextId, $recipientType, $recipientIdentifier];

        if (isset($filters['unacknowledged_only']) && $filters['unacknowledged_only']) {
            $conditions[] = "rh.acknowledged = FALSE";
        }

        if (isset($filters['priority'])) {
            $conditions[] = "r.priority = ?";
            $params[] = $filters['priority'];
        }

        $sql = "SELECT r.*, rh.routed_at, rh.acknowledged, rh.acknowledged_at
                FROM atak_tactical_reports r
                JOIN atak_report_routing_history rh ON r.id = rh.report_id
                WHERE " . implode(" AND ", $conditions) . "
                ORDER BY FIELD(r.priority, 'FLASH', 'IMMEDIATE', 'PRIORITY', 'ROUTINE'), r.report_timestamp DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * @param list<array{type:string, identifier:string}> $recipients
     */
    public function listForRecipients(int $tenantId, int $contextId, array $recipients, array $filters = []): array
    {
        if ($recipients === []) {
            return [];
        }

        $recipientWhere = [];
        $params = [$tenantId, $contextId];
        foreach ($recipients as $recipient) {
            $recipientWhere[] = '(rh.routed_to_type = ? AND rh.routed_to_identifier = ?)';
            $params[] = $recipient['type'];
            $params[] = $recipient['identifier'];
        }

        $conditions = [
            'r.tenant_id = ?',
            'r.context_id = ?',
            'r.deleted_at IS NULL',
            '(' . implode(' OR ', $recipientWhere) . ')',
        ];
        if (!empty($filters['unacknowledged_only'])) {
            $conditions[] = 'rh.acknowledged = FALSE';
        }
        if (!empty($filters['priority'])) {
            $conditions[] = 'r.priority = ?';
            $params[] = (string) $filters['priority'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 100)));

        $sql = "SELECT r.*, rh.id AS routing_id, rh.routed_to_type, rh.routed_to_identifier,
                       rh.routed_at, rh.acknowledged, rh.acknowledged_at
                FROM atak_tactical_reports r
                JOIN atak_report_routing_history rh ON r.id = rh.report_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY FIELD(r.priority, 'FLASH', 'IMMEDIATE', 'PRIORITY', 'ROUTINE'), r.report_timestamp DESC
                LIMIT {$limit}";

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Acquitte une distribution précise, uniquement si elle appartient à une identité autorisée.
     *
     * @param list<array{type:string, identifier:string}> $recipients
     */
    public function acknowledgeRoutingForRecipients(
        int $routingId,
        int $reportId,
        int $tenantId,
        int $contextId,
        array $recipients,
        int $userId
    ): bool
    {
        if ($recipients === []) {
            return false;
        }

        $recipientWhere = [];
        $params = [$userId, $routingId, $reportId, $tenantId, $contextId];
        foreach ($recipients as $recipient) {
            $recipientWhere[] = '(rh.routed_to_type = ? AND rh.routed_to_identifier = ?)';
            $params[] = $recipient['type'];
            $params[] = $recipient['identifier'];
        }

        return $this->db->execute(
            "UPDATE atak_report_routing_history rh
             JOIN atak_tactical_reports r ON r.id = rh.report_id
             SET rh.acknowledged = TRUE, rh.acknowledged_by_user_id = ?, rh.acknowledged_at = NOW()
             WHERE rh.id = ? AND rh.report_id = ? AND r.tenant_id = ? AND r.context_id = ?
               AND rh.acknowledged = FALSE
               AND (" . implode(' OR ', $recipientWhere) . ')',
            $params
        ) > 0;
    }

    /** @return list<array{tenant_id:int|string, context_id:int|string}> */
    public function listEscalationContexts(): array
    {
        return $this->db->query(
            "SELECT DISTINCT r.tenant_id, r.context_id
             FROM atak_tactical_reports r
             JOIN atak_report_routing_history rh ON rh.report_id = r.id
             JOIN atak_report_routing_rules rr ON rr.id = rh.routing_rule_id
             WHERE r.deleted_at IS NULL AND r.status = 'SUBMITTED'
               AND rh.acknowledged = FALSE AND rr.is_active = TRUE
               AND rr.escalate_after_minutes IS NOT NULL"
        )->fetchAll();
    }

    /**
     * Marque un routage comme acquitté
     */
    public function acknowledgeRouting(
        int $reportId,
        int $tenantId,
        int $contextId,
        string $recipientType,
        string $recipientIdentifier,
        int $userId
    ): bool
    {
        return $this->db->execute(
            "UPDATE atak_report_routing_history rh
             JOIN atak_tactical_reports r ON r.id = rh.report_id
             SET rh.acknowledged = TRUE, rh.acknowledged_by_user_id = ?, rh.acknowledged_at = NOW()
             WHERE rh.report_id = ? AND r.tenant_id = ? AND r.context_id = ?
               AND rh.routed_to_type = ? AND rh.routed_to_identifier = ?",
            [$userId, $reportId, $tenantId, $contextId, $recipientType, $recipientIdentifier]
        ) > 0;
    }
}
