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
}
