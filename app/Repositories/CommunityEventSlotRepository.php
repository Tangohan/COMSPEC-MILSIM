<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Slots de mission (rôle/capacité/loadout) rattachés à un community_events — permet de faire
 * signer les membres sur un poste précis plutôt qu'un simple RSVP oui/non/peut-être.
 */
class CommunityEventSlotRepository
{
    private PDO $pdo;

    /** Cache : colonnes de prérequis de qualification présentes (déploiement migré ou non). */
    private ?bool $qualificationColumnsReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Le prérequis de qualification est-il disponible sur les postes ?
     * Sur un déploiement non migré, l'UI et les écritures l'ignorent silencieusement.
     */
    public function qualificationColumnsReady(): bool
    {
        if ($this->qualificationColumnsReady !== null) {
            return $this->qualificationColumnsReady;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'community_event_slots'
                   AND COLUMN_NAME IN ('required_training_course_id', 'qualification_enforcement')"
            );
            $st->execute();
            $this->qualificationColumnsReady = (int) $st->fetchColumn() === 2;
        } catch (\Throwable) {
            $this->qualificationColumnsReady = false;
        }

        return $this->qualificationColumnsReady;
    }

    /**
     * Slots d'un événement avec le nombre d'inscrits confirmés (une seule requête, pas de N+1).
     *
     * @return list<array<string, mixed>>
     */
    public function listForEventWithCounts(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.name AS unit_name,
                    COALESCE(SUM(CASE WHEN a.status = \'confirmed\' THEN 1 ELSE 0 END), 0) AS confirmed_count,
                    COALESCE(SUM(CASE WHEN a.status = \'waitlisted\' THEN 1 ELSE 0 END), 0) AS waitlisted_count
             FROM community_event_slots s
             LEFT JOIN units u ON u.id = s.unit_id
             LEFT JOIN community_event_slot_assignments a ON a.slot_id = s.id
             WHERE s.event_id = ?
             GROUP BY s.id
             ORDER BY s.position ASC, s.id ASC'
        );
        $stmt->execute([$eventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Slots de plusieurs événements avec compteurs, en une seule requête (liste membre —
     * évite une requête par événement affiché).
     *
     * @param list<int> $eventIds
     * @return array<int, list<array<string, mixed>>> clé = event_id
     */
    public function listForEventsWithCounts(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($eventIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.name AS unit_name,
                    COALESCE(SUM(CASE WHEN a.status = \'confirmed\' THEN 1 ELSE 0 END), 0) AS confirmed_count,
                    COALESCE(SUM(CASE WHEN a.status = \'waitlisted\' THEN 1 ELSE 0 END), 0) AS waitlisted_count
             FROM community_event_slots s
             LEFT JOIN units u ON u.id = s.unit_id
             LEFT JOIN community_event_slot_assignments a ON a.slot_id = s.id
             WHERE s.event_id IN (' . $placeholders . ')
             GROUP BY s.id
             ORDER BY s.event_id ASC, s.position ASC, s.id ASC'
        );
        $stmt->execute($eventIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['event_id']][] = $row;
        }

        return $out;
    }

    public function findByIdForEvent(int $slotId, int $eventId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM community_event_slots WHERE id = ? AND event_id = ? LIMIT 1');
        $stmt->execute([$slotId, $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countConfirmed(int $slotId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM community_event_slot_assignments WHERE slot_id = ? AND status = 'confirmed'"
        );
        $stmt->execute([$slotId]);

        return (int) $stmt->fetchColumn();
    }

    public function nextPositionForEvent(int $eventId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM community_event_slots WHERE event_id = ?');
        $stmt->execute([$eventId]);

        return (int) $stmt->fetchColumn();
    }

    public function create(int $tenantId, int $eventId, array $data): int
    {
        $columns = ['tenant_id', 'event_id', 'label', 'unit_id', 'capacity', 'loadout_notes', 'position'];
        $values = [
            $tenantId,
            $eventId,
            $data['label'],
            $data['unit_id'] ?? null,
            $data['capacity'] ?? 1,
            $data['loadout_notes'] ?? null,
            $data['position'] ?? $this->nextPositionForEvent($eventId),
        ];

        if ($this->qualificationColumnsReady()) {
            $columns[] = 'required_training_course_id';
            $values[] = $data['required_training_course_id'] ?? null;
            $columns[] = 'qualification_enforcement';
            $values[] = $data['qualification_enforcement'] ?? 'advisory';
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO community_event_slots (' . implode(', ', $columns) . ', created_at)
             VALUES (' . $placeholders . ', NOW())'
        );
        $stmt->execute($values);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $slotId, int $eventId, array $data): bool
    {
        $cols = ['label', 'unit_id', 'capacity', 'loadout_notes', 'position'];
        if ($this->qualificationColumnsReady()) {
            $cols[] = 'required_training_course_id';
            $cols[] = 'qualification_enforcement';
        }
        $sets = [];
        $params = [];
        foreach ($cols as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[] = "{$col} = ?";
            $params[] = $data[$col];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $slotId;
        $params[] = $eventId;
        $stmt = $this->pdo->prepare(
            'UPDATE community_event_slots SET ' . implode(', ', $sets) . ' WHERE id = ? AND event_id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $slotId, int $eventId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM community_event_slots WHERE id = ? AND event_id = ?');
        $stmt->execute([$slotId, $eventId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteAllForEvent(int $eventId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM community_event_slots WHERE event_id = ?');
        $stmt->execute([$eventId]);
    }
}
