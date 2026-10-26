<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * App Distances de l'ATAK natif : distances parcourues et heures de pilote comptées par le serveur Arma
 * (COMSPEC Link « Distance.Batch », POST /api/atak/distance/batch, toutes les 10 min).
 *
 * - atak_distance_stats : une ligne par membre et session jouée (clé de mission), les envois s'y ajoutent ;
 * - atak_pilot_time : secondes et mètres aux commandes par membre et classe d'appareil, regroupés à l'affichage
 *   par type d'appareil (Chinook, Black Hawk…) d'après la classe et le nom (aircraftFamily).
 */
class AtakDistanceRepository
{
    use LazyDatabaseConnection;

    /**
     * Compteurs d'un envoi et plafond par envoi (10 min prévues, mais un envoi en échec peut cumuler plusieurs
     * heures : au-delà c'est une erreur ou une triche, ramené au plafond).
     */
    public const COUNTERS = [
        'steps' => 300000,
        'foot_m' => 150000,
        'foot_s' => 43200,
        'land_m' => 3000000,
        'land_s' => 43200,
        'sea_m' => 1500000,
        'sea_s' => 43200,
        'air_m' => 15000000,
        'air_s' => 43200,
        'halo_m' => 300000,
        'halo_s' => 43200,
        'halo_jumps' => 100,
    ];

    /**
     * Types d'appareils reconnus dans la classe ou le nom (tirets et espaces retirés, minuscules).
     * Les appareils d'Arma 3 de base proches sont rangés avec le modèle réel (Huron avec le Chinook, Ghost Hawk avec
     * le Black Hawk, Hummingbird et Pawnee avec le Little Bird). Le reste garde le nom de l'appareil dans le jeu.
     */
    private const FAMILIES = [
        'ch47' => ['CH-47 Chinook', '/ch_?47|chinook|huron|heli_transport_03/'],
        'ch53' => ['CH-53 Sea Stallion', '/ch_?53|seastallion|superstallion/'],
        'uh60' => ['UH-60 Black Hawk', '/[uhm]h_?60|blackhawk|pavehawk|ghosthawk|heli_transport_01/'],
        'ah64' => ['AH-64 Apache', '/ah_?64|apache/'],
        'ah1' => ['AH-1 Cobra', '/ah_?1[zwgf]|ah_?1(?![0-9])|cobra|viper/'],
        'uh1' => ['UH-1 Huey', '/uh_?1[yhnd]|uh_?1(?![0-9])|huey|venom/'],
        'mh6' => ['MH-6 / AH-6 Little Bird', '/(?<![a-z0-9])[am]h_?6(?![0-9])|littlebird|hummingbird|pawnee|heli_light_01/'],
        'v22' => ['V-22 Osprey', '/[mc]?v_?22|osprey/'],
        'mi8' => ['Mi-8 / Mi-17 Hip', '/mi_?(8|17|171)(?![0-9])/'],
        'mi24' => ['Mi-24 Hind', '/mi_?(24|35)(?![0-9])|hind/'],
        'mi28' => ['Mi-28 Havoc', '/mi_?28/'],
        'ka52' => ['Ka-52 Alligator', '/ka_?5[02]/'],
        'c130' => ['C-130 Hercules', '/c_?130|hercules/'],
        'a10' => ['A-10 Thunderbolt II', '/(?<![a-z0-9])a_?10(?![0-9])|thunderbolt/'],
        'f16' => ['F-16 Fighting Falcon', '/f_?16(?![0-9])|fightingfalcon/'],
        'fa18' => ['F/A-18 Hornet', '/f(a|_a)?_?18(?![0-9])|hornet/'],
        'f35' => ['F-35 Lightning II', '/f_?35(?![0-9])/'],
        'su25' => ['Su-25 Frogfoot', '/su_?25(?![0-9])|frogfoot/'],
    ];

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_distance_stats' LIMIT 1");
                self::$ready = $st !== false && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Type d'appareil d'une classe Arma (et de son nom affiché) : clé et libellé à regrouper.
     *
     * @return array{key: string, label: string}
     */
    public static function aircraftFamily(string $class, string $label = ''): array
    {
        $hay = strtolower(str_replace(['-', ' ', '/'], '', $class . '_' . $label));
        foreach (self::FAMILIES as $key => [$name, $re]) {
            if (preg_match($re, $hay) === 1) {
                return ['key' => $key, 'label' => $name];
            }
        }
        $label = trim($label);

        return ['key' => 'class:' . strtolower($class), 'label' => $label !== '' ? $label : $class];
    }

    private static function cleanText(mixed $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $v) ?? ''), 0, $max);
    }

    /**
     * Envoi groupé du serveur Arma : [{player_uid, call_sign, steps, foot_m…, pilot: [{class, label, kind, seconds, meters}]}]
     * → joueurs valides (Steam 17 chiffres), compteurs entiers bornés, 100 joueurs et 20 appareils chacun au plus,
     * un même joueur fusionné. Un joueur sans rien à compter est écarté.
     *
     * @return list<array{player_uid: string, call_sign: string, stats: array<string, int>, pilot: list<array{class: string, label: string, kind: string, seconds: int, meters: int}>}>
     */
    public static function normalizeBatch(mixed $players): array
    {
        if (!is_array($players)) {
            return [];
        }
        $out = [];
        foreach (array_slice($players, 0, 100) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $uid = trim((string) ($p['player_uid'] ?? $p['steam_uid'] ?? ''));
            if (!preg_match('/^7656\d{13}$/', $uid)) {
                continue;
            }
            $row = $out[$uid] ?? ['player_uid' => $uid, 'call_sign' => '', 'stats' => array_fill_keys(array_keys(self::COUNTERS), 0), 'pilot' => []];
            foreach (self::COUNTERS as $k => $cap) {
                $v = is_numeric($p[$k] ?? null) ? (int) round((float) $p[$k]) : 0;
                $row['stats'][$k] = min($cap, $row['stats'][$k] + max(0, $v));
            }
            $pilot = [];
            foreach ($row['pilot'] as $it) {
                $pilot[$it['class']] = $it;
            }
            foreach (array_slice(is_array($p['pilot'] ?? null) ? $p['pilot'] : [], 0, 20) as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $class = trim((string) ($it['class'] ?? ''));
                $sec = is_numeric($it['seconds'] ?? null) ? (int) round((float) $it['seconds']) : 0;
                if (!preg_match('/^[A-Za-z0-9_]{1,80}$/', $class) || $sec < 1) {
                    continue;
                }
                $m = is_numeric($it['meters'] ?? null) ? max(0, (int) round((float) $it['meters'])) : 0;
                $prev = $pilot[$class] ?? ['seconds' => 0, 'meters' => 0];
                $pilot[$class] = [
                    'class' => $class,
                    'label' => self::cleanText($it['label'] ?? '', 120),
                    'kind' => ($it['kind'] ?? '') === 'plane' ? 'plane' : 'heli',
                    'seconds' => min(self::COUNTERS['air_s'], $prev['seconds'] + $sec),
                    'meters' => min(self::COUNTERS['air_m'], $prev['meters'] + $m),
                ];
            }
            $row['pilot'] = array_values($pilot);
            $call = self::cleanText($p['call_sign'] ?? '', 64);
            if ($call !== '') {
                $row['call_sign'] = $call;
            }
            $out[$uid] = $row;
        }

        return array_values(array_filter(
            $out,
            static fn (array $r): bool => array_sum($r['stats']) > 0 || $r['pilot'] !== []
        ));
    }

    /**
     * Ajoute un envoi à la session du membre (créée au premier envoi) et à ses heures de pilote.
     *
     * @param array<string, int> $stats
     * @param list<array{class: string, label: string, kind: string, seconds: int, meters: int}> $pilot
     */
    public function addSession(int $tenantId, int $userId, string $missionKey, array $stats, array $pilot): bool
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return false;
        }
        $day = date('Y-m-d');
        $hash = sha1($missionKey !== '' ? $missionKey : 'sans-cle@' . $day);
        $cols = array_keys(self::COUNTERS);
        $vals = array_map(static fn (string $k): int => (int) ($stats[$k] ?? 0), $cols);
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_distance_stats (tenant_id, user_id, mission_hash, mission_key, day, ' . implode(', ', $cols) . ')
             VALUES (?, ?, ?, ?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')
             ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $k): string => "{$k} = {$k} + VALUES({$k})", $cols))
        );
        $st->execute(array_merge([$tenantId, $userId, $hash, $missionKey, $day], $vals));
        if ($pilot !== []) {
            $pst = $this->pdo()->prepare(
                'INSERT INTO atak_pilot_time (tenant_id, user_id, vehicle_class, vehicle_label, vehicle_kind, seconds, meters, last_flown_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds), meters = meters + VALUES(meters),
                    vehicle_label = IF(VALUES(vehicle_label) = \'\', vehicle_label, VALUES(vehicle_label)),
                    vehicle_kind = VALUES(vehicle_kind), last_flown_at = NOW()'
            );
            foreach ($pilot as $it) {
                $pst->execute([$tenantId, $userId, $it['class'], $it['label'], $it['kind'], $it['seconds'], $it['meters']]);
            }
        }
        // Carnet de vol : la même heure de vol, rangée par mission, jour et appareil.
        try {
            (new PilotLogbookRepository($this->pdo()))->recordModFlights($tenantId, $userId, $hash, $missionKey, $day, $pilot);
        } catch (\Throwable) {
        }

        return true;
    }

    /**
     * Bilan d'un membre (fiche du site) : cumul de toutes les sessions, nombre de sessions, temps moyen à pied par
     * session où il a marché, et heures de pilote par type d'appareil (du plus long au plus court).
     * null si rien n'a encore été compté.
     *
     * @return array{totals: array<string, int>, total_m: int, sessions: int, foot_sessions: int, avg_foot_s: int, last_day: string, pilot: list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string}>}|null
     */
    public function summaryForUser(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return null;
        }
        $cols = array_keys(self::COUNTERS);
        $st = $this->pdo()->prepare(
            'SELECT COUNT(*) AS sessions, SUM(foot_s > 0) AS foot_sessions, MAX(day) AS last_day, '
            . implode(', ', array_map(static fn (string $k): string => "COALESCE(SUM({$k}), 0) AS {$k}", $cols))
            . ' FROM atak_distance_stats WHERE tenant_id = ? AND user_id = ?'
        );
        $st->execute([$tenantId, $userId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $pst = $this->pdo()->prepare(
            'SELECT vehicle_class, vehicle_label, vehicle_kind, seconds, meters, last_flown_at
             FROM atak_pilot_time WHERE tenant_id = ? AND user_id = ?'
        );
        $pst->execute([$tenantId, $userId]);
        $pilot = self::groupPilot($pst->fetchAll(PDO::FETCH_ASSOC) ?: []);
        if ((int) ($r['sessions'] ?? 0) === 0 && $pilot === []) {
            return null;
        }

        return self::buildSummary($r, $pilot);
    }

    /**
     * Distances de chaque membre sur la période (page Métriques joueurs) : cumul des sessions jouées depuis $days jours
     * (0 = depuis le début), indexé par membre.
     *
     * @return array<int, array{user_id: int, display_name: string, callsign: string, totals: array<string, int>, total_m: int, sessions: int, last_day: string}>
     */
    public function summaryByUser(int $tenantId, int $days): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $where = 'd.tenant_id = ?';
        $args = [$tenantId];
        if ($days > 0) {
            $where .= ' AND d.day >= (CURRENT_DATE - INTERVAL ? DAY)';
            $args[] = $days - 1;
        }
        $cols = array_keys(self::COUNTERS);
        $st = $this->pdo()->prepare(
            'SELECT d.user_id, MAX(u.display_name) AS display_name, MAX(u.callsign) AS callsign, COUNT(*) AS sessions, MAX(d.day) AS last_day, '
            . implode(', ', array_map(static fn (string $k): string => "COALESCE(SUM(d.{$k}), 0) AS {$k}", $cols))
            . " FROM atak_distance_stats d LEFT JOIN users u ON u.id = d.user_id WHERE {$where} GROUP BY d.user_id"
        );
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $uid = (int) $r['user_id'];
            $sum = self::buildSummary($r, []);
            $out[$uid] = [
                'user_id' => $uid,
                'display_name' => (string) ($r['display_name'] ?? ''),
                'callsign' => (string) ($r['callsign'] ?? ''),
                'totals' => $sum['totals'],
                'total_m' => $sum['total_m'],
                'sessions' => $sum['sessions'],
                'last_day' => $sum['last_day'],
            ];
        }

        return $out;
    }

    /**
     * Heures de pilote de la communauté (cumul depuis le début : la table ne garde pas le jour du vol) : secondes par
     * membre, et par type d'appareil avec le nombre de pilotes.
     *
     * @return array{by_user: array<int, int>, families: list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string, pilots: int}>}
     */
    public function pilotTotals(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return ['by_user' => [], 'families' => []];
        }
        $st = $this->pdo()->prepare(
            'SELECT user_id, vehicle_class, vehicle_label, vehicle_kind, seconds, meters, last_flown_at FROM atak_pilot_time WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return self::pilotTotalsFromRows($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Heures de pilote de quelques membres (tableur des effectifs) : total et détail par type d'appareil, indexé
     * par membre. Les membres sans vol n'apparaissent pas.
     *
     * @param list<int> $userIds
     * @return array<int, array{seconds: int, last_flown_at: string, families: list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string}>}>
     */
    public function pilotByUsers(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($tenantId < 1 || $userIds === [] || !$this->schemaReady()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $st = $this->pdo()->prepare(
            "SELECT user_id, vehicle_class, vehicle_label, vehicle_kind, seconds, meters, last_flown_at FROM atak_pilot_time
             WHERE tenant_id = ? AND user_id IN ({$in})"
        );
        $st->execute(array_merge([$tenantId], $userIds));

        return self::pilotByUserFromRows($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * @param list<array<string, mixed>> $rows lignes de atak_pilot_time (avec user_id)
     * @return array<int, array{seconds: int, last_flown_at: string, families: list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string}>}>
     */
    public static function pilotByUserFromRows(array $rows): array
    {
        $byUser = [];
        foreach ($rows as $row) {
            $byUser[(int) ($row['user_id'] ?? 0)][] = $row;
        }
        $out = [];
        foreach ($byUser as $uid => $userRows) {
            $families = self::groupPilot($userRows);
            $seconds = array_sum(array_column($families, 'seconds'));
            if ($uid < 1 || $seconds < 1) {
                continue;
            }
            $out[$uid] = [
                'seconds' => $seconds,
                'last_flown_at' => (string) max(array_column($families, 'last_flown_at') ?: ['']),
                'families' => $families,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows lignes de atak_pilot_time (avec user_id)
     * @return array{by_user: array<int, int>, families: list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string, pilots: int}>}
     */
    public static function pilotTotalsFromRows(array $rows): array
    {
        $byUser = [];
        $pilots = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $byUser[$uid] = ($byUser[$uid] ?? 0) + (int) ($row['seconds'] ?? 0);
            $fam = self::aircraftFamily((string) ($row['vehicle_class'] ?? ''), (string) ($row['vehicle_label'] ?? ''));
            $pilots[$fam['key']][$uid] = true;
        }
        $families = array_map(
            static fn (array $g): array => $g + ['pilots' => count($pilots[$g['key']] ?? [])],
            self::groupPilot($rows)
        );

        return ['by_user' => $byUser, 'families' => $families];
    }

    /**
     * @param array<string, mixed> $r ligne SUM(...) de atak_distance_stats
     * @param list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string}> $pilot
     * @return array{totals: array<string, int>, total_m: int, sessions: int, foot_sessions: int, avg_foot_s: int, last_day: string, pilot: list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string}>}
     */
    public static function buildSummary(array $r, array $pilot): array
    {
        $totals = [];
        foreach (array_keys(self::COUNTERS) as $k) {
            $totals[$k] = (int) ($r[$k] ?? 0);
        }
        $footSessions = (int) ($r['foot_sessions'] ?? 0);

        return [
            'totals' => $totals,
            'total_m' => $totals['foot_m'] + $totals['land_m'] + $totals['sea_m'] + $totals['air_m'] + $totals['halo_m'],
            'sessions' => (int) ($r['sessions'] ?? 0),
            'foot_sessions' => $footSessions,
            'avg_foot_s' => $footSessions > 0 ? intdiv($totals['foot_s'], $footSessions) : 0,
            'last_day' => (string) ($r['last_day'] ?? ''),
            'pilot' => $pilot,
        ];
    }

    /**
     * Lignes par classe d'appareil → une ligne par type (Chinook, Black Hawk…), avec les noms des variantes pilotées.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{key: string, label: string, kind: string, seconds: int, meters: int, variants: list<string>, last_flown_at: string}>
     */
    public static function groupPilot(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $class = (string) ($row['vehicle_class'] ?? '');
            $label = (string) ($row['vehicle_label'] ?? '');
            $fam = self::aircraftFamily($class, $label);
            $g = $out[$fam['key']] ?? ['key' => $fam['key'], 'label' => $fam['label'], 'kind' => (string) ($row['vehicle_kind'] ?? 'heli'), 'seconds' => 0, 'meters' => 0, 'variants' => [], 'last_flown_at' => ''];
            $g['seconds'] += (int) ($row['seconds'] ?? 0);
            $g['meters'] += (int) ($row['meters'] ?? 0);
            $variant = $label !== '' ? $label : $class;
            if ($variant !== '' && !in_array($variant, $g['variants'], true)) {
                $g['variants'][] = $variant;
            }
            $g['last_flown_at'] = max($g['last_flown_at'], (string) ($row['last_flown_at'] ?? ''));
            $out[$fam['key']] = $g;
        }
        $list = array_values($out);
        usort($list, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        return $list;
    }
}
