<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ArmaPlaytimeRepository
{
    private PDO $pdo;

    private static ?bool $tableReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$tableReady === null) {
            try {
                $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_arma_playtime' LIMIT 1");
                self::$tableReady = $st && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    public function addSeconds(int $tenantId, int $userId, int $seconds): void
    {
        if ($seconds <= 0 || !$this->schemaReady()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_arma_playtime (tenant_id, user_id, total_seconds, last_report_at, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                total_seconds = user_arma_playtime.total_seconds + ?,
                last_report_at = NOW(),
                updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $userId, $seconds, $seconds]);
    }

    /**
     * @return array{total_seconds: int|string, last_report_at: ?string}|null
     */
    public function getSummaryForUser(int $tenantId, int $userId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT total_seconds, last_report_at FROM user_arma_playtime WHERE tenant_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
