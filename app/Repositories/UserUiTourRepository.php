<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

class UserUiTourRepository
{
    public const KEY_DASHBOARD = 'dashboard.v1';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_ui_tours' LIMIT 1"
            );

            return (bool) $st?->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function isDismissed(int $userId, string $tourKey): bool
    {
        if ($userId < 1 || !$this->schemaReady()) {
            return false;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM user_ui_tours WHERE user_id = ? AND tour_key = ? AND dismissed_at IS NOT NULL LIMIT 1'
            );
            $st->execute([$userId, $tourKey]);

            return (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function dismiss(int $userId, string $tourKey, bool $completed = false): void
    {
        if ($userId < 1 || $tourKey === '' || !$this->schemaReady()) {
            return;
        }
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO user_ui_tours (user_id, tour_key, dismissed_at, completed_at, created_at)
                 VALUES (?, ?, NOW(), IF(?, NOW(), NULL), NOW())
                 ON DUPLICATE KEY UPDATE
                    dismissed_at = NOW(),
                    completed_at = IF(?, NOW(), completed_at),
                    updated_at = NOW()'
            );
            $done = $completed ? 1 : 0;
            $st->execute([$userId, $tourKey, $done, $done]);
        } catch (PDOException) {
            // Table absente ou indisponible : le guide reste masquable côté écran.
        }
    }
}
