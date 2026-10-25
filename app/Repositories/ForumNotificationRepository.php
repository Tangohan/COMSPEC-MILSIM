<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumNotificationRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /** Mémoïsé pour la durée de la requête (barre latérale et bandeau de modération). */
    private static ?bool $tableExistsCache = null;

    public function tableExists(): bool
    {
        if (self::$tableExistsCache === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_notifications' LIMIT 1");
            self::$tableExistsCache = (bool) $stmt?->fetchColumn();
        }

        return self::$tableExistsCache;
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

    public function markRead(int $tenantId, int $userId, int $notificationId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->pdo->prepare(
            'UPDATE forum_notifications SET read_at = NOW() WHERE id = ? AND tenant_id = ? AND user_id = ? AND read_at IS NULL'
        )->execute([$notificationId, $tenantId, $userId]);
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

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecentForUser(int $tenantId, int $userId, int $limit = 40): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $lim = max(1, min(80, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, type, payload_json, read_at, created_at FROM forum_notifications
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Notifications non lues d'un type pour un compte, toutes communautés (le compte est unique).
     *
     * @return list<array<string, mixed>>
     */
    public function listUnreadOfTypeForUser(int $userId, string $type, int $maxAgeDays = 30, int $limit = 20): array
    {
        if ($userId < 1 || !$this->tableExists()) {
            return [];
        }
        $lim = max(1, min(50, $limit));
        $days = max(1, min(365, $maxAgeDays));
        $stmt = $this->pdo->prepare(
            "SELECT id, type, payload_json, created_at FROM forum_notifications
             WHERE user_id = ? AND type = ? AND read_at IS NULL AND created_at >= ?
             ORDER BY created_at DESC, id DESC LIMIT {$lim}"
        );
        $stmt->execute([$userId, $type, date('Y-m-d H:i:s', time() - $days * 86400)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Marque lues des notifications d'un type, seulement si elles appartiennent à ce compte.
     *
     * @param list<int> $ids
     */
    public function markReadForUser(array $ids, int $userId, string $type): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === [] || $userId < 1 || !$this->tableExists()) {
            return 0;
        }
        $ids = array_slice($ids, 0, 50);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE forum_notifications SET read_at = NOW() WHERE user_id = ? AND type = ? AND read_at IS NULL AND id IN ({$in})"
        );
        $stmt->execute(array_merge([$userId, $type], $ids));

        return $stmt->rowCount();
    }
}
