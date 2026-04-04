<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumVoteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_post_votes' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    public function getUserVote(int $postId, int $userId): ?int
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT value FROM forum_post_votes WHERE post_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$postId, $userId]);
        $v = $stmt->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    public function setVote(int $tenantId, int $postId, int $userId, int $value): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $value = $value >= 0 ? 1 : -1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO forum_post_votes (tenant_id, post_id, user_id, value, created_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute([$tenantId, $postId, $userId, $value]);
    }

    public function removeVote(int $postId, int $userId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM forum_post_votes WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
    }

    public function sumForPost(int $postId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(value), 0) FROM forum_post_votes WHERE post_id = ?');
        $stmt->execute([$postId]);

        return (int) $stmt->fetchColumn();
    }
}
