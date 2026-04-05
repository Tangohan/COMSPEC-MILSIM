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
        $lim = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NULL AND starts_at >= NOW()
             ORDER BY starts_at ASC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Événements à venir avec ligne RSVP et pointage pour l’utilisateur (LEFT JOIN).
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingForTenantWithUserRsvp(int $tenantId, int $userId, int $limit = 50): array
    {
        $lim = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT ce.*,
                    r.status AS rsvp_status,
                    r.checked_in_at AS rsvp_checked_in_at,
                    r.reminder_sent_at AS rsvp_reminder_sent_at
             FROM community_events ce
             LEFT JOIN community_event_rsvps r ON r.event_id = ce.id AND r.user_id = ?
             WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL AND ce.starts_at >= NOW()
             ORDER BY ce.starts_at ASC
             LIMIT {$lim}"
        );
        $stmt->execute([$userId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Événements dont la date de début est aujourd’hui (serveur).
     *
     * @return list<array<string, mixed>>
     */
    public function todayForTenantWithUserRsvp(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ce.*,
                    r.status AS rsvp_status,
                    r.checked_in_at AS rsvp_checked_in_at
             FROM community_events ce
             LEFT JOIN community_event_rsvps r ON r.event_id = ce.id AND r.user_id = ?
             WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL
               AND DATE(ce.starts_at) = CURDATE()
             ORDER BY ce.starts_at ASC'
        );
        $stmt->execute([$userId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historique récent (passés).
     *
     * @return list<array<string, mixed>>
     */
    public function pastForTenantWithUserRsvp(int $tenantId, int $userId, int $limit = 15): array
    {
        $lim = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT ce.*,
                    r.status AS rsvp_status,
                    r.checked_in_at AS rsvp_checked_in_at
             FROM community_events ce
             LEFT JOIN community_event_rsvps r ON r.event_id = ce.id AND r.user_id = ?
             WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL AND ce.starts_at < NOW()
             ORDER BY ce.starts_at DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$userId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIdForTenant(int $eventId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM community_events WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$eventId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(
        int $tenantId,
        int $createdBy,
        string $title,
        ?string $description,
        ?string $location,
        string $startsAtIso,
        ?string $endsAtIso,
        ?string $campaignTag,
        string $eventType = 'evenement'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO community_events (tenant_id, title, description, location, campaign_tag, event_type, starts_at, ends_at, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $title,
            $description,
            $location,
            $campaignTag,
            $eventType,
            $startsAtIso,
            $endsAtIso,
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setRsvp(int $eventId, int $userId, string $status): void
    {
        if ($status === 'no') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO community_event_rsvps (event_id, user_id, status, checked_in_at, created_at) VALUES (?, ?, ?, NULL, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), checked_in_at = NULL, updated_at = NOW()'
            );
            $stmt->execute([$eventId, $userId, $status]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO community_event_rsvps (event_id, user_id, status, created_at) VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()'
            );
            $stmt->execute([$eventId, $userId, $status]);
        }
    }

    public function getRsvp(int $eventId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM community_event_rsvps WHERE event_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$eventId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRsvpsWithUsersForEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.user_id, r.status, r.checked_in_at, r.created_at AS rsvp_created_at,
                    u.email, u.display_name, u.callsign
             FROM community_event_rsvps r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.event_id = ?
             ORDER BY r.status ASC, u.display_name ASC'
        );
        $stmt->execute([$eventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<string> $statuses
     * @return list<array{user_id:int, email:string, display_name:string}>
     */
    public function listUsersForEventByStatuses(int $eventId, array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT u.id AS user_id, u.email, u.display_name
                FROM community_event_rsvps r
                INNER JOIN users u ON u.id = r.user_id
                WHERE r.event_id = ? AND r.status IN ({$placeholders})";
        $params = array_merge([$eventId], $statuses);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setCheckIn(int $eventId, int $userId, string $whenIso): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE community_event_rsvps SET checked_in_at = ?, updated_at = NOW() WHERE event_id = ? AND user_id = ?'
        );
        $stmt->execute([$whenIso, $eventId, $userId]);
    }

    public function cancelEvent(int $eventId, int $tenantId, ?string $reason): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE community_events SET cancelled_at = NOW(), cancelled_reason = ? WHERE id = ? AND tenant_id = ? AND cancelled_at IS NULL'
        );
        $stmt->execute([$reason, $eventId, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function markReminderSent(int $eventId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE community_event_rsvps SET reminder_sent_at = NOW(), updated_at = NOW() WHERE event_id = ? AND user_id = ?'
        );
        $stmt->execute([$eventId, $userId]);
    }

    /**
     * RSVP oui/peut-être sans rappel encore, événement dans les prochaines 24 h, non annulé.
     *
     * @return list<array{event_id:int, user_id:int, tenant_id:int, starts_at:string, title:string, email:string, display_name:string}>
     */
    public function listRsvpRowsEligibleForReminder(): array
    {
        $sql = 'SELECT ce.id AS event_id, ce.tenant_id, ce.starts_at, ce.title,
                       r.user_id, u.email, u.display_name
                FROM community_event_rsvps r
                INNER JOIN community_events ce ON ce.id = r.event_id
                INNER JOIN users u ON u.id = r.user_id
                WHERE ce.cancelled_at IS NULL
                  AND r.reminder_sent_at IS NULL
                  AND r.status IN (\'yes\', \'maybe\')
                  AND ce.starts_at > NOW()
                  AND ce.starts_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)';

        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function belongsToTenant(int $eventId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM community_events WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$eventId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    /** Compte les créations sur [ $startInclusive, $endExclusive [ (created_at). */
    public function countCreatedInPeriod(int $tenantId, string $startInclusive, string $endExclusive): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM community_events WHERE tenant_id = ? AND created_at >= ? AND created_at < ?'
        );
        $stmt->execute([$tenantId, $startInclusive, $endExclusive]);

        return (int) $stmt->fetchColumn();
    }
}
