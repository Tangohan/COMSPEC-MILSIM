<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\PilotFleet;
use PDO;
use Throwable;

/**
 * Flotte aérienne de la communauté : familles (pilot_aircraft_groups), fiches d'appareils créées ou réglées
 * (pilot_aircraft) et classes Arma rattachées à chaque appareil (pilot_aircraft_classes), plus les appareils
 * réellement pilotés relevés par le mod (atak_pilot_time) et le nombre de vols du carnet (pilot_logbook_entries).
 */
final class PilotFleetRepository
{
    private ?PDO $pdo;
    private ?bool $ready = null;

    /** @var array<int, PilotFleet> */
    private array $fleets = [];

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
        if ($this->ready === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM pilot_aircraft_classes LIMIT 1');
                $this->pdo()->query('SELECT 1 FROM pilot_aircraft_groups LIMIT 1');
                $this->ready = true;
            } catch (Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    /** Flotte de la communauté (gardée pour la requête). */
    public function fleet(int $tenantId): PilotFleet
    {
        if (isset($this->fleets[$tenantId])) {
            return $this->fleets[$tenantId];
        }
        if ($tenantId < 1 || !$this->schemaReady()) {
            return PilotFleet::empty();
        }
        $groups = [];
        $aircraft = [];
        $classes = [];
        try {
            $st = $this->pdo()->prepare('SELECT group_key, name, sort_order, archived_at FROM pilot_aircraft_groups WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $g) {
                $groups[(string) $g['group_key']] = ['name' => (string) $g['name'], 'sort' => (int) $g['sort_order'], 'archived' => $g['archived_at'] !== null];
            }
            $st = $this->pdo()->prepare('SELECT * FROM pilot_aircraft WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $ids = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
                $aircraft[(string) $a['aircraft_key']] = $a;
                $ids[(int) $a['id']] = (string) $a['aircraft_key'];
            }
            $st = $this->pdo()->prepare('SELECT aircraft_id, vehicle_class FROM pilot_aircraft_classes WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
                if (isset($ids[(int) $c['aircraft_id']])) {
                    $classes[strtolower((string) $c['vehicle_class'])] = $ids[(int) $c['aircraft_id']];
                }
            }
        } catch (Throwable) {
            return PilotFleet::empty();
        }

        return $this->fleets[$tenantId] = new PilotFleet($groups, $aircraft, $classes);
    }

    public function forget(int $tenantId): void
    {
        unset($this->fleets[$tenantId]);
    }

    /**
     * Appareils pilotés dans la communauté, par classe : heures cumulées, pilotes, dernier vol.
     *
     * @return list<array{vehicle_class: string, vehicle_label: string, vehicle_kind: string, seconds: int, pilots: int, last_flown_at: string}>
     */
    public function flownClasses(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT vehicle_class, MAX(vehicle_label) AS vehicle_label, MAX(vehicle_kind) AS vehicle_kind, SUM(seconds) AS seconds,
                        COUNT(DISTINCT user_id) AS pilots, MAX(last_flown_at) AS last_flown_at
                 FROM atak_pilot_time WHERE tenant_id = ? AND seconds > 0 GROUP BY vehicle_class ORDER BY seconds DESC LIMIT 500'
            );
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'vehicle_class' => (string) $r['vehicle_class'],
            'vehicle_label' => (string) ($r['vehicle_label'] ?? ''),
            'vehicle_kind' => (string) ($r['vehicle_kind'] ?? 'heli'),
            'seconds' => (int) $r['seconds'],
            'pilots' => (int) $r['pilots'],
            'last_flown_at' => (string) ($r['last_flown_at'] ?? ''),
        ], $rows);
    }

    /**
     * Pilotes par classe (pour compter les pilotes d'un appareil à plusieurs classes sans les compter deux fois).
     *
     * @return array<string, list<int>> classe en minuscules => membres
     */
    public function pilotsByClass(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT vehicle_class, user_id FROM atak_pilot_time WHERE tenant_id = ? AND seconds > 0');
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[strtolower((string) $r['vehicle_class'])][] = (int) $r['user_id'];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Vols du carnet par membre et par classe : un par mission et par appareil relevé par le mod, plus les vols
     * saisis par le pilote une fois contresignés.
     *
     * @return array<int, list<array{vehicle_class: string, vehicle_label: string, vehicle_kind: string, flights: int}>>
     */
    public function flightCountsByUser(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare(
                "SELECT user_id, vehicle_class, MAX(vehicle_label) AS vehicle_label, MAX(vehicle_kind) AS vehicle_kind, COUNT(*) AS flights
                 FROM pilot_logbook_entries
                 WHERE tenant_id = ? AND seconds > 0 AND (source = 'mod' OR status = 'countersigned')
                 GROUP BY user_id, vehicle_class"
            );
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['user_id']][] = [
                    'vehicle_class' => (string) $r['vehicle_class'],
                    'vehicle_label' => (string) ($r['vehicle_label'] ?? ''),
                    'vehicle_kind' => (string) ($r['vehicle_kind'] ?? 'heli'),
                    'flights' => (int) $r['flights'],
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function findAircraft(int $tenantId, string $key): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM pilot_aircraft WHERE tenant_id = ? AND aircraft_key = ? LIMIT 1');
        $st->execute([$tenantId, $key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Crée ou met à jour la fiche d'un appareil.
     *
     * @param array{name: string, track: ?string, group_key: ?string, image_path?: ?string, is_custom: int, archived: bool} $data
     */
    public function saveAircraft(int $tenantId, string $key, array $data, ?int $actorId): int
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->findAircraft($tenantId, $key);
        $archivedAt = $data['archived'] ? (($existing['archived_at'] ?? null) ?: $now) : null;
        if ($existing === null) {
            $st = $this->pdo()->prepare(
                'INSERT INTO pilot_aircraft (tenant_id, aircraft_key, name, track, group_key, image_path, is_custom, archived_at, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$tenantId, $key, $data['name'], $data['track'], $data['group_key'], $data['image_path'] ?? null, $data['is_custom'], $archivedAt, $actorId, $now, $now]);
            $this->forget($tenantId);

            return (int) $this->pdo()->lastInsertId();
        }
        $image = array_key_exists('image_path', $data) && $data['image_path'] !== null ? $data['image_path'] : ($existing['image_path'] ?? null);
        $st = $this->pdo()->prepare(
            'UPDATE pilot_aircraft SET name = ?, track = ?, group_key = ?, image_path = ?, archived_at = ?, updated_at = ? WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$data['name'], $data['track'], $data['group_key'], $image, $archivedAt, $now, $tenantId, (int) $existing['id']]);
        $this->forget($tenantId);

        return (int) $existing['id'];
    }

    public function removeImage(int $tenantId, int $aircraftId): void
    {
        $this->pdo()->prepare('UPDATE pilot_aircraft SET image_path = NULL, updated_at = ? WHERE tenant_id = ? AND id = ?')
            ->execute([date('Y-m-d H:i:s'), $tenantId, $aircraftId]);
        $this->forget($tenantId);
    }

    /**
     * Classes Arma d'un appareil : la liste envoyée remplace l'ancienne. Une classe déjà rattachée à un autre appareil
     * de la communauté y est reprise (une classe n'appartient qu'à un appareil).
     *
     * @param list<string> $classes
     */
    public function setClasses(int $tenantId, int $aircraftId, array $classes): void
    {
        $classes = array_values(array_unique(array_map(static fn (string $c): string => strtolower($c), $classes)));
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM pilot_aircraft_classes WHERE tenant_id = ? AND aircraft_id = ?')->execute([$tenantId, $aircraftId]);
            if ($classes !== []) {
                $in = implode(',', array_fill(0, count($classes), '?'));
                $pdo->prepare("DELETE FROM pilot_aircraft_classes WHERE tenant_id = ? AND vehicle_class IN ({$in})")->execute(array_merge([$tenantId], $classes));
                $ins = $pdo->prepare('INSERT INTO pilot_aircraft_classes (tenant_id, aircraft_id, vehicle_class, created_at) VALUES (?, ?, ?, ?)');
                foreach ($classes as $c) {
                    $ins->execute([$tenantId, $aircraftId, $c, date('Y-m-d H:i:s')]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->forget($tenantId);
    }

    /** Crée, renomme ou archive une famille. */
    public function saveGroup(int $tenantId, string $key, string $name, int $sort, bool $archived, ?int $actorId): void
    {
        $now = date('Y-m-d H:i:s');
        $st = $this->pdo()->prepare('SELECT id, archived_at FROM pilot_aircraft_groups WHERE tenant_id = ? AND group_key = ? LIMIT 1');
        $st->execute([$tenantId, $key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->pdo()->prepare(
                'INSERT INTO pilot_aircraft_groups (tenant_id, group_key, name, sort_order, archived_at, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$tenantId, $key, $name, $sort, $archived ? $now : null, $actorId, $now, $now]);
        } else {
            $this->pdo()->prepare('UPDATE pilot_aircraft_groups SET name = ?, sort_order = ?, archived_at = ?, updated_at = ? WHERE id = ?')
                ->execute([$name, $sort, $archived ? ($row['archived_at'] ?? $now) : null, $now, (int) $row['id']]);
        }
        $this->forget($tenantId);
    }

    public function groupExists(int $tenantId, string $key): bool
    {
        $st = $this->pdo()->prepare('SELECT 1 FROM pilot_aircraft_groups WHERE tenant_id = ? AND group_key = ? LIMIT 1');
        $st->execute([$tenantId, $key]);

        return (bool) $st->fetchColumn();
    }
}
