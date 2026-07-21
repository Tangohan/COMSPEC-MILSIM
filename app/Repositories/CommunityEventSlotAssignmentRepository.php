<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Inscriptions nominatives sur un slot de mission (confirmées ou en liste d'attente).
 * Un membre ne peut tenir qu'un seul slot par événement (contrainte unique event_id+user_id).
 */
class CommunityEventSlotAssignmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findForUserAndEvent(int $eventId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM community_event_slot_assignments WHERE event_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$eventId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Inscrits (confirmés + liste d'attente) d'un slot, avec identité, triés confirmés d'abord puis rang d'attente. */
    public function listForSlotWithUsers(int $slotId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.user_id, a.status, a.waitlist_position, a.signed_up_at,
                    u.display_name, u.callsign, u.email
             FROM community_event_slot_assignments a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.slot_id = ?
             ORDER BY (a.status = \'confirmed\') DESC, a.waitlist_position ASC, a.signed_up_at ASC'
        );
        $stmt->execute([$slotId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Toutes les inscriptions d'un événement, groupées par slot_id, avec identité — pour
     * l'affichage membre (une requête pour tous les slots plutôt qu'une par slot).
     *
     * @return array<int, list<array<string,mixed>>>
     */
    public function listForEventGroupedBySlot(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.slot_id, a.user_id, a.status, a.waitlist_position, a.signed_up_at,
                    u.display_name, u.callsign, u.email
             FROM community_event_slot_assignments a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.event_id = ?
             ORDER BY (a.status = \'confirmed\') DESC, a.waitlist_position ASC, a.signed_up_at ASC'
        );
        $stmt->execute([$eventId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['slot_id']][] = $row;
        }

        return $out;
    }

    /**
     * Inscription du membre courant sur chacun des événements donnés (au plus une par
     * événement), en une seule requête — pour la liste membre.
     *
     * @param list<int> $eventIds
     * @return array<int, array<string, mixed>> clé = event_id
     */
    public function listForUserAcrossEvents(int $userId, array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($eventIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM community_event_slot_assignments WHERE user_id = ? AND event_id IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([$userId], $eventIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['event_id']] = $row;
        }

        return $out;
    }

    public function nextWaitlistPosition(int $slotId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(waitlist_position), 0) + 1 FROM community_event_slot_assignments WHERE slot_id = ? AND status = 'waitlisted'"
        );
        $stmt->execute([$slotId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(int $tenantId, int $slotId, int $eventId, int $userId, string $status, ?int $waitlistPosition): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO community_event_slot_assignments (tenant_id, slot_id, event_id, user_id, status, waitlist_position, signed_up_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $slotId, $eventId, $userId, $status, $waitlistPosition]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM community_event_slot_assignments WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Premier membre en liste d'attente d'un slot (le plus ancien rang), ou null si aucun. */
    public function firstWaitlisted(int $slotId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM community_event_slot_assignments WHERE slot_id = ? AND status = 'waitlisted' ORDER BY waitlist_position ASC LIMIT 1"
        );
        $stmt->execute([$slotId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function promoteToConfirmed(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE community_event_slot_assignments SET status = 'confirmed', waitlist_position = NULL WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    public function deleteAllForEvent(int $eventId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM community_event_slot_assignments WHERE event_id = ?');
        $stmt->execute([$eventId]);
    }

    public function deleteAllForSlot(int $slotId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM community_event_slot_assignments WHERE slot_id = ?');
        $stmt->execute([$slotId]);
    }
}
