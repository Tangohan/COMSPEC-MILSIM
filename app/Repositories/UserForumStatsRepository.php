<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserForumStatsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_forum_stats' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    public function incrementPostCount(int $tenantId, int $userId): void
    {
        if (!$this->tableExists() || $userId <= 0) {
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO user_forum_stats (tenant_id, user_id, post_count, score, reputation, updated_at)
             VALUES (?, ?, 1, 0, 0, NOW())
             ON DUPLICATE KEY UPDATE post_count = post_count + 1, updated_at = NOW()'
        )->execute([$tenantId, $userId]);
    }

    public function addScore(int $tenantId, int $userId, int $delta): void
    {
        if (!$this->tableExists() || $userId <= 0 || $delta === 0) {
            return;
        }
        $this->pdo->prepare(
            'INSERT INTO user_forum_stats (tenant_id, user_id, post_count, score, reputation, updated_at)
             VALUES (?, ?, 0, 0, 0, NOW())
             ON DUPLICATE KEY UPDATE score = score + ?, updated_at = NOW()'
        )->execute([$tenantId, $userId, $delta]);
    }

    public function getForUser(int $tenantId, int $userId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM user_forum_stats WHERE tenant_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
