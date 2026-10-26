<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Services\Intel\IntelPlayerFeedService;
use App\Services\Intel\IntelPlayerIdentity;
use App\Support\LazyDatabaseConnection;
use App\Support\YearReviewCalendar;
use App\Support\YearReviewStats;
use PDO;

/**
 * Rétrospective annuelle d'une communauté : lit ce que le jeu et le site ont déjà enregistré sur l'année
 * (temps de jeu et d'écran, distances, vols, pertes, MEDEVAC, SSE, comptes rendus, debriefings, événements),
 * le ramène à une clé par joueur (IntelPlayerIdentity) et le passe à YearReviewStats.
 *
 * Chaque table est facultative : une source absente ou en erreur compte zéro et est notée dans « sources ».
 * Le résultat est mis en cache par communauté et par année (30 min pour l'année en cours, 1 jour sinon).
 */
final class YearReviewService
{
    use LazyDatabaseConnection;

    private const CACHE_TTL_CURRENT = 1800;
    private const CACHE_TTL_PAST = 86400;

    /** @var array<string, array<string, true>>|null */
    private ?array $columns = null;

    public function __construct(?PDO $pdo = null, private ?string $cacheDir = null, private ?IntelPlayerFeedService $feed = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{tenant_id: int, year: int, from: string, until: string, partial: bool, generated_at: string, sources: array<string, bool>, players: array<string, array<string, mixed>>, community: array<string, mixed>}
     */
    public function forTenant(int $tenantId, int $year, ?int $now = null, bool $fresh = false): array
    {
        $now ??= time();
        $cached = $fresh ? null : $this->readCache($tenantId, $year, $now);
        if ($cached !== null) {
            return $cached;
        }
        $review = $this->compute($tenantId, $year, $now);
        $this->writeCache($tenantId, $year, $review);

        return $review;
    }

    /** Fiche d'un compte (null : pas assez d'activité sur l'année). */
    public function forUser(int $tenantId, int $userId, int $year, ?int $now = null): ?array
    {
        $review = $this->forTenant($tenantId, $year, $now);
        $p = $review['players']['u' . $userId] ?? null;

        return is_array($p) && !empty($p['active']) ? $p : null;
    }

    public function clearCache(): int
    {
        $n = 0;
        foreach (glob($this->dir() . '/t*_*.json') ?: [] as $file) {
            if (@unlink($file)) {
                $n++;
            }
        }

        return $n;
    }

    // ---------------------------------------------------------------- calcul

    /** @return array<string, mixed> */
    private function compute(int $tenantId, int $year, int $now): array
    {
        $period = YearReviewCalendar::period($year, $now);
        $from = $period['from'];
        $until = $period['until'];
        // Tables au jour près : borne haute exclue (lendemain d'aujourd'hui pour l'année en cours).
        $dFrom = substr($from, 0, 10);
        $dUntil = $period['partial'] ? date('Y-m-d', strtotime(substr($until, 0, 10) . ' +1 day')) : substr($until, 0, 10);
        $feed = $this->feed ?? new IntelPlayerFeedService($this->pdo());
        $identity = $feed->identity($tenantId);
        $raw = [];
        $sources = [];
        $add = static function (string $key, string $field, int $n) use (&$raw): void {
            if ($key === IntelPlayerIdentity::UNKNOWN || $n === 0) {
                return;
            }
            $raw[$key][$field] = ($raw[$key][$field] ?? 0) + $n;
        };

        // Temps de jeu (serveur Arma ou téléphone), temps d'écran, appli et rôle préférés, mois par mois.
        $hasMission = $this->hasColumn('atak_screen_time', 'mission_key');
        $rows = $this->rows(
            "SELECT user_id, kind, item_key, MAX(item_label) AS label, MONTH(day) AS m, SUM(seconds) AS s
             FROM atak_screen_time WHERE tenant_id = ? AND day >= ? AND day < ?
             GROUP BY user_id, kind, item_key, m",
            [$tenantId, $dFrom, $dUntil]
        );
        $sources['screen_time'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            $key = 'u' . (int) $r['user_id'];
            $sec = (int) $r['s'];
            $item = (string) $r['item_key'];
            switch ((string) $r['kind']) {
                case 'play':
                    if ($item === 'TOTAL') {
                        $add($key, 'play_s', $sec);
                        $raw[$key]['months'][(int) $r['m'] - 1] = ($raw[$key]['months'][(int) $r['m'] - 1] ?? 0) + $sec;
                    }
                    break;
                case 'screen':
                    if ($item === 'TOTAL') {
                        $add($key, 'screen_s', $sec);
                    }
                    break;
                case 'app':
                    $label = trim((string) $r['label']) ?: $item;
                    $raw[$key]['apps'][$label] = ($raw[$key]['apps'][$label] ?? 0) + $sec;
                    break;
                case 'role':
                    $label = trim((string) $r['label']) ?: $item;
                    $raw[$key]['roles'][$label] = ($raw[$key]['roles'][$label] ?? 0) + $sec;
                    break;
            }
        }

        // Jours de jeu et sessions (missions) distinctes, tous relevés confondus.
        $sessionRows = array_merge(
            $this->rows(
                'SELECT user_id, day, ' . ($hasMission ? 'mission_key' : "''") . " AS mk FROM atak_screen_time
                 WHERE tenant_id = ? AND day >= ? AND day < ? AND kind = 'play' GROUP BY user_id, day, mk",
                [$tenantId, $dFrom, $dUntil]
            ) ?? [],
            $this->rows(
                'SELECT user_id, day, mission_key AS mk FROM atak_distance_stats
                 WHERE tenant_id = ? AND day >= ? AND day < ? GROUP BY user_id, day, mk',
                [$tenantId, $dFrom, $dUntil]
            ) ?? []
        );
        $days = [];
        $missions = [];
        $allMissions = [];
        $firstDay = [];
        foreach ($sessionRows as $r) {
            $key = 'u' . (int) $r['user_id'];
            $day = (string) $r['day'];
            $days[$key][$day] = true;
            $firstDay[$key] = isset($firstDay[$key]) ? min($firstDay[$key], $day) : $day;
            $mk = trim((string) $r['mk']);
            if ($mk !== '') {
                $missions[$key][$mk] = true;
                $allMissions[$mk] = true;
            }
        }
        foreach ($days as $key => $set) {
            $add($key, 'days', count($set));
            $add($key, 'missions', count($missions[$key] ?? []));
            $raw[$key]['first_day'] = $firstDay[$key];
        }

        // Distances parcourues (serveur Arma, ou téléphone depuis la correction des heures de vol).
        $rows = $this->rows(
            'SELECT user_id, SUM(steps) AS steps, SUM(foot_m) AS foot_m, SUM(land_m) AS land_m, SUM(sea_m) AS sea_m,
                    SUM(air_m) AS air_m, SUM(air_s) AS air_s, SUM(halo_m) AS halo_m, SUM(halo_jumps) AS halo_jumps
             FROM atak_distance_stats WHERE tenant_id = ? AND day >= ? AND day < ? GROUP BY user_id',
            [$tenantId, $dFrom, $dUntil]
        );
        $sources['distances'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            foreach (['steps', 'foot_m', 'land_m', 'sea_m', 'air_m', 'air_s', 'halo_m', 'halo_jumps'] as $f) {
                $add('u' . (int) $r['user_id'], $f, (int) $r[$f]);
            }
        }

        // Heures de pilote : cumul par appareil, retenu quand l'appareil a volé dans l'année.
        $rows = $this->rows(
            'SELECT user_id, vehicle_class, vehicle_label, seconds, meters FROM atak_pilot_time
             WHERE tenant_id = ? AND last_flown_at >= ? AND last_flown_at < ?',
            [$tenantId, $from, $until]
        );
        $sources['pilot'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            $key = 'u' . (int) $r['user_id'];
            $add($key, 'pilot_s', (int) $r['seconds']);
            $add($key, 'pilot_m', (int) $r['meters']);
            $label = trim((string) $r['vehicle_label']) ?: (string) $r['vehicle_class'];
            $raw[$key]['aircraft'][$label] = ($raw[$key]['aircraft'][$label] ?? 0) + (int) $r['seconds'];
        }

        // Blessés et tués (la victime, pas celui qui l'a signalé).
        $rows = $this->rows(
            'SELECT call_sign, kind, COUNT(*) AS n FROM atak_casualties
             WHERE tenant_id = ? AND killed_at >= ? AND killed_at < ? GROUP BY call_sign, kind',
            [$tenantId, $from, $until]
        );
        $sources['casualties'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            $add($identity->resolve(null, null, $r['call_sign']), (string) $r['kind'] === 'wia' ? 'wia' : 'kia', (int) $r['n']);
        }

        // Évacué en MEDEVAC (patient d'une demande).
        $rows = $this->rows(
            'SELECT p.patient_steam_id AS s, p.patient_callsign AS c, COUNT(*) AS n
             FROM atak_medevac_patients p INNER JOIN atak_medevac_requests r ON r.id = p.medevac_request_id
             WHERE r.tenant_id = ? AND p.created_at >= ? AND p.created_at < ? GROUP BY s, c',
            [$tenantId, $from, $until]
        );
        $sources['medevac_patients'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            $add($identity->resolve(null, $r['s'], $r['c']), 'medevac_patient', (int) $r['n']);
        }

        // SSE sur objectif : prélèvements biométriques et chaîne de garde des saisies.
        foreach (['sse_biometric_samples' => ['operator_callsign', 'sse_samples'], 'sse_custody_events' => ['actor_callsign', 'sse_custody']] as $table => [$col, $field]) {
            $rows = $this->rows(
                "SELECT {$col} AS c, COUNT(*) AS n FROM {$table} WHERE tenant_id = ? AND created_at >= ? AND created_at < ? GROUP BY c",
                [$tenantId, $from, $until]
            );
            $sources[$table] = $rows !== null;
            foreach ($rows ?? [] as $r) {
                $add($identity->resolve(null, null, $r['c']), $field, (int) $r['n']);
            }
        }

        // Debriefings de fin d'opération remplis.
        $rows = $this->rows(
            'SELECT user_id AS u, steam_uid AS s, callsign AS c, COUNT(*) AS n FROM atak_debrief_recipients
             WHERE tenant_id = ? AND submitted_at >= ? AND submitted_at < ? GROUP BY u, s, c',
            [$tenantId, $from, $until]
        );
        $sources['debriefs'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            $add($identity->resolve($r['u'], $r['s'], $r['c']), 'debriefs', (int) $r['n']);
        }

        // Événements de la communauté où le joueur a répondu présent.
        $rows = $this->rows(
            "SELECT r.user_id AS u, COUNT(*) AS n FROM community_event_rsvps r INNER JOIN community_events e ON e.id = r.event_id
             WHERE e.tenant_id = ? AND e.starts_at >= ? AND e.starts_at < ? AND e.cancelled_at IS NULL
               AND (r.status = 'yes' OR r.checked_in_at IS NOT NULL) GROUP BY r.user_id",
            [$tenantId, $from, $until]
        );
        $sources['events'] = $rows !== null;
        foreach ($rows ?? [] as $r) {
            $add('u' . (int) $r['u'], 'events', (int) $r['n']);
        }

        // Remontées du terrain (comptes rendus, fiches et photos SSE, MEDEVAC, SIGINT, POI…).
        try {
            $intel = $feed->playersBetween($tenantId, $from, $until);
            $sources['intel'] = true;
        } catch (\Throwable) {
            $intel = ['players' => []];
            $sources['intel'] = false;
        }
        foreach ($intel['players'] as $p) {
            $key = (string) ($p['key'] ?? '');
            $c = (array) ($p['counts'] ?? []);
            $add($key, 'cr', (int) ($c['cr'] ?? 0));
            $add($key, 'sse_notes', (int) ($c['fiche'] ?? 0));
            $add($key, 'sse_photos', (int) ($p['subs']['photo']['Photo SSE'] ?? 0));
            $add($key, 'medevac_requests', (int) ($c['medevac'] ?? 0));
            $add($key, 'poi', (int) ($c['poi'] ?? 0));
            $add($key, 'sigint', (int) ($c['sigint'] ?? 0));
            $add($key, 'intel_total', (int) ($p['total'] ?? 0));
        }

        $names = [];
        foreach (array_keys($raw) as $key) {
            $card = $identity->card((string) $key);
            $names[$key] = $card['callsign'] !== '' ? $card['callsign'] : $card['account'];
        }
        $built = YearReviewStats::build($raw, $names, count($allMissions));

        return [
            'tenant_id' => $tenantId,
            'year' => $year,
            'from' => $from,
            'until' => $until,
            'partial' => $period['partial'],
            'generated_at' => date('Y-m-d H:i:s', $now),
            'sources' => $sources,
            'players' => $built['players'],
            'community' => $built['community'],
        ];
    }

    /**
     * @param list<mixed> $args
     * @return list<array<string, mixed>>|null null : table absente ou requête en erreur
     */
    private function rows(string $sql, array $args): ?array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->columns === null) {
            $this->columns = [];
            foreach ($this->rows(
                "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('atak_screen_time')",
                []
            ) ?? [] as $r) {
                $this->columns[(string) $r['t']][(string) $r['c']] = true;
            }
        }

        return isset($this->columns[$table][$column]);
    }

    // ---------------------------------------------------------------- cache

    private function dir(): string
    {
        return $this->cacheDir ?? (function_exists('base_path') ? base_path('storage/cache/year_review') : sys_get_temp_dir() . '/year_review');
    }

    private function file(int $tenantId, int $year): string
    {
        return $this->dir() . '/t' . $tenantId . '_' . $year . '.json';
    }

    /** @return array<string, mixed>|null */
    private function readCache(int $tenantId, int $year, int $now): ?array
    {
        $file = $this->file($tenantId, $year);
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return null;
        }
        $ttl = (int) date('Y', $now) > $year ? self::CACHE_TTL_PAST : self::CACHE_TTL_CURRENT;
        if ($now - $mtime > $ttl) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) && (int) ($data['tenant_id'] ?? 0) === $tenantId ? $data : null;
    }

    /** @param array<string, mixed> $review */
    private function writeCache(int $tenantId, int $year, array $review): void
    {
        $dir = $this->dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $json = json_encode($review, JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            @file_put_contents($this->file($tenantId, $year), $json, LOCK_EX);
        }
    }
}
