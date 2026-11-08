<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\CasNineLineRepository;
use App\Services\Personnel\MemberNameService;
use App\Support\MemberName;
use PDO;

/**
 * Page /9-line : historique des 9-LINE de la communauté, rédaction depuis le site, suppression, et aéronefs
 * remontés par le jeu (fn_reportCrewedAirAssets → COMSPEC Link → POST /api/atak/flight-manifest → atak_air_assets).
 *
 * Droits : un 9-LINE se supprime par son auteur (rédigé sur le site, author_user_id) ou par un responsable ; vider
 * l'historique est réservé aux responsables. Tout est limité à la communauté de la session.
 */
final class NineLineBoardService
{
    /** Aéronef montré s'il a été remonté depuis moins de 10 min ; « en direct » sous 30 s (TTL de la carte). */
    public const AIRCRAFT_WINDOW_SECONDS = 600;

    public const AIRCRAFT_LIVE_SECONDS = 30;

    public const HISTORY_LIMIT = 30;

    /** Lignes obligatoires par type (le reste peut rester vide : « néant »). */
    public const REQUIRED_LINES = [
        CasNineLineRepository::KIND_CAS => [4, 5, 6],
        CasNineLineRepository::KIND_MEDEVAC => [1, 2, 3],
        CasNineLineRepository::KIND_FIVE => [2, 3, 4],
    ];

    /** Nombre de lignes par type (5-LINE : lignes 1 à 5). */
    public const LINE_COUNT = [
        CasNineLineRepository::KIND_CAS => 9,
        CasNineLineRepository::KIND_MEDEVAC => 9,
        CasNineLineRepository::KIND_FIVE => 5,
    ];

    private ?bool $hasAuthorUser = null;

    public function __construct(
        private ?PDO $pdo = null,
        private ?CasNineLineRepository $repo = null,
        private ?MemberNameService $names = null,
        private ?AirBriefService $briefs = null,
        private ?AirCrewSpecialtyService $specialties = null,
    ) {
    }

    /**
     * Derniers 9-LINE (toutes cartes, ou une carte), avec le droit de suppression de chacun pour ce membre.
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $tenantId, int $userId, bool $manager, ?int $mapId = null, int $limit = self::HISTORY_LIMIT): array
    {
        $sql = 'SELECT n.*, m.label AS map_name FROM atak_nine_line n LEFT JOIN atak_maps m ON m.id = n.map_id WHERE n.tenant_id = ?';
        $params = [$tenantId];
        if ($mapId !== null && $mapId > 0) {
            $sql .= ' AND n.map_id = ?';
            $params[] = $mapId;
        }
        $sql .= ' ORDER BY COALESCE(n.updated_at, n.created_at) DESC, n.id DESC LIMIT ' . max(1, min(100, $limit));
        $st = $this->db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        try {
            $recipients = $this->briefs()->forLines($tenantId, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        } catch (\Throwable) {
            $recipients = [];
        }
        $authors = $this->authorSpecs($tenantId, $rows);
        $out = [];
        foreach ($rows as $row) {
            $item = $this->present($row, $userId, $manager);
            $item['recipients'] = $recipients[(int) $row['id']] ?? [];
            $item['author_check'] = $authors[(int) $row['id']] ?? null;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Auteur de chaque 9-LINE au regard de son dossier : membre reconnu (compte du site, ou nom envoyé par le jeu) et
     * qualification ou fonction JTAC détectée. Auteur non reconnu : pas de ligne, la page n'affiche rien.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<int, array{linked: bool, spec: ?array<string, mixed>}>
     */
    private function authorSpecs(int $tenantId, array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $names = [];
        foreach ($rows as $row) {
            if ((int) ($row['author_user_id'] ?? 0) < 1) {
                $names[] = (string) ($row['author'] ?? '');
            }
        }
        $members = $names !== [] ? $this->membersByGameName($tenantId, $names) : [];
        $byRow = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['author_user_id'] ?? 0);
            if ($uid < 1) {
                $uid = (int) ($members[self::key((string) ($row['author'] ?? ''))]['id'] ?? 0);
            }
            if ($uid > 0) {
                $byRow[(int) $row['id']] = $uid;
            }
        }
        $specs = $this->specialties()->forMembers($tenantId, array_values($byRow));
        $out = [];
        foreach ($byRow as $rowId => $uid) {
            $out[$rowId] = ['linked' => true, 'spec' => $specs[$uid][AirCrewSpecialtyService::KIND_JTAC] ?? null];
        }

        return $out;
    }

    /**
     * Cartes présentes dans l'historique (filtre de la page).
     *
     * @return list<array{id: int, label: string, count: int}>
     */
    public function historyMaps(int $tenantId): array
    {
        $st = $this->db()->prepare(
            'SELECT n.map_id, m.label, COUNT(*) AS c FROM atak_nine_line n LEFT JOIN atak_maps m ON m.id = n.map_id
             WHERE n.tenant_id = ? GROUP BY n.map_id, m.label ORDER BY n.map_id'
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $id = (int) $r['map_id'];
            $label = trim((string) ($r['label'] ?? ''));
            $out[] = ['id' => $id, 'label' => $label !== '' ? $label : ('Carte ' . $id), 'count' => (int) $r['c']];
        }

        return $out;
    }

    /**
     * Contrôle d'un 9-LINE ou 5-LINE rédigé sur le site : lignes obligatoires, longueurs, cap et grille lisibles.
     * Saisie libre : seules les longueurs bloquent (le reste n'est qu'un avertissement, côté page).
     *
     * @param array<int, string> $lines numéro de ligne (1 à 9, ou 1 à 5) => texte
     * @return array<int, string> numéro de ligne => code d'erreur (required, too_long, heading, grid ; vide si tout est bon)
     */
    public static function validate(string $kind, array $lines, bool $free = false): array
    {
        $kind = CasNineLineRepository::normalizeKind($kind);
        $count = self::LINE_COUNT[$kind];
        $errors = [];
        if (!$free) {
            foreach (self::REQUIRED_LINES[$kind] as $n) {
                if (trim((string) ($lines[$n] ?? '')) === '') {
                    $errors[$n] = 'required';
                }
            }
        }
        for ($n = 1; $n <= $count; $n++) {
            $v = trim((string) ($lines[$n] ?? ''));
            if ($v !== '' && mb_strlen($v) > ($n === $count ? 1000 : 255)) {
                $errors[$n] = 'too_long';
            }
        }
        if ($free) {
            return $errors;
        }
        if ($kind === CasNineLineRepository::KIND_CAS && !isset($errors[2])) {
            $heading = trim((string) ($lines[2] ?? ''));
            if ($heading !== '' && preg_match('/^(\d{1,3})(?:[.,]\d+)?\s*°?/u', $heading, $m) && (int) $m[1] > 360) {
                $errors[2] = 'heading';
            }
        }
        if ($kind === CasNineLineRepository::KIND_CAS && !isset($errors[6])) {
            $grid = trim((string) ($lines[6] ?? ''));
            if ($grid !== '' && !self::looksLikeGrid($grid)) {
                $errors[6] = 'grid';
            }
        }

        return $errors;
    }

    /** Grille Arma ou MGRS : 4 à 10 chiffres (nombre pair), préfixe de zone facultatif (« 35T LF 0234 5678 »). */
    public static function looksLikeGrid(string $raw): bool
    {
        $t = strtoupper(trim($raw));
        $lastLetter = -1;
        for ($i = strlen($t) - 1; $i >= 0; $i--) {
            if (ctype_alpha($t[$i])) {
                $lastLetter = $i;
                break;
            }
        }
        $digits = preg_replace('/\D+/', ' ', substr($t, $lastLetter + 1)) ?? '';
        $groups = preg_split('/\s+/', trim($digits)) ?: [];
        $groups = array_values(array_filter($groups, static fn (string $g): bool => $g !== ''));
        if (count($groups) === 2) {
            if (strlen($groups[0]) !== strlen($groups[1])) {
                return false;
            }
            $s = $groups[0] . $groups[1];
        } else {
            $s = implode('', $groups);
        }
        $len = strlen($s);

        return $len >= 4 && $len <= 10 && $len % 2 === 0;
    }

    /**
     * Enregistre un 9-LINE, 5-LINE ou MEDEVAC rédigé sur le site (même table que l'app JTAC : visible en jeu et dans
     * Overwatch) et ses destinataires (statut « envoyé ») : le pilote le reçoit dans l'espace BRIEFS AIR de son
     * téléphone (boîte GET /api/cas?assignedTo=~air|…).
     *
     * @param array<int, string> $lines
     * @param list<string|array{callsign?: mixed, label?: mixed, crew?: mixed}> $aircraft destinataires (indicatifs)
     * @return array<string, mixed>
     */
    public function create(int $tenantId, int $userId, string $author, string $kind, array $lines, array $aircraft, int $mapId, string $remarks = ''): array
    {
        $kind = CasNineLineRepository::normalizeKind($kind);
        $recipients = AirBriefService::normalizeRecipients($aircraft);
        $labels = array_map(static fn (array $r): string => $r['callsign'], $recipients);
        $payload = ['assigned_aircraft' => self::aircraftLabel($labels), 'remarks' => trim($remarks)];
        for ($n = 1; $n <= 9; $n++) {
            $payload['line' . $n] = $n <= self::LINE_COUNT[$kind] ? trim((string) ($lines[$n] ?? '')) : '';
        }
        $author = trim($author) !== '' ? trim($author) : 'JTAC';
        $mapId = max(1, $mapId);
        $row = match ($kind) {
            CasNineLineRepository::KIND_MEDEVAC => $this->repo()->createMedevac($tenantId, $mapId, $author, $payload),
            CasNineLineRepository::KIND_FIVE => $this->repo()->createFive($tenantId, $mapId, $author, $payload),
            default => $this->repo()->createCas($tenantId, $mapId, $author, $payload),
        };
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0 && $userId > 0 && $this->hasAuthorUserColumn()) {
            $this->db()->prepare('UPDATE atak_nine_line SET author_user_id = ? WHERE tenant_id = ? AND id = ?')
                ->execute([$userId, $tenantId, $id]);
            $row['author_user_id'] = $userId;
        }
        if ($id > 0 && $recipients !== []) {
            try {
                $row['recipients'] = $this->briefs()->addRecipients($tenantId, $id, $recipients);
            } catch (\Throwable $e) {
                error_log('[9-line] destinataires : ' . $e->getMessage());
            }
        }

        return $row;
    }

    /** Indicatifs des destinataires, dans la colonne assigned_aircraft (128 caractères). */
    public static function aircraftLabel(array $aircraft): ?string
    {
        $clean = [];
        foreach ($aircraft as $cs) {
            $cs = trim((string) $cs);
            if ($cs !== '' && !in_array($cs, $clean, true)) {
                $clean[] = $cs;
            }
        }
        if ($clean === []) {
            return null;
        }

        return mb_substr(implode(', ', $clean), 0, 128);
    }

    /** Un 9-LINE de cette communauté, ou null. */
    public function find(int $tenantId, int $id): ?array
    {
        return $id > 0 ? $this->repo()->getCas($tenantId, $id) : null;
    }

    public static function canDelete(array $row, int $userId, bool $manager): bool
    {
        if ($manager) {
            return true;
        }
        $author = (int) ($row['author_user_id'] ?? 0);

        return $userId > 0 && $author === $userId;
    }

    /**
     * @return array{result: 'ok'|'not_found'|'forbidden', row: ?array<string, mixed>}
     */
    public function delete(int $tenantId, int $id, int $userId, bool $manager): array
    {
        $row = $this->find($tenantId, $id);
        if ($row === null) {
            return ['result' => 'not_found', 'row' => null];
        }
        if (!self::canDelete($row, $userId, $manager)) {
            return ['result' => 'forbidden', 'row' => $row];
        }
        $this->repo()->deleteCas($tenantId, $id);
        $this->dropRecipients('nine_line_id = ?', [$id], $tenantId);

        return ['result' => 'ok', 'row' => $row];
    }

    /** Vide l'historique de la communauté (toutes cartes, ou une carte). Nombre de 9-LINE supprimés. */
    public function clear(int $tenantId, ?int $mapId = null): int
    {
        $sql = 'DELETE FROM atak_nine_line WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($mapId !== null && $mapId > 0) {
            $sql .= ' AND map_id = ?';
            $params[] = $mapId;
        }
        if ($mapId !== null && $mapId > 0) {
            $this->dropRecipients('nine_line_id IN (SELECT id FROM atak_nine_line WHERE tenant_id = ? AND map_id = ?)', [$tenantId, $mapId], $tenantId);
        } else {
            $this->dropRecipients('1 = 1', [], $tenantId);
        }
        $st = $this->db()->prepare($sql);
        $st->execute($params);

        return $st->rowCount();
    }

    /**
     * Destinataires supprimés avec leurs demandes (cascade de la clé étrangère ; explicite pour les bases sans).
     *
     * @param list<int> $params
     */
    private function dropRecipients(string $where, array $params, int $tenantId): void
    {
        try {
            $this->db()->prepare('DELETE FROM atak_nine_line_recipients WHERE tenant_id = ? AND ' . $where)->execute(array_merge([$tenantId], $params));
        } catch (\Throwable) {
        }
    }

    /**
     * Aéronefs avec équipage remontés par le jeu ces 10 dernières minutes, le plus récent d'abord : indicatif,
     * appareil, position (grille), altitude, cap, état, carburant, pilote et équipage (membre reconnu par son
     * indicatif ou son pseudo : indicatif d'abord, puis « Prénom N. »), fraîcheur de la donnée.
     *
     * @return list<array<string, mixed>>
     */
    public function aircraft(int $tenantId, ?int $now = null): array
    {
        $now ??= time();
        $st = $this->db()->prepare(
            'SELECT a.*, m.label AS map_name FROM atak_air_assets a LEFT JOIN atak_maps m ON m.id = a.map_id
             WHERE a.tenant_id = ? AND a.updated_at >= ? ORDER BY a.updated_at DESC, a.callsign LIMIT 40'
        );
        $st->execute([$tenantId, date('Y-m-d H:i:s', $now - self::AIRCRAFT_WINDOW_SECONDS)]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [];
        }
        $crewByRow = [];
        $gameNames = [];
        foreach ($rows as $i => $r) {
            $crew = $r['crew'] ?? null;
            $crew = is_string($crew) && $crew !== '' ? json_decode($crew, true) : $crew;
            $crew = is_array($crew) ? array_values(array_filter($crew, 'is_array')) : [];
            $crewByRow[$i] = $crew;
            foreach ($crew as $c) {
                $gameNames[] = (string) ($c['name'] ?? '');
            }
            $gameNames[] = (string) ($r['pilot'] ?? '');
        }
        $members = $this->membersByGameName($tenantId, $gameNames);
        try {
            $overrides = $this->briefs()->aircraftOverrides($tenantId);
        } catch (\Throwable) {
            $overrides = [];
        }
        $userIds = [];
        foreach ($members as $identity) {
            $userIds[] = (int) ($identity['id'] ?? 0);
        }
        $specs = $userIds !== [] ? $this->specialties()->forMembers($tenantId, $userIds) : [];
        $out = [];
        foreach ($rows as $i => $r) {
            $updated = strtotime((string) ($r['updated_at'] ?? '')) ?: 0;
            $age = max(0, $now - $updated);
            $crew = [];
            $pilot = null;
            foreach ($crewByRow[$i] as $c) {
                $person = $this->person((string) ($c['name'] ?? ''), $members, $specs);
                $person['seat'] = (string) ($c['seat'] ?? 'cargo');
                $person['role'] = (string) ($c['role'] ?? '');
                $person['player'] = !empty($c['player']);
                $crew[] = $person;
                if ($pilot === null && $person['seat'] === 'driver') {
                    $pilot = $person;
                }
            }
            $pilotName = trim((string) ($r['pilot'] ?? ''));
            if ($pilot === null && $pilotName !== '' && strcasecmp($pilotName, 'Pilote') !== 0) {
                $pilot = $this->person($pilotName, $members, $specs) + ['seat' => 'driver', 'role' => '', 'player' => false];
            }
            $x = $r['pos_x'] !== null ? (float) $r['pos_x'] : null;
            $y = $r['pos_y'] !== null ? (float) $r['pos_y'] : null;
            $alt = $r['alt'] !== null ? (float) $r['alt'] : null;
            $reported = (string) $r['callsign'];
            $hasPlayer = ($pilot['player'] ?? false) === true;
            foreach ($crew as $c) {
                $hasPlayer = $hasPlayer || $c['player'];
            }
            $out[] = [
                'id' => (int) $r['id'],
                'callsign' => $reported,
                'label' => $overrides[AirBriefService::cleanCallsign($reported)] ?? $reported,
                'has_player' => $hasPlayer,
                'model' => (string) ($r['model'] ?? ''),
                'aircraft_type' => (string) ($r['aircraft_type'] ?? ''),
                'map_id' => (int) $r['map_id'],
                'map_name' => (string) ($r['map_name'] ?? ''),
                'side' => (string) ($r['side'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'fuel_pct' => isset($r['fuel_pct']) && $r['fuel_pct'] !== null ? (int) $r['fuel_pct'] : null,
                'grid' => $x !== null && $y !== null ? self::grid($x, $y) : '',
                'alt_m' => $alt !== null ? (int) round($alt) : null,
                'alt_ft' => $alt !== null ? (int) round($alt * 3.28084) : null,
                'heading' => $r['heading'] !== null ? (int) round((float) $r['heading']) % 360 : null,
                'freq' => (string) ($r['freq'] ?? ''),
                'laser' => (string) ($r['laser'] ?? ''),
                'pilot' => $pilot,
                'crew' => $crew,
                'crew_count' => count($crew),
                'updated_at' => (string) ($r['updated_at'] ?? ''),
                'age_s' => $age,
                'live' => $age <= self::AIRCRAFT_LIVE_SECONDS,
            ];
        }

        return $out;
    }

    /** Grille à 8 chiffres (cases de 10 m) depuis une position du monde Arma. */
    public static function grid(float $x, float $y): string
    {
        $gx = max(0, (int) floor($x / 10));
        $gy = max(0, (int) floor($y / 10));

        return sprintf('%04d %04d', $gx % 10000, $gy % 10000);
    }

    /**
     * Qui est à bord, et ce que dit son dossier : spec_pilot et spec_jtac (null : rien au dossier).
     *
     * @param array<string, array<string, mixed>> $members
     * @param array<int, array<string, array<string, mixed>>> $specs
     * @return array{game_name: string, label: string, name: string, callsign: string, linked: bool, spec_pilot: ?array<string, mixed>, spec_jtac: ?array<string, mixed>}
     */
    private function person(string $gameName, array $members, array $specs = []): array
    {
        $gameName = trim($gameName);
        $identity = $members[self::key($gameName)] ?? null;
        if ($identity === null) {
            return ['game_name' => $gameName, 'label' => $gameName, 'name' => '', 'callsign' => '', 'linked' => false, 'spec_pilot' => null, 'spec_jtac' => null];
        }
        $label = MemberName::c2($identity);
        $short = MemberName::short($identity);
        $mine = $specs[(int) ($identity['id'] ?? 0)] ?? [];

        return [
            'game_name' => $gameName,
            'label' => $label !== '' ? $label : $gameName,
            'name' => $short !== $label ? $short : '',
            'callsign' => MemberName::callsign($identity),
            'linked' => true,
            'spec_pilot' => $mine[AirCrewSpecialtyService::KIND_PILOT] ?? null,
            'spec_jtac' => $mine[AirCrewSpecialtyService::KIND_JTAC] ?? null,
        ];
    }

    /**
     * Membres de la communauté reconnus par le nom envoyé par le jeu (indicatif Athena, pseudo ou « Prénom Nom »).
     *
     * @param list<string> $gameNames
     * @return array<string, array<string, mixed>> nom normalisé => identité (MemberNameService::identitiesFor)
     */
    private function membersByGameName(int $tenantId, array $gameNames): array
    {
        $wanted = [];
        foreach ($gameNames as $n) {
            $k = self::key($n);
            if ($k !== '' && $k !== 'pilote') {
                $wanted[$k] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }
        try {
            $names = $this->names ??= new MemberNameService($this->db());
            $identities = $names->identitiesFor($tenantId, $names->memberIds($tenantId));
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($identities as $identity) {
            $full = trim((string) ($identity['first_name'] ?? '') . ' ' . (string) ($identity['last_name'] ?? ''));
            foreach ([(string) ($identity['callsign'] ?? ''), (string) ($identity['display_name'] ?? ''), $full] as $alias) {
                $k = self::key($alias);
                if ($k !== '' && isset($wanted[$k]) && !isset($out[$k])) {
                    $out[$k] = $identity;
                }
            }
        }

        return $out;
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row, int $userId, bool $manager): array
    {
        $kind = CasNineLineRepository::normalizeKind($row['mission_kind'] ?? CasNineLineRepository::KIND_CAS);
        $item = [
            'id' => (int) $row['id'],
            'map_id' => (int) ($row['map_id'] ?? 1),
            'map_name' => (string) ($row['map_name'] ?? ''),
            'mission_kind' => $kind,
            'format' => AirBriefService::format($kind),
            'remarks' => (string) ($row['remarks'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'from_site' => (int) ($row['author_user_id'] ?? 0) > 0,
            'assigned_aircraft' => (string) ($row['assigned_aircraft'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'can_delete' => self::canDelete($row, $userId, $manager),
        ];
        for ($n = 1; $n <= 9; $n++) {
            $item['line' . $n] = (string) ($row['line' . $n] ?? '');
        }

        return $item;
    }

    private function hasAuthorUserColumn(): bool
    {
        if ($this->hasAuthorUser === null) {
            try {
                $this->hasAuthorUser = $this->db()->query('SELECT author_user_id FROM atak_nine_line LIMIT 1') !== false;
            } catch (\Throwable) {
                $this->hasAuthorUser = false;
            }
        }

        return $this->hasAuthorUser;
    }

    private function specialties(): AirCrewSpecialtyService
    {
        return $this->specialties ??= new AirCrewSpecialtyService($this->db());
    }

    private function briefs(): AirBriefService
    {
        return $this->briefs ??= new AirBriefService($this->db());
    }

    private function repo(): CasNineLineRepository
    {
        return $this->repo ??= new CasNineLineRepository($this->db());
    }

    private function db(): PDO
    {
        return $this->pdo ??= \App\Core\Database::getPdo();
    }
}
