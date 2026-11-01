<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Database;
use PDO;

/**
 * Chiffres réels de la page publique « Notre histoire ».
 *
 * Seuls des agrégats sortent d’ici (totaux, comptes par année) : aucune ligne par joueur.
 * Portée « platform » = toute la plateforme ; portée communauté = un tenant précis (réservée
 * à ses membres connectés, qui voient alors aussi ses opérations marquantes et ses articles publiés).
 *
 * Kilomètres : distances comptées en jeu par le serveur Arma (app Distances de l’ATAK natif, table
 * atak_distance_stats : à pied, véhicule terrestre, air, mer, chute libre). Heures de jeu : temps de jeu
 * remonté par le serveur et les téléphones (atak_screen_time, type « play »).
 *
 * Une table absente ou vide vaut 0 / liste vide — jamais une valeur estimée. Le résultat est
 * mis en cache 10 minutes dans storage/cache (un fichier par portée).
 */
final class PublicHistoryStatsService
{
    public const CACHE_TTL = 600;

    private const HIGHLIGHTS_LIMIT = 6;

    /** Mode de déplacement → colonne (mètres) d’atak_distance_stats. */
    public const DISTANCE_MODES = [
        'foot' => 'foot_m',
        'land' => 'land_m',
        'air' => 'air_m',
        'sea' => 'sea_m',
        'halo' => 'halo_m',
    ];

    public function __construct(
        private ?PDO $pdo = null,
        private ?string $cacheDir = null,
        private ?\DateTimeImmutable $now = null,
    ) {}

    /**
     * @return array{
     *   scope: string,
     *   community_name: ?string,
     *   founded_at: ?string,
     *   members: int,
     *   operations: int,
     *   flight_hours: int,
     *   academy_validated: int,
     *   orbat_units: int,
     *   communities: int,
     *   distance_km: int,
     *   distance_modes: array<string, int>,
     *   distance_sessions: int,
     *   play_hours: int,
     *   years: list<array{year: string, operations: int, members: int, flight_hours: int, distance_km: int}>,
     *   highlights: list<array{kind: string, date: string, title: string, value: ?int}>
     * }
     */
    public function stats(?int $tenantId = null, bool $fresh = false): array
    {
        $tenantId = $tenantId !== null && $tenantId > 0 ? $tenantId : null;
        $key = $tenantId === null ? 'platform' : 'tenant_' . $tenantId;
        if (!$fresh) {
            $cached = $this->readCache($key);
            if ($cached !== null) {
                return $cached;
            }
        }
        $data = $this->compute($tenantId);
        $this->writeCache($key, $data);

        return $data;
    }

    /**
     * @return array{
     *   scope: string,
     *   community_name: ?string,
     *   founded_at: ?string,
     *   members: int,
     *   operations: int,
     *   flight_hours: int,
     *   academy_validated: int,
     *   orbat_units: int,
     *   communities: int,
     *   distance_km: int,
     *   distance_modes: array<string, int>,
     *   distance_sessions: int,
     *   play_hours: int,
     *   years: list<array{year: string, operations: int, members: int, flight_hours: int, distance_km: int}>,
     *   highlights: list<array{kind: string, date: string, title: string, value: ?int}>
     * }
     */
    private function compute(?int $tenantId): array
    {
        $now = ($this->now ?? new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        // Filtre tenant : uniquement des entiers liés en paramètre.
        $t = static fn (string $alias = ''): string => $tenantId === null ? '' : ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'tenant_id = ?';
        $p = static fn (array $params = []): array => $tenantId === null ? $params : [...$params, $tenantId];

        $communityName = null;
        if ($tenantId !== null) {
            $founded = $this->value('SELECT created_at FROM tenants WHERE id = ?', [$tenantId]);
            $name = $this->value('SELECT name FROM tenants WHERE id = ?', [$tenantId]);
            $communityName = is_string($name) && trim($name) !== '' ? trim($name) : null;
        } else {
            $founded = $this->value('SELECT MIN(created_at) FROM tenants');
        }

        // Membres actifs : comptes actifs, hors comptes de service (colonne absente sur vieux schémas).
        $members = $this->int(
            "SELECT COUNT(*) FROM users WHERE status = 'active' AND COALESCE(is_service_account, 0) = 0" . $t(),
            $p()
        ) ?? $this->int("SELECT COUNT(*) FROM users WHERE status = 'active'" . $t(), $p()) ?? 0;

        // Opérations et manœuvres jouées : événements non annulés déjà commencés.
        $operations = $this->int(
            'SELECT COUNT(*) FROM community_events WHERE cancelled_at IS NULL AND starts_at <= ?' . $t(),
            $p([$now])
        ) ?? 0;

        // Heures de vol : carnet de vol, entrées non rejetées.
        $flightSeconds = $this->int(
            "SELECT COALESCE(SUM(seconds), 0) FROM pilot_logbook_entries WHERE status <> 'rejected'" . $t(),
            $p()
        ) ?? 0;

        // Formations validées : parcours Academy terminés + formations LMS réussies.
        $academy = ($this->int(
            "SELECT COUNT(*) FROM academy_learners WHERE course_key <> '__notes' AND state_json LIKE '%\"completed\":true%'" . $t(),
            $p()
        ) ?? 0) + ($this->int(
            "SELECT COUNT(*) FROM training_enrollments WHERE status = 'completed'" . $t(),
            $p()
        ) ?? 0);

        $units = $this->int('SELECT COUNT(*) FROM units WHERE 1 = 1' . $t(), $p()) ?? 0;

        // Communautés : espaces créés, hors espace technique par défaut de la plateforme.
        $communities = $tenantId !== null ? 1 : ($this->int(
            "SELECT COUNT(*) FROM tenants WHERE COALESCE(slug, '') <> 'default'"
        ) ?? $this->int('SELECT COUNT(*) FROM tenants') ?? 0);

        // Kilomètres parcourus en jeu, par mode de déplacement (mètres cumulés → km entiers).
        $sums = [];
        foreach (self::DISTANCE_MODES as $mode => $col) {
            $sums[] = "COALESCE(SUM({$col}), 0) AS {$mode}";
        }
        $row = ($this->rows('SELECT COUNT(*) AS sessions, ' . implode(', ', $sums) . ' FROM atak_distance_stats WHERE 1 = 1' . $t(), $p()) ?? [])[0] ?? [];
        $modes = [];
        $totalM = 0;
        foreach (array_keys(self::DISTANCE_MODES) as $mode) {
            $m = max(0, (int) ($row[$mode] ?? 0));
            $modes[$mode] = intdiv($m, 1000);
            $totalM += $m;
        }

        // Heures de jeu : lignes « play / total » (une par membre et par jour).
        $playSeconds = $this->int(
            "SELECT COALESCE(SUM(seconds), 0) FROM atak_screen_time WHERE kind = 'play' AND item_key = 'total'" . $t(),
            $p()
        ) ?? 0;

        return [
            'scope' => $tenantId === null ? 'platform' : 'community',
            'community_name' => $communityName,
            'founded_at' => $this->day($founded),
            'members' => $members,
            'operations' => $operations,
            'flight_hours' => intdiv(max(0, $flightSeconds), 3600),
            'academy_validated' => $academy,
            'orbat_units' => $units,
            'communities' => $communities,
            'distance_km' => intdiv($totalM, 1000),
            'distance_modes' => $modes,
            'distance_sessions' => max(0, (int) ($row['sessions'] ?? 0)),
            'play_hours' => intdiv(max(0, $playSeconds), 3600),
            'years' => $this->years($tenantId, $now),
            'highlights' => $tenantId === null ? [] : $this->highlights($tenantId, $now),
        ];
    }

    /**
     * Frise annuelle : opérations jouées, nouveaux membres, heures de vol et kilomètres par année.
     *
     * @return list<array{year: string, operations: int, members: int, flight_hours: int, distance_km: int}>
     */
    private function years(?int $tenantId, string $now): array
    {
        $t = $tenantId === null ? '' : ' AND tenant_id = ?';
        $p = static fn (array $params = []): array => $tenantId === null ? $params : [...$params, $tenantId];

        $ops = $this->pairs(
            'SELECT SUBSTR(starts_at, 1, 4) AS y, COUNT(*) AS n FROM community_events
             WHERE cancelled_at IS NULL AND starts_at <= ?' . $t . ' GROUP BY SUBSTR(starts_at, 1, 4)',
            $p([$now])
        );
        $members = $this->pairs(
            "SELECT SUBSTR(created_at, 1, 4) AS y, COUNT(*) AS n FROM users
             WHERE status = 'active' AND created_at IS NOT NULL" . $t . ' GROUP BY SUBSTR(created_at, 1, 4)',
            $p()
        );
        $flight = $this->pairs(
            "SELECT SUBSTR(flown_on, 1, 4) AS y, COALESCE(SUM(seconds), 0) AS n FROM pilot_logbook_entries
             WHERE status <> 'rejected'" . $t . ' GROUP BY SUBSTR(flown_on, 1, 4)',
            $p()
        );

        $distance = $this->pairs(
            'SELECT SUBSTR(day, 1, 4) AS y, COALESCE(SUM(' . implode(' + ', self::DISTANCE_MODES) . '), 0) AS n
             FROM atak_distance_stats WHERE 1 = 1' . $t . ' GROUP BY SUBSTR(day, 1, 4)',
            $p()
        );

        $all = array_unique([...array_keys($ops), ...array_keys($members), ...array_keys($flight), ...array_keys($distance)]);
        $all = array_values(array_filter(array_map('strval', $all), static fn (string $y): bool => preg_match('/^\d{4}$/', $y) === 1));
        sort($all);

        $out = [];
        foreach ($all as $year) {
            $out[] = [
                'year' => $year,
                'operations' => $ops[$year] ?? 0,
                'members' => $members[$year] ?? 0,
                'flight_hours' => intdiv($flight[$year] ?? 0, 3600),
                'distance_km' => intdiv($distance[$year] ?? 0, 1000),
            ];
        }

        return $out;
    }

    /**
     * Opérations marquantes (les plus suivies) et articles publiés d’une communauté.
     *
     * @return list<array{kind: string, date: string, title: string, value: ?int}>
     */
    private function highlights(int $tenantId, string $now): array
    {
        $items = [];
        foreach ($this->rows(
            "SELECT e.title, e.starts_at AS d,
                    (SELECT COUNT(*) FROM community_event_rsvps r WHERE r.event_id = e.id AND r.status = 'yes') AS n
             FROM community_events e
             WHERE e.tenant_id = ? AND e.cancelled_at IS NULL AND e.starts_at <= ?
             ORDER BY n DESC, e.starts_at DESC
             LIMIT " . self::HIGHLIGHTS_LIMIT,
            [$tenantId, $now]
        ) ?? $this->rows(
            // Sans table d’inscriptions : les plus récentes.
            'SELECT title, starts_at AS d, NULL AS n FROM community_events
             WHERE tenant_id = ? AND cancelled_at IS NULL AND starts_at <= ?
             ORDER BY starts_at DESC LIMIT ' . self::HIGHLIGHTS_LIMIT,
            [$tenantId, $now]
        ) ?? [] as $row) {
            $items[] = ['kind' => 'operation', 'row' => $row];
        }
        foreach ($this->rows(
            "SELECT title, published_at AS d, NULL AS n FROM tenant_mini_articles
             WHERE tenant_id = ? AND status = 'published' AND published_at IS NOT NULL AND published_at <= ?
             ORDER BY published_at DESC LIMIT " . self::HIGHLIGHTS_LIMIT,
            [$tenantId, $now]
        ) ?? [] as $row) {
            $items[] = ['kind' => 'article', 'row' => $row];
        }

        $out = [];
        foreach ($items as $item) {
            $date = $this->day($item['row']['d'] ?? null);
            $title = trim((string) ($item['row']['title'] ?? ''));
            if ($date === null || $title === '') {
                continue;
            }
            $n = $item['row']['n'] ?? null;
            $out[] = [
                'kind' => $item['kind'],
                'date' => $date,
                'title' => mb_substr($title, 0, 160),
                'value' => $n === null ? null : (int) $n,
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $out;
    }

    private function day(mixed $value): ?string
    {
        $day = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && $day !== '0000-00-00' ? $day : null;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** @param list<mixed> $params */
    private function value(string $sql, array $params = []): mixed
    {
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);
            $v = $st->fetchColumn();
        } catch (\Throwable) {
            return null;
        }

        return $v === false ? null : $v;
    }

    /**
     * @param list<mixed> $params
     */
    private function int(string $sql, array $params = []): ?int
    {
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);
            $v = $st->fetchColumn();
        } catch (\Throwable) {
            // Table ou colonne absente : la source est ignorée.
            return null;
        }

        return max(0, (int) $v);
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>|null
     */
    private function rows(string $sql, array $params = []): ?array
    {
        try {
            $st = $this->db()->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param list<mixed> $params
     * @return array<string, int>
     */
    private function pairs(string $sql, array $params = []): array
    {
        $out = [];
        foreach ($this->rows($sql, $params) ?? [] as $row) {
            $year = (string) ($row['y'] ?? '');
            if ($year !== '') {
                $out[$year] = max(0, (int) ($row['n'] ?? 0));
            }
        }

        return $out;
    }

    private function cachePath(string $key): string
    {
        $dir = $this->cacheDir ?? base_path('storage/cache');

        return rtrim($dir, '/\\') . '/public_history_' . $key . '.json';
    }

    /** @return array<string, mixed>|null */
    private function readCache(string $key): ?array
    {
        $path = $this->cachePath($key);
        if (!is_file($path) || filemtime($path) < time() - self::CACHE_TTL) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data) || !isset($data['scope'], $data['years'], $data['highlights'], $data['distance_modes'])) {
            return null;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function writeCache(string $key, array $data): void
    {
        $path = $this->cachePath($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, (string) json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
