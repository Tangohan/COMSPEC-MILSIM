<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumNotificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_notifications' LIMIT 1");

        return (bool) $stmt?->fetchColumn();
    }

    public function create(int $tenantId, int $userId, string $type, array $payload = []): ?int
    {
        if (!$this->tableExists()) {
            return null;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare('INSERT INTO forum_notifications (tenant_id, user_id, type, payload_json, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $userId, $type, $json]);

        return (int) $this->pdo->lastInsertId();
    }

    public function unreadCount(int $tenantId, int $userId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM forum_notifications WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL');
        $stmt->execute([$tenantId, $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecentUnread(int $tenantId, int $userId, int $limit = 15): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, type, payload_json, created_at FROM forum_notifications
             WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL
             ORDER BY created_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $rows;
    }

    public function markAllRead(int $tenantId, int $userId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->pdo->prepare(
            'UPDATE forum_notifications SET read_at = NOW() WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL'
        )->execute([$tenantId, $userId]);
    }
}
