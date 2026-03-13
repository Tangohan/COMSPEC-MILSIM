<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumPostRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listByTopic(int $topicId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fp.*, u.display_name AS author_name, u.callsign AS author_callsign, u.role_id AS author_role_id, u.avatar_url AS author_avatar_url
             FROM forum_posts fp
             LEFT JOIN users u ON u.id = fp.user_id
             WHERE fp.topic_id = ?
             ORDER BY fp.created_at ASC'
        );
        $stmt->execute([$topicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM forum_posts WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $tenantId, int $topicId, int $userId, string $body): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_posts (tenant_id, topic_id, user_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$tenantId, $topicId, $userId, $body]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, string $body): bool
    {
        $stmt = $this->pdo->prepare('UPDATE forum_posts SET body = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$body, $id, $tenantId]);
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM forum_posts WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$id, $tenantId]);
    }

    public function countByTopic(int $topicId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_posts WHERE topic_id = ?');
        $stmt->execute([$topicId]);
        return (int) $stmt->fetchColumn();
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
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.display_name, u.callsign, COUNT(fp.id) AS post_count
             FROM forum_posts fp
             JOIN users u ON u.id = fp.user_id
             WHERE fp.tenant_id = ?
             GROUP BY fp.user_id
             ORDER BY post_count DESC
             LIMIT ?'
        );
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
