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

    public function create(int $tenantId, int $reporterId, ?int $postId, ?int $topicId, string $reason): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_reports (tenant_id, reporter_id, post_id, topic_id, reason, status, created_at) VALUES (?, ?, ?, ?, ?, \'pending\', NOW())'
        );
        $stmt->execute([$tenantId, $reporterId, $postId, $topicId, $reason]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listPending(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fr.*, u.display_name AS reporter_name,
                    fp.body AS post_excerpt, fp.topic_id AS post_topic_id,
                    ft.title AS topic_title
             FROM forum_reports fr
             LEFT JOIN users u ON u.id = fr.reporter_id
             LEFT JOIN forum_posts fp ON fp.id = fr.post_id
             LEFT JOIN forum_topics ft ON ft.id = COALESCE(fr.topic_id, fp.topic_id)
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
