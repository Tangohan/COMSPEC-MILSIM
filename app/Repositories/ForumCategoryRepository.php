<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumCategoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fc.*,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS topic_count,
                    (SELECT COUNT(*) FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS post_count,
                    (SELECT MAX(fp.created_at) FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id) AS last_post_at,
                    (SELECT u.display_name FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id LEFT JOIN users u ON u.id = fp.user_id WHERE ft.category_id = fc.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_author
             FROM forum_categories fc
             WHERE fc.tenant_id = ? AND fc.parent_id IS NULL
             ORDER BY fc.display_order ASC, fc.id ASC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fc.*,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS topic_count,
                    (SELECT COUNT(*) FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS post_count
             FROM forum_categories fc
             WHERE fc.tenant_id = ? AND fc.slug = ? AND fc.parent_id IS NULL
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM forum_categories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSubcategories(int $parentId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fc.*,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS topic_count
             FROM forum_categories fc
             WHERE fc.tenant_id = ? AND fc.parent_id = ?
             ORDER BY fc.display_order ASC'
        );
        $stmt->execute([$tenantId, $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
