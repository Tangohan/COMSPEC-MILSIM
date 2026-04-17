<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumReportRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(int $tenantId, int $reporterId, ?int $postId, ?int $topicId, string $reason, string $reportType = 'other', ?string $comment = null, ?string $reportedUrl = null, ?string $contentKind = null): int
    {
        $hasReportType = $this->columnExists('forum_reports', 'report_type');
        $hasReportedUrl = $this->columnExists('forum_reports', 'reported_url');
        $hasContentKind = $this->columnExists('forum_reports', 'content_kind');

        if ($hasReportType && $hasReportedUrl && $hasContentKind) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forum_reports (tenant_id, reporter_id, post_id, topic_id, reason, report_type, comment, reported_url, content_kind, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
            );
            $stmt->execute([
                $tenantId,
                $reporterId,
                $postId,
                $topicId,
                $reason,
                $reportType,
                $comment,
                $reportedUrl !== null && $reportedUrl !== '' ? $reportedUrl : null,
                $contentKind !== null && $contentKind !== '' ? $contentKind : null,
            ]);
        } elseif ($hasReportType && $hasReportedUrl) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forum_reports (tenant_id, reporter_id, post_id, topic_id, reason, report_type, comment, reported_url, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
            );
            $stmt->execute([$tenantId, $reporterId, $postId, $topicId, $reason, $reportType, $comment, $reportedUrl !== null && $reportedUrl !== '' ? $reportedUrl : null]);
        } elseif ($hasReportType) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forum_reports (tenant_id, reporter_id, post_id, topic_id, reason, report_type, comment, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())'
            );
            $stmt->execute([$tenantId, $reporterId, $postId, $topicId, $reason, $reportType, $comment]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forum_reports (tenant_id, reporter_id, post_id, topic_id, reason, status, created_at) VALUES (?, ?, ?, ?, ?, \'pending\', NOW())'
            );
            $reasonOut = $reason;
            if ($reportedUrl !== null && $reportedUrl !== '' && str_contains($reasonOut, $reportedUrl) === false) {
                $reasonOut .= "\nURL : " . $reportedUrl;
            }
            $stmt->execute([$tenantId, $reporterId, $postId, $topicId, $reasonOut]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $k = $table . '.' . $column;
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        $cache[$k] = (bool) $stmt->fetchColumn();

        return $cache[$k];
    }

    public function countPending(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_reports WHERE tenant_id = ? AND status = ?');
        $stmt->execute([$tenantId, 'pending']);

        return (int) $stmt->fetchColumn();
    }

    /** Signalements forum encore ouverts, toutes communautés confondues. */
    public function countPendingAllTenants(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM forum_reports WHERE status = 'pending'");

        return (int) $stmt->fetchColumn();
    }

    /**
     * Communautés avec le plus de signalements en attente (aperçu opérateur).
     *
     * @return list<array{tenant_id: int, pending: int, tenant_name: string|null}>
     */
    public function pendingCountTopTenants(int $limit): array
    {
        $limit = max(1, min(25, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT fr.tenant_id AS tenant_id, COUNT(*) AS pending, MAX(t.name) AS tenant_name
             FROM forum_reports fr
             LEFT JOIN tenants t ON t.id = fr.tenant_id
             WHERE fr.status = \'pending\'
             GROUP BY fr.tenant_id
             ORDER BY pending DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $r): array {
            return [
                'tenant_id' => (int) ($r['tenant_id'] ?? 0),
                'pending' => (int) ($r['pending'] ?? 0),
                'tenant_name' => isset($r['tenant_name']) && $r['tenant_name'] !== '' && $r['tenant_name'] !== null
                    ? (string) $r['tenant_name']
                    : null,
            ];
        }, $rows);
    }

    public function listPending(int $tenantId): array
    {
        $hasAssignedTo = $this->columnExists('forum_reports', 'assigned_to');
        $assignedSelect = $hasAssignedTo ? ', au.display_name AS assigned_to_name' : ', NULL AS assigned_to_name';
        $assignedJoin = $hasAssignedTo ? ' LEFT JOIN users au ON au.id = fr.assigned_to ' : ' ';
        $stmt = $this->pdo->prepare(
            'SELECT fr.*, u.display_name AS reporter_name,
                    fp.body AS post_excerpt, fp.topic_id AS post_topic_id, fp.user_id AS post_author_id,
                    ft.title AS topic_title'
                    . $assignedSelect . ',
                    COALESCE(fc.scope, \'general\') AS category_scope
             FROM forum_reports fr
             LEFT JOIN users u ON u.id = fr.reporter_id
             LEFT JOIN forum_posts fp ON fp.id = fr.post_id
             LEFT JOIN forum_topics ft ON ft.id = COALESCE(fr.topic_id, fp.topic_id)
             ' . $assignedJoin . '
             LEFT JOIN forum_categories fc ON fc.id = ft.category_id
             WHERE fr.tenant_id = ? AND fr.status = \'pending\'
             ORDER BY fr.created_at DESC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listHandled(int $tenantId, int $limit = 20): array
    {
        $hasAssignedTo = $this->columnExists('forum_reports', 'assigned_to');
        $assignedSelect = $hasAssignedTo ? ', au.display_name AS assigned_to_name' : ', NULL AS assigned_to_name';
        $assignedJoin = $hasAssignedTo ? ' LEFT JOIN users au ON au.id = fr.assigned_to ' : ' ';
        $stmt = $this->pdo->prepare(
            'SELECT fr.*, u.display_name AS reporter_name,
                    fp.topic_id AS post_topic_id, ft.title AS topic_title, hu.display_name AS handled_by_name' . $assignedSelect . '
             FROM forum_reports fr
             LEFT JOIN users u ON u.id = fr.reporter_id
             LEFT JOIN forum_posts fp ON fp.id = fr.post_id
             LEFT JOIN forum_topics ft ON ft.id = COALESCE(fr.topic_id, fp.topic_id)
             LEFT JOIN users hu ON hu.id = fr.handled_by
             ' . $assignedJoin . '
             WHERE fr.tenant_id = ? AND fr.status = \'handled\'
             ORDER BY fr.handled_at DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remet un dossier clos dans la file d’attente (statut pending).
     */
    public function reopenToPending(int $id, int $tenantId): bool
    {
        if ($this->columnExists('forum_reports', 'assigned_to')) {
            $stmt = $this->pdo->prepare(
                "UPDATE forum_reports
                 SET status = 'pending', handled_by = NULL, handled_at = NULL, assigned_to = NULL, assigned_at = NULL
                 WHERE id = ? AND tenant_id = ? AND status = 'handled'"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "UPDATE forum_reports
                 SET status = 'pending', handled_by = NULL, handled_at = NULL
                 WHERE id = ? AND tenant_id = ? AND status = 'handled'"
            );
        }

        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function markHandled(int $id, int $tenantId, int $handledBy): bool
    {
        if (!$this->columnExists('forum_reports', 'assigned_to') || !$this->columnExists('forum_reports', 'assigned_at')) {
            $stmt = $this->pdo->prepare('UPDATE forum_reports SET status = \'handled\', handled_by = ?, handled_at = NOW() WHERE id = ? AND tenant_id = ?');

            return $stmt->execute([$handledBy, $id, $tenantId]);
        }
        $stmt = $this->pdo->prepare(
            'UPDATE forum_reports
             SET status = \'handled\', handled_by = ?, handled_at = NOW(), assigned_to = NULL, assigned_at = NULL
             WHERE id = ? AND tenant_id = ?'
        );
        return $stmt->execute([$handledBy, $id, $tenantId]);
    }

    public function assignTo(int $id, int $tenantId, int $userId): bool
    {
        if (!$this->columnExists('forum_reports', 'assigned_to') || !$this->columnExists('forum_reports', 'assigned_at')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE forum_reports
             SET assigned_to = ?, assigned_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = \'pending\''
        );

        return $stmt->execute([$userId, $id, $tenantId]);
    }

    public function unassign(int $id, int $tenantId): bool
    {
        if (!$this->columnExists('forum_reports', 'assigned_to') || !$this->columnExists('forum_reports', 'assigned_at')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE forum_reports
             SET assigned_to = NULL, assigned_at = NULL
             WHERE id = ? AND tenant_id = ? AND status = \'pending\''
        );

        return $stmt->execute([$id, $tenantId]);
    }

    public function saveResolution(int $id, int $tenantId, string $followUp, string $note): bool
    {
        if (!$this->columnExists('forum_reports', 'last_follow_up_action') || !$this->columnExists('forum_reports', 'resolution_note')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE forum_reports
             SET last_follow_up_action = ?, resolution_note = ?
             WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$followUp !== '' ? $followUp : null, $note !== '' ? $note : null, $id, $tenantId]);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM forum_reports WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Dossier signalement enrichi (même jointures que la file / l’historique console).
     */
    public function findEnrichedForConsole(int $id, int $tenantId): ?array
    {
        $hasAssignedTo = $this->columnExists('forum_reports', 'assigned_to');
        $assignedSelect = $hasAssignedTo ? ', au.display_name AS assigned_to_name' : ', NULL AS assigned_to_name';
        $assignedJoin = $hasAssignedTo ? ' LEFT JOIN users au ON au.id = fr.assigned_to ' : ' ';
        $stmt = $this->pdo->prepare(
            'SELECT fr.*, u.display_name AS reporter_name,
                    fp.body AS post_excerpt, fp.topic_id AS post_topic_id, fp.user_id AS post_author_id,
                    ft.title AS topic_title, hu.display_name AS handled_by_name' . $assignedSelect . ',
                    COALESCE(fc.scope, \'general\') AS category_scope
             FROM forum_reports fr
             LEFT JOIN users u ON u.id = fr.reporter_id
             LEFT JOIN forum_posts fp ON fp.id = fr.post_id
             LEFT JOIN forum_topics ft ON ft.id = COALESCE(fr.topic_id, fp.topic_id)
             LEFT JOIN users hu ON hu.id = fr.handled_by
             ' . $assignedJoin . '
             LEFT JOIN forum_categories fc ON fc.id = ft.category_id
             WHERE fr.id = ? AND fr.tenant_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Nombre total de signalements déposés par un membre sur cette communauté. */
    public function countReportsFiledByReporter(int $tenantId, int $reporterId): int
    {
        if ($reporterId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_reports WHERE tenant_id = ? AND reporter_id = ?');
        $stmt->execute([$tenantId, $reporterId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Signalements de ce membre ayant abouti à une mesure sur le contenu ou un avertissement formel
     * (dossiers clos uniquement).
     */
    public function countReportsFiledWithSubstantiveOutcome(int $tenantId, int $reporterId): int
    {
        if ($reporterId < 1 || !$this->columnExists('forum_reports', 'last_follow_up_action')) {
            return 0;
        }
        $actions = ['hide_post', 'delete_post', 'lock_topic', 'hide_topic', 'sanction_warn'];
        $ph = implode(',', array_fill(0, count($actions), '?'));
        $sql = "SELECT COUNT(*) FROM forum_reports
                WHERE tenant_id = ? AND reporter_id = ? AND status = 'handled'
                  AND last_follow_up_action IN ({$ph})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId, $reporterId], $actions));

        return (int) $stmt->fetchColumn();
    }

    /** Signalements dont le message visé est rédigé par ce membre (messages du fil). */
    public function countReportsOnAuthoredPosts(int $tenantId, int $postAuthorId): int
    {
        if ($postAuthorId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT fr.id) FROM forum_reports fr
             INNER JOIN forum_posts fp ON fp.id = fr.post_id
             WHERE fr.tenant_id = ? AND fp.user_id = ?'
        );
        $stmt->execute([$tenantId, $postAuthorId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Parmi les signalements ciblant un message de ce membre, dossiers clos avec mesure substantielle.
     */
    public function countReportsOnAuthoredPostsWithSubstantiveOutcome(int $tenantId, int $postAuthorId): int
    {
        if ($postAuthorId < 1 || !$this->columnExists('forum_reports', 'last_follow_up_action')) {
            return 0;
        }
        $actions = ['hide_post', 'delete_post', 'lock_topic', 'hide_topic', 'sanction_warn'];
        $ph = implode(',', array_fill(0, count($actions), '?'));
        $sql = "SELECT COUNT(DISTINCT fr.id) FROM forum_reports fr
                INNER JOIN forum_posts fp ON fp.id = fr.post_id
                WHERE fr.tenant_id = ? AND fp.user_id = ? AND fr.status = 'handled'
                  AND fr.last_follow_up_action IN ({$ph})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId, $postAuthorId], $actions));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Signalements sans message forum mais motif profil / fiche mentionnant explicitement ce compte.
     */
    public function countProfileStyleReportsMentioningUser(int $tenantId, int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }
        $like1 = '%compte n° ' . $userId . '%';
        $like2 = '%(n° ' . $userId . ')%';
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM forum_reports
             WHERE tenant_id = ? AND post_id IS NULL
               AND (reason LIKE ? OR reason LIKE ?)"
        );
        $stmt->execute([$tenantId, $like1, $like2]);

        return (int) $stmt->fetchColumn();
    }

    public function addTimelineEvent(
        int $tenantId,
        int $reportId,
        ?int $actorUserId,
        string $eventType,
        string $eventLabel,
        ?string $eventDetail = null
    ): void {
        if (!$this->tableExists('forum_report_timeline')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_report_timeline (tenant_id, report_id, actor_user_id, event_type, event_label, event_detail, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $reportId,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            $eventType,
            $eventLabel,
            $eventDetail !== null && $eventDetail !== '' ? $eventDetail : null,
        ]);
    }

    /**
     * @param list<int> $reportIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function timelineByReportIds(int $tenantId, array $reportIds, int $limitPerReport = 8): array
    {
        if (!$this->tableExists('forum_report_timeline')) {
            return [];
        }
        $reportIds = array_values(array_unique(array_filter(array_map('intval', $reportIds), static fn (int $id): bool => $id > 0)));
        if ($reportIds === []) {
            return [];
        }
        $limitPerReport = max(1, min(20, $limitPerReport));
        $in = implode(',', array_fill(0, count($reportIds), '?'));
        $sql = "SELECT tr.*, u.display_name AS actor_name
                FROM forum_report_timeline tr
                LEFT JOIN users u ON u.id = tr.actor_user_id
                WHERE tr.tenant_id = ? AND tr.report_id IN ({$in})
                ORDER BY tr.report_id ASC, tr.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $reportIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        $counts = [];
        foreach ($rows as $row) {
            $rid = (int) ($row['report_id'] ?? 0);
            if ($rid < 1) {
                continue;
            }
            $counts[$rid] = (int) ($counts[$rid] ?? 0);
            if ($counts[$rid] >= $limitPerReport) {
                continue;
            }
            $out[$rid] ??= [];
            $out[$rid][] = $row;
            $counts[$rid]++;
        }

        return $out;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    }
}
