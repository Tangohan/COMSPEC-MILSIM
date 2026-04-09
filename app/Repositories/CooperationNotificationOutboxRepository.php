<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * File d’attente minimale pour notifications coopération (traitement ultérieur : e-mail, in-app, digest).
 */
final class CooperationNotificationOutboxRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_notification_outbox' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function enqueue(?int $tenantId, ?int $userId, string $eventKey, ?array $payload, ?string $aggregationKey = null): void
    {
        if (!$this->tableExists() || $eventKey === '') {
            return;
        }
        $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO cooperation_notification_outbox (tenant_id, user_id, event_key, payload_json, aggregation_key, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $userId, $eventKey, $json, $aggregationKey]);
    }

    /**
     * Signal générique après écriture dans interteam_mission_events.
     *
     * @param array<string, mixed>|null $payload
     */
    public function enqueueMissionEvent(int $missionId, string $eventType, ?array $payload): void
    {
        $this->enqueue(null, null, 'cooperation.signal.' . $eventType, array_merge(
            ['mission_id' => $missionId],
            $payload ?? []
        ), 'mission:' . $missionId . ':' . $eventType);
    }
}
