<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CommunityEventRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function upcomingForTenant(int $tenantId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM community_events WHERE tenant_id = ? AND starts_at >= NOW() ORDER BY starts_at ASC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        int $tenantId,
        int $createdBy,
        string $title,
        ?string $description,
        ?string $location,
        string $startsAtIso,
        ?string $endsAtIso,
        ?string $campaignTag
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO community_events (tenant_id, title, description, location, campaign_tag, starts_at, ends_at, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $title,
            $description,
            $location,
            $campaignTag,
            $startsAtIso,
            $endsAtIso,
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setRsvp(int $eventId, int $userId, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO community_event_rsvps (event_id, user_id, status, created_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()'
        );
        $stmt->execute([$eventId, $userId, $status]);
    }

    public function belongsToTenant(int $eventId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM community_events WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$eventId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }
}
