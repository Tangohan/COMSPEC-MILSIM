<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakReleaseInfo;
use PDO;

/**
 * Métriques du Data Inspector, lues sur les tables réellement alimentées par le jeu :
 * sessions de jeu, profils opérateur, mods détectés, terminaux ATAK, trafic d'ingestion,
 * files d'envoi COMSPEC Link, comptes rendus, rapports d'incident du mod, télémétrie HTTP.
 *
 * Portée : une communauté (tenant) ou toute la plateforme (tenantId = null).
 * Chaque lecture est isolée : une table absente rend une section vide, jamais une erreur.
 */
final class AthenaInspectorMetricsRepository
{
    /** Terminal vu depuis moins de N minutes = en ligne ; moins de 30 min = dégradé. */
    public const ONLINE_MIN = 5;
    public const DEGRADED_MIN = 30;

    /** @var list<string> sources indisponibles rencontrées pendant la requête */
    private array $missing = [];

    public function __construct(private ?PDO $pdo = null, private float $telemetrySampleRate = 0.05)
    {
        if (function_exists('env')) {
            $this->telemetrySampleRate = max(0.0001, min(1.0, (float) env('REQUEST_TELEMETRY_SAMPLE_RATE', $telemetrySampleRate)));
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::getPdo();
            // Les compteurs par joueur sont neufs : sans leurs tables, les jointures Steam échoueraient.
            (new AtakIngestPlayerStatsRepository($this->pdo))->ensure();
        }

        return $this->pdo;
    }

    /** @return list<string> */
    public function missingSources(): array
    {
        return array_values(array_unique($this->missing));
    }

    // =================================================================== helpers

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $source, string $sql, array $params = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[data-inspector] ' . $source . ' : ' . mb_substr($e->getMessage(), 0, 300));
            $this->missing[] = $source;

            return [];
        }
    }

    /** @param list<mixed> $params */
    private function one(string $source, string $sql, array $params = []): array
    {
        return $this->rows($source, $sql, $params)[0] ?? [];
    }

    /** @param list<mixed> $params */
    private function scalar(string $source, string $sql, array $params = []): int
    {
        $r = $this->one($source, $sql, $params);

        return (int) (array_values($r)[0] ?? 0);
    }

    /**
     * Clause tenant + paramètres.
     *
     * @return array{0:string,1:list<mixed>}
     */
    private function t(?int $tenantId, string $alias = ''): array
    {
        $col = ($alias !== '' ? $alias . '.' : '') . 'tenant_id';

        return $tenantId === null ? ['1=1', []] : [$col . ' = ?', [$tenantId]];
    }

    /** Sous-requête des identifiants Steam connus d'une communauté (pour les tables sans tenant_id). */
    private function tenantSteamSubquery(): string
    {
        return '(SELECT steam_id FROM game_sessions WHERE tenant_id = ? AND steam_id IS NOT NULL
                 UNION SELECT steam_id FROM operator_game_profiles WHERE tenant_id = ?
                 UNION SELECT steam_uid FROM atak_send_queue_snapshots WHERE tenant_id = ?
                 UNION SELECT steam_uid FROM atak_ingest_player_daily WHERE tenant_id = ?)';
    }

    /** Décalage horaire de MySQL (NOW() vs UTC), pour convertir les dates en ISO-8601. */
    public function dbOffset(): string
    {
        $r = $this->one('db', 'SELECT TIME_FORMAT(TIMEDIFF(NOW(), UTC_TIMESTAMP()), "%H:%i") AS o');
        $o = (string) ($r['o'] ?? '00:00');

        return str_starts_with($o, '-') ? $o : '+' . $o;
    }

    // =================================================================== vue d'ensemble

    /**
     * @return array<string, mixed>
     */
    public function overview(?int $tenantId, int $hours): array
    {
        [$w, $p] = $this->t($tenantId);

        $players = $this->scalar('joueurs', "SELECT COUNT(DISTINCT k) FROM (
                SELECT steam_id AS k FROM game_sessions WHERE {$w} AND last_seen_at >= NOW() - INTERVAL ? HOUR AND steam_id IS NOT NULL
                UNION SELECT steam_id FROM operator_game_profiles WHERE {$w} AND last_seen_at >= NOW() - INTERVAL ? HOUR
                UNION SELECT steam_uid FROM atak_ingest_player_daily WHERE {$w} AND last_at >= NOW() - INTERVAL ? HOUR
            ) x", array_merge($p, [$hours], $p, [$hours], $p, [$hours]));

        $term = $this->one('terminaux', "SELECT COUNT(*) total,
                SUM(last_seen_at >= NOW() - INTERVAL " . self::ONLINE_MIN . " MINUTE) online,
                SUM(last_seen_at >= NOW() - INTERVAL ? HOUR) active,
                SUM(compromise_state NOT IN ('none','')) compromised
            FROM atak_terminals WHERE {$w}", array_merge([$hours], $p));

        $traffic = $this->one('trafic', "SELECT COALESCE(SUM(bytes),0) bytes, COALESCE(SUM(photo_bytes),0) photo_bytes,
                COALESCE(SUM(requests),0) requests FROM atak_ingest_traffic WHERE {$w} AND bucket_ts >= NOW() - INTERVAL ? HOUR",
            array_merge($p, [$hours]));

        $reports = $this->reportCounts($tenantId, $hours);
        $http = $this->httpHealth($tenantId, $hours);
        $ingest = $this->one('ingestion', "SELECT COALESCE(SUM(events_accepted),0) acc, COALESCE(SUM(events_rejected),0) rej
            FROM atak_ingest_player_daily WHERE {$w} AND day >= CURDATE() - INTERVAL ? DAY",
            array_merge($p, [max(0, intdiv($hours, 24))]));
        $acc = (int) ($ingest['acc'] ?? 0);
        $rej = (int) ($ingest['rej'] ?? 0);

        $incidents = $this->modReportCount($tenantId, $hours);
        $versions = $this->versionCompliance($tenantId, $hours);

        $out = [
            'players_active' => $players,
            'terminals_total' => (int) ($term['total'] ?? 0),
            'terminals_online' => (int) ($term['online'] ?? 0),
            'terminals_active' => (int) ($term['active'] ?? 0),
            'terminals_compromised' => (int) ($term['compromised'] ?? 0),
            'bytes' => (int) ($traffic['bytes'] ?? 0),
            'photo_bytes' => (int) ($traffic['photo_bytes'] ?? 0),
            'requests' => (int) ($traffic['requests'] ?? 0),
            'reports' => $reports,
            'reports_total' => array_sum(array_column($reports, 'count')),
            'events_accepted' => $acc,
            'events_rejected' => $rej,
            'ingest_error_rate' => ($acc + $rej) > 0 ? round($rej / ($acc + $rej) * 100, 2) : null,
            'http' => $http,
            'incidents' => $incidents,
            'versions' => $versions,
            'series' => $this->hourlySeries($tenantId, $hours),
        ];
        if ($tenantId === null) {
            $out['communities_total'] = $this->scalar('communautés', 'SELECT COUNT(*) FROM tenants');
            $out['communities_active'] = $this->scalar('communautés', "SELECT COUNT(DISTINCT tenant_id) FROM atak_ingest_traffic
                WHERE bucket_ts >= NOW() - INTERVAL ? HOUR", [$hours]);
        }

        return $out;
    }

    /**
     * Comptes rendus et données remontés du jeu, par catégorie.
     *
     * @return list<array{key:string,label:string,count:int}>
     */
    public function reportCounts(?int $tenantId, int $hours): array
    {
        [$w, $p] = $this->t($tenantId);
        $defs = [
            ['cr', 'Comptes rendus tactiques', "SELECT COUNT(*) FROM atak_tactical_reports WHERE {$w} AND deleted_at IS NULL AND created_at >= NOW() - INTERVAL ? HOUR"],
            ['sse', 'Fiches SSE', "SELECT COUNT(*) FROM sse_field_notes WHERE {$w} AND created_at >= NOW() - INTERVAL ? HOUR"],
            ['medevac', 'MEDEVAC / 9-line', "SELECT COUNT(*) FROM atak_nine_line WHERE {$w} AND created_at >= NOW() - INTERVAL ? HOUR"],
            ['sigint', 'SIGINT', "SELECT COUNT(*) FROM atak_sigint_reports WHERE {$w} AND created_at >= NOW() - INTERVAL ? HOUR"],
            ['photos', 'Photos / recon', "SELECT COUNT(*) FROM recon_images WHERE {$w} AND created_at >= NOW() - INTERVAL ? HOUR"],
            ['chat', 'Messages jeu', "SELECT COUNT(*) FROM atak_chat_messages WHERE {$w} AND source = 'game' AND created_at >= NOW() - INTERVAL ? HOUR"],
        ];
        $out = [];
        foreach ($defs as [$key, $label, $sql]) {
            $out[] = ['key' => $key, 'label' => $label, 'count' => $this->scalar($label, $sql, array_merge($p, [$hours]))];
        }

        return $out;
    }

    /**
     * Santé HTTP des API jeu (request_telemetry : erreurs et requêtes lentes tracées à 100 %,
     * succès échantillonnés → total estimé).
     *
     * @return array<string, mixed>
     */
    public function httpHealth(?int $tenantId, int $hours): array
    {
        [$w, $p] = $this->t($tenantId);
        $r = $this->one('télémétrie HTTP', "SELECT
                SUM(status_code >= 500) e5, SUM(status_code BETWEEN 400 AND 499) e4,
                SUM(status_code < 400 AND duration_ms >= 1000) slow_ok,
                SUM(status_code < 400 AND duration_ms < 1000) fast_ok,
                ROUND(AVG(CASE WHEN status_code < 400 THEN duration_ms END)) avg_ms
            FROM request_telemetry WHERE {$w} AND route_path LIKE '/api/%' AND created_at >= NOW() - INTERVAL ? HOUR",
            array_merge($p, [$hours]));
        $e5 = (int) ($r['e5'] ?? 0);
        $e4 = (int) ($r['e4'] ?? 0);
        $total = $e5 + $e4 + (int) ($r['slow_ok'] ?? 0) + (int) round(((int) ($r['fast_ok'] ?? 0)) / $this->telemetrySampleRate);

        return [
            'requests_estimated' => $total,
            'errors_4xx' => $e4,
            'errors_5xx' => $e5,
            'error_rate' => $total > 0 ? round(($e4 + $e5) / $total * 100, 2) : null,
            'availability' => $total > 0 ? round((1 - $e5 / $total) * 100, 3) : null,
            'avg_ms' => isset($r['avg_ms']) ? (int) $r['avg_ms'] : null,
            'sample_rate' => $this->telemetrySampleRate,
        ];
    }

    /**
     * Séries horaires : octets, requêtes, erreurs HTTP, disponibilité estimée.
     *
     * @return list<array<string, mixed>>
     */
    public function hourlySeries(?int $tenantId, int $hours): array
    {
        $hours = min($hours, 24 * 30);
        $step = $hours > 72 ? 24 : 1; // > 3 jours : un point par jour
        [$w, $p] = $this->t($tenantId);
        $bucket = $step === 24 ? "DATE_FORMAT(%s, '%%Y-%%m-%%d 00:00:00')" : "DATE_FORMAT(%s, '%%Y-%%m-%%d %%H:00:00')";

        $traffic = $this->rows('trafic', 'SELECT ' . sprintf($bucket, 'bucket_ts') . " b, SUM(bytes) bytes, SUM(requests) requests
            FROM atak_ingest_traffic WHERE {$w} AND bucket_ts >= NOW() - INTERVAL ? HOUR GROUP BY b", array_merge($p, [$hours]));
        $http = $this->rows('télémétrie HTTP', 'SELECT ' . sprintf($bucket, 'created_at') . " b,
                SUM(status_code >= 500) e5, SUM(status_code BETWEEN 400 AND 499) e4,
                SUM(status_code < 400 AND duration_ms >= 1000) slow_ok, SUM(status_code < 400 AND duration_ms < 1000) fast_ok
            FROM request_telemetry WHERE {$w} AND route_path LIKE '/api/%' AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY b",
            array_merge($p, [$hours]));
        $incidents = $tenantId === null
            ? $this->rows('incidents mod', 'SELECT ' . sprintf($bucket, 'last_seen_at') . ' b, SUM(hit_count) n FROM atak_mod_reports
                WHERE last_seen_at >= NOW() - INTERVAL ? HOUR GROUP BY b', [$hours])
            : $this->rows('incidents mod', 'SELECT ' . sprintf($bucket, 'last_seen_at') . ' b, SUM(hit_count) n FROM atak_mod_reports
                WHERE last_seen_at >= NOW() - INTERVAL ? HOUR AND steam_uid IN ' . $this->tenantSteamSubquery() . ' GROUP BY b',
                [$hours, $tenantId, $tenantId, $tenantId, $tenantId]);

        $idx = [];
        foreach ($traffic as $r) {
            $idx[$r['b']]['bytes'] = (int) $r['bytes'];
            $idx[$r['b']]['requests'] = (int) $r['requests'];
        }
        foreach ($http as $r) {
            $e5 = (int) $r['e5'];
            $e4 = (int) $r['e4'];
            $tot = $e5 + $e4 + (int) $r['slow_ok'] + (int) round(((int) $r['fast_ok']) / $this->telemetrySampleRate);
            $idx[$r['b']]['errors'] = $e4 + $e5;
            $idx[$r['b']]['errors_5xx'] = $e5;
            $idx[$r['b']]['availability'] = $tot > 0 ? round((1 - $e5 / $tot) * 100, 2) : null;
        }
        foreach ($incidents as $r) {
            $idx[$r['b']]['incidents'] = (int) $r['n'];
        }

        // Points vides inclus pour un axe temporel continu.
        $out = [];
        $now = new \DateTimeImmutable($this->one('db', 'SELECT NOW() n')['n'] ?? 'now');
        $start = $step === 24 ? $now->setTime(0, 0)->modify('-' . (intdiv($hours, 24) - 1) . ' days') : $now->setTime((int) $now->format('H'), 0)->modify('-' . ($hours - 1) . ' hours');
        for ($t = $start; $t <= $now; $t = $t->modify('+' . $step . ' hours')) {
            $k = $t->format('Y-m-d H:i:s');
            $out[] = array_merge(['t' => $k, 'bytes' => 0, 'requests' => 0, 'errors' => 0, 'errors_5xx' => 0, 'availability' => null, 'incidents' => 0], $idx[$k] ?? []);
        }

        return $out;
    }

    private function modReportCount(?int $tenantId, int $hours): array
    {
        $r = $tenantId === null
            ? $this->one('incidents mod', "SELECT COUNT(*) tickets, COALESCE(SUM(hit_count),0) hits, SUM(workflow_status = 'new') open
                FROM atak_mod_reports WHERE last_seen_at >= NOW() - INTERVAL ? HOUR", [$hours])
            : $this->one('incidents mod', "SELECT COUNT(*) tickets, COALESCE(SUM(hit_count),0) hits, SUM(workflow_status = 'new') open
                FROM atak_mod_reports WHERE last_seen_at >= NOW() - INTERVAL ? HOUR AND steam_uid IN " . $this->tenantSteamSubquery(),
                [$hours, $tenantId, $tenantId, $tenantId, $tenantId]);

        return ['tickets' => (int) ($r['tickets'] ?? 0), 'hits' => (int) ($r['hits'] ?? 0), 'open' => (int) ($r['open'] ?? 0)];
    }

    // =================================================================== versions & mods

    /**
     * Versions mod / COMSPEC Link utilisées par les joueurs vs la dernière publiée.
     *
     * @return array<string, mixed>
     */
    public function versionCompliance(?int $tenantId, int $hours): array
    {
        [$w, $p] = $this->t($tenantId, 'g');
        $latest = ['atak_native' => null, 'link' => null];
        try {
            $latest = AtakReleaseInfo::latest();
        } catch (\Throwable) {
        }
        // Dernière session de chaque joueur.
        $rows = $this->rows('sessions de jeu', "SELECT g.steam_id, g.mod_version, g.extension_version FROM game_sessions g
            JOIN (SELECT steam_id, MAX(id) id FROM game_sessions g WHERE {$w} AND steam_id IS NOT NULL
                  AND COALESCE(last_seen_at, created_at) >= NOW() - INTERVAL ? HOUR GROUP BY steam_id) m ON m.id = g.id",
            array_merge($p, [$hours]));
        $mod = [];
        $ext = [];
        $outdated = 0;
        foreach ($rows as $r) {
            $mv = trim((string) $r['mod_version']) ?: 'inconnue';
            $ev = trim((string) $r['extension_version']) ?: 'inconnue';
            $mod[$mv] = ($mod[$mv] ?? 0) + 1;
            $ext[$ev] = ($ext[$ev] ?? 0) + 1;
            if (($latest['link'] ?? null) && $ev !== 'inconnue' && version_compare($ev, (string) $latest['link'], '<')) {
                $outdated++;
            }
        }
        uksort($mod, static fn ($a, $b) => version_compare((string) $b, (string) $a));
        uksort($ext, static fn ($a, $b) => version_compare((string) $b, (string) $a));

        return [
            'latest_mod' => $latest['atak_native'] ?? null,
            'latest_link' => $latest['link'] ?? null,
            'players' => count($rows),
            'outdated' => $outdated,
            'mod_versions' => $mod,
            'link_versions' => $ext,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mods(?int $tenantId, int $hours, string $search = ''): array
    {
        [$w, $p] = $this->t($tenantId, 'm');
        [$w2, $p2] = $this->t($tenantId, 'pm');
        $like = '%' . $search . '%';
        // Tri sur l’agrégat, pas sur la colonne last_seen : avec ONLY_FULL_GROUP_BY, ORDER BY last_seen
        // vise la colonne brute, la lecture des noms échoue, et l’onglet annonce une source vide
        // alors que les listes joueurs et les versions de session sont déjà là.
        $community = $tenantId === null ? ', COUNT(DISTINCT m.tenant_id) AS communities' : '';
        $mods = $this->rows(
            'catalogue des mods',
            "SELECT m.mod_dir, MAX(m.name) AS name, MAX(m.workshop_id) AS workshop_id, MAX(m.is_dlc) AS is_dlc,
                    MIN(m.first_seen) AS first_seen, MAX(m.last_seen) AS last_seen{$community}
             FROM game_detected_mods m
             WHERE {$w} AND (? = '' OR m.mod_dir LIKE ? OR m.name LIKE ?)
             GROUP BY m.mod_dir
             ORDER BY MAX(m.last_seen) DESC
             LIMIT 2000",
            array_merge($p, [$search, $like, $like])
        );
        $catalogAvailable = !in_array('catalogue des mods', $this->missing, true);

        $byDir = [];
        if ($mods !== []) {
            foreach ($this->rows(
                'listes de mods des joueurs',
                "SELECT pm.mod_dir,
                        COUNT(DISTINCT pm.steam_id) AS players,
                        COUNT(DISTINCT CASE WHEN pm.last_seen >= NOW() - INTERVAL ? HOUR THEN pm.steam_id END) AS players_recent
                 FROM game_player_mods pm
                 WHERE {$w2}
                 GROUP BY pm.mod_dir",
                array_merge([$hours], $p2)
            ) as $row) {
                $byDir[(string) $row['mod_dir']] = $row;
            }
        }
        foreach ($mods as &$m) {
            $stats = $byDir[(string) $m['mod_dir']] ?? null;
            $m['players'] = (int) ($stats['players'] ?? 0);
            $m['players_recent'] = (int) ($stats['players_recent'] ?? 0);
            $m['is_dlc'] = (int) ($m['is_dlc'] ?? 0) === 1;
            if ($tenantId === null) {
                $m['communities'] = (int) ($m['communities'] ?? 0);
            }
        }
        unset($m);
        usort($mods, static fn (array $a, array $b): int => [$b['players'], (string) $b['last_seen']] <=> [$a['players'], (string) $a['last_seen']]);
        $modsTotal = count($mods);
        if ($modsTotal > 400) {
            $mods = array_slice($mods, 0, 400);
        }

        $summary = $this->one(
            'listes de mods des joueurs',
            "SELECT COUNT(*) AS lists, COALESCE(ROUND(AVG(n)), 0) AS avg_mods, COALESCE(MAX(n), 0) AS max_mods
             FROM (
                SELECT steam_id, COUNT(*) AS n
                FROM game_player_mods pm
                WHERE {$w2}
                GROUP BY steam_id
             ) player_lists",
            $p2
        );

        return [
            'mods' => $mods,
            'mods_total' => $catalogAvailable ? $modsTotal : 0,
            'catalog_available' => $catalogAvailable,
            'players_with_list' => (int) ($summary['lists'] ?? 0),
            'avg_mods_per_player' => (int) ($summary['avg_mods'] ?? 0),
            'max_mods_per_player' => (int) ($summary['max_mods'] ?? 0),
            'versions' => $this->versionCompliance($tenantId, $hours),
        ];
    }

    /**
     * Joueurs ayant chargé un mod donné.
     *
     * @return list<array<string, mixed>>
     */
    public function modPlayers(?int $tenantId, string $modDir): array
    {
        [$w, $p] = $this->t($tenantId, 'pm');

        return $this->rows('mods détectés', "SELECT pm.steam_id, pm.last_seen, pm.tenant_id,
                (SELECT o.callsign FROM operator_game_profiles o WHERE o.steam_id = pm.steam_id AND o.tenant_id = pm.tenant_id ORDER BY o.last_seen_at DESC LIMIT 1) callsign
            FROM game_player_mods pm WHERE {$w} AND pm.mod_dir = ? ORDER BY pm.last_seen DESC LIMIT 300", array_merge($p, [$modDir]));
    }

    // =================================================================== terminaux

    /**
     * @return array<string, mixed>
     */
    public function terminals(?int $tenantId, string $search = ''): array
    {
        [$w, $p] = $this->t($tenantId, 'a');
        $like = '%' . $search . '%';
        $rows = $this->rows('terminaux', "SELECT a.id, a.tenant_id, " . ($tenantId === null ? 't.name tenant_name,' : '') . "
                a.terminal_uid, a.terminal_label, a.terminal_type, a.platform_label, a.operator_callsign, a.user_id,
                COALESCE(u.display_name, u.callsign) user_label, a.status, a.compromise_state,
                a.first_seen_at, a.last_seen_at, a.mod_version, a.extension_version, a.server_host,
                CASE WHEN a.last_seen_at >= NOW() - INTERVAL " . self::ONLINE_MIN . " MINUTE THEN 'online'
                     WHEN a.last_seen_at >= NOW() - INTERVAL " . self::DEGRADED_MIN . " MINUTE THEN 'degraded'
                     WHEN a.last_seen_at IS NULL THEN 'never' ELSE 'offline' END live,
                s.pending sync_pending, s.reported_at sync_at
            FROM atak_terminals a
            LEFT JOIN users u ON u.id = a.user_id
            " . ($tenantId === null ? 'LEFT JOIN tenants t ON t.id = a.tenant_id' : '') . "
            LEFT JOIN atak_terminal_sync s ON s.tenant_id = a.tenant_id AND s.terminal_uid = a.terminal_uid
            WHERE {$w} AND (? = '' OR a.terminal_uid LIKE ? OR a.terminal_label LIKE ? OR a.operator_callsign LIKE ?)
            ORDER BY a.last_seen_at IS NULL, a.last_seen_at DESC LIMIT 500",
            array_merge($p, [$search, $like, $like, $like]));
        if ($rows === [] && in_array('terminaux', $this->missing, true)) {
            // atak_terminal_sync absente sur certaines installations : relecture sans la jointure.
            $this->missing = array_values(array_diff($this->missing, ['terminaux']));
            $rows = $this->rows('terminaux', "SELECT a.*, COALESCE(u.display_name, u.callsign) user_label,
                    CASE WHEN a.last_seen_at >= NOW() - INTERVAL " . self::ONLINE_MIN . " MINUTE THEN 'online'
                         WHEN a.last_seen_at >= NOW() - INTERVAL " . self::DEGRADED_MIN . " MINUTE THEN 'degraded'
                         WHEN a.last_seen_at IS NULL THEN 'never' ELSE 'offline' END live
                FROM atak_terminals a LEFT JOIN users u ON u.id = a.user_id
                WHERE {$w} AND (? = '' OR a.terminal_uid LIKE ? OR a.terminal_label LIKE ? OR a.operator_callsign LIKE ?)
                ORDER BY a.last_seen_at IS NULL, a.last_seen_at DESC LIMIT 500", array_merge($p, [$search, $like, $like, $like]));
        }
        $byType = [];
        $byLive = ['online' => 0, 'degraded' => 0, 'offline' => 0, 'never' => 0];
        foreach ($rows as $r) {
            $byType[$r['terminal_type']] = ($byType[$r['terminal_type']] ?? 0) + 1;
            $byLive[$r['live']] = ($byLive[$r['live']] ?? 0) + 1;
        }
        arsort($byType);

        return ['terminals' => $rows, 'by_type' => $byType, 'by_status' => $byLive, 'online_min' => self::ONLINE_MIN];
    }

    // =================================================================== débit

    /**
     * @return array<string, mixed>
     */
    public function traffic(?int $tenantId, int $hours): array
    {
        [$w, $p] = $this->t($tenantId);
        $days = max(1, (int) ceil($hours / 24));

        $topPlayers = $this->rows('ingestion par joueur', "SELECT steam_uid, MAX(callsign) callsign, SUM(bytes) bytes, SUM(photo_bytes) photo_bytes,
                SUM(requests) requests, SUM(events_accepted) accepted, SUM(events_rejected) rejected, MAX(last_at) last_at
            FROM atak_ingest_player_daily WHERE {$w} AND day >= CURDATE() - INTERVAL ? DAY
            GROUP BY steam_uid ORDER BY bytes DESC LIMIT 25", array_merge($p, [$days - 1]));

        $byGroup = $tenantId === null
            ? $this->rows('trafic', "SELECT x.tenant_id, t.name label, SUM(x.bytes) bytes, SUM(x.requests) requests, SUM(x.photo_bytes) photo_bytes
                FROM atak_ingest_traffic x LEFT JOIN tenants t ON t.id = x.tenant_id
                WHERE x.bucket_ts >= NOW() - INTERVAL ? HOUR GROUP BY x.tenant_id ORDER BY bytes DESC LIMIT 25", [$hours])
            : $this->rows('trafic', "SELECT map_id, CONCAT('Carte ', map_id) label, SUM(bytes) bytes, SUM(requests) requests, SUM(photo_bytes) photo_bytes
                FROM atak_ingest_traffic WHERE {$w} AND bucket_ts >= NOW() - INTERVAL ? HOUR GROUP BY map_id ORDER BY bytes DESC",
                array_merge($p, [$hours]));

        $peak = $this->one('trafic', "SELECT bucket_ts, SUM(bytes) b FROM atak_ingest_traffic WHERE {$w}
            AND bucket_ts >= NOW() - INTERVAL ? HOUR GROUP BY bucket_ts ORDER BY b DESC LIMIT 1", array_merge($p, [$hours]));

        // Files d'envoi COMSPEC Link : dernier relevé par joueur.
        [$wq, $pq] = $this->t($tenantId, 's');
        $queues = $this->rows("files d'envoi", "SELECT s.steam_uid, s.call_sign, s.client_kind, s.mod_version, s.extension_version,
                s.queued, s.retry, s.failed, s.done, s.oldest_queued_s, s.overload, s.avg_latency_ms, s.p95_latency_ms,
                s.http_429, s.http_5xx, s.timeouts, s.received_at
            FROM atak_send_queue_snapshots s
            JOIN (SELECT tenant_id, steam_uid, MAX(id) id FROM atak_send_queue_snapshots s WHERE {$wq}
                  AND received_at >= NOW() - INTERVAL ? HOUR GROUP BY tenant_id, steam_uid) m ON m.id = s.id
            ORDER BY s.failed DESC, s.p95_latency_ms DESC LIMIT 100", array_merge($pq, [$hours]));
        $q = ['clients' => count($queues), 'avg_latency_ms' => 0, 'p95_latency_ms' => 0, 'failed' => 0, 'http_429' => 0, 'http_5xx' => 0, 'timeouts' => 0, 'overloaded' => 0];
        foreach ($queues as $r) {
            $q['avg_latency_ms'] += (int) $r['avg_latency_ms'];
            $q['p95_latency_ms'] = max($q['p95_latency_ms'], (int) $r['p95_latency_ms']);
            foreach (['failed', 'http_429', 'http_5xx', 'timeouts'] as $k) {
                $q[$k] += (int) $r[$k];
            }
            $q['overloaded'] += $r['overload'] !== 'ok' ? 1 : 0;
        }
        $q['avg_latency_ms'] = $queues !== [] ? (int) round($q['avg_latency_ms'] / count($queues)) : 0;

        $sum = $this->one('trafic', "SELECT COALESCE(SUM(bytes),0) bytes, COALESCE(SUM(photo_bytes),0) photo_bytes, COALESCE(SUM(requests),0) requests
            FROM atak_ingest_traffic WHERE {$w} AND bucket_ts >= NOW() - INTERVAL ? HOUR", array_merge($p, [$hours]));

        return [
            'bytes' => (int) ($sum['bytes'] ?? 0),
            'photo_bytes' => (int) ($sum['photo_bytes'] ?? 0),
            'requests' => (int) ($sum['requests'] ?? 0),
            'avg_kbps' => round(((int) ($sum['bytes'] ?? 0)) * 8 / 1000 / max(1, $hours * 3600), 2),
            'peak' => ['at' => $peak['bucket_ts'] ?? null, 'kbps' => round(((int) ($peak['b'] ?? 0)) * 8 / 1000 / 10, 1)],
            'series' => $this->hourlySeries($tenantId, $hours),
            'by_group' => $byGroup,
            'group_kind' => $tenantId === null ? 'communauté' : 'carte',
            'top_players' => $topPlayers,
            'queues' => $queues,
            'queue_summary' => $q,
        ];
    }

    // =================================================================== joueurs

    /**
     * Classement des joueurs par volume remonté (toutes sources), avec leur santé de liaison.
     *
     * @return list<array<string, mixed>>
     */
    public function players(?int $tenantId, int $hours, string $search = ''): array
    {
        [$w, $p] = $this->t($tenantId);
        [$wo] = $this->t($tenantId, 'o');
        [$wg] = $this->t($tenantId, 'g');
        $days = max(1, (int) ceil($hours / 24));
        $players = [];
        $csToSteam = [];

        $add = function (string $key, array $fields) use (&$players): void {
            $players[$key] ??= [
                'key' => $key, 'steam_uid' => str_starts_with($key, 'cs:') ? null : $key, 'callsign' => null, 'user_label' => null,
                'tenant_id' => null, 'bytes' => 0, 'requests' => 0, 'events_accepted' => 0, 'events_rejected' => 0,
                'reports' => 0, 'sse_notes' => 0, 'photos' => 0, 'chat' => 0, 'incidents' => 0,
                'mod_version' => null, 'extension_version' => null, 'last_at' => null,
            ];
            foreach ($fields as $k => $v) {
                if (in_array($k, ['callsign', 'user_label', 'mod_version', 'extension_version', 'tenant_id'], true)) {
                    if ($v !== null && $v !== '' && $players[$key][$k] === null) {
                        $players[$key][$k] = $v;
                    }
                } elseif ($k === 'last_at') {
                    if ($v !== null && ($players[$key]['last_at'] === null || $v > $players[$key]['last_at'])) {
                        $players[$key]['last_at'] = $v;
                    }
                } else {
                    $players[$key][$k] += (int) $v;
                }
            }
        };
        $resolve = function (?string $steam, ?string $cs) use (&$csToSteam): ?string {
            $steam = trim((string) $steam);
            if ($steam !== '') {
                return $steam;
            }
            $c = mb_strtolower(trim((string) $cs), 'UTF-8');

            return $c === '' ? null : ($csToSteam[$c] ?? 'cs:' . trim((string) $cs));
        };

        // Identités : profils opérateur (steam ↔ indicatif ↔ compte).
        foreach ($this->rows('profils opérateur', "SELECT o.steam_id, o.callsign, o.tenant_id, o.last_seen_at,
                COALESCE(u.display_name, u.callsign) user_label, o.overwatch_version, o.atak_version
            FROM operator_game_profiles o LEFT JOIN users u ON u.id = o.user_id
            WHERE {$wo} AND o.last_seen_at >= NOW() - INTERVAL ? HOUR", array_merge($p, [$hours])) as $r) {
            if (trim((string) $r['callsign']) !== '') {
                $csToSteam[mb_strtolower(trim((string) $r['callsign']), 'UTF-8')] = (string) $r['steam_id'];
            }
            $add((string) $r['steam_id'], ['callsign' => $r['callsign'], 'user_label' => $r['user_label'], 'tenant_id' => $r['tenant_id'],
                'mod_version' => $r['overwatch_version'], 'last_at' => $r['last_seen_at']]);
        }
        foreach ($this->rows('sessions de jeu', "SELECT g.steam_id, g.tenant_id, MAX(COALESCE(g.last_seen_at, g.created_at)) last_at,
                SUBSTRING_INDEX(GROUP_CONCAT(g.mod_version ORDER BY g.id DESC), ',', 1) mod_version,
                SUBSTRING_INDEX(GROUP_CONCAT(g.extension_version ORDER BY g.id DESC), ',', 1) extension_version,
                MAX(COALESCE(u.display_name, u.callsign)) user_label, MAX(u.callsign) callsign
            FROM game_sessions g LEFT JOIN users u ON u.id = g.user_id
            WHERE {$wg} AND g.steam_id IS NOT NULL AND COALESCE(g.last_seen_at, g.created_at) >= NOW() - INTERVAL ? HOUR
            GROUP BY g.steam_id, g.tenant_id", array_merge($p, [$hours])) as $r) {
            $add((string) $r['steam_id'], ['user_label' => $r['user_label'], 'callsign' => $r['callsign'], 'tenant_id' => $r['tenant_id'],
                'mod_version' => $r['mod_version'], 'extension_version' => $r['extension_version'], 'last_at' => $r['last_at']]);
        }
        foreach ($this->rows('ingestion par joueur', "SELECT steam_uid, MAX(callsign) callsign, MAX(tenant_id) tenant_id, SUM(bytes) bytes,
                SUM(requests) requests, SUM(events_accepted) acc, SUM(events_rejected) rej, MAX(last_at) last_at
            FROM atak_ingest_player_daily WHERE {$w} AND day >= CURDATE() - INTERVAL ? DAY GROUP BY steam_uid",
            array_merge($p, [$days - 1])) as $r) {
            $k = str_starts_with((string) $r['steam_uid'], 'cs:') ? ($resolve(null, substr((string) $r['steam_uid'], 3)) ?? (string) $r['steam_uid']) : (string) $r['steam_uid'];
            $add($k, ['callsign' => $r['callsign'], 'tenant_id' => $r['tenant_id'], 'bytes' => $r['bytes'], 'requests' => $r['requests'],
                'events_accepted' => $r['acc'], 'events_rejected' => $r['rej'], 'last_at' => $r['last_at']]);
        }
        $sources = [
            ['reports', 'comptes rendus', "SELECT submitter_steam_id s, submitter_callsign c, MAX(tenant_id) t, COUNT(*) n, MAX(created_at) l
                FROM atak_tactical_reports WHERE {$w} AND deleted_at IS NULL AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY s, c"],
            ['sse_notes', 'fiches SSE', "SELECT author_steam_id s, author_label c, MAX(tenant_id) t, COUNT(*) n, MAX(created_at) l
                FROM sse_field_notes WHERE {$w} AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY s, c"],
            ['photos', 'photos recon', "SELECT NULL s, author_callsign c, MAX(tenant_id) t, COUNT(*) n, MAX(created_at) l
                FROM recon_images WHERE {$w} AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY c"],
            ['chat', 'messages jeu', "SELECT NULL s, author c, MAX(tenant_id) t, COUNT(*) n, MAX(created_at) l
                FROM atak_chat_messages WHERE {$w} AND source = 'game' AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY c"],
        ];
        foreach ($sources as [$field, $label, $sql]) {
            foreach ($this->rows($label, $sql, array_merge($p, [$hours])) as $r) {
                $k = $resolve($r['s'] ?? null, $r['c'] ?? null);
                if ($k !== null) {
                    $add($k, ['callsign' => $r['c'] ?? null, 'tenant_id' => $r['t'], $field => $r['n'], 'last_at' => $r['l']]);
                }
            }
        }
        $inc = $tenantId === null
            ? $this->rows('incidents mod', 'SELECT steam_uid s, MAX(callsign) c, SUM(hit_count) n, MAX(last_seen_at) l FROM atak_mod_reports
                WHERE last_seen_at >= NOW() - INTERVAL ? HOUR AND steam_uid IS NOT NULL GROUP BY steam_uid', [$hours])
            : $this->rows('incidents mod', 'SELECT steam_uid s, MAX(callsign) c, SUM(hit_count) n, MAX(last_seen_at) l FROM atak_mod_reports
                WHERE last_seen_at >= NOW() - INTERVAL ? HOUR AND steam_uid IN ' . $this->tenantSteamSubquery() . ' GROUP BY steam_uid',
                [$hours, $tenantId, $tenantId, $tenantId, $tenantId]);
        foreach ($inc as $r) {
            $add((string) $r['s'], ['callsign' => $r['c'], 'incidents' => $r['n'], 'last_at' => $r['l']]);
        }

        $out = array_values($players);
        foreach ($out as &$pl) {
            $pl['items'] = $pl['reports'] + $pl['sse_notes'] + $pl['photos'] + $pl['chat'];
            $tot = $pl['events_accepted'] + $pl['events_rejected'];
            $pl['reject_rate'] = $tot > 0 ? round($pl['events_rejected'] / $tot * 100, 1) : null;
        }
        unset($pl);
        if ($search !== '') {
            $s = mb_strtolower($search, 'UTF-8');
            $out = array_values(array_filter($out, static fn (array $r): bool => str_contains(mb_strtolower(
                ($r['callsign'] ?? '') . ' ' . ($r['user_label'] ?? '') . ' ' . ($r['steam_uid'] ?? ''),
                'UTF-8'
            ), $s)));
        }
        usort($out, static fn (array $a, array $b): int => [$b['bytes'], $b['items']] <=> [$a['bytes'], $a['items']]);

        return array_slice($out, 0, 500);
    }

    // =================================================================== pipeline joueur

    /**
     * Étapes de la chaîne de remontée par joueur : liaison → profil → mods → terminal →
     * file d'envoi COMSPEC Link → ingestion Athena → remontées → incidents.
     *
     * @return list<array<string, mixed>>
     */
    public function pipeline(?int $tenantId, int $hours, string $search = ''): array
    {
        $players = $this->players($tenantId, $hours, $search);
        $players = array_slice($players, 0, 200);
        $steams = array_values(array_filter(array_map(static fn ($p) => $p['steam_uid'], $players)));
        if ($steams === []) {
            return array_map(fn ($p) => $p + ['stages' => $this->emptyStages()], $players);
        }
        $in = implode(',', array_fill(0, count($steams), '?'));
        [$w, $p] = $this->t($tenantId);
        [$wa] = $this->t($tenantId, 'a');

        $index = static function (array $rows, string $key): array {
            $o = [];
            foreach ($rows as $r) {
                $o[(string) $r[$key]] = $r;
            }

            return $o;
        };
        $sessions = $index($this->rows('sessions de jeu', "SELECT steam_id, MIN(created_at) first_at, MAX(COALESCE(last_seen_at, created_at)) last_at,
                SUM(revoked_at IS NULL AND expires_at > NOW()) active FROM game_sessions WHERE {$w} AND steam_id IN ({$in}) GROUP BY steam_id",
            array_merge($p, $steams)), 'steam_id');
        $profiles = $index($this->rows('profils opérateur', "SELECT steam_id, MAX(last_sync_at) sync_at, MAX(sync_status) sync_status,
                MAX(user_id IS NOT NULL) linked FROM operator_game_profiles WHERE {$w} AND steam_id IN ({$in}) GROUP BY steam_id",
            array_merge($p, $steams)), 'steam_id');
        $mods = $index($this->rows('mods détectés', "SELECT steam_id, COUNT(*) n, MAX(last_seen) last_at FROM game_player_mods
            WHERE {$w} AND steam_id IN ({$in}) GROUP BY steam_id", array_merge($p, $steams)), 'steam_id');
        $queues = $index($this->rows("files d'envoi", "SELECT s.steam_uid, s.received_at, s.failed, s.retry, s.queued, s.http_5xx, s.http_429,
                s.timeouts, s.overload, s.p95_latency_ms FROM atak_send_queue_snapshots s
            JOIN (SELECT steam_uid, MAX(id) id FROM atak_send_queue_snapshots WHERE {$w} AND steam_uid IN ({$in}) GROUP BY steam_uid) m ON m.id = s.id",
            array_merge($p, $steams)), 'steam_uid');
        $ingest = $index($this->rows('ingestion par joueur', "SELECT steam_uid, MAX(last_at) last_at, SUM(events_rejected) rej, SUM(events_accepted) acc
            FROM atak_ingest_player_daily WHERE {$w} AND steam_uid IN ({$in}) AND day >= CURDATE() - INTERVAL ? DAY GROUP BY steam_uid",
            array_merge($p, $steams, [max(1, (int) ceil($hours / 24))])), 'steam_uid');
        $incidents = $index($this->rows('incidents mod', "SELECT steam_uid, MAX(last_seen_at) last_at, SUM(workflow_status = 'new') open,
                MAX(id) last_id FROM atak_mod_reports WHERE steam_uid IN ({$in}) GROUP BY steam_uid", $steams), 'steam_uid');
        // Terminal : par compte lié, sinon par indicatif.
        $terms = $this->rows('terminaux', "SELECT a.user_id, a.operator_callsign, a.last_seen_at, a.compromise_state, a.status, g.steam_id
            FROM atak_terminals a LEFT JOIN (SELECT DISTINCT user_id, steam_id FROM game_sessions WHERE steam_id IN ({$in})) g ON g.user_id = a.user_id
            WHERE {$wa} AND a.terminal_type <> 'web'", array_merge($steams, $p));
        $termBySteam = [];
        $termByCs = [];
        foreach ($terms as $t) {
            if ($t['steam_id'] && (!isset($termBySteam[$t['steam_id']]) || $t['last_seen_at'] > $termBySteam[$t['steam_id']]['last_seen_at'])) {
                $termBySteam[$t['steam_id']] = $t;
            }
            $c = mb_strtolower(trim((string) $t['operator_callsign']), 'UTF-8');
            if ($c !== '' && (!isset($termByCs[$c]) || $t['last_seen_at'] > $termByCs[$c]['last_seen_at'])) {
                $termByCs[$c] = $t;
            }
        }

        $recentCut = date('Y-m-d H:i:s', time() - $hours * 3600);
        foreach ($players as &$pl) {
            $s = (string) $pl['steam_uid'];
            $se = $sessions[$s] ?? null;
            $pr = $profiles[$s] ?? null;
            $mo = $mods[$s] ?? null;
            $qu = $queues[$s] ?? null;
            $ig = $ingest[$s] ?? null;
            $ic = $incidents[$s] ?? null;
            $te = $termBySteam[$s] ?? ($termByCs[mb_strtolower(trim((string) $pl['callsign']), 'UTF-8')] ?? null);

            $stages = [];
            $stages['link'] = $se === null ? $this->stage('missing', null, 'Aucune session de jeu')
                : $this->stage((int) $se['active'] > 0 ? 'ok' : 'warn', $se['last_at'], (int) $se['active'] > 0 ? 'Session active' : 'Session expirée ou révoquée', $se['first_at']);
            $stages['profile'] = $pr === null ? $this->stage('missing', null, 'Profil non synchronisé')
                : $this->stage(
                    in_array(strtolower((string) $pr['sync_status']), ['ok', 'synced', 'matched', ''], true) && (int) $pr['linked'] ? 'ok' : 'warn',
                    $pr['sync_at'],
                    ((int) $pr['linked'] ? 'Compte lié' : 'Steam non lié à un compte') . ($pr['sync_status'] ? ' · ' . $pr['sync_status'] : '')
                );
            $stages['mods'] = $mo === null ? $this->stage('missing', null, 'Liste de mods non reçue')
                : $this->stage('ok', $mo['last_at'], (int) $mo['n'] . ' mods chargés');
            $stages['terminal'] = $te === null ? $this->stage('missing', null, 'Aucun terminal ATAK')
                : $this->stage(
                    $te['compromise_state'] !== 'none' && $te['compromise_state'] !== '' ? 'ko'
                        : ($te['last_seen_at'] !== null && $te['last_seen_at'] >= date('Y-m-d H:i:s', time() - self::DEGRADED_MIN * 60) ? 'ok' : 'warn'),
                    $te['last_seen_at'],
                    $te['compromise_state'] !== 'none' && $te['compromise_state'] !== '' ? 'Terminal compromis' : 'Statut ' . $te['status']
                );
            if ($qu === null) {
                $stages['queue'] = $this->stage('missing', null, 'Aucun relevé de file');
            } else {
                $bad = (int) $qu['failed'] + (int) $qu['http_5xx'] + (int) $qu['timeouts'];
                $stages['queue'] = $this->stage(
                    $bad > 0 ? 'warn' : ($qu['overload'] !== 'ok' ? 'warn' : 'ok'),
                    $qu['received_at'],
                    $bad > 0 ? sprintf('%d échec(s) · %d 5xx · %d timeouts', $qu['failed'], $qu['http_5xx'], $qu['timeouts'])
                        : sprintf('p95 %d ms · file %d', $qu['p95_latency_ms'], $qu['queued'])
                );
            }
            if ($ig === null) {
                $stages['ingest'] = $this->stage('missing', null, 'Aucune ingestion mesurée');
            } else {
                $tot = (int) $ig['acc'] + (int) $ig['rej'];
                $rate = $tot > 0 ? (int) $ig['rej'] / $tot : 0;
                $stages['ingest'] = $this->stage($rate > 0.2 ? 'ko' : ($rate > 0 ? 'warn' : 'ok'), $ig['last_at'],
                    sprintf('%d acceptés · %d rejetés', $ig['acc'], $ig['rej']));
            }
            $stages['reports'] = $pl['items'] > 0 ? $this->stage('ok', $pl['last_at'], $pl['items'] . ' élément(s) remonté(s)')
                : $this->stage('missing', null, 'Rien remonté sur la période');
            $stages['incidents'] = $ic === null ? $this->stage('ok', null, 'Aucun incident')
                : $this->stage((int) $ic['open'] > 0 ? 'ko' : ($ic['last_at'] >= $recentCut ? 'warn' : 'ok'), $ic['last_at'],
                    (int) $ic['open'] > 0 ? $ic['open'] . ' ticket(s) ouvert(s) · dernier #' . $ic['last_id'] : 'Tickets traités · dernier #' . $ic['last_id']);
            $pl['stages'] = $stages;
            $pl['health'] = $this->overallHealth($stages);
        }
        unset($pl);

        return $players;
    }

    /** @return array<string, array<string, mixed>> */
    private function emptyStages(): array
    {
        $o = [];
        foreach (['link', 'profile', 'mods', 'terminal', 'queue', 'ingest', 'reports', 'incidents'] as $k) {
            $o[$k] = $this->stage('missing', null, 'Donnée non renseignée');
        }

        return $o;
    }

    /** @return array<string, mixed> */
    private function stage(string $status, ?string $at, string $detail, ?string $firstAt = null): array
    {
        return ['status' => $status, 'at' => $at, 'first_at' => $firstAt, 'detail' => $detail];
    }

    /** @param array<string, array<string, mixed>> $stages */
    private function overallHealth(array $stages): string
    {
        $s = array_column($stages, 'status');
        if (in_array('ko', $s, true)) {
            return 'ko';
        }

        return in_array('warn', $s, true) ? 'warn' : 'ok';
    }

    // =================================================================== erreurs

    /**
     * @return array<string, mixed>
     */
    public function errors(?int $tenantId, int $hours): array
    {
        $steamFilter = $tenantId === null ? '' : ' AND r.steam_uid IN ' . $this->tenantSteamSubquery();
        $sp = $tenantId === null ? [] : [$tenantId, $tenantId, $tenantId, $tenantId];

        $tickets = $this->rows('incidents mod', "SELECT r.id, r.severity, r.workflow_status, r.channel, r.message, r.source, r.steam_uid,
                r.callsign, r.player_name, r.mod_version, r.extension_version, r.hit_count, r.created_at, r.last_seen_at,
                d.thread_id discord_thread
            FROM atak_mod_reports r LEFT JOIN atak_mod_report_discord_tickets d ON d.opened_report_id = r.id
            WHERE r.last_seen_at >= NOW() - INTERVAL ? HOUR{$steamFilter} ORDER BY r.last_seen_at DESC LIMIT 200",
            array_merge([$hours], $sp));
        if ($tickets === [] && in_array('incidents mod', $this->missing, true)) {
            $this->missing = array_values(array_diff($this->missing, ['incidents mod']));
            $tickets = $this->rows('incidents mod', "SELECT r.id, r.severity, r.workflow_status, r.channel, r.message, r.source, r.steam_uid,
                    r.callsign, r.player_name, r.mod_version, r.extension_version, r.hit_count, r.created_at, r.last_seen_at
                FROM atak_mod_reports r WHERE r.last_seen_at >= NOW() - INTERVAL ? HOUR{$steamFilter} ORDER BY r.last_seen_at DESC LIMIT 200",
                array_merge([$hours], $sp));
        }
        $byChannel = $this->rows('incidents mod', "SELECT r.channel label, COUNT(*) tickets, SUM(r.hit_count) hits FROM atak_mod_reports r
            WHERE r.last_seen_at >= NOW() - INTERVAL ? HOUR{$steamFilter} GROUP BY r.channel ORDER BY hits DESC LIMIT 15", array_merge([$hours], $sp));
        $byVersion = $this->rows('incidents mod', "SELECT CONCAT(COALESCE(r.mod_version,'inconnue'), ' · Link ', COALESCE(r.extension_version,'inconnue')) label,
                COUNT(*) tickets, SUM(r.hit_count) hits FROM atak_mod_reports r
            WHERE r.last_seen_at >= NOW() - INTERVAL ? HOUR{$steamFilter} GROUP BY label ORDER BY hits DESC LIMIT 15", array_merge([$hours], $sp));
        $byStatus = $this->rows('incidents mod', "SELECT r.workflow_status label, COUNT(*) tickets FROM atak_mod_reports r
            WHERE r.last_seen_at >= NOW() - INTERVAL ? HOUR{$steamFilter} GROUP BY r.workflow_status", array_merge([$hours], $sp));

        [$w, $p] = $this->t($tenantId);
        $days = max(1, (int) ceil($hours / 24));
        $ingest = $this->rows('erreurs ingestion', "SELECT event_type, error_code, SUM(hits) hits, MAX(last_at) last_at, MAX(last_steam_uid) last_steam_uid
            FROM atak_ingest_error_daily WHERE {$w} AND day >= CURDATE() - INTERVAL ? DAY GROUP BY event_type, error_code ORDER BY hits DESC LIMIT 50",
            array_merge($p, [$days - 1]));
        $routes = $this->rows('télémétrie HTTP', "SELECT method, route_path, status_code, COUNT(*) n, ROUND(AVG(duration_ms)) avg_ms, MAX(created_at) last_at
            FROM request_telemetry WHERE {$w} AND route_path LIKE '/api/%' AND status_code >= 400 AND created_at >= NOW() - INTERVAL ? HOUR
            GROUP BY method, route_path, status_code ORDER BY n DESC LIMIT 40", array_merge($p, [$hours]));
        $slow = $this->rows('télémétrie HTTP', "SELECT method, route_path, COUNT(*) n, ROUND(AVG(duration_ms)) avg_ms, MAX(duration_ms) max_ms
            FROM request_telemetry WHERE {$w} AND route_path LIKE '/api/%' AND status_code < 400 AND duration_ms >= 1000
            AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY method, route_path ORDER BY n DESC LIMIT 20", array_merge($p, [$hours]));

        return [
            'tickets' => $tickets,
            'by_channel' => $byChannel,
            'by_version' => $byVersion,
            'by_status' => $byStatus,
            'ingest_errors' => $ingest,
            'http_errors' => $routes,
            'slow_routes' => $slow,
            'http' => $this->httpHealth($tenantId, $hours),
        ];
    }

    // =================================================================== communautés

    /**
     * Vue plateforme : activité par communauté.
     *
     * @return list<array<string, mixed>>
     */
    public function communities(int $hours): array
    {
        $tenants = $this->rows('communautés', 'SELECT id, name, slug, tenant_type, created_at FROM tenants ORDER BY name');
        $agg = function (string $source, string $sql, array $params = []) {
            $o = [];
            foreach ($this->rows($source, $sql, $params) as $r) {
                $o[(int) $r['tenant_id']] = $r;
            }

            return $o;
        };
        $traffic = $agg('trafic', 'SELECT tenant_id, SUM(bytes) bytes, SUM(requests) requests, MAX(bucket_ts) last_at FROM atak_ingest_traffic
            WHERE bucket_ts >= NOW() - INTERVAL ? HOUR GROUP BY tenant_id', [$hours]);
        $players = $agg('joueurs', 'SELECT tenant_id, COUNT(DISTINCT steam_id) n FROM operator_game_profiles
            WHERE last_seen_at >= NOW() - INTERVAL ? HOUR GROUP BY tenant_id', [$hours]);
        $terms = $agg('terminaux', 'SELECT tenant_id, COUNT(*) total, SUM(last_seen_at >= NOW() - INTERVAL ' . self::ONLINE_MIN . ' MINUTE) online
            FROM atak_terminals GROUP BY tenant_id');
        $reports = $agg('comptes rendus', 'SELECT tenant_id, COUNT(*) n FROM atak_tactical_reports WHERE deleted_at IS NULL
            AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY tenant_id', [$hours]);
        $notes = $agg('fiches SSE', 'SELECT tenant_id, COUNT(*) n FROM sse_field_notes WHERE created_at >= NOW() - INTERVAL ? HOUR GROUP BY tenant_id', [$hours]);
        $users = $agg('utilisateurs', 'SELECT tenant_id, COUNT(*) n FROM users GROUP BY tenant_id');
        $mods = $agg('mods détectés', 'SELECT tenant_id, COUNT(DISTINCT mod_dir) n FROM game_detected_mods GROUP BY tenant_id');
        $http = $agg('télémétrie HTTP', "SELECT tenant_id, SUM(status_code >= 500) e5, SUM(status_code BETWEEN 400 AND 499) e4,
                SUM(status_code < 400 AND duration_ms >= 1000) slow_ok, SUM(status_code < 400 AND duration_ms < 1000) fast_ok
            FROM request_telemetry WHERE route_path LIKE '/api/%' AND created_at >= NOW() - INTERVAL ? HOUR GROUP BY tenant_id", [$hours]);

        $out = [];
        foreach ($tenants as $t) {
            $id = (int) $t['id'];
            $h = $http[$id] ?? null;
            $tot = $h ? (int) $h['e5'] + (int) $h['e4'] + (int) $h['slow_ok'] + (int) round(((int) $h['fast_ok']) / $this->telemetrySampleRate) : 0;
            $out[] = [
                'id' => $id,
                'name' => $t['name'],
                'slug' => $t['slug'],
                'type' => $t['tenant_type'],
                'created_at' => $t['created_at'],
                'users' => (int) ($users[$id]['n'] ?? 0),
                'players_active' => (int) ($players[$id]['n'] ?? 0),
                'terminals' => (int) ($terms[$id]['total'] ?? 0),
                'terminals_online' => (int) ($terms[$id]['online'] ?? 0),
                'bytes' => (int) ($traffic[$id]['bytes'] ?? 0),
                'requests' => (int) ($traffic[$id]['requests'] ?? 0),
                'reports' => (int) ($reports[$id]['n'] ?? 0) + (int) ($notes[$id]['n'] ?? 0),
                'mods' => (int) ($mods[$id]['n'] ?? 0),
                'error_rate' => $tot > 0 ? round(((int) $h['e4'] + (int) $h['e5']) / $tot * 100, 2) : null,
                'availability' => $tot > 0 ? round((1 - (int) $h['e5'] / $tot) * 100, 2) : null,
                'last_activity' => $traffic[$id]['last_at'] ?? null,
            ];
        }
        usort($out, static fn ($a, $b) => [$b['bytes'], $b['players_active']] <=> [$a['bytes'], $a['players_active']]);

        return $out;
    }
}
