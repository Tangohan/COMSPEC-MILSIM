<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AtakDistanceRepository;
use App\Repositories\PilotLogbookRepository;
use App\Support\PilotSyllabusCatalog;
use DateTimeImmutable;
use RuntimeException;

/**
 * Carnet de vol : le pilote saisit ses vols et annote ceux relevés en jeu (fonction à bord, heures de nuit, aux
 * instruments, en patrouille, atterrissages) ; un instructeur de la filière contresigne, comme sur un vrai carnet.
 * Ce qui est déclaré ne compte pour le cursus qu'une fois contresigné.
 */
final class PilotLogbookService
{
    public function __construct(
        private PilotLogbookRepository $logbook,
        private PilotQualificationService $syllabus,
    ) {
    }

    public function schemaReady(): bool
    {
        return $this->logbook->schemaReady();
    }

    /**
     * Saisie du formulaire -> champs d'une ligne du carnet. Durées en « 1:30 », « 1h30 », « 1,5 » ou « 90 min ».
     *
     * @param array<string, mixed> $in
     * @return array{flown_on: string, mission_key: ?string, vehicle_class: string, vehicle_label: string, vehicle_kind: string, seconds: int, crew_role: ?string, night_seconds: int, ifr_seconds: int, formation_seconds: int, landings: int, remarks: ?string}
     */
    public static function normalizeEntry(array $in, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) ($in['flown_on'] ?? '')));
        if ($date === false || $date > $today || $date < new DateTimeImmutable('2000-01-01')) {
            throw new RuntimeException('Date du vol invalide (pas de vol dans le futur).');
        }
        $seconds = self::parseDuration((string) ($in['duration'] ?? ''));
        if ($seconds < 60 || $seconds > 86400) {
            throw new RuntimeException('Durée du vol invalide : entre 1 minute et 24 heures, par exemple 1:30.');
        }
        $kind = ($in['vehicle_kind'] ?? '') === 'plane' ? PilotSyllabusCatalog::TRACK_PLANE : PilotSyllabusCatalog::TRACK_HELI;
        $familyKey = trim((string) ($in['aircraft'] ?? ''));
        $known = PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_HELI) + PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_PLANE);
        if (isset($known[$familyKey])) {
            $label = $known[$familyKey];
            $kind = PilotSyllabusCatalog::familyTrack($familyKey);
        } else {
            $label = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($in['aircraft_other'] ?? ''))), 0, 120);
            if ($label === '') {
                throw new RuntimeException('Indiquez l’appareil piloté.');
            }
        }
        $class = substr(trim((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $label), '_'), 0, 80);
        $role = (string) ($in['crew_role'] ?? '');
        $cond = [];
        foreach (['night_seconds' => 'night', 'ifr_seconds' => 'ifr', 'formation_seconds' => 'formation'] as $col => $field) {
            $raw = trim((string) ($in[$field] ?? ''));
            $cond[$col] = $raw === '' ? 0 : min($seconds, self::parseDuration($raw));
        }
        $remarks = mb_substr(trim((string) ($in['remarks'] ?? '')), 0, 500);
        $mission = mb_substr(trim((string) ($in['mission'] ?? '')), 0, 160);

        return [
            'flown_on' => $date->format('Y-m-d'),
            'mission_key' => $mission !== '' ? $mission : null,
            'vehicle_class' => $class,
            'vehicle_label' => $label,
            'vehicle_kind' => $kind,
            'seconds' => $seconds,
            'crew_role' => isset(PilotSyllabusCatalog::crewRoles()[$role]) ? $role : null,
            'night_seconds' => $cond['night_seconds'],
            'ifr_seconds' => $cond['ifr_seconds'],
            'formation_seconds' => $cond['formation_seconds'],
            'landings' => max(0, min(999, (int) ($in['landings'] ?? 0))),
            'remarks' => $remarks !== '' ? $remarks : null,
        ];
    }

    /** « 1:30 », « 1h30 », « 1 h 30 », « 1,5 », « 90 min » -> secondes ; 0 si illisible. */
    public static function parseDuration(string $raw): int
    {
        $t = mb_strtolower(trim($raw));
        if ($t === '') {
            return 0;
        }
        if (preg_match('/^(\d{1,2})\s*(?::|h)\s*(\d{1,2})?\s*(?:min)?$/u', $t, $m) === 1) {
            return (int) $m[1] * 3600 + (int) ($m[2] ?? 0) * 60;
        }
        if (preg_match('/^(\d{1,4})\s*min$/u', $t, $m) === 1) {
            return (int) $m[1] * 60;
        }
        if (preg_match('/^\d{1,2}(?:[.,]\d{1,2})?$/', $t) === 1) {
            return (int) round((float) str_replace(',', '.', $t) * 3600);
        }

        return 0;
    }

    /** @param array<string, mixed> $in */
    public function add(int $tenantId, int $userId, array $in): int
    {
        return $this->logbook->createPilotEntry($tenantId, $userId, self::normalizeEntry($in));
    }

    /**
     * Le pilote corrige un vol qu'il a saisi, ou annote un vol relevé en jeu (seules les annotations changent alors).
     * Toute modification repart en attente de contreseing ; un vol relevé sans heures de nuit, IFR ou patrouille
     * reste « relevé en jeu ».
     *
     * @param array<string, mixed> $in
     */
    public function update(int $tenantId, int $userId, int $entryId, array $in): void
    {
        $entry = $this->ownEntry($tenantId, $userId, $entryId);
        $fromMod = ($entry['source'] ?? '') === PilotLogbookRepository::SOURCE_MOD;
        if ($fromMod) {
            // Les champs du vol relevé ne bougent pas : on les reprend pour valider les annotations.
            $in['flown_on'] = (string) $entry['flown_on'];
            $in['duration'] = sprintf('%d:%02d', intdiv((int) $entry['seconds'], 3600), intdiv((int) $entry['seconds'] % 3600, 60));
            $in['aircraft'] = '';
            $in['aircraft_other'] = (string) (($entry['vehicle_label'] ?? '') !== '' ? $entry['vehicle_label'] : $entry['vehicle_class']);
            $in['vehicle_kind'] = (string) $entry['vehicle_kind'];
        }
        $d = self::normalizeEntry($in, $fromMod ? new DateTimeImmutable('2100-01-01') : null);
        if ($fromMod) {
            $d['seconds'] = max(60, (int) $entry['seconds']);
            foreach (['night_seconds', 'ifr_seconds', 'formation_seconds'] as $col) {
                $d[$col] = min($d[$col], (int) $entry['seconds']);
            }
        }
        $claims = $d['night_seconds'] + $d['ifr_seconds'] + $d['formation_seconds'] > 0;
        $status = $fromMod && !$claims ? PilotLogbookRepository::VERIFIED : PilotLogbookRepository::DECLARED;
        $this->logbook->updateByPilot($tenantId, $entryId, $d, !$fromMod, $status);
    }

    public function delete(int $tenantId, int $userId, int $entryId): void
    {
        $entry = $this->ownEntry($tenantId, $userId, $entryId);
        if (($entry['source'] ?? '') !== PilotLogbookRepository::SOURCE_PILOT) {
            throw new RuntimeException('Un vol relevé en jeu ne se supprime pas ; vous pouvez retirer vos annotations.');
        }
        if (($entry['status'] ?? '') === PilotLogbookRepository::COUNTERSIGNED) {
            throw new RuntimeException('Ce vol est contresigné : demandez à un instructeur s’il doit être retiré.');
        }
        $this->logbook->deletePilotEntry($tenantId, $entryId);
    }

    /**
     * Contreseing d'un instructeur de la filière (ou d'un gestionnaire tant qu'il n'y a aucun instructeur) ; jamais
     * sur son propre carnet.
     */
    public function review(int $tenantId, int $entryId, int $actorId, bool $approve, ?string $note, bool $bypass = false): void
    {
        $entry = $this->logbook->find($tenantId, $entryId);
        if ($entry === null) {
            throw new RuntimeException('Vol introuvable.');
        }
        if (($entry['status'] ?? '') !== PilotLogbookRepository::DECLARED) {
            throw new RuntimeException('Ce vol n’attend pas de contreseing.');
        }
        if ((int) $entry['user_id'] === $actorId) {
            throw new RuntimeException('On ne contresigne pas son propre carnet de vol.');
        }
        if (!$bypass && !$this->canReview($tenantId, (string) $entry['vehicle_kind'], $actorId)) {
            throw new RuntimeException('Seul un instructeur ' . mb_strtolower(PilotSyllabusCatalog::trackLabel((string) $entry['vehicle_kind'])) . ' de la communauté peut contresigner ce vol.');
        }
        if (!$approve && trim((string) $note) === '') {
            throw new RuntimeException('Indiquez au pilote pourquoi le vol est refusé.');
        }
        $this->logbook->review($tenantId, $entryId, $approve ? PilotLogbookRepository::COUNTERSIGNED : PilotLogbookRepository::REJECTED, $actorId, $note);
    }

    public function canReview(int $tenantId, string $track, int $actorId): bool
    {
        $ids = $this->syllabus->instructorIds($tenantId, $track);

        return $ids === null || in_array($actorId, $ids, true);
    }

    /** @return list<array<string, mixed>> */
    public function entries(int $tenantId, int $userId): array
    {
        return $this->logbook->listForUser($tenantId, $userId);
    }

    /** @return list<array<string, mixed>> */
    public function declared(int $tenantId): array
    {
        return $this->logbook->listDeclared($tenantId);
    }

    /**
     * Totaux du carnet comme en bas de page d'un vrai carnet : heures comptées (relevées en jeu, et saisies une fois
     * contresignées), par appareil, de nuit / aux instruments / en patrouille contresignées, atterrissages, vols en
     * attente, et report des heures relevées avant l'ouverture du carnet.
     *
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $legacyRows lignes atak_pilot_time du membre (cumul depuis le début)
     * @return array{total: int, by_aircraft: list<array{label: string, kind: string, seconds: int}>, cond: array<string, int>, landings: int, pending: int, flights: int, carried: int}
     */
    public static function summary(array $entries, array $legacyRows): array
    {
        $total = 0;
        $by = [];
        $cond = ['night' => 0, 'ifr' => 0, 'formation' => 0];
        $landings = 0;
        $pending = 0;
        $flights = 0;
        $modByFamily = [];
        foreach ($entries as $e) {
            $status = (string) ($e['status'] ?? '');
            $mod = ($e['source'] ?? '') === PilotLogbookRepository::SOURCE_MOD;
            if ($status === PilotLogbookRepository::DECLARED) {
                $pending++;
            }
            $fam = AtakDistanceRepository::aircraftFamily((string) ($e['vehicle_class'] ?? ''), (string) ($e['vehicle_label'] ?? ''));
            if ($mod) {
                $modByFamily[$fam['key']] = ($modByFamily[$fam['key']] ?? 0) + (int) $e['seconds'];
            }
            $counts = $mod || $status === PilotLogbookRepository::COUNTERSIGNED;
            if (!$counts) {
                continue;
            }
            $flights++;
            $total += (int) $e['seconds'];
            $by[$fam['key']] ??= ['label' => $fam['label'], 'kind' => (string) ($e['vehicle_kind'] ?? 'heli'), 'seconds' => 0];
            $by[$fam['key']]['seconds'] += (int) $e['seconds'];
            if ($status === PilotLogbookRepository::COUNTERSIGNED) {
                $cond['night'] += (int) ($e['night_seconds'] ?? 0);
                $cond['ifr'] += (int) ($e['ifr_seconds'] ?? 0);
                $cond['formation'] += (int) ($e['formation_seconds'] ?? 0);
                $landings += (int) ($e['landings'] ?? 0);
            }
        }
        // Report : heures déjà relevées par le mod avant que le carnet ne les range vol par vol.
        $carried = 0;
        $legacyByFamily = [];
        foreach ($legacyRows as $row) {
            $fam = AtakDistanceRepository::aircraftFamily((string) ($row['vehicle_class'] ?? ''), (string) ($row['vehicle_label'] ?? ''));
            $legacyByFamily[$fam['key']] = ($legacyByFamily[$fam['key']] ?? ['label' => $fam['label'], 'kind' => (string) ($row['vehicle_kind'] ?? 'heli'), 'seconds' => 0]);
            $legacyByFamily[$fam['key']]['seconds'] += (int) ($row['seconds'] ?? 0);
        }
        foreach ($legacyByFamily as $key => $f) {
            $extra = $f['seconds'] - ($modByFamily[$key] ?? 0);
            if ($extra > 0) {
                $carried += $extra;
                $by[$key] ??= ['label' => $f['label'], 'kind' => $f['kind'], 'seconds' => 0];
                $by[$key]['seconds'] += $extra;
            }
        }
        $by = array_values($by);
        usort($by, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        return [
            'total' => $total + $carried,
            'by_aircraft' => $by,
            'cond' => $cond,
            'landings' => $landings,
            'pending' => $pending,
            'flights' => $flights,
            'carried' => $carried,
        ];
    }

    /** @return array<string, mixed> */
    private function ownEntry(int $tenantId, int $userId, int $entryId): array
    {
        $entry = $this->logbook->find($tenantId, $entryId);
        if ($entry === null || (int) $entry['user_id'] !== $userId) {
            throw new RuntimeException('Vol introuvable dans votre carnet.');
        }

        return $entry;
    }
}
