<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PlatformUsageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function record(int $tenantId, ?int $userId, string $featureKey, string $action): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_usage_events (tenant_id, user_id, feature_key, action, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $userId, $featureKey, $action]);
    }

    /** Compte d’événements sur une fenêtre (ex. analytics). */
    public function countByFeatureSince(int $tenantId, string $featureKey, string $sinceIso): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM platform_usage_events WHERE tenant_id = ? AND feature_key = ? AND created_at >= ?'
        );
        $stmt->execute([$tenantId, $featureKey, $sinceIso]);

        return (int) $stmt->fetchColumn();
    }
}
