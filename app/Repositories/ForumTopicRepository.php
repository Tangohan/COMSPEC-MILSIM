<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumTopicRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listByCategory(int $categoryId, int $tenantId, int $page = 1, int $perPage = 20, string $sort = 'activity'): array
    {
        $offset = ($page - 1) * $perPage;
        $orderBy = match ($sort) {
            'newest' => 'ft.created_at DESC',
            'replies' => 'post_count DESC, ft.updated_at DESC',
            default => 'ft.is_pinned DESC, ft.updated_at DESC',
        };

        $stmt = $this->pdo->prepare(
            "SELECT ft.*, u.display_name AS author_name, u.callsign AS author_callsign,
                    (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) AS post_count,
                    (SELECT fp.created_at FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_at,
                    (SELECT u2.display_name FROM forum_posts fp JOIN users u2 ON u2.id = fp.user_id WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_author_name
             FROM forum_topics ft
             LEFT JOIN users u ON u.id = ft.user_id
             WHERE ft.category_id = ? AND ft.tenant_id = ? AND ft.is_hidden = 0
             ORDER BY $orderBy
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute([$categoryId, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByCategory(int $categoryId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_topics WHERE category_id = ? AND tenant_id = ? AND is_hidden = 0');
        $stmt->execute([$categoryId, $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ft.*, u.display_name AS author_name, u.callsign AS author_callsign, u.role_id AS author_role_id,
                    fc.name AS category_name, fc.slug AS category_slug
             FROM forum_topics ft
             LEFT JOIN users u ON u.id = ft.user_id
             LEFT JOIN forum_categories fc ON fc.id = ft.category_id
             WHERE ft.id = ? AND ft.tenant_id = ?
             LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $tenantId, int $categoryId, int $userId, string $title, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_topics (tenant_id, category_id, user_id, title, slug, is_pinned, is_locked, is_archived, is_hidden, view_count, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, NOW(), NOW())'
        );
        $stmt->execute([$tenantId, $categoryId, $userId, $title, $slug]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $allowed = ['title', 'is_pinned', 'is_locked', 'is_archived', 'is_hidden'];
        $set = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $set[] = "`$key` = ?";
                $params[] = $data[$key];
            }
        }
        if (empty($set)) {
            return true;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare('UPDATE forum_topics SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        return $stmt->execute($params);
    }

    public function touchUpdatedAt(int $topicId): void
    {
        $stmt = $this->pdo->prepare('UPDATE forum_topics SET updated_at = NOW() WHERE id = ?');
        $stmt->execute([$topicId]);
    }

    public function incrementViewCount(int $topicId): void
    {
        $stmt = $this->pdo->prepare('UPDATE forum_topics SET view_count = view_count + 1 WHERE id = ?');
        $stmt->execute([$topicId]);
    }

    public function getPinnedInCategory(int $categoryId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ft.*, u.display_name AS author_name
             FROM forum_topics ft
             LEFT JOIN users u ON u.id = ft.user_id
             WHERE ft.category_id = ? AND ft.tenant_id = ? AND ft.is_pinned = 1 AND ft.is_hidden = 0
             ORDER BY ft.updated_at DESC'
        );
        $stmt->execute([$categoryId, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentForIndex(int $tenantId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ft.id, ft.title, ft.slug, ft.updated_at, fc.name AS category_name, fc.slug AS category_slug, fc.color_theme,
                    (SELECT u.display_name FROM forum_posts fp JOIN users u ON u.id = fp.user_id WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_author_name
             FROM forum_topics ft
             JOIN forum_categories fc ON fc.id = ft.category_id
             WHERE ft.tenant_id = ? AND ft.is_hidden = 0
             ORDER BY ft.updated_at DESC
             LIMIT ?'
        );
        $stmt->execute([$tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(int $tenantId, string $query, int $limit = 50): array
    {
        $term = '%' . trim($query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT ft.id, ft.title, ft.slug, ft.updated_at, fc.name AS category_name, fc.slug AS category_slug,
                    u.display_name AS author_name
             FROM forum_topics ft
             JOIN forum_categories fc ON fc.id = ft.category_id
             LEFT JOIN users u ON u.id = ft.user_id
             WHERE ft.tenant_id = ? AND ft.is_hidden = 0 AND (ft.title LIKE ? OR EXISTS (
                 SELECT 1 FROM forum_posts fp WHERE fp.topic_id = ft.id AND fp.body LIKE ?
             ))
             ORDER BY ft.updated_at DESC
             LIMIT ?'
        );
        $stmt->execute([$tenantId, $term, $term, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isSubscribed(int $userId, int $topicId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM forum_topic_subscriptions WHERE user_id = ? AND topic_id = ? LIMIT 1');
        $stmt->execute([$userId, $topicId]);
        return (bool) $stmt->fetchColumn();
    }

    public function subscribe(int $userId, int $topicId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO forum_topic_subscriptions (user_id, topic_id, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$userId, $topicId]);
    }

    public function unsubscribe(int $userId, int $topicId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM forum_topic_subscriptions WHERE user_id = ? AND topic_id = ?');
        $stmt->execute([$userId, $topicId]);
    }

    public function getTotalTopicCount(int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_topics WHERE tenant_id = ? AND is_hidden = 0');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }
}
