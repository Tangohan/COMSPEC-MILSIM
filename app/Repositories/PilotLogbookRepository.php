<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Carnet de vol (pilot_logbook_entries) : vols relevés par le mod, une ligne par mission, jour et appareil, et vols
 * saisis par le pilote. Statuts : verified (relevé en jeu, rien à contresigner), declared (saisi ou annoté, en attente
 * d'un instructeur), countersigned, rejected.
 */
final class PilotLogbookRepository
{
    public const SOURCE_MOD = 'mod';
    public const SOURCE_PILOT = 'pilot';

    public const VERIFIED = 'verified';
    public const DECLARED = 'declared';
    public const COUNTERSIGNED = 'countersigned';
    public const REJECTED = 'rejected';

    private ?PDO $pdo;
    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pilot_logbook_entries' LIMIT 1");
                self::$ready = $st !== false && (bool) $st->fetchColumn();
            } catch (Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Vols d'un envoi du serveur Arma : cumulés sur la ligne de la mission, du jour et de l'appareil.
     *
     * @param list<array{class: string, label: string, kind: string, seconds: int, meters: int}> $pilot
     */
    public function recordModFlights(int $tenantId, int $userId, string $missionHash, string $missionKey, string $day, array $pilot): void
    {
        if ($pilot === [] || !$this->schemaReady()) {
            return;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO pilot_logbook_entries
                (tenant_id, user_id, source, flown_on, mission_hash, mission_key, vehicle_class, vehicle_label, vehicle_kind, seconds, meters, status)
             VALUES (?, ?, \'mod\', ?, ?, ?, ?, ?, ?, ?, ?, \'verified\')
             ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds), meters = meters + VALUES(meters),
                vehicle_label = IF(VALUES(vehicle_label) = \'\', vehicle_label, VALUES(vehicle_label))'
        );
        foreach ($pilot as $it) {
            $st->execute([
                $tenantId, $userId, $day, $missionHash, mb_substr($missionKey, 0, 160), $it['class'], $it['label'], $it['kind'],
                $it['seconds'], $it['meters'],
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $tenantId, int $userId, int $limit = 500): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT e.*, r.display_name AS reviewer_name, r.callsign AS reviewer_callsign
             FROM pilot_logbook_entries e LEFT JOIN users r ON r.id = e.reviewed_by
             WHERE e.tenant_id = ? AND e.user_id = ?
             ORDER BY e.flown_on DESC, e.id DESC LIMIT ' . max(1, min(2000, $limit))
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vols contresignés de la communauté (ceux qui ajoutent des heures au cursus), par membre.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function countersignedByUser(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare("SELECT * FROM pilot_logbook_entries WHERE tenant_id = ? AND status = 'countersigned'");
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['user_id']][] = $row;
        }

        return $out;
    }

    /** Vols à contresigner de la communauté, les plus anciens d'abord. @return list<array<string, mixed>> */
    public function listDeclared(int $tenantId, int $limit = 200): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT e.*, u.display_name, u.callsign FROM pilot_logbook_entries e JOIN users u ON u.id = e.user_id
             WHERE e.tenant_id = ? AND e.status = 'declared' ORDER BY e.updated_at ASC, e.id ASC LIMIT " . max(1, min(1000, $limit))
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM pilot_logbook_entries WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $d champs normalisés (PilotLogbookService::normalizeEntry) */
    public function createPilotEntry(int $tenantId, int $userId, array $d): int
    {
        $st = $this->pdo()->prepare(
            "INSERT INTO pilot_logbook_entries
                (tenant_id, user_id, source, flown_on, mission_hash, mission_key, vehicle_class, vehicle_label, vehicle_kind, seconds,
                 crew_role, night_seconds, ifr_seconds, formation_seconds, landings, remarks, status)
             VALUES (?, ?, 'pilot', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'declared')"
        );
        $st->execute([
            $tenantId, $userId, $d['flown_on'], sha1('pilot@' . microtime(true) . '@' . random_int(0, PHP_INT_MAX)), $d['mission_key'],
            $d['vehicle_class'], $d['vehicle_label'], $d['vehicle_kind'], $d['seconds'], $d['crew_role'],
            $d['night_seconds'], $d['ifr_seconds'], $d['formation_seconds'], $d['landings'], $d['remarks'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Le pilote corrige ou annote un de ses vols ; un vol saisi garde ses champs de vol, un vol relevé en jeu ne prend
     * que les annotations.
     *
     * @param array<string, mixed> $d
     */
    public function updateByPilot(int $tenantId, int $id, array $d, bool $flightFields, string $status): bool
    {
        $sets = ['crew_role = ?', 'night_seconds = ?', 'ifr_seconds = ?', 'formation_seconds = ?', 'landings = ?', 'remarks = ?', 'status = ?',
            'reviewed_by = NULL', 'reviewed_at = NULL', 'review_note = NULL'];
        $vals = [$d['crew_role'], $d['night_seconds'], $d['ifr_seconds'], $d['formation_seconds'], $d['landings'], $d['remarks'], $status];
        if ($flightFields) {
            array_unshift($sets, 'flown_on = ?', 'mission_key = ?', 'vehicle_class = ?', 'vehicle_label = ?', 'vehicle_kind = ?', 'seconds = ?');
            array_unshift($vals, $d['flown_on'], $d['mission_key'], $d['vehicle_class'], $d['vehicle_label'], $d['vehicle_kind'], $d['seconds']);
        }
        $vals[] = $tenantId;
        $vals[] = $id;
        $st = $this->pdo()->prepare('UPDATE pilot_logbook_entries SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE tenant_id = ? AND id = ?');

        return $st->execute($vals);
    }

    public function review(int $tenantId, int $id, string $status, int $reviewerId, ?string $note): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE pilot_logbook_entries SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([$status, $reviewerId, $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null, $tenantId, $id]);
    }

    public function deletePilotEntry(int $tenantId, int $id): bool
    {
        $st = $this->pdo()->prepare("DELETE FROM pilot_logbook_entries WHERE tenant_id = ? AND id = ? AND source = 'pilot'");

        return $st->execute([$tenantId, $id]);
    }
}
