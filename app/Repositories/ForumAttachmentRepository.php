<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumAttachmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Mémoïsé pour la durée de la requête (appelé pour chaque message d’un sujet). */
    private static ?bool $tableExistsCache = null;

    public function tableExists(): bool
    {
        if (self::$tableExistsCache === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_attachments' LIMIT 1");
            self::$tableExistsCache = (bool) $stmt?->fetchColumn();
        }

        return self::$tableExistsCache;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForPost(int $postId, int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM forum_attachments WHERE post_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$postId, $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    /**
     * Pièces jointes de plusieurs messages en une requête (page d’un sujet).
     *
     * @param list<int> $postIds
     * @return array<int, list<array<string, mixed>>> postId => pièces jointes
     */
    public function listForPosts(array $postIds, int $tenantId): array
    {
        $postIds = array_values(array_filter(array_map('intval', $postIds), fn ($id) => $id > 0));
        if ($postIds === [] || !$this->tableExists()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM forum_attachments WHERE post_id IN ($placeholders) AND tenant_id = ? ORDER BY id ASC");
        $stmt->execute(array_merge($postIds, [$tenantId]));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['post_id']][] = $row;
        }

        return $out;
    }

    public function insert(int $tenantId, int $postId, string $filePath, string $mime, int $sizeBytes): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_attachments (tenant_id, post_id, file_path, mime, size_bytes, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $postId, $filePath, $mime, $sizeBytes]);

        return (int) $this->pdo->lastInsertId();
    }
}
