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
        $stmt = $this->pdo->prepare(
            'SELECT fr.*, u.display_name AS reporter_name,
                    fp.body AS post_excerpt, fp.topic_id AS post_topic_id, fp.user_id AS post_author_id,
                    ft.title AS topic_title,
                    COALESCE(fc.scope, \'general\') AS category_scope
             FROM forum_reports fr
             LEFT JOIN users u ON u.id = fr.reporter_id
             LEFT JOIN forum_posts fp ON fp.id = fr.post_id
             LEFT JOIN forum_topics ft ON ft.id = COALESCE(fr.topic_id, fp.topic_id)
             LEFT JOIN forum_categories fc ON fc.id = ft.category_id
             WHERE fr.tenant_id = ? AND fr.status = \'pending\'
             ORDER BY fr.created_at DESC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listHandled(int $tenantId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fr.*, u.display_name AS reporter_name,
                    fp.topic_id AS post_topic_id, ft.title AS topic_title, hu.display_name AS handled_by_name
             FROM forum_reports fr
             LEFT JOIN users u ON u.id = fr.reporter_id
             LEFT JOIN forum_posts fp ON fp.id = fr.post_id
             LEFT JOIN forum_topics ft ON ft.id = COALESCE(fr.topic_id, fp.topic_id)
             LEFT JOIN users hu ON hu.id = fr.handled_by
             WHERE fr.tenant_id = ? AND fr.status = \'handled\'
             ORDER BY fr.handled_at DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markHandled(int $id, int $tenantId, int $handledBy): bool
    {
        $stmt = $this->pdo->prepare('UPDATE forum_reports SET status = \'handled\', handled_by = ?, handled_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$handledBy, $id, $tenantId]);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM forum_reports WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
