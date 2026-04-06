<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
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
        }

        return $id;
    }

    public function updateMissionStatus(int $missionId, string $status): void
    {
        $allowed = ['draft', 'pending', 'active', 'archived'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE interteam_missions SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->execute([$status, $missionId]);
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

    public function tenantIsLead(int $missionId, int $tenantId): bool
    {
        $m = $this->findById($missionId);
        if (!$m) {
            return false;
        }

        return (int) ($m['created_by_tenant_id'] ?? 0) === $tenantId;
    }

    public function allPartnersAccepted(int $missionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM interteam_mission_participants WHERE mission_id = ? AND role = \'partner\' AND status != \'active\''
        );
        $stmt->execute([$missionId]);
        $pending = (int) $stmt->fetchColumn();

        $stmt2 = $this->pdo->prepare(
            'SELECT COUNT(*) FROM interteam_mission_participants WHERE mission_id = ? AND role = \'partner\''
        );
        $stmt2->execute([$missionId]);
        $totalPartners = (int) $stmt2->fetchColumn();

        return $totalPartners > 0 && $pending === 0;
    }

    public function hasPartnerInvited(int $missionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM interteam_mission_participants WHERE mission_id = ? AND role = \'partner\' LIMIT 1'
        );
        $stmt->execute([$missionId]);

        return (bool) $stmt->fetchColumn();
    }
}
