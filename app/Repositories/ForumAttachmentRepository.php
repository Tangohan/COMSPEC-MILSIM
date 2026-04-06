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

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_attachments' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
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
