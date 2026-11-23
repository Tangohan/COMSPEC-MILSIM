<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Core\Database;
use App\Support\ShortCache;
use PDO;

/**
 * Fraîcheur des données remontées par le jeu (docs/dev/SYNCHRONISATION-JEU-WEB.md) : âge de la dernière donnée reçue
 * par flux, pour la communauté (bandeau « Données du jeu il y a Xs » des pages 9-LINE, TOC et fiche opérateur) et par
 * joueur vu récemment (diagnostic réservé aux responsables).
 *
 * Lecture seule, sans migration : chaque flux lit l'horodatage de sa propre table. Une table absente (étape pas encore
 * passée) donne « aucune donnée » pour ce flux, jamais une erreur. Les âges se calculent en PHP (strtotime/time), comme
 * le reste du dépôt ATAK : la base et PHP tournent en UTC (Database::sessionInitSql).
 */
final class GameSyncFreshnessService
{
    /** Au-delà, une donnée est « en retard » (bandeau orange) ; au-delà de STALE_SECONDS, « ancienne ». */
    public const LATE_SECONDS = 60;

    public const STALE_SECONDS = 900;

    /** Joueurs listés au diagnostic : vus par le serveur ces deux dernières heures. */
    public const PLAYER_WINDOW_SECONDS = 7200;

    public const PLAYER_LIMIT = 80;

    /** Résumé de la communauté gardé 10 s (le bandeau relit toutes les 15 s, plusieurs onglets ouverts). */
    private const CACHE_TTL = 10;

    /**
     * Flux lus pour la communauté : clé => [libellé, table, expression d'horodatage, filtre SQL en plus].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public const FLOWS = [
        'requests' => ['Requêtes du jeu', 'atak_ingest_player_daily', 'last_at', ''],
        'position' => ['Positions (BFT)', 'atak_units', 'updated_at', ''],
        'aircraft' => ['Aéronefs', 'atak_air_assets', 'updated_at', ''],
        'markers' => ['Marqueurs', 'atak_markers', 'COALESCE(updated_at, created_at)', ''],
        'shapes' => ['Tracés', 'atak_map_shapes', 'COALESCE(updated_at, created_at)', ''],
        'chat' => ['Messages du jeu', 'atak_chat_messages', 'created_at', "source = 'game'"],
        'nine_line' => ['9-LINE et briefs air', 'atak_nine_line', 'COALESCE(updated_at, created_at)', ''],
        'orders' => ['Ordres', 'atak_orders', 'updated_at', ''],
        'reports' => ['Comptes rendus', 'atak_tactical_reports', 'report_timestamp', ''],
    ];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /**
     * Âge de la dernière donnée par flux pour la communauté, et la plus récente toutes confondues.
     *
     * @return array{now: int, latest_at: ?string, latest_age_s: ?int, state: string, flows: list<array{key: string, label: string, at: ?string, age_s: ?int, state: string}>}
     */
    public function tenantSummary(int $tenantId, ?int $now = null): array
    {
        $now ??= time();
        $rows = ShortCache::remember('sync-fresh:t' . $tenantId, self::CACHE_TTL, fn (): array => $this->latestByFlow($tenantId));
        $flows = [];
        $latest = null;
        foreach (self::FLOWS as $key => $def) {
            $at = $rows[$key] ?? null;
            $age = self::age($at, $now);
            if ($age !== null && ($latest === null || $age < $latest[1])) {
                $latest = [$at, $age];
            }
            $flows[] = ['key' => $key, 'label' => $def[0], 'at' => $at, 'age_s' => $age, 'state' => self::state($age)];
        }

        return [
            'now' => $now,
            'latest_at' => $latest[0] ?? null,
            'latest_age_s' => $latest[1] ?? null,
            'state' => self::state($latest[1] ?? null),
            'flows' => $flows,
        ];
    }

    /**
     * Joueurs vus par le serveur ces deux dernières heures (Steam, sinon indicatif) avec l'âge de leur dernière donnée
     * par flux : requête du jeu, position, message, compte rendu. Quatre requêtes en tout, jamais une par joueur.
     *
     * @return list<array{steam_uid: string, callsign: string, flows: array<string, ?int>, oldest_flow: ?string}>
     */
    public function players(int $tenantId, ?int $now = null): array
    {
        $now ??= time();
        $since = date('Y-m-d H:i:s', $now - self::PLAYER_WINDOW_SECONDS);
        $players = [];
        try {
            $st = $this->db()->prepare(
                'SELECT steam_uid, MAX(callsign) AS cs, MAX(last_at) AS seen_at FROM atak_ingest_player_daily
                 WHERE tenant_id = ? AND day >= ? AND last_at >= ? GROUP BY steam_uid ORDER BY seen_at DESC LIMIT ' . self::PLAYER_LIMIT
            );
            $st->execute([$tenantId, date('Y-m-d', $now - 86400), $since]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $steam = (string) $r['steam_uid'];
                $players[$steam] = [
                    'steam_uid' => $steam,
                    'callsign' => trim((string) ($r['cs'] ?? '')),
                    'flows' => ['requests' => self::age((string) $r['seen_at'], $now), 'position' => null, 'chat' => null, 'reports' => null],
                ];
            }
        } catch (\Throwable) {
            return [];
        }
        if ($players === []) {
            return [];
        }
        $callsigns = array_values(array_unique(array_filter(array_map(static fn (array $p): string => $p['callsign'], $players), static fn (string $c): bool => $c !== '')));
        $steams = array_keys($players);
        $position = $callsigns !== [] ? $this->latestBy($tenantId, 'SELECT call_sign AS k, MAX(updated_at) AS at FROM atak_units WHERE tenant_id = ? AND call_sign IN (%s) GROUP BY call_sign', $callsigns) : [];
        $chat = $callsigns !== [] ? $this->latestBy($tenantId, "SELECT author AS k, MAX(created_at) AS at FROM atak_chat_messages WHERE tenant_id = ? AND source = 'game' AND author IN (%s) GROUP BY author", $callsigns) : [];
        $reports = $this->latestBy($tenantId, 'SELECT submitter_steam_id AS k, MAX(report_timestamp) AS at FROM atak_tactical_reports WHERE tenant_id = ? AND submitter_steam_id IN (%s) GROUP BY submitter_steam_id', $steams);
        $out = [];
        foreach ($players as $steam => $p) {
            $cs = mb_strtolower($p['callsign']);
            $p['flows']['position'] = self::age($position[$cs] ?? null, $now);
            $p['flows']['chat'] = self::age($chat[$cs] ?? null, $now);
            $p['flows']['reports'] = self::age($reports[mb_strtolower((string) $steam)] ?? null, $now);
            $oldest = null;
            foreach (['requests', 'position'] as $k) {
                if ($p['flows'][$k] !== null && ($oldest === null || $p['flows'][$k] > $p['flows'][$oldest])) {
                    $oldest = $k;
                }
            }
            $p['oldest_flow'] = $oldest;
            $out[] = $p;
        }

        return $out;
    }

    /** Âge en secondes d'un horodatage SQL (null : aucune donnée). */
    public static function age(?string $at, int $now): ?int
    {
        if ($at === null || $at === '') {
            return null;
        }
        $ts = strtotime($at);

        return $ts === false ? null : max(0, $now - $ts);
    }

    /** live (moins d'une minute), late (moins de 15 min), stale (plus), none (rien reçu). */
    public static function state(?int $age): string
    {
        return match (true) {
            $age === null => 'none',
            $age <= self::LATE_SECONDS => 'live',
            $age <= self::STALE_SECONDS => 'late',
            default => 'stale',
        };
    }

    /** @return array<string, ?string> */
    private function latestByFlow(int $tenantId): array
    {
        $out = [];
        foreach (self::FLOWS as $key => [, $table, $expr, $extra]) {
            try {
                $st = $this->db()->prepare('SELECT MAX(' . $expr . ') FROM ' . $table . ' WHERE tenant_id = ?' . ($extra !== '' ? ' AND ' . $extra : ''));
                $st->execute([$tenantId]);
                $v = $st->fetchColumn();
                $out[$key] = is_string($v) && $v !== '' ? $v : null;
            } catch (\Throwable) {
                $out[$key] = null;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $keys
     * @return array<string, string> clé en minuscules => horodatage le plus récent
     */
    private function latestBy(int $tenantId, string $sql, array $keys): array
    {
        $keys = array_slice($keys, 0, self::PLAYER_LIMIT);
        if ($keys === []) {
            return [];
        }
        $out = [];
        try {
            $st = $this->db()->prepare(sprintf($sql, implode(', ', array_fill(0, count($keys), '?'))));
            $st->execute(array_merge([$tenantId], $keys));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $k = mb_strtolower((string) $r['k']);
                $at = (string) ($r['at'] ?? '');
                if ($at !== '' && (!isset($out[$k]) || $at > $out[$k])) {
                    $out[$k] = $at;
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }
}
