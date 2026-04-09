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

    public function listByCategory(
        int $categoryId,
        int $tenantId,
        int $page = 1,
        int $perPage = 20,
        string $sort = 'activity',
        ?string $filter = null,
        ?int $userId = null,
        bool $includeHiddenForUser = false
    ): array {
        $offset = ($page - 1) * $perPage;
        $orderBy = match ($sort) {
            'newest' => 'ft.created_at DESC',
            'oldest' => 'ft.created_at ASC',
            'replies' => 'post_count DESC, ft.updated_at DESC',
            'popular_7d' => 'posts_7d DESC, ft.updated_at DESC',
            'solved' => 'ft.is_solved DESC, ft.updated_at DESC',
            default => 'ft.is_pinned DESC, ft.updated_at DESC',
        };

        $hiddenCond = $includeHiddenForUser ? '1' : 'ft.is_hidden = 0';
        $filterJoin = '';
        $filterWhere = '';
        $params = [$categoryId, $tenantId];
        if ($userId !== null && $filter !== null && $filter !== '') {
            switch ($filter) {
                case 'unread':
                    $filterJoin = 'LEFT JOIN forum_read fr ON fr.topic_id = ft.id AND fr.user_id = ?';
                    $filterWhere = ' AND fr.read_at IS NULL';
                    $params[] = $userId;
                    break;
                case 'unanswered':
                    $filterWhere = ' AND (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) <= 1';
                    break;
                case 'my_subscriptions':
                    $filterJoin = 'INNER JOIN forum_topic_subscriptions fts ON fts.topic_id = ft.id AND fts.user_id = ?';
                    $params[] = $userId;
                    break;
                case 'my_topics':
                    $filterWhere = ' AND ft.user_id = ?';
                    $params[] = $userId;
                    break;
            }
        }

        $sql = "SELECT ft.*, u.id AS topic_author_user_id, u.display_name AS author_name, u.callsign AS author_callsign,
                    (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) AS post_count,
                    (SELECT fp.created_at FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_at,
                    (SELECT u2.display_name FROM forum_posts fp JOIN users u2 ON u2.id = fp.user_id WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_author_name_legacy,
                    (SELECT fp.user_id FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_user_id,
                    (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS posts_7d
             FROM forum_topics ft
             $filterJoin
             LEFT JOIN users u ON u.id = ft.user_id
             WHERE ft.category_id = ? AND ft.tenant_id = ? AND ($hiddenCond)
             $filterWhere
             ORDER BY $orderBy
             LIMIT $perPage OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByCategory(int $categoryId, int $tenantId, ?string $filter = null, ?int $userId = null, bool $includeHiddenForUser = false): int
    {
        $hiddenCond = $includeHiddenForUser ? '1' : 'ft.is_hidden = 0';
        $filterJoin = '';
        $filterWhere = '';
        $params = [$categoryId, $tenantId];
        if ($userId !== null && $filter !== null && $filter !== '') {
            switch ($filter) {
                case 'unread':
                    $filterJoin = 'LEFT JOIN forum_read fr ON fr.topic_id = ft.id AND fr.user_id = ?';
                    $filterWhere = ' AND fr.read_at IS NULL';
                    $params[] = $userId;
                    break;
                case 'unanswered':
                    $filterWhere = ' AND (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) <= 1';
                    break;
                case 'my_subscriptions':
                    $filterJoin = 'INNER JOIN forum_topic_subscriptions fts ON fts.topic_id = ft.id AND fts.user_id = ?';
                    $params[] = $userId;
                    break;
                case 'my_topics':
                    $filterWhere = ' AND ft.user_id = ?';
                    $params[] = $userId;
                    break;
            }
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM forum_topics ft $filterJoin WHERE ft.category_id = ? AND ft.tenant_id = ? AND ($hiddenCond) $filterWhere"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function searchByCategory(int $categoryId, string $query, int $tenantId, bool $includeHiddenForUser = false, int $limit = 50): array
    {
        $term = '%' . trim($query) . '%';
        $hiddenCond = $includeHiddenForUser ? '1' : 'ft.is_hidden = 0';
        $stmt = $this->pdo->prepare(
            "SELECT ft.*, u.id AS topic_author_user_id, u.display_name AS author_name,
                    (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) AS post_count,
                    (SELECT fp.created_at FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_at,
                    (SELECT u2.display_name FROM forum_posts fp JOIN users u2 ON u2.id = fp.user_id WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_author_name_legacy,
                    (SELECT fp.user_id FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_user_id,
                    (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS posts_7d
             FROM forum_topics ft
             LEFT JOIN users u ON u.id = ft.user_id
             WHERE ft.category_id = ? AND ft.tenant_id = ? AND ($hiddenCond) AND (ft.title LIKE ? OR EXISTS (
                 SELECT 1 FROM forum_posts fp WHERE fp.topic_id = ft.id AND fp.body LIKE ?
             ))
             ORDER BY ft.updated_at DESC
             LIMIT ?"
        );
        $stmt->execute([$categoryId, $tenantId, $term, $term, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ft.*, u.id AS topic_author_user_id, u.display_name AS author_name, u.callsign AS author_callsign, u.role_id AS author_role_id,
                    fc.name AS category_name, fc.slug AS category_slug, COALESCE(fc.scope, \'general\') AS category_scope,
                    (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS posts_7d
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
        $allowed = ['title', 'is_pinned', 'is_locked', 'is_archived', 'is_hidden', 'is_official', 'suppress_auto_lock'];
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

    /**
     * Verrouille automatiquement les sujets de plus de 6 mois (sauf si suppression du verrou auto demandée).
     */
    public function applyAutoLockIfStale(int $topicId, int $tenantId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, is_locked, created_at, suppress_auto_lock FROM forum_topics WHERE id = ? AND tenant_id = ? LIMIT 1'
            );
            $stmt->execute([$topicId, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            if (!empty($row['is_locked']) || !empty($row['suppress_auto_lock'])) {
                return false;
            }
            $created = strtotime((string) ($row['created_at'] ?? ''));
            if ($created === false) {
                return false;
            }
            if ($created >= strtotime('-6 months')) {
                return false;
            }
            $this->update($topicId, $tenantId, ['is_locked' => 1]);
            $hasCol = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_topics' AND COLUMN_NAME = 'auto_locked_at' LIMIT 1");
            if ($hasCol && $hasCol->fetchColumn()) {
                $u = $this->pdo->prepare('UPDATE forum_topics SET auto_locked_at = NOW() WHERE id = ? AND tenant_id = ?');
                $u->execute([$topicId, $tenantId]);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getPinnedInCategory(int $categoryId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ft.*, u.id AS topic_author_user_id, u.display_name AS author_name
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
                    (SELECT fp.user_id FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_user_id
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

    public function setBestAnswer(int $topicId, int $tenantId, ?int $postId): bool
    {
        $chk = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_topics' AND COLUMN_NAME = 'best_answer_post_id' LIMIT 1");
        if (!$chk || !$chk->fetchColumn()) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE forum_topics SET best_answer_post_id = ?, is_solved = ? WHERE id = ? AND tenant_id = ?');

        return $stmt->execute([$postId, $postId ? 1 : 0, $topicId, $tenantId]);
    }

    /**
     * Liste de sujets récents pour sélection (back-office coopération).
     *
     * @return list<array{id: int, title: string}>
     */
    public function listRecentTitlesForTenant(int $tenantId, int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, title FROM forum_topics WHERE tenant_id = ? AND is_hidden = 0 ORDER BY updated_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
