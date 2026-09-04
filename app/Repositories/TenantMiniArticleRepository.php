<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

class TenantMiniArticleRepository
{
    private PDO $pdo;

    private static ?bool $tableReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }
        try {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_mini_articles' LIMIT 1"
            );
            self::$tableReady = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            self::$tableReady = false;
        }

        return self::$tableReady;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, int $limit = 100): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tenant_mini_articles
                 WHERE tenant_id = ?
                 ORDER BY pinned DESC, COALESCE(published_at, created_at) DESC, id DESC
                 LIMIT ' . $limit
            );
            $stmt->execute([$tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listPublishedForTenant(int $tenantId, int $limit = 20): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM tenant_mini_articles
                 WHERE tenant_id = ? AND status = 'published'
                 ORDER BY pinned DESC, published_at DESC, id DESC
                 LIMIT " . $limit
            );
            $stmt->execute([$tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        if (!$this->schemaReady() || $id < 1 || $tenantId < 1) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tenant_mini_articles WHERE id = ? AND tenant_id = ? LIMIT 1'
            );
            $stmt->execute([$id, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(int $tenantId, string $slug): ?array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $slug === '') {
            return null;
        }
        try {
            $slugEq = SqlText::equals($this->pdo, 'slug');
            $statusPublished = SqlText::inLiterals($this->pdo, 'status', ['published']);
            $stmt = $this->pdo->prepare(
                "SELECT * FROM tenant_mini_articles
                 WHERE tenant_id = ? AND {$slugEq} AND {$statusPublished}
                 LIMIT 1"
            );
            $stmt->execute([$tenantId, $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function slugExists(int $tenantId, string $slug, ?int $exceptId = null): bool
    {
        if (!$this->schemaReady() || $tenantId < 1 || $slug === '') {
            return false;
        }
        try {
            if ($exceptId !== null && $exceptId > 0) {
                $slugEq = SqlText::equals($this->pdo, 'slug');
                $stmt = $this->pdo->prepare(
                    'SELECT 1 FROM tenant_mini_articles WHERE tenant_id = ? AND ' . $slugEq . ' AND id <> ? LIMIT 1'
                );
                $stmt->execute([$tenantId, $slug, $exceptId]);
            } else {
                $slugEq = SqlText::equals($this->pdo, 'slug');
                $stmt = $this->pdo->prepare(
                    'SELECT 1 FROM tenant_mini_articles WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1'
                );
                $stmt->execute([$tenantId, $slug]);
            }

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(int $tenantId, array $data): int
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_mini_articles
                (tenant_id, author_user_id, title, slug, excerpt, body_html, tags_json, cover_path, gallery_json, status, published_at, pinned)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['author_user_id'] ?? null,
            $data['title'],
            $data['slug'],
            $data['excerpt'] ?? null,
            $data['body_html'] ?? '',
            $data['tags_json'] ?? null,
            $data['cover_path'] ?? null,
            $data['gallery_json'] ?? null,
            $data['status'] ?? 'draft',
            $data['published_at'] ?? null,
            !empty($data['pinned']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        if (!$this->schemaReady() || $id < 1 || $tenantId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_mini_articles SET
                title = ?, slug = ?, excerpt = ?, body_html = ?, tags_json = ?,
                cover_path = ?, gallery_json = ?, status = ?, published_at = ?, pinned = ?
             WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['excerpt'] ?? null,
            $data['body_html'] ?? '',
            $data['tags_json'] ?? null,
            $data['cover_path'] ?? null,
            $data['gallery_json'] ?? null,
            $data['status'] ?? 'draft',
            $data['published_at'] ?? null,
            !empty($data['pinned']) ? 1 : 0,
            $id,
            $tenantId,
        ]);
    }

    public function delete(int $id, int $tenantId): bool
    {
        if (!$this->schemaReady() || $id < 1 || $tenantId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM tenant_mini_articles WHERE id = ? AND tenant_id = ?');

        return $stmt->execute([$id, $tenantId]);
    }
}
