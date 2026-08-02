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
        
        foreach ($rules as $rule) {
            // Vérifier conditions
            if ($this->matchesConditions($report, $rule)) {
                // Appliquer routage
                $recipients = $this->routeReport($reportId, $rule);
                $routedTo = array_merge($routedTo, $recipients);
            }
        }

        return [
            'report_id' => $reportId,
            'routed_to' => $routedTo,
            'rules_applied' => count($routedTo)
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
            $roles = json_decode($rule['auto_assign_to_roles'], true);
            foreach ($roles as $role) {
                $this->createRoutingEntry($reportId, $rule['id'], 'ROLE', $role, $rule);
                $recipients[] = ['type' => 'ROLE', 'identifier' => $role];
            }
        }

        // Router vers utilisateurs
        if ($rule['auto_assign_to_users']) {
            $users = json_decode($rule['auto_assign_to_users'], true);
            foreach ($users as $userId) {
                $this->createRoutingEntry($reportId, $rule['id'], 'USER', (string)$userId, $rule);
                $recipients[] = ['type' => 'USER', 'identifier' => $userId];
            }
        }

        // Router vers unités
        if ($rule['auto_assign_to_units']) {
            $units = json_decode($rule['auto_assign_to_units'], true);
            foreach ($units as $unit) {
                $this->createRoutingEntry($reportId, $rule['id'], 'UNIT', $unit, $rule);
                $recipients[] = ['type' => 'UNIT', 'identifier' => $unit];
            }
        }

        return $recipients;
    }

    /**
     * Crée une entrée de routage
     */
    private function createRoutingEntry(int $reportId, int $ruleId, string $type, string $identifier, array $rule): void
    {
        $this->db->execute(
            "INSERT INTO atak_report_routing_history 
             (report_id, routing_rule_id, routed_to_type, routed_to_identifier, notification_sent, notification_channel) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $reportId,
                $ruleId,
                $type,
                $identifier,
                $rule['send_notification'] ? 1 : 0,
                $rule['send_notification'] ? 'in-game' : null
            ]
        );
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
               AND rr.escalate_after_minutes IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, rh.routed_at, NOW()) >= rr.escalate_after_minutes",
            [$tenantId, $contextId]
        );

        $escalated = [];

        foreach ($stmt as $row) {
            // Escalader vers rôles supérieurs
            if ($row['escalate_to_roles']) {
                $roles = json_decode($row['escalate_to_roles'], true);
                foreach ($roles as $role) {
                    $this->createRoutingEntry($row['id'], $row['routing_rule_id'], 'ROLE', $role, [
                        'send_notification' => true
                    ]);
                }
                $escalated[] = $row['id'];
            }
        }

        return $escalated;
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
                ORDER BY r.priority DESC, r.report_timestamp DESC";

        return $this->db->query($sql, $params)->fetchAll();
    }

    /**
     * Marque un routage comme acquitté
     */
    /**
     * À qui un rapport a été diffusé.
     *
     * Le dépôt savait lister les rapports d'un destinataire, pas les destinataires
     * d'un rapport. Sans cette lecture, la diffusion reste invisible depuis la
     * fiche du rapport — et une diffusion qu'on ne voit pas ne se vérifie pas.
     *
     * **Cloisonnement par communauté : à la charge de l'appelant.**
     * `atak_report_routing_history` ne porte pas de `tenant_id` — filtrer dessus
     * ici échouerait. L'appelant doit donc avoir déjà vérifié que le rapport
     * appartient bien à sa communauté avant d'appeler cette méthode, ce que fait
     * la consultation d'un rapport.
     *
     * @return list<array<string, mixed>>
     */
    public function listForReport(int $reportId): array
    {
        if ($reportId < 1) {
            return [];
        }

        try {
            return $this->db->query(
                "SELECT rh.*, rr.rule_name
                   FROM atak_report_routing_history rh
                   LEFT JOIN atak_report_routing_rules rr ON rr.id = rh.routing_rule_id
                  WHERE rh.report_id = ?
                  ORDER BY rh.id ASC",
                [$reportId]
            )->fetchAll();
        } catch (\Throwable) {
            // Migration non passée : la fiche du rapport doit rester consultable.
            return [];
        }
    }

    public function acknowledgeRouting(int $reportId, string $recipientType, string $recipientIdentifier, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE atak_report_routing_history 
             SET acknowledged = TRUE, acknowledged_by_user_id = ?, acknowledged_at = NOW()
             WHERE report_id = ? AND routed_to_type = ? AND routed_to_identifier = ?",
            [$userId, $reportId, $recipientType, $recipientIdentifier]
        );
    }
}
