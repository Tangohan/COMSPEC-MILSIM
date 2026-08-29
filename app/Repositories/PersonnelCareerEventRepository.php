<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PersonnelCareerEventRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_career_events' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function record(
        int $tenantId,
        int $userId,
        string $eventType,
        ?int $actorUserId = null,
        ?array $metadata = null
    ): ?int {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1 || trim($eventType) === '') {
            return null;
        }
        $json = null;
        if ($metadata !== null) {
            $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            $json = is_string($encoded) ? $encoded : null;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO personnel_career_events
                (tenant_id, user_id, event_type, actor_user_id, metadata_json, created_at)
             VALUES (?,?,?,?,?,NOW())'
        );
        $st->execute([$tenantId, $userId, trim($eventType), $actorUserId, $json]);
        $id = (int) $this->pdo()->lastInsertId();

        return $id > 0 ? $id : null;
    }
}
