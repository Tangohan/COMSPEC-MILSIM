<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AnalyticsEventRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tablesExist(): bool
    {
        $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usage_analytics_events' LIMIT 1");

        return $st && (bool) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed>|null $props
     */
    public function insert(
        int $tenantId,
        ?int $actorUserId,
        ?string $sessionHash,
        string $category,
        string $name,
        ?string $subjectType,
        ?int $subjectId,
        ?int $durationSeconds,
        ?array $props
    ): void {
        if (!$this->tablesExist()) {
            return;
        }
        $json = null;
        if ($props !== null && $props !== []) {
            $enc = json_encode($props, JSON_UNESCAPED_UNICODE);
            $json = $enc !== false ? $enc : null;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO usage_analytics_events (
                tenant_id, actor_user_id, session_hash, category, name, subject_type, subject_id, duration_seconds, props, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $actorUserId,
            $sessionHash !== null && $sessionHash !== '' ? $sessionHash : null,
            $category,
            $name,
            $subjectType,
            $subjectId,
            $durationSeconds,
            $json,
        ]);
    }

    public function countByTenantNameSince(int $tenantId, string $name, string $sinceIso): int
    {
        if (!$this->tablesExist()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usage_analytics_events WHERE tenant_id = ? AND name = ? AND created_at >= ?'
        );
        $stmt->execute([$tenantId, $name, $sinceIso]);

        return (int) $stmt->fetchColumn();
    }

    public function countDistinctSessionByTenantCategorySince(int $tenantId, string $category, string $sinceIso): int
    {
        if (!$this->tablesExist()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT COALESCE(session_hash, CONCAT('u:', COALESCE(actor_user_id, 0)))) 
             FROM usage_analytics_events 
             WHERE tenant_id = ? AND category = ? AND created_at >= ? AND (session_hash IS NOT NULL OR actor_user_id IS NOT NULL)"
        );
        $stmt->execute([$tenantId, $category, $sinceIso]);

        return (int) $stmt->fetchColumn();
    }
}
