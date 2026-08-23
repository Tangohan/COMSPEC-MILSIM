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
        $conditions = json_decode((string) ($rule['trigger_conditions'] ?? ''), true);
        if (!is_array($conditions)) {
            $conditions = [];
        } 
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
            $content = strtolower((string) ($report['summary'] ?? '') . ' ' . (string) ($report['details'] ?? ''));
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
                // `notification_sent` doit dire ce qui s'est passé, pas ce qui
                // était prévu. Aucun envoi n'est effectué ici : marquer 1
                // ferait croire à une notification reçue et masquerait
                // l'absence de dispatcher. On enregistre l'intention dans le
                // canal, et l'envoi reste à 0 tant que rien ne part.
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
    /**
     * Marque les lignes de diffusion d'un rapport comme réellement notifiées.
     *
     * Appelé **après** création de la notification, jamais avant : le drapeau doit
     * décrire ce qui s'est passé. Le poser à l'insertion, comme c'était le cas,
     * faisait croire à une notification reçue alors qu'aucun envoi n'existait.
     */
    public function markNotified(int $reportId): bool
    {
        if ($reportId < 1) {
            return false;
        }

        try {
            return $this->db->execute(
                "UPDATE atak_report_routing_history
                    SET notification_sent = 1, notification_sent_at = NOW()
                  WHERE report_id = :id AND notification_sent = 0",
                ['id' => $reportId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Règles de diffusion d'une communauté, dans leur ordre d'application.
     *
     * @return list<array<string, mixed>>
     */
    public function listRules(int $tenantId): array
    {
        try {
            return $this->db->query(
                "SELECT * FROM atak_report_routing_rules
                  WHERE tenant_id = ?
                  ORDER BY is_active DESC, priority_order ASC, id ASC",
                [$tenantId]
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRule(int $id, int $tenantId): ?array
    {
        try {
            $row = $this->db->query(
                "SELECT * FROM atak_report_routing_rules WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();
        } catch (\Throwable) {
            return null;
        }

        return $row ?: null;
    }

    /**
     * Enregistre une règle. Les tableaux vides sont écrits `NULL` et non `[]` :
     * le moteur teste la présence de la clé, un tableau vide y serait interprété
     * comme « aucune condition » et ferait correspondre la règle à tout.
     *
     * @param array<string, mixed> $data
     */
    public function saveRule(int $tenantId, array $data, ?int $id = null): int
    {
        $json = static function (mixed $v): ?string {
            if (!is_array($v) || $v === []) {
                return null;
            }

            return json_encode(array_values($v), JSON_UNESCAPED_UNICODE);
        };

        $conditions = [];
        foreach (['report_types', 'priorities', 'keywords'] as $k) {
            if (!empty($data[$k]) && is_array($data[$k])) {
                $conditions[$k] = array_values($data[$k]);
            }
        }

        $params = [
            'tenant_id' => $tenantId,
            'rule_name' => trim((string) ($data['rule_name'] ?? '')) ?: 'Règle sans nom',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'priority_order' => max(1, min(9999, (int) ($data['priority_order'] ?? 100))),
            'trigger_conditions' => json_encode($conditions, JSON_UNESCAPED_UNICODE),
            'roles' => $json($data['auto_assign_to_roles'] ?? null),
            'units' => $json($data['auto_assign_to_units'] ?? null),
            'send_notification' => !empty($data['send_notification']) ? 1 : 0,
            'escalate' => !empty($data['escalate_after_minutes'])
                ? max(1, (int) $data['escalate_after_minutes'])
                : null,
        ];

        if ($id !== null && $id > 0) {
            $params['id'] = $id;
            $this->db->execute(
                "UPDATE atak_report_routing_rules
                    SET rule_name = :rule_name,
                        is_active = :is_active,
                        priority_order = :priority_order,
                        trigger_conditions = :trigger_conditions,
                        auto_assign_to_roles = :roles,
                        auto_assign_to_units = :units,
                        send_notification = :send_notification,
                        escalate_after_minutes = :escalate
                  WHERE id = :id AND tenant_id = :tenant_id",
                $params
            );

            return $id;
        }

        return (int) $this->db->insert(
            "INSERT INTO atak_report_routing_rules
                (tenant_id, rule_name, is_active, priority_order, trigger_conditions,
                 auto_assign_to_roles, auto_assign_to_units, send_notification, escalate_after_minutes)
             VALUES
                (:tenant_id, :rule_name, :is_active, :priority_order, :trigger_conditions,
                 :roles, :units, :send_notification, :escalate)",
            $params
        );
    }

    public function deleteRule(int $id, int $tenantId): bool
    {
        try {
            return $this->db->execute(
                "DELETE FROM atak_report_routing_rules WHERE id = :id AND tenant_id = :t",
                ['id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function toggleRule(int $id, int $tenantId, bool $active): bool
    {
        try {
            return $this->db->execute(
                "UPDATE atak_report_routing_rules SET is_active = :a WHERE id = :id AND tenant_id = :t",
                ['a' => $active ? 1 : 0, 'id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

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
