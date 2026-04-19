<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\CooperationDictionary;
use PDO;

class InterteamMissionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_missions' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM interteam_missions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        if (!$this->tableExists() || $slug === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM interteam_missions WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId <= 0) {
            return [];
        }
        $sql = 'SELECT m.* FROM interteam_missions m
            WHERE m.created_by_tenant_id = ?
            OR EXISTS (SELECT 1 FROM interteam_mission_participants p WHERE p.mission_id = m.id AND p.tenant_id = ?)
            ORDER BY m.updated_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Missions actives où le tenant est participant (pour le brief).
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForConsumerTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId <= 0) {
            return [];
        }
        $sql = 'SELECT DISTINCT m.id, m.title, m.slug, m.status, m.created_by_tenant_id
            FROM interteam_missions m
            INNER JOIN interteam_mission_participants p ON p.mission_id = m.id AND p.tenant_id = ? AND p.status = \'active\'
            WHERE m.status = \'active\'
            ORDER BY m.title ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listParticipants(int $missionId): array
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.*, t.name AS tenant_name FROM interteam_mission_participants p
             INNER JOIN tenants t ON t.id = p.tenant_id
             WHERE p.mission_id = ? ORDER BY p.role DESC, t.name ASC'
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGrantsForMission(int $missionId): array
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT g.*, tn.name AS consumer_tenant_name
             FROM interteam_mission_forum_grants g
             INNER JOIN tenants tn ON tn.id = g.consumer_tenant_id
             WHERE g.mission_id = ?
             ORDER BY g.grant_type ASC, g.resource_id ASC'
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Sujets partagés avec un tenant consommateur (mission active).
     *
     * @return list<array<string, mixed>>
     */
    public function listSharedTopicsForConsumer(int $consumerTenantId, int $limit = 50): array
    {
        if (!$this->tableExists() || $consumerTenantId <= 0) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $sql = "SELECT g.mission_id, g.resource_id AS topic_id, g.home_tenant_id, m.slug AS mission_slug, m.title AS mission_title,
                t.title AS topic_title, t.slug AS topic_slug
            FROM interteam_mission_forum_grants g
            INNER JOIN interteam_missions m ON m.id = g.mission_id AND m.status = 'active'
            INNER JOIN interteam_mission_participants p ON p.mission_id = g.mission_id AND p.tenant_id = ? AND p.status = 'active'
            INNER JOIN forum_topics t ON t.id = g.resource_id AND t.tenant_id = g.home_tenant_id
            WHERE g.grant_type = 'topic' AND g.consumer_tenant_id = ?
            ORDER BY m.title ASC, t.updated_at DESC
            LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$consumerTenantId, $consumerTenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findTopicGrantForConsumer(string $missionSlug, int $topicId, int $consumerTenantId): ?array
    {
        if (!$this->tableExists() || $missionSlug === '' || $topicId <= 0 || $consumerTenantId <= 0) {
            return null;
        }
        $sql = "SELECT g.* FROM interteam_mission_forum_grants g
            INNER JOIN interteam_missions m ON m.id = g.mission_id AND m.slug = ? AND m.status = 'active'
            INNER JOIN interteam_mission_participants p ON p.mission_id = g.mission_id AND p.tenant_id = ? AND p.status = 'active'
            WHERE g.grant_type = 'topic' AND g.resource_id = ? AND g.consumer_tenant_id = ?
            LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$missionSlug, $consumerTenantId, $topicId, $consumerTenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createMission(string $title, string $slug, int $createdByTenantId, int $createdByUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_missions (title, slug, status, created_by_tenant_id, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, \'draft\', ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$title, $slug, $createdByTenantId, $createdByUserId]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $p = $this->pdo->prepare(
                'INSERT INTO interteam_mission_participants (mission_id, tenant_id, role, status, invited_at, responded_at)
                 VALUES (?, ?, \'lead\', \'active\', NOW(), NOW())'
            );
            $p->execute([$id, $createdByTenantId]);
            if ($this->columnExists('interteam_missions', 'cooperation_phase')) {
                $u = $this->pdo->prepare(
                    'UPDATE interteam_missions SET cooperation_phase = ?, requesting_tenant_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1'
                );
                $u->execute(['draft', $createdByTenantId, $id]);
            }
        }

        return $id;
    }

    public function updateMissionStatus(int $missionId, string $status): void
    {
        $allowed = ['draft', 'pending', 'active', 'archived'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $phase = match ($status) {
            'draft' => 'draft',
            'pending' => 'proposed',
            'active' => 'active',
            'archived' => 'closed',
            default => null,
        };
        if ($phase !== null && $this->columnExists('interteam_missions', 'cooperation_phase')) {
            $stmt = $this->pdo->prepare(
                'UPDATE interteam_missions SET status = ?, cooperation_phase = ?, updated_at = NOW() WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$status, $phase, $missionId]);

            return;
        }
        $stmt = $this->pdo->prepare('UPDATE interteam_missions SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->execute([$status, $missionId]);
    }

    /**
     * @return list<string>
     */
    public function operationalStageChoices(): array
    {
        return ['opord_draft', 'command_validation', 'execution', 'closed_aar', 'corrective_actions'];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function updateOperationalStage(int $missionId, string $targetStage, array $fields = []): array
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'operational_stage')) {
            return ['ok' => false, 'error' => 'workflow_unavailable'];
        }
        $targetStage = trim($targetStage);
        if (!in_array($targetStage, $this->operationalStageChoices(), true)) {
            return ['ok' => false, 'error' => 'invalid_stage'];
        }
        $mission = $this->findById($missionId);
        if (!$mission) {
            return ['ok' => false, 'error' => 'mission_not_found'];
        }
        $currentStage = (string) ($mission['operational_stage'] ?? 'opord_draft');
        $sequence = array_flip($this->operationalStageChoices());
        $currentSeq = $sequence[$currentStage] ?? 0;
        $targetSeq = $sequence[$targetStage] ?? 0;
        if ($targetSeq < $currentSeq) {
            return ['ok' => false, 'error' => 'backward_transition_forbidden'];
        }
        if ($targetSeq > $currentSeq + 1) {
            return ['ok' => false, 'error' => 'jump_transition_forbidden'];
        }

        if ($targetStage === 'command_validation' && trim((string) ($fields['opord_text'] ?? $mission['opord_text'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'opord_required'];
        }
        if ($targetStage === 'execution' && ($mission['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'mission_must_be_active'];
        }
        if ($targetStage === 'closed_aar' && trim((string) ($fields['aar_summary'] ?? $mission['aar_summary'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'aar_required'];
        }

        $set = ['operational_stage = ?'];
        $params = [$targetStage];
        $allowedFields = [
            'opord_text',
            'command_validation_notes',
            'aar_summary',
            'corrective_actions_json',
            'linked_resources_json',
            'simulated_losses_json',
            'lessons_learned_json',
        ];
        foreach ($allowedFields as $column) {
            if (!array_key_exists($column, $fields) || !$this->columnExists('interteam_missions', $column)) {
                continue;
            }
            $set[] = "`{$column}` = ?";
            $params[] = $fields[$column];
        }

        if ($targetStage === 'command_validation' && $this->columnExists('interteam_missions', 'command_validated_at')) {
            $set[] = 'command_validated_at = NOW()';
        }
        if ($targetStage === 'execution' && $this->columnExists('interteam_missions', 'execution_started_at')) {
            $set[] = 'execution_started_at = COALESCE(execution_started_at, NOW())';
        }
        if ($targetStage === 'closed_aar' && $this->columnExists('interteam_missions', 'closed_at')) {
            $set[] = 'closed_at = COALESCE(closed_at, NOW())';
        }

        $params[] = $missionId;
        $sql = 'UPDATE interteam_missions SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? LIMIT 1';
        $this->pdo->prepare($sql)->execute($params);

        return ['ok' => true];
    }

    /**
     * Première invitation : passage en « proposition envoyée » (status pending + phase proposed si colonnes présentes).
     */
    public function markProposalSentIfDraft(int $missionId): void
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return;
        }
        if ($this->columnExists('interteam_missions', 'cooperation_phase')) {
            $stmt = $this->pdo->prepare(
                "UPDATE interteam_missions SET status = 'pending', cooperation_phase = 'proposed', updated_at = NOW()
                 WHERE id = ? AND status = 'draft' LIMIT 1"
            );
            $stmt->execute([$missionId]);

            return;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE interteam_missions SET status = 'pending', updated_at = NOW() WHERE id = ? AND status = 'draft' LIMIT 1"
        );
        $stmt->execute([$missionId]);
    }

    /**
     * @param array<string, string|null> $fields title, cooperation_typology, cooperation_priority, proposal_deadline_at (Y-m-d H:i:s ou null), suspensive_conditions_json
     */
    public function updateMissionProposalMeta(int $missionId, array $fields): void
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return;
        }
        $allowed = ['title', 'cooperation_typology', 'cooperation_priority', 'proposal_deadline_at', 'suspensive_conditions_json'];
        $set = [];
        $params = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $fields)) {
                continue;
            }
            if (!$this->columnExists('interteam_missions', $k)) {
                continue;
            }
            $set[] = "`{$k}` = ?";
            $params[] = $fields[$k];
        }
        if ($set === []) {
            return;
        }
        $params[] = $missionId;
        $sql = 'UPDATE interteam_missions SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? LIMIT 1';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function invitePartner(int $missionId, int $partnerTenantId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_participants (mission_id, tenant_id, role, status, invited_at, responded_at)
             VALUES (?, ?, \'partner\', \'invited\', NOW(), NULL)
             ON DUPLICATE KEY UPDATE status = IF(status = \'declined\', \'invited\', status), invited_at = NOW(), responded_at = NULL'
        );
        $stmt->execute([$missionId, $partnerTenantId]);
    }

    public function setParticipantStatus(int $missionId, int $tenantId, string $status): void
    {
        $allowed = ['invited', 'active', 'declined', 'left'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_mission_participants SET status = ?, responded_at = NOW() WHERE mission_id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$status, $missionId, $tenantId]);
    }

    public function addForumGrant(
        int $missionId,
        string $grantType,
        int $resourceId,
        int $homeTenantId,
        int $consumerTenantId
    ): void {
        if (!in_array($grantType, ['category', 'topic'], true)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_forum_grants (mission_id, grant_type, resource_id, home_tenant_id, consumer_tenant_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE home_tenant_id = VALUES(home_tenant_id)'
        );
        $stmt->execute([$missionId, $grantType, $resourceId, $homeTenantId, $consumerTenantId]);
    }

    public function deleteGrant(int $grantId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM interteam_mission_forum_grants WHERE id = ? LIMIT 1');
        $stmt->execute([$grantId]);
    }

    /**
     * Unité qui héberge le brief partagé (catégorie / sujets).
     */
    public function tenantIsForumHost(int $missionId, int $tenantId): bool
    {
        $m = $this->findById($missionId);
        if (!$m) {
            return false;
        }

        return (int) ($m['created_by_tenant_id'] ?? 0) === $tenantId;
    }

    /** @deprecated Utiliser {@see tenantIsForumHost} */
    public function tenantIsLead(int $missionId, int $tenantId): bool
    {
        return $this->tenantIsForumHost($missionId, $tenantId);
    }

    /**
     * Pilote de mission : porteur initial ou co-pilote désigné (invitations, activation, clôture).
     */
    public function tenantCanPilotMission(int $missionId, int $tenantId): bool
    {
        if (!$this->tableExists() || $missionId <= 0 || $tenantId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM interteam_mission_participants
             WHERE mission_id = ? AND tenant_id = ? AND status = \'active\'
             AND role IN (\'lead\', \'co_lead\') LIMIT 1'
        );
        $stmt->execute([$missionId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    public function promotePartnerToCoLead(int $missionId, int $partnerTenantId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_mission_participants SET role = \'co_lead\'
             WHERE mission_id = ? AND tenant_id = ? AND role = \'partner\' AND status = \'active\' LIMIT 1'
        );
        $stmt->execute([$missionId, $partnerTenantId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteAllGrantsForMission(int $missionId): void
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return;
        }
        $this->pdo->prepare('DELETE FROM interteam_mission_forum_grants WHERE mission_id = ?')->execute([$missionId]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function logEvent(int $missionId, int $actorUserId, int $actorTenantId, string $eventType, ?array $payload = null): void
    {
        if (!$this->eventsTableExists()) {
            return;
        }
        $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_events (mission_id, actor_user_id, actor_tenant_id, event_type, payload_json, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$missionId, $actorUserId, $actorTenantId, $eventType, $json]);
        try {
            (new CooperationNotificationOutboxRepository())->enqueueMissionEvent($missionId, $eventType, $payload);
        } catch (\Throwable) {
        }
    }

    public function eventsTableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_events' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEvents(int $missionId, int $limit = 80): array
    {
        return $this->listEventsPaginated($missionId, 1, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEventsPaginated(int $missionId, int $page = 1, int $perPage = 40): array
    {
        if (!$this->eventsTableExists() || $missionId <= 0) {
            return [];
        }
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT e.*, u.display_name AS actor_display_name
             FROM interteam_mission_events e
             LEFT JOIN users u ON u.id = e.actor_user_id
             WHERE e.mission_id = ?
             ORDER BY e.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sitrepTableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_sitreps' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSitreps(int $missionId, int $limit = 40): array
    {
        if (!$this->sitrepTableExists() || $missionId <= 0) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT s.*, u.display_name AS actor_display_name
             FROM interteam_mission_sitreps s
             LEFT JOIN users u ON u.id = s.actor_user_id
             WHERE s.mission_id = ?
             ORDER BY s.occurred_at DESC, s.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function createSitrep(int $missionId, int $actorUserId, int $actorTenantId, string $summary, ?string $occurredAt = null, ?array $payload = null): bool
    {
        if (!$this->sitrepTableExists() || $missionId <= 0 || trim($summary) === '') {
            return false;
        }
        $timestamp = trim((string) $occurredAt);
        if ($timestamp === '') {
            $timestamp = date('Y-m-d H:i:s');
        }
        $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_sitreps (mission_id, actor_user_id, actor_tenant_id, occurred_at, summary, payload_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        return $stmt->execute([$missionId, $actorUserId, $actorTenantId, $timestamp, $summary, $json]);
    }

    public function countEvents(int $missionId): int
    {
        if (!$this->eventsTableExists() || $missionId <= 0) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM interteam_mission_events WHERE mission_id = ?');
        $stmt->execute([$missionId]);

        return (int) $stmt->fetchColumn();
    }

    public function consentsTableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_cooperation_consents' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    public function findConsent(int $missionId, int $userId): ?array
    {
        if (!$this->consentsTableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM interteam_cooperation_consents WHERE mission_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$missionId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function hasVerifiedConsent(int $missionId, int $userId): bool
    {
        $row = $this->findConsent($missionId, $userId);
        if (!$row) {
            return false;
        }

        return !empty($row['otp_verified_at']);
    }

    /**
     * @param list<string> $selectionKeys
     */
    public function upsertConsentDraft(int $missionId, int $userId, int $tenantId, array $selectionKeys): void
    {
        if (!$this->consentsTableExists()) {
            return;
        }
        $json = json_encode(['keys' => array_values($selectionKeys)], JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_cooperation_consents (mission_id, user_id, tenant_id, selections_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE selections_json = VALUES(selections_json), tenant_id = VALUES(tenant_id), updated_at = NOW()'
        );
        $stmt->execute([$missionId, $userId, $tenantId, $json]);
    }

    public function markConsentOtpVerified(int $missionId, int $userId): void
    {
        if (!$this->consentsTableExists()) {
            return;
        }
        if ($this->columnExists('interteam_cooperation_consents', 'consent_expires_at')) {
            $hours = \App\Services\Cooperation\CooperationConsentDefaults::consentTtlHours();
            $stmt = $this->pdo->prepare(
                "UPDATE interteam_cooperation_consents SET otp_verified_at = NOW(), consent_expires_at = DATE_ADD(NOW(), INTERVAL {$hours} HOUR), updated_at = NOW() WHERE mission_id = ? AND user_id = ? LIMIT 1"
            );
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE interteam_cooperation_consents SET otp_verified_at = NOW(), updated_at = NOW() WHERE mission_id = ? AND user_id = ? LIMIT 1'
            );
        }
        $stmt->execute([$missionId, $userId]);
    }

    public function updateConsentJustification(int $missionId, int $userId, ?string $text): void
    {
        if (!$this->consentsTableExists() || !$this->columnExists('interteam_cooperation_consents', 'justification_sensitive')) {
            return;
        }
        $t = $text !== null && trim($text) !== '' ? mb_substr(trim($text), 0, 4000) : null;
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_cooperation_consents SET justification_sensitive = ?, updated_at = NOW() WHERE mission_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$t, $missionId, $userId]);
    }

    public function recordOtpAttempt(int $missionId, int $userId, string $outcome, ?string $ipPrefix = null): void
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_cooperation_otp_attempts' LIMIT 1");
            if (!$st || !$st->fetch()) {
                return;
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO interteam_cooperation_otp_attempts (mission_id, user_id, outcome, ip_prefix, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$missionId, $userId, $outcome, $ipPrefix]);
        } catch (\Throwable) {
        }
    }

    public function countRecentOtpFailures(int $missionId, int $userId, int $withinSeconds = 900): int
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_cooperation_otp_attempts' LIMIT 1");
            if (!$st || !$st->fetch()) {
                return 0;
            }
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM interteam_cooperation_otp_attempts WHERE mission_id = ? AND user_id = ? AND outcome = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $withinSeconds . ' SECOND)'
            );
            $stmt->execute([$missionId, $userId, 'fail']);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function setActivationSnapshotJson(int $missionId, string $json): void
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'activation_snapshot_json')) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE interteam_missions SET activation_snapshot_json = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->execute([$json, $missionId]);
    }

    /**
     * @param array<string, string|null> $fields closure_motive, closure_summary, archive_retention
     */
    public function updateClosureMeta(int $missionId, array $fields): void
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return;
        }
        $allowed = ['closure_motive', 'closure_summary', 'archive_retention'];
        $set = [];
        $params = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $fields)) {
                continue;
            }
            if (!$this->columnExists('interteam_missions', $k)) {
                continue;
            }
            $set[] = "`{$k}` = ?";
            $params[] = $fields[$k];
        }
        if ($set === []) {
            return;
        }
        $params[] = $missionId;
        $sql = 'UPDATE interteam_missions SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? LIMIT 1';
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @return array<string, int>
     */
    public function cooperationKpisForTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId <= 0) {
            return ['active' => 0, 'pending' => 0, 'draft' => 0, 'archived' => 0];
        }
        $sql = 'SELECT m.status, COUNT(*) AS c FROM interteam_missions m
            WHERE m.created_by_tenant_id = ?
            OR EXISTS (SELECT 1 FROM interteam_mission_participants p WHERE p.mission_id = m.id AND p.tenant_id = ?)
            GROUP BY m.status';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId, $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = ['active' => 0, 'pending' => 0, 'draft' => 0, 'archived' => 0];
        foreach ($rows as $r) {
            $st = (string) ($r['status'] ?? '');
            $c = (int) ($r['c'] ?? 0);
            if (isset($out[$st])) {
                $out[$st] = $c;
            }
        }

        return $out;
    }

    /**
     * @return list<array{mission_id: int, title: string, reason: string}>
     */
    public function cooperationActionsRequiredForTenant(int $tenantId, int $currentUserId): array
    {
        $missions = $this->listForTenant($tenantId);
        $out = [];
        foreach ($missions as $m) {
            $mid = (int) ($m['id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $this->recordProposalDeadlineElapsedIfNeeded($mid);
            $m = $this->findById($mid) ?? $m;
            $status = (string) ($m['status'] ?? '');
            $isPilot = $this->tenantCanPilotMission($mid, $tenantId);
            if ($status === 'pending' && ($m['counter_proposal_status'] ?? '') === 'pending_host' && $isPilot) {
                $out[] = ['mission_id' => $mid, 'title' => (string) ($m['title'] ?? ''), 'reason' => 'Contre-proposition à traiter'];
            }
            if ($status === 'pending' && $isPilot) {
                $dl = trim((string) ($m['proposal_deadline_at'] ?? ''));
                if ($dl !== '' && strtotime($dl) !== false && strtotime($dl) < time()) {
                    $out[] = ['mission_id' => $mid, 'title' => (string) ($m['title'] ?? ''), 'reason' => 'Date limite de réponse dépassée'];
                }
            }
            if ($status === 'active' && $this->consentsTableExists() && $currentUserId > 0 && !$this->hasVerifiedConsent($mid, $currentUserId)) {
                $parts = $this->listParticipants($mid);
                foreach ($parts as $p) {
                    if ((int) ($p['tenant_id'] ?? 0) === $tenantId && ($p['status'] ?? '') === 'active') {
                        $out[] = ['mission_id' => $mid, 'title' => (string) ($m['title'] ?? ''), 'reason' => 'Autorisation de partage à confirmer'];
                        break;
                    }
                }
            }
            if ($status === 'archived' && $this->rexTableExists() && $this->findRexForTenant($mid, $tenantId) === null) {
                $parts = $this->listParticipants($mid);
                foreach ($parts as $p) {
                    if ((int) ($p['tenant_id'] ?? 0) === $tenantId && ($p['status'] ?? '') === 'active') {
                        $out[] = ['mission_id' => $mid, 'title' => (string) ($m['title'] ?? ''), 'reason' => 'Retour d’expérience à compléter'];
                        break;
                    }
                }
            }
        }

        return $out;
    }

    public function missionMembersTableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_members' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMissionMembers(int $missionId): array
    {
        if (!$this->missionMembersTableExists() || $missionId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT mm.*, u.display_name AS user_display_name, t.name AS tenant_name
             FROM interteam_mission_members mm
             INNER JOIN users u ON u.id = mm.user_id
             INNER JOIN tenants t ON t.id = mm.tenant_id
             WHERE mm.mission_id = ? ORDER BY mm.assigned_at ASC'
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function assignMissionMember(int $missionId, int $userId, int $tenantId, string $roleSlug, ?int $assignedByUserId): bool
    {
        if (!$this->missionMembersTableExists() || $missionId <= 0 || $userId <= 0) {
            return false;
        }
        $roleSlug = preg_replace('/[^a-z0-9_]/', '', strtolower($roleSlug)) ?? 'observer';
        if ($roleSlug === '') {
            $roleSlug = 'observer';
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_members (mission_id, user_id, tenant_id, role_slug, assigned_by_user_id, assigned_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE role_slug = VALUES(role_slug), assigned_by_user_id = VALUES(assigned_by_user_id)'
        );

        return $stmt->execute([$missionId, $userId, $tenantId, $roleSlug, $assignedByUserId]);
    }

    /**
     * Duplique une coopération clôturée ou brouillon vers un nouveau brouillon (sans participants ni forum).
     */
    public function duplicateMissionAsDraft(int $sourceId, int $creatorTenantId, int $creatorUserId, string $newTitle, string $newSlug): int
    {
        if (!$this->tableExists() || $sourceId <= 0) {
            return 0;
        }
        $src = $this->findById($sourceId);
        if (!$src) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_missions (title, slug, status, created_by_tenant_id, created_by_user_id, cooperation_typology, cooperation_priority, created_at, updated_at)
             VALUES (?, ?, \'draft\', ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $newTitle,
            $newSlug,
            $creatorTenantId,
            $creatorUserId,
            $src['cooperation_typology'] ?? null,
            $src['cooperation_priority'] ?? 'routine',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            $p = $this->pdo->prepare(
                'INSERT INTO interteam_mission_participants (mission_id, tenant_id, role, status, invited_at, responded_at)
                 VALUES (?, ?, \'lead\', \'active\', NOW(), NOW())'
            );
            $p->execute([$id, $creatorTenantId]);
            if ($this->columnExists('interteam_missions', 'cooperation_phase')) {
                $this->pdo->prepare(
                    'UPDATE interteam_missions SET cooperation_phase = ?, requesting_tenant_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1'
                )->execute(['draft', $creatorTenantId, $id]);
            }
            if ($this->columnExists('interteam_missions', 'template_source_mission_id')) {
                $this->pdo->prepare(
                    'UPDATE interteam_missions SET template_source_mission_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1'
                )->execute([$sourceId, $id]);
            }
            $this->logEvent($id, $creatorUserId, $creatorTenantId, 'mission_created', ['duplicated_from' => $sourceId]);
        }

        return $id;
    }

    public function meetingsTableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_meetings' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMeetings(int $missionId, int $limit = 20): array
    {
        if (!$this->meetingsTableExists()) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM interteam_mission_meetings WHERE mission_id = ? ORDER BY created_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, string|null> $fields liaison_notes, atak_*, meeting_replay_url, cooperation_ends_at, competency_needs_json, atak_sync_status
     */
    public function updateMissionMeta(int $missionId, array $fields): void
    {
        if (!$this->tableExists() || $missionId <= 0) {
            return;
        }
        $allowed = [
            'liaison_notes', 'atak_endpoint_primary', 'atak_endpoint_partner', 'meeting_replay_url', 'cooperation_ends_at',
            'atak_primary_label', 'atak_partner_label', 'atak_bascule_notes', 'atak_sync_status', 'competency_needs_json',
            'exchange_lock_mode', 'cooperation_checklist_json',
        ];
        $set = [];
        $params = [];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $fields)) {
                continue;
            }
            if (!$this->columnExists('interteam_missions', $k)) {
                continue;
            }
            $set[] = "`{$k}` = ?";
            $params[] = $fields[$k];
        }
        if ($set === []) {
            return;
        }
        $params[] = $missionId;
        $sql = 'UPDATE interteam_missions SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? LIMIT 1';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function setCoopForumIds(int $missionId, ?int $categoryId, ?int $topicId): void
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'coop_forum_category_id')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_missions SET coop_forum_category_id = ?, coop_forum_topic_id = ?, cooperation_starts_at = COALESCE(cooperation_starts_at, NOW()), updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$categoryId, $topicId, $missionId]);
    }

    public function columnExists(string $table, string $column): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<int>
     */
    public function activeParticipantTenantIds(int $missionId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT tenant_id FROM interteam_mission_participants WHERE mission_id = ? AND status = \'active\''
        );
        $stmt->execute([$missionId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function allPartnersAccepted(int $missionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM interteam_mission_participants WHERE mission_id = ? AND role != \'lead\' AND status != \'active\''
        );
        $stmt->execute([$missionId]);
        $pending = (int) $stmt->fetchColumn();

        $stmt2 = $this->pdo->prepare(
            'SELECT COUNT(*) FROM interteam_mission_participants WHERE mission_id = ? AND role != \'lead\''
        );
        $stmt2->execute([$missionId]);
        $totalOthers = (int) $stmt2->fetchColumn();

        return $totalOthers > 0 && $pending === 0;
    }

    public function hasPartnerInvited(int $missionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM interteam_mission_participants WHERE mission_id = ? AND role != \'lead\' LIMIT 1'
        );
        $stmt->execute([$missionId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Partenaire / co-pilote peut proposer une contre-proposition (coopération en attente de validation).
     */
    public function partnerCanProposeCounter(int $missionId, int $tenantId): bool
    {
        if (!$this->tableExists() || $missionId <= 0 || $tenantId <= 0) {
            return false;
        }
        $m = $this->findById($missionId);
        if (!$m || ($m['status'] ?? '') !== 'pending') {
            return false;
        }
        if ($this->tenantIsForumHost($missionId, $tenantId)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT role, status FROM interteam_mission_participants WHERE mission_id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$missionId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $role = (string) ($row['role'] ?? '');
        $st = (string) ($row['status'] ?? '');

        return in_array($role, ['partner', 'co_lead'], true) && in_array($st, ['invited', 'active'], true);
    }

    public function counterProposalPending(int $missionId): bool
    {
        $m = $this->findById($missionId);
        if (!$m || !$this->columnExists('interteam_missions', 'counter_proposal_status')) {
            return false;
        }

        return ($m['counter_proposal_status'] ?? '') === 'pending_host';
    }

    /**
     * @param array<string, string> $parts calendar, support_unit, scope, sharing, coordination, conditions
     */
    public function saveCounterProposal(int $missionId, int $tenantId, int $userId, array $parts): void
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'counter_proposal_json')) {
            return;
        }
        $json = json_encode($parts, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_missions SET counter_proposal_json = ?, counter_proposal_submitted_at = NOW(),
             counter_proposal_tenant_id = ?, counter_proposal_status = \'pending_host\', updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$json, $tenantId, $missionId]);
        if ($this->columnExists('interteam_missions', 'cooperation_phase')) {
            $this->pdo->prepare(
                'UPDATE interteam_missions SET cooperation_phase = \'negotiating\', updated_at = NOW() WHERE id = ? LIMIT 1'
            )->execute([$missionId]);
        }
        $this->logEvent($missionId, $userId, $tenantId, 'counter_proposal_submitted', []);
    }

    public function integrateCounterProposal(int $missionId, int $hostTenantId, int $userId): void
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'counter_proposal_json')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_missions SET counter_proposal_json = NULL, counter_proposal_submitted_at = NULL,
             counter_proposal_tenant_id = NULL, counter_proposal_status = NULL, updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$missionId]);
        if ($this->columnExists('interteam_missions', 'cooperation_phase')) {
            $this->pdo->prepare(
                "UPDATE interteam_missions SET cooperation_phase = 'proposed', updated_at = NOW() WHERE id = ? LIMIT 1"
            )->execute([$missionId]);
        }
        $this->logEvent($missionId, $userId, $hostTenantId, 'counter_proposal_accepted', []);
    }

    public function declineCounterProposal(int $missionId, int $hostTenantId, int $userId): void
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'counter_proposal_status')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE interteam_missions SET counter_proposal_status = \'declined\', updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$missionId]);
        if ($this->columnExists('interteam_missions', 'cooperation_phase')) {
            $this->pdo->prepare(
                "UPDATE interteam_missions SET cooperation_phase = 'proposed', updated_at = NOW() WHERE id = ? LIMIT 1"
            )->execute([$missionId]);
        }
        $this->logEvent($missionId, $userId, $hostTenantId, 'counter_proposal_declined', []);
    }

    public function recordProposalDeadlineElapsedIfNeeded(int $missionId): void
    {
        if (!$this->tableExists() || !$this->columnExists('interteam_missions', 'proposal_deadline_notified_at')) {
            return;
        }
        $m = $this->findById($missionId);
        if (!$m || ($m['status'] ?? '') !== 'pending') {
            return;
        }
        $dl = trim((string) ($m['proposal_deadline_at'] ?? ''));
        if ($dl === '') {
            return;
        }
        $ts = strtotime($dl);
        if ($ts === false || $ts > time()) {
            return;
        }
        if (!empty($m['proposal_deadline_notified_at'])) {
            return;
        }
        $this->pdo->prepare(
            'UPDATE interteam_missions SET proposal_deadline_notified_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1'
        )->execute([$missionId]);
        $actorUid = (int) ($m['created_by_user_id'] ?? 0);
        $actorTid = (int) ($m['created_by_tenant_id'] ?? 0);
        if ($actorUid > 0 && $actorTid > 0) {
            $this->logEvent($missionId, $actorUid, $actorTid, 'proposal_deadline_elapsed', ['deadline' => $dl]);
        }
    }

    public function createMeeting(
        int $missionId,
        int $createdByUserId,
        ?string $title = null,
        ?string $agenda = null,
        ?string $scheduledAt = null,
        ?string $expectedParticipantsNote = null
    ): int {
        if (!$this->meetingsTableExists()) {
            return 0;
        }
        if ($this->columnExists('interteam_mission_meetings', 'meeting_title')) {
            $hasState = $this->columnExists('interteam_mission_meetings', 'meeting_state');
            $hasExp = $this->columnExists('interteam_mission_meetings', 'expected_participants_note');
            $t = $title !== null && $title !== '' ? substr($title, 0, 255) : null;
            $a = $agenda !== null && $agenda !== '' ? $agenda : null;
            $sched = $scheduledAt !== null && trim((string) $scheduledAt) !== '' ? $scheduledAt : null;
            $expNote = $expectedParticipantsNote !== null && trim($expectedParticipantsNote) !== ''
                ? (strlen($expectedParticipantsNote) > 2000 ? substr($expectedParticipantsNote, 0, 2000) : $expectedParticipantsNote)
                : null;
            $isPlannedJournal = $t !== null || $a !== null || $sched !== null || ($hasExp && $expNote !== null);
            $state = $isPlannedJournal ? 'planned' : 'open';
            $startedAt = $isPlannedJournal ? null : date('Y-m-d H:i:s');
            if ($hasState && $hasExp) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO interteam_mission_meetings (mission_id, created_by_user_id, meeting_title, meeting_agenda, scheduled_at, expected_participants_note, meeting_state, started_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$missionId, $createdByUserId, $t, $a, $sched, $expNote, $state, $startedAt]);
            } elseif ($hasState) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO interteam_mission_meetings (mission_id, created_by_user_id, meeting_title, meeting_agenda, scheduled_at, meeting_state, started_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$missionId, $createdByUserId, $t, $a, $sched, $state, $startedAt]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO interteam_mission_meetings (mission_id, created_by_user_id, meeting_title, meeting_agenda, scheduled_at, started_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$missionId, $createdByUserId, $t, $a, $sched, $startedAt ?? date('Y-m-d H:i:s')]);
            }

            return (int) $this->pdo->lastInsertId();
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_meetings (mission_id, created_by_user_id, started_at, created_at) VALUES (?, ?, NOW(), NOW())'
        );
        $stmt->execute([$missionId, $createdByUserId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function rexTableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_rex' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRexForTenant(int $missionId, int $tenantId): ?array
    {
        if (!$this->rexTableExists() || $missionId <= 0 || $tenantId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM interteam_mission_rex WHERE mission_id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$missionId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRexForMission(int $missionId): array
    {
        if (!$this->rexTableExists() || $missionId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT r.*, t.name AS tenant_name FROM interteam_mission_rex r
             INNER JOIN tenants t ON t.id = r.tenant_id
             WHERE r.mission_id = ? ORDER BY r.updated_at DESC'
        );
        $stmt->execute([$missionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function upsertRex(int $missionId, int $tenantId, int $userId, array $fields): void
    {
        if (!$this->rexTableExists()) {
            return;
        }
        $t = static function (mixed $v): ?string {
            $s = trim((string) $v);

            return $s !== '' ? substr($s, 0, 20000) : null;
        };
        $r = static function (mixed $v): ?int {
            if ($v === null || $v === '') {
                return null;
            }
            $n = (int) $v;

            return ($n >= 1 && $n <= 5) ? $n : null;
        };
        $worked = $t($fields['worked_well'] ?? '');
        $failed = $t($fields['failed_aspects'] ?? '');
        $coord = $t($fields['coordination_incidents'] ?? '');
        $share = $t($fields['sharing_difficulties'] ?? '');
        $tech = $t($fields['technical_difficulties'] ?? '');
        $reco = $t($fields['recommendations'] ?? '');
        $rf = $r($fields['rating_fluidity'] ?? null);
        $rs = $r($fields['rating_security'] ?? null);
        $ru = $r($fields['rating_usefulness'] ?? null);
        $rr = $r($fields['rating_reactivity'] ?? null);
        $stmt = $this->pdo->prepare(
            'INSERT INTO interteam_mission_rex (mission_id, tenant_id, user_id, worked_well, failed_aspects, coordination_incidents, sharing_difficulties, technical_difficulties, recommendations, rating_fluidity, rating_security, rating_usefulness, rating_reactivity, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE worked_well = VALUES(worked_well), failed_aspects = VALUES(failed_aspects),
             coordination_incidents = VALUES(coordination_incidents), sharing_difficulties = VALUES(sharing_difficulties),
             technical_difficulties = VALUES(technical_difficulties), recommendations = VALUES(recommendations),
             rating_fluidity = VALUES(rating_fluidity), rating_security = VALUES(rating_security),
             rating_usefulness = VALUES(rating_usefulness), rating_reactivity = VALUES(rating_reactivity),
             user_id = VALUES(user_id), updated_at = NOW()'
        );
        $stmt->execute([
            $missionId, $tenantId, $userId, $worked, $failed, $coord, $share, $tech, $reco, $rf, $rs, $ru, $rr,
        ]);
    }
}
