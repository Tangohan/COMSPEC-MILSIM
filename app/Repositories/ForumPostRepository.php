<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumPostRepository
{
    private PDO $pdo;

    /** @var array{select: string, join: string}|null */
    private ?array $gradesConfig = null;

    private ?bool $hasDisplaySettingsTable = null;

    private ?bool $hasPreferredDisplayRoleColumn = null;

    private ?bool $hasBodyFormatColumnCache = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasDisplaySettingsTable(): bool
    {
        if ($this->hasDisplaySettingsTable !== null) {
            return $this->hasDisplaySettingsTable;
        }
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profile_display_settings' LIMIT 1");
        $this->hasDisplaySettingsTable = (bool) ($stmt && $stmt->fetchColumn());

        return $this->hasDisplaySettingsTable;
    }

    private function hasPreferredDisplayRoleColumn(): bool
    {
        if ($this->hasPreferredDisplayRoleColumn !== null) {
            return $this->hasPreferredDisplayRoleColumn;
        }
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_display_role_id' LIMIT 1");
            $this->hasPreferredDisplayRoleColumn = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            $this->hasPreferredDisplayRoleColumn = false;
        }

        return $this->hasPreferredDisplayRoleColumn;
    }

    private function hasBodyFormatColumn(): bool
    {
        if ($this->hasBodyFormatColumnCache !== null) {
            return $this->hasBodyFormatColumnCache;
        }
        try {
            $chk = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND COLUMN_NAME = 'body_format' LIMIT 1");
            $this->hasBodyFormatColumnCache = (bool) ($chk && $chk->fetchColumn());
        } catch (\Throwable) {
            $this->hasBodyFormatColumnCache = false;
        }

        return $this->hasBodyFormatColumnCache;
    }

    /**
     * Détecte la structure de la table grades (colonnes name vs label_long, présence de tenant_id)
     * et retourne le fragment SELECT et la condition de JOIN pour les grades.
     */
    private function getGradesConfig(): array
    {
        if ($this->gradesConfig !== null) {
            return $this->gradesConfig;
        }
        $stmt = $this->pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME IN ('name', 'label_long', 'tenant_id')"
        );
        $columns = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME') : [];
        $hasLabelLong = in_array('label_long', $columns, true);
        $hasTenantId = in_array('tenant_id', $columns, true);
        $select = $hasLabelLong
            ? 'g.label_long AS author_grade_name, g.label_short AS author_grade_short, g.label_otan AS author_grade_nato'
            : 'g.name AS author_grade_name, g.short_name AS author_grade_short, g.nato_code AS author_grade_nato';
        $join = $hasTenantId
            ? 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id'
            : 'LEFT JOIN grades g ON g.id = u.grade_id';
        $this->gradesConfig = ['select' => $select, 'join' => $join];
        return $this->gradesConfig;
    }

    /**
     * Tous les messages d'un membre pour un tenant, avec le titre du sujet — pour l'export
     * de données personnelles (RGPD).
     *
     * @return list<array<string, mixed>>
     */
    public function listByUserId(int $userId, int $tenantId, int $limit = 2000): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fp.id, fp.topic_id, ft.title AS topic_title, fp.body, fp.created_at
             FROM forum_posts fp
             INNER JOIN forum_topics ft ON ft.id = fp.topic_id AND ft.tenant_id = fp.tenant_id
             WHERE fp.tenant_id = ? AND fp.user_id = ?
             ORDER BY fp.created_at DESC
             LIMIT ' . max(1, min(5000, $limit))
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listByTopic(int $topicId): array
    {
        return $this->listByTopicPaginated($topicId, 1, 9999, true);
    }

    /**
     * Liste des messages d'un sujet avec pagination.
     * Si $includeHiddenForModo est false, les messages is_hidden sont exclus.
     * Compatible table grades (name/short_name/nato_code) ou référentiel (label_long/label_short/label_otan).
     */
    public function listByTopicPaginated(int $topicId, int $page = 1, int $perPage = 20, bool $includeHiddenForModo = false): array
    {
        $offset = ($page - 1) * $perPage;
        $hiddenCond = $includeHiddenForModo ? '1' : 'COALESCE(fp.is_hidden, 0) = 0';
        $grades = $this->getGradesConfig();
        $gradeCols = $grades['select'];
        $gradeJoin = $grades['join'];
        $identityCols = $this->hasDisplaySettingsTable()
            ? 'u.id AS author_user_id,
                    u.email AS author_email,
                    up.first_name AS author_first_name,
                    up.last_name AS author_last_name,
                    pp.character_name AS author_character_name,
                    ups.forum_alias AS author_forum_alias,
                    ups.forum_label_mode AS author_forum_label_mode,
                    COALESCE(ups.show_matricule_forum, 1) AS author_show_matricule_forum,
                    COALESCE(ups.show_grade_forum, 1) AS author_show_grade_forum,
                    COALESCE(ups.show_unit_forum, 1) AS author_show_unit_forum,
                    COALESCE(ups.show_bio_forum, 1) AS author_show_bio_forum,
                    COALESCE(ups.hide_forum_level, 0) AS author_hide_forum_level,'
            : 'u.id AS author_user_id,
                    u.email AS author_email,
                    up.first_name AS author_first_name,
                    up.last_name AS author_last_name,
                    pp.character_name AS author_character_name,
                    NULL AS author_forum_alias,
                    NULL AS author_forum_label_mode,
                    1 AS author_show_matricule_forum,
                    1 AS author_show_grade_forum,
                    1 AS author_show_unit_forum,
                    1 AS author_show_bio_forum,
                    0 AS author_hide_forum_level,';
        $upsJoin = $this->hasDisplaySettingsTable()
            ? 'LEFT JOIN user_profile_display_settings ups ON ups.user_id = u.id'
            : '';
        $roleJoinSql = 'LEFT JOIN roles r ON r.id = u.role_id';
        if ($this->hasDisplaySettingsTable()) {
            if ($this->hasPreferredDisplayRoleColumn()) {
                $roleJoinSql = 'LEFT JOIN roles r ON r.id = COALESCE(NULLIF(u.preferred_display_role_id, 0), NULLIF(ups.forum_visible_role_id, 0), u.role_id)';
            } else {
                $roleJoinSql = 'LEFT JOIN roles r ON r.id = COALESCE(NULLIF(ups.forum_visible_role_id, 0), u.role_id)';
            }
        }
        $fullSql = "SELECT fp.*,
                    u.display_name AS author_name, u.callsign AS author_callsign, u.role_id AS author_role_id, u.avatar_url AS author_avatar_url, u.created_at AS author_created_at,
                    $identityCols
                    r.name AS author_role_name, r.slug AS author_role_slug, r.role_layer AS author_role_layer,
                    up.bio AS author_bio,
                    $gradeCols,
                    pp.matricule_internal AS author_matricule,
                    TRIM(CONCAT(COALESCE(pjr.name, ''), IF(pjrole.role_detail IS NOT NULL AND pjrole.role_detail <> '', CONCAT(' — ', pjrole.role_detail), ''))) AS author_primary_role,
                    un.name AS author_unit_name, un.code AS author_unit_code,
                    (SELECT GROUP_CONCAT(psh.title ORDER BY psh.event_date DESC, psh.id DESC SEPARATOR ' · ')
                     FROM personnel_service_history psh
                     WHERE psh.user_id = u.id AND psh.event_type = 'award') AS author_awards,
                    (SELECT COUNT(*) FROM forum_posts fpc WHERE fpc.user_id = u.id) AS author_post_count
             FROM forum_posts fp
             LEFT JOIN users u ON u.id = fp.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             $gradeJoin
             LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
             LEFT JOIN personnel_profile_job_roles pjrole ON pjrole.id = (
                 SELECT pj2.id FROM personnel_profile_job_roles pj2
                 WHERE pj2.user_id = u.id AND pj2.tenant_id = u.tenant_id
                 ORDER BY pj2.is_primary DESC, pj2.sort_order ASC, pj2.id ASC
                 LIMIT 1
             )
             LEFT JOIN personnel_job_roles pjr ON pjr.id = pjrole.personnel_job_role_id AND pjr.tenant_id = u.tenant_id
             LEFT JOIN units un ON un.id = pp.primary_unit_id
             $upsJoin
             $roleJoinSql
             WHERE fp.topic_id = ? AND ($hiddenCond)
             ORDER BY fp.created_at ASC
             LIMIT $perPage OFFSET $offset";
        $fallbackSql = "SELECT fp.*,
                    u.display_name AS author_name, u.callsign AS author_callsign, u.role_id AS author_role_id, u.avatar_url AS author_avatar_url, u.created_at AS author_created_at,
                    u.id AS author_user_id,
                    u.email AS author_email,
                    up.first_name AS author_first_name,
                    up.last_name AS author_last_name,
                    NULL AS author_character_name,
                    NULL AS author_forum_alias,
                    NULL AS author_forum_label_mode,
                    1 AS author_show_matricule_forum,
                    1 AS author_show_grade_forum,
                    1 AS author_show_unit_forum,
                    1 AS author_show_bio_forum,
                    0 AS author_hide_forum_level,
                    r.name AS author_role_name, r.slug AS author_role_slug, r.role_layer AS author_role_layer,
                    up.bio AS author_bio,
                    $gradeCols,
                    NULL AS author_matricule, NULL AS author_primary_role,
                    NULL AS author_unit_name, NULL AS author_unit_code,
                    (SELECT GROUP_CONCAT(psh.title ORDER BY psh.event_date DESC, psh.id DESC SEPARATOR ' · ')
                     FROM personnel_service_history psh
                     WHERE psh.user_id = u.id AND psh.event_type = 'award') AS author_awards,
                    (SELECT COUNT(*) FROM forum_posts fpc WHERE fpc.user_id = u.id) AS author_post_count
             FROM forum_posts fp
             LEFT JOIN users u ON u.id = fp.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             $gradeJoin
             WHERE fp.topic_id = ? AND ($hiddenCond)
             ORDER BY fp.created_at ASC
             LIMIT $perPage OFFSET $offset";

        try {
            $stmt = $this->pdo->prepare($fullSql);
            $stmt->execute([$topicId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            if ((string) $e->getCode() === '42S22' || strpos($msg, 'Unknown column') !== false || strpos($msg, "doesn't exist") !== false) {
                $stmt = $this->pdo->prepare($fallbackSql);
                $stmt->execute([$topicId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            throw $e;
        }
    }

    public function countByTopic(int $topicId, bool $includeHiddenForModo = false): int
    {
        $hiddenCond = $includeHiddenForModo ? '1' : 'COALESCE(is_hidden, 0) = 0';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE topic_id = ? AND ($hiddenCond)");
        $stmt->execute([$topicId]);
        return (int) $stmt->fetchColumn();
    }

    public function setHidden(int $id, int $tenantId, bool $hidden): bool
    {
        $stmt = $this->pdo->prepare('UPDATE forum_posts SET is_hidden = ? WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$hidden ? 1 : 0, $id, $tenantId]);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM forum_posts WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(
        int $tenantId,
        int $topicId,
        int $userId,
        string $body,
        ?int $parentPostId = null,
        ?int $coopSourceTenantId = null,
        ?string $coopOfficialKind = null,
        bool $isDraft = false,
        ?string $coopMissionRole = null,
        string $bodyFormat = 'markdown'
    ): int {
        $hasCoop = false;
        $chkCoop = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND COLUMN_NAME = 'coop_source_tenant_id' LIMIT 1");
        if ($chkCoop && $chkCoop->fetchColumn()) {
            $hasCoop = true;
        }
        $hasKind = false;
        $chkK = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND COLUMN_NAME = 'coop_official_kind' LIMIT 1");
        if ($chkK && $chkK->fetchColumn()) {
            $hasKind = true;
        }
        $chk = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_posts' AND COLUMN_NAME = 'parent_post_id' LIMIT 1");
        if ($chk && $chk->fetchColumn()) {
            if ($hasKind && $hasCoop && $coopSourceTenantId !== null && $coopSourceTenantId > 0) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, parent_post_id, user_id, body, coop_source_tenant_id, coop_official_kind, is_draft, coop_mission_role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([
                    $tenantId, $topicId, $parentPostId, $userId, $body, $coopSourceTenantId,
                    $coopOfficialKind, $isDraft ? 1 : 0, $coopMissionRole,
                ]);
            } elseif ($hasKind) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, parent_post_id, user_id, body, coop_official_kind, is_draft, coop_mission_role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$tenantId, $topicId, $parentPostId, $userId, $body, $coopOfficialKind, $isDraft ? 1 : 0, $coopMissionRole]);
            } elseif ($hasCoop && $coopSourceTenantId !== null && $coopSourceTenantId > 0) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, parent_post_id, user_id, body, coop_source_tenant_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$tenantId, $topicId, $parentPostId, $userId, $body, $coopSourceTenantId]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, parent_post_id, user_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$tenantId, $topicId, $parentPostId, $userId, $body]);
            }
        } else {
            if ($hasKind && $hasCoop && $coopSourceTenantId !== null && $coopSourceTenantId > 0) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, user_id, body, coop_source_tenant_id, coop_official_kind, is_draft, coop_mission_role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([
                    $tenantId, $topicId, $userId, $body, $coopSourceTenantId,
                    $coopOfficialKind, $isDraft ? 1 : 0, $coopMissionRole,
                ]);
            } elseif ($hasKind) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, user_id, body, coop_official_kind, is_draft, coop_mission_role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$tenantId, $topicId, $userId, $body, $coopOfficialKind, $isDraft ? 1 : 0, $coopMissionRole]);
            } elseif ($hasCoop && $coopSourceTenantId !== null && $coopSourceTenantId > 0) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, user_id, body, coop_source_tenant_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$tenantId, $topicId, $userId, $body, $coopSourceTenantId]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO forum_posts (tenant_id, topic_id, user_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$tenantId, $topicId, $userId, $body]);
            }
        }

        $newId = (int) $this->pdo->lastInsertId();
        if ($newId > 0 && $this->hasBodyFormatColumn() && strtolower($bodyFormat) === 'html') {
            try {
                $this->pdo->prepare('UPDATE forum_posts SET body_format = ? WHERE id = ? AND tenant_id = ?')->execute(['html', $newId, $tenantId]);
            } catch (\Throwable) {
            }
        }

        return $newId;
    }

    public function update(int $id, int $tenantId, string $body): bool
    {
        $stmt = $this->pdo->prepare('UPDATE forum_posts SET body = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$body, $id, $tenantId]);
    }

    public function updatePublicationBadge(int $id, int $tenantId, ?string $badge): bool
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE forum_posts SET publication_badge = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
            return $stmt->execute([$badge, $id, $tenantId]);
        } catch (\PDOException) {
            return false;
        }
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM forum_posts WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$id, $tenantId]);
    }

    public function getFirstPostOfTopic(int $topicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM forum_posts WHERE topic_id = ? ORDER BY created_at ASC LIMIT 1');
        $stmt->execute([$topicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getTotalPostCount(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_posts WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function getPostsThisWeekCount(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_posts WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function getActiveMembersCount24h(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM forum_posts WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function getTopContributors(int $tenantId, int $limit = 10): array
    {
        if ($this->hasDisplaySettingsTable()) {
            $sql = 'SELECT u.id, u.email, u.display_name, u.callsign,
                    up.first_name AS author_first_name, up.last_name AS author_last_name,
                    pp.character_name AS author_character_name,
                    ups.forum_alias AS author_forum_alias, ups.forum_label_mode AS author_forum_label_mode,
                    COUNT(fp.id) AS post_count
             FROM forum_posts fp
             JOIN users u ON u.id = fp.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
             LEFT JOIN user_profile_display_settings ups ON ups.user_id = u.id
             WHERE fp.tenant_id = ?
             GROUP BY u.id, u.email, u.display_name, u.callsign, up.first_name, up.last_name, pp.character_name, ups.forum_alias, ups.forum_label_mode
             ORDER BY post_count DESC
             LIMIT ?';
        } else {
            $sql = 'SELECT u.id, u.email, u.display_name, u.callsign,
                    up.first_name AS author_first_name, up.last_name AS author_last_name,
                    pp.character_name AS author_character_name,
                    NULL AS author_forum_alias, NULL AS author_forum_label_mode,
                    COUNT(fp.id) AS post_count
             FROM forum_posts fp
             JOIN users u ON u.id = fp.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
             WHERE fp.tenant_id = ?
             GROUP BY u.id, u.email, u.display_name, u.callsign, up.first_name, up.last_name, pp.character_name
             ORDER BY post_count DESC
             LIMIT ?';
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId, $limit]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $stmt = $this->pdo->prepare(
                'SELECT u.id, u.email, u.display_name, u.callsign, COUNT(fp.id) AS post_count
                 FROM forum_posts fp
                 JOIN users u ON u.id = fp.user_id
                 WHERE fp.tenant_id = ?
                 GROUP BY u.id, u.email, u.display_name, u.callsign
                 ORDER BY post_count DESC
                 LIMIT ?'
            );
            $stmt->execute([$tenantId, $limit]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Dernière date de message du membre dans le tenant (pause entre envois).
     */
    public function latestPostCreatedAtForUser(int $tenantId, int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(created_at) FROM forum_posts WHERE tenant_id = ? AND user_id = ?'
        );
        $stmt->execute([$tenantId, $userId]);
        $v = $stmt->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $s = (string) $v;

        return $s !== '' ? $s : null;
    }

    /**
     * Contexte sujet pour plusieurs messages (file modération contenus).
     *
     * @param list<int> $postIds
     * @return array<int, array{post_id: int, topic_id: int, topic_title: string}> indexé par post_id
     */
    public function findTopicBriefsForPosts(array $postIds, int $tenantId): array
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds), static fn (int $v): bool => $v > 0)));
        if ($postIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $sql = 'SELECT fp.id AS post_id, fp.topic_id AS topic_id, ft.title AS topic_title
                FROM forum_posts fp
                INNER JOIN forum_topics ft ON ft.id = fp.topic_id AND ft.tenant_id = fp.tenant_id
                WHERE fp.tenant_id = ? AND fp.id IN (' . $placeholders . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $postIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $pid = (int) ($row['post_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $out[$pid] = [
                'post_id' => $pid,
                'topic_id' => (int) ($row['topic_id'] ?? 0),
                'topic_title' => (string) ($row['topic_title'] ?? ''),
            ];
        }

        return $out;
    }
}
