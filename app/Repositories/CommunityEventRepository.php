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

    /**
     * Actifs (commencés mais pas encore terminés) + à venir.
     * Fin = ends_at si renseigné, sinon starts_at.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingForTenant(int $tenantId, int $limit = 50): array
    {
        $lim = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NULL
               AND COALESCE(ends_at, starts_at) >= NOW()
             ORDER BY starts_at ASC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Créneaux vraiment terminés (non annulés). @return list<array<string, mixed>> */
    public function pastForTenant(int $tenantId, int $limit = 100): array
    {
        $lim = max(1, min(150, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NULL
               AND COALESCE(ends_at, starts_at) < NOW()
             ORDER BY starts_at DESC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function cancelledForTenant(int $tenantId, int $limit = 100): array
    {
        $lim = max(1, min(150, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NOT NULL
             ORDER BY cancelled_at DESC LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actifs + à venir, avec ligne RSVP et pointage pour l’utilisateur (LEFT JOIN).
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
             WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL
               AND COALESCE(ce.ends_at, ce.starts_at) >= NOW()
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
     * Historique récent (vraiment terminés).
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
             WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL
               AND COALESCE(ce.ends_at, ce.starts_at) < NOW()
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

    /**
     * Enrichit un créneau (image, conditions, déroulement, étiquettes).
     *
     * @param array{
     *   cover_image_path?: ?string,
     *   conditions_general?: ?string,
     *   conditions_special?: ?string,
     *   schedule_json?: ?string,
     *   tags_json?: ?string,
     *   description?: ?string,
     *   location?: ?string,
     *   title?: ?string
     * } $data
     */
    public function updateDetails(int $eventId, int $tenantId, array $data): bool
    {
        $sets = [];
        $params = [];
        $map = [
            'title' => 'title',
            'description' => 'description',
            'location' => 'location',
            'cover_image_path' => 'cover_image_path',
            'conditions_general' => 'conditions_general',
            'conditions_special' => 'conditions_special',
            'schedule_json' => 'schedule_json',
            'tags_json' => 'tags_json',
        ];
        foreach ($map as $key => $col) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if (!$this->hasColumn($col)) {
                continue;
            }
            $sets[] = "{$col} = ?";
            $params[] = $data[$key];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $eventId;
        $params[] = $tenantId;
        $sql = 'UPDATE community_events SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ? AND cancelled_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Synthèse des réponses pour plusieurs créneaux (affichage public).
     *
     * @param list<int> $eventIds
     * @return array<int, array{yes:list<array{display_name:string,callsign:string}>,maybe:list<array{display_name:string,callsign:string}>,no:list<array{display_name:string,callsign:string}>}>
     */
    public function rsvpSummariesForEvents(array $eventIds): array
    {
        $eventIds = array_values(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0));
        if ($eventIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT r.event_id, r.status, u.display_name, u.callsign
             FROM community_event_rsvps r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.event_id IN ({$placeholders})
             ORDER BY u.display_name ASC"
        );
        $stmt->execute($eventIds);
        $out = [];
        foreach ($eventIds as $eid) {
            $out[$eid] = ['yes' => [], 'maybe' => [], 'no' => []];
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int) ($row['event_id'] ?? 0);
            $st = (string) ($row['status'] ?? '');
            if (!isset($out[$eid]) || !isset($out[$eid][$st])) {
                continue;
            }
            $out[$eid][$st][] = [
                'display_name' => (string) ($row['display_name'] ?? ''),
                'callsign' => trim((string) ($row['callsign'] ?? '')),
            ];
        }

        return $out;
    }

    private function hasColumn(string $name): bool
    {
        static $cache = [];
        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_events' AND COLUMN_NAME = ? LIMIT 1"
            );
            $st->execute([$name]);
            $cache[$name] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cache[$name] = false;
        }

        return $cache[$name];
    }

    public function setRsvp(
        int $eventId,
        int $userId,
        string $status,
        ?string $absenceReason = null,
        ?string $absenceNote = null,
        ?int $actorUserId = null
    ): void {
        $prev = $this->getRsvp($eventId, $userId);
        $prevStatus = $prev !== null ? (string) ($prev['status'] ?? '') : null;
        $prevReason = $prev !== null ? (isset($prev['absence_reason']) ? (string) $prev['absence_reason'] : null) : null;
        $prevNote = $prev !== null ? (isset($prev['absence_note']) ? (string) $prev['absence_note'] : null) : null;

        $reason = $absenceReason !== null ? trim($absenceReason) : null;
        $note = $absenceNote !== null ? trim($absenceNote) : null;
        if ($status === 'no') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO community_event_rsvps (event_id, user_id, status, absence_reason, absence_note, checked_in_at, created_at) VALUES (?, ?, ?, ?, ?, NULL, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), absence_reason = VALUES(absence_reason), absence_note = VALUES(absence_note), checked_in_at = NULL, updated_at = NOW()'
            );
            $stmt->execute([$eventId, $userId, $status, $reason, $note !== '' ? $note : null]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO community_event_rsvps (event_id, user_id, status, created_at) VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), absence_reason = NULL, absence_note = NULL, updated_at = NOW()'
            );
            $stmt->execute([$eventId, $userId, $status]);
            $reason = null;
            $note = null;
        }

        $changed = $prevStatus !== $status
            || ($status === 'no' && (($prevReason ?? '') !== ($reason ?? '') || ($prevNote ?? '') !== ($note ?? '')))
            || $prev === null;
        if ($changed) {
            $this->appendRsvpHistory(
                $eventId,
                $userId,
                $actorUserId ?? $userId,
                'rsvp_set',
                $prevStatus !== '' ? $prevStatus : null,
                $status,
                $reason,
                $note !== null && $note !== '' ? $note : null
            );
        }
    }

    /**
     * Toutes les participations d'un membre, avec le titre de l'événement — pour l'export
     * de données personnelles (RGPD).
     *
     * @return list<array<string, mixed>>
     */
    public function listRsvpsForUser(int $userId, int $tenantId, int $limit = 2000): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.event_id, e.title AS event_title, e.starts_at, r.status, r.absence_reason, r.checked_in_at, r.created_at
             FROM community_event_rsvps r
             INNER JOIN community_events e ON e.id = r.event_id
             WHERE e.tenant_id = ? AND r.user_id = ?
             ORDER BY e.starts_at DESC
             LIMIT ' . max(1, min(5000, $limit))
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
            'SELECT r.user_id, r.status, r.absence_reason, r.absence_note, r.checked_in_at, r.reminder_sent_at, r.created_at AS rsvp_created_at,
                    u.email, u.display_name, u.callsign
             FROM community_event_rsvps r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.event_id = ?
             ORDER BY r.status ASC, u.display_name ASC'
        );
        $stmt->execute([$eventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteRsvp(int $eventId, int $userId, ?int $actorUserId = null): void
    {
        $prev = $this->getRsvp($eventId, $userId);
        $stmt = $this->pdo->prepare(
            'DELETE FROM community_event_rsvps WHERE event_id = ? AND user_id = ?'
        );
        $stmt->execute([$eventId, $userId]);
        if ($prev !== null) {
            $this->appendRsvpHistory(
                $eventId,
                $userId,
                $actorUserId ?? $userId,
                'rsvp_remove',
                (string) ($prev['status'] ?? ''),
                null,
                isset($prev['absence_reason']) ? (string) $prev['absence_reason'] : null,
                isset($prev['absence_note']) ? (string) $prev['absence_note'] : null
            );
        }
    }

    public function clearCheckIn(int $eventId, int $userId, ?int $actorUserId = null): void
    {
        $prev = $this->getRsvp($eventId, $userId);
        $stmt = $this->pdo->prepare(
            'UPDATE community_event_rsvps SET checked_in_at = NULL, updated_at = NOW() WHERE event_id = ? AND user_id = ?'
        );
        $stmt->execute([$eventId, $userId]);
        if ($prev !== null && !empty($prev['checked_in_at'])) {
            $this->appendRsvpHistory(
                $eventId,
                $userId,
                $actorUserId ?? $userId,
                'check_in_clear',
                (string) ($prev['status'] ?? ''),
                (string) ($prev['status'] ?? ''),
                null,
                null
            );
        }
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

    public function setCheckIn(int $eventId, int $userId, string $whenIso, ?int $actorUserId = null): void
    {
        $prev = $this->getRsvp($eventId, $userId);
        $stmt = $this->pdo->prepare(
            'UPDATE community_event_rsvps SET checked_in_at = ?, updated_at = NOW() WHERE event_id = ? AND user_id = ?'
        );
        $stmt->execute([$whenIso, $eventId, $userId]);
        $this->appendRsvpHistory(
            $eventId,
            $userId,
            $actorUserId ?? $userId,
            'check_in',
            $prev !== null ? (string) ($prev['status'] ?? '') : null,
            $prev !== null ? (string) ($prev['status'] ?? '') : null,
            null,
            null
        );
    }

    public function rsvpHistoryTableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_event_rsvp_history' LIMIT 1"
            );
            $cache = $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $cache = false;
        }

        return $cache;
    }

    public function appendRsvpHistory(
        int $eventId,
        int $userId,
        ?int $actorUserId,
        string $action,
        ?string $statusFrom,
        ?string $statusTo,
        ?string $absenceReason = null,
        ?string $absenceNote = null
    ): void {
        if ($eventId < 1 || $userId < 1 || !$this->rsvpHistoryTableExists()) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO community_event_rsvp_history
                    (event_id, user_id, actor_user_id, action, status_from, status_to, absence_reason, absence_note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $eventId,
                $userId,
                $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
                $action,
                $statusFrom !== null && $statusFrom !== '' ? $statusFrom : null,
                $statusTo !== null && $statusTo !== '' ? $statusTo : null,
                $absenceReason !== null && $absenceReason !== '' ? $absenceReason : null,
                $absenceNote !== null && $absenceNote !== '' ? mb_substr($absenceNote, 0, 500) : null,
            ]);
        } catch (\Throwable) {
            // Ne jamais bloquer le flux métier si l’historique échoue.
        }
    }

    /**
     * Historique des changements pour un membre, groupé par événement.
     *
     * @param list<int> $eventIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listRsvpHistoryForUserByEvents(int $userId, array $eventIds, int $perEvent = 12): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($userId < 1 || $eventIds === [] || !$this->rsvpHistoryTableExists()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $limit = max(1, min(40, $perEvent)) * count($eventIds);
        $limit = max(1, min(500, $limit));
        $sql = "SELECT h.event_id, h.action, h.status_from, h.status_to, h.absence_reason, h.absence_note,
                       h.actor_user_id, h.created_at,
                       a.display_name AS actor_display_name, a.email AS actor_email
                FROM community_event_rsvp_history h
                LEFT JOIN users a ON a.id = h.actor_user_id
                WHERE h.user_id = ? AND h.event_id IN ({$placeholders})
                ORDER BY h.created_at DESC, h.id DESC
                LIMIT {$limit}";
        $params = array_merge([$userId], $eventIds);
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $eid = (int) ($row['event_id'] ?? 0);
            if ($eid < 1) {
                continue;
            }
            if (!isset($out[$eid])) {
                $out[$eid] = [];
            }
            if (count($out[$eid]) >= $perEvent) {
                continue;
            }
            $out[$eid][] = $row;
        }

        return $out;
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

    /**
     * @return array{confirmed_yes:int,effective_yes:int,no_show_yes:int}
     */
    public function attendanceKpisForTenant(int $tenantId, int $windowDays = 90): array
    {
        $days = max(7, min(365, $windowDays));
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN r.status = 'yes' THEN 1 ELSE 0 END) AS confirmed_yes,
                SUM(CASE WHEN r.status = 'yes' AND r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS effective_yes,
                SUM(CASE WHEN r.status = 'yes' AND r.checked_in_at IS NULL AND ce.starts_at < NOW() THEN 1 ELSE 0 END) AS no_show_yes
            FROM community_event_rsvps r
            INNER JOIN community_events ce ON ce.id = r.event_id
            WHERE ce.tenant_id = ?
              AND ce.cancelled_at IS NULL
              AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'confirmed_yes' => (int) ($row['confirmed_yes'] ?? 0),
            'effective_yes' => (int) ($row['effective_yes'] ?? 0),
            'no_show_yes' => (int) ($row['no_show_yes'] ?? 0),
        ];
    }

    /** @return list<array{absence_reason:string,total:int}> */
    public function absenceReasonBreakdownForTenant(int $tenantId, int $windowDays = 90, int $limit = 6): array
    {
        $days = max(7, min(365, $windowDays));
        $lim = max(3, min(12, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(NULLIF(r.absence_reason, ''), 'non_renseigne') AS absence_reason, COUNT(*) AS total
             FROM community_event_rsvps r
             INNER JOIN community_events ce ON ce.id = r.event_id
             WHERE ce.tenant_id = ?
               AND ce.cancelled_at IS NULL
               AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
               AND r.status = 'no'
             GROUP BY COALESCE(NULLIF(r.absence_reason, ''), 'non_renseigne')
             ORDER BY total DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{day_of_week:int,hour_slot:int,sample_size:int,attendance_rate:float}> */
    public function recommendedSlotsForTenant(int $tenantId, int $windowDays = 120, int $limit = 3): array
    {
        $days = max(30, min(365, $windowDays));
        $lim = max(1, min(8, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT
                DAYOFWEEK(ce.starts_at) AS day_of_week,
                HOUR(ce.starts_at) AS hour_slot,
                COUNT(*) AS sample_size,
                AVG(CASE WHEN r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS attendance_rate
             FROM community_event_rsvps r
             INNER JOIN community_events ce ON ce.id = r.event_id
             WHERE ce.tenant_id = ?
               AND ce.cancelled_at IS NULL
               AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
               AND r.status IN ('yes', 'maybe')
             GROUP BY DAYOFWEEK(ce.starts_at), HOUR(ce.starts_at)
             HAVING COUNT(*) >= 3
             ORDER BY attendance_rate DESC, sample_size DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $row): array => [
                'day_of_week' => (int) ($row['day_of_week'] ?? 0),
                'hour_slot' => (int) ($row['hour_slot'] ?? 0),
                'sample_size' => (int) ($row['sample_size'] ?? 0),
                'attendance_rate' => isset($row['attendance_rate']) ? (float) $row['attendance_rate'] : 0.0,
            ],
            $rows
        );
    }

    /**
     * @return list<array{user_id:int,display_name:string,regularity_score:float,commitments:int}>
     */
    public function regularityScoresForTenant(int $tenantId, int $windowDays = 60, int $limit = 8): array
    {
        $days = max(14, min(180, $windowDays));
        $lim = max(3, min(20, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id AS user_id,
                u.display_name,
                SUM(CASE WHEN r.status IN ('yes', 'maybe') THEN 1 ELSE 0 END) AS commitments,
                AVG(CASE WHEN r.status IN ('yes', 'maybe') THEN CASE WHEN r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END END) AS regularity_score
             FROM community_event_rsvps r
             INNER JOIN community_events ce ON ce.id = r.event_id
             INNER JOIN users u ON u.id = r.user_id
             WHERE ce.tenant_id = ?
               AND ce.cancelled_at IS NULL
               AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
             GROUP BY u.id, u.display_name
             HAVING commitments >= 2
             ORDER BY regularity_score ASC, commitments DESC
             LIMIT {$lim}"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $row): array => [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'display_name' => (string) ($row['display_name'] ?? 'Membre'),
                'regularity_score' => isset($row['regularity_score']) ? (float) $row['regularity_score'] : 0.0,
                'commitments' => (int) ($row['commitments'] ?? 0),
            ],
            $rows
        );
    }

    public function newMembersParticipationDeltaForTenant(int $tenantId, int $windowDays = 120): float
    {
        $days = max(90, min(365, $windowDays));
        $stmt = $this->pdo->prepare(
            "SELECT
                AVG(CASE WHEN ce.starts_at >= u.created_at AND ce.starts_at < DATE_ADD(u.created_at, INTERVAL 30 DAY) AND r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS first_30,
                AVG(CASE WHEN ce.starts_at >= DATE_ADD(u.created_at, INTERVAL 30 DAY) AND ce.starts_at < DATE_ADD(u.created_at, INTERVAL 90 DAY) AND r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS next_60
             FROM users u
             LEFT JOIN community_event_rsvps r ON r.user_id = u.id
             LEFT JOIN community_events ce ON ce.id = r.event_id AND ce.tenant_id = u.tenant_id AND ce.cancelled_at IS NULL
             WHERE u.tenant_id = ?
               AND u.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $first = isset($row['first_30']) ? (float) $row['first_30'] : 0.0;
        $next = isset($row['next_60']) ? (float) $row['next_60'] : 0.0;

        return $next - $first;
    }
}
