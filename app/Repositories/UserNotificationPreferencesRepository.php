<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserNotificationPreferencesRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, tenant_id, channel, event_key, enabled, created_at, updated_at
             FROM user_notification_preferences WHERE user_id = ? ORDER BY channel, event_key'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setEnabled(int $userId, int $tenantId, string $channel, string $eventKey, bool $enabled): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_notification_preferences (user_id, tenant_id, channel, event_key, enabled, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = NOW()'
        );
        $stmt->execute([$userId, $tenantId, $channel, $eventKey, $enabled ? 1 : 0]);
    }

    /**
     * @param list<array{channel: string, event_key: string, enabled: bool}> $rows
     */
    public function replaceMany(int $userId, int $tenantId, array $rows): void
    {
        foreach ($rows as $r) {
            $ch = (string) ($r['channel'] ?? 'in_app');
            $ev = (string) ($r['event_key'] ?? '');
            if ($ev === '') {
                continue;
            }
            $this->setEnabled($userId, $tenantId, $ch, $ev, (bool) ($r['enabled'] ?? true));
        }
    }
}
