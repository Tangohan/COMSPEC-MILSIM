<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Charge de l'application : compteurs par minute et par route, par heure, communauté et route.
 * Écrits par lots (WatchFlusher), lus par la page d'administration « Charge et surcharge ».
 *
 * Mesure côté application seulement : requêtes qui ont atteint PHP. Ni l'état du réseau, ni la saturation avant PHP
 * (nginx, pare-feu, hébergeur) n'y figurent.
 */
class PlatformLoadRepository
{
    use LazyDatabaseConnection;

    /** Bornes hautes (ms) des classes de durée, de la plus rapide à la plus lente ; la dernière classe est ouverte. */
    public const BUCKETS = [100, 300, 1000, 3000];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** Classe de durée d'une requête : 0 à 4. */
    public static function bucketOf(int $ms): int
    {
        foreach (self::BUCKETS as $i => $edge) {
            if ($ms <= $edge) {
                return $i;
            }
        }

        return count(self::BUCKETS);
    }

    /**
     * p95 approché par classes : borne haute de la classe qui contient le 95e centile (null si aucune requête,
     * 'plus de 3000' représenté par la dernière borne + 1).
     *
     * @param list<int> $counts effectifs des 5 classes
     */
    public static function approxP95(array $counts): ?int
    {
        $total = array_sum($counts);
        if ($total < 1) {
            return null;
        }
        $target = (int) ceil($total * 0.95);
        $acc = 0;
        foreach ($counts as $i => $n) {
            $acc += (int) $n;
            if ($acc >= $target) {
                return self::BUCKETS[$i] ?? (self::BUCKETS[count(self::BUCKETS) - 1] + 1);
            }
        }

        return null;
    }

    /**
     * Écrit des compteurs de minute (ajout aux valeurs existantes).
     *
     * @param array<string, array<string, int>> $rows clé « minute|route » => requests, errors, shed, dur_sum, dur_max, b[0..4]
     */
    public function addMinutes(array $rows): void
    {
        $this->bulk(
            'platform_load_minute',
            ['minute_ts', 'route', 'requests', 'errors_5xx', 'shed', 'dur_sum_ms', 'dur_max_ms', 'b100', 'b300', 'b1000', 'b3000', 'bmore'],
            'requests = requests + VALUES(requests), errors_5xx = errors_5xx + VALUES(errors_5xx), shed = shed + VALUES(shed),'
            . ' dur_sum_ms = dur_sum_ms + VALUES(dur_sum_ms), dur_max_ms = GREATEST(dur_max_ms, VALUES(dur_max_ms)),'
            . ' b100 = b100 + VALUES(b100), b300 = b300 + VALUES(b300), b1000 = b1000 + VALUES(b1000), b3000 = b3000 + VALUES(b3000), bmore = bmore + VALUES(bmore)',
            array_map(static function (string $key, array $r): array {
                [$minute, $route] = explode('|', $key, 2);

                return [gmdate('Y-m-d H:i:s', (int) $minute), $route, $r['requests'], $r['errors'], $r['shed'], $r['dur_sum'], $r['dur_max'],
                    $r['b'][0] ?? 0, $r['b'][1] ?? 0, $r['b'][2] ?? 0, $r['b'][3] ?? 0, $r['b'][4] ?? 0];
            }, array_keys($rows), $rows)
        );
    }

    /**
     * @param array<string, array<string, int>> $rows clé « heure|communauté|route » => requests, errors, shed, dur_sum
     */
    public function addTenantHours(array $rows): void
    {
        $this->bulk(
            'platform_load_tenant_hour',
            ['hour_ts', 'tenant_id', 'route', 'requests', 'errors_5xx', 'shed', 'dur_sum_ms'],
            'requests = requests + VALUES(requests), errors_5xx = errors_5xx + VALUES(errors_5xx), shed = shed + VALUES(shed), dur_sum_ms = dur_sum_ms + VALUES(dur_sum_ms)',
            array_map(static function (string $key, array $r): array {
                [$hour, $tenant, $route] = explode('|', $key, 3);

                return [gmdate('Y-m-d H:i:s', (int) $hour), (int) $tenant, $route, $r['requests'], $r['errors'], $r['shed'], $r['dur_sum']];
            }, array_keys($rows), $rows)
        );
    }

    /**
     * @param list<string> $columns
     * @param list<list<int|string>> $values
     */
    private function bulk(string $table, array $columns, string $onDuplicate, array $values): void
    {
        foreach (array_chunk($values, 150) as $chunk) {
            $row = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES '
                . implode(',', array_fill(0, count($chunk), $row)) . ' ON DUPLICATE KEY UPDATE ' . $onDuplicate;
            $this->pdo()->prepare($sql)->execute(array_merge(...$chunk));
        }
    }

    /**
     * Totaux par minute sur la période.
     *
     * @return list<array{minute: int, requests: int, errors: int, shed: int, avg_ms: int, p95_ms: ?int}> du plus ancien au plus récent
     */
    public function minuteSeries(int $minutes, int $now): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT minute_ts, SUM(requests) r, SUM(errors_5xx) e, SUM(shed) s, SUM(dur_sum_ms) d,
                        SUM(b100) b0, SUM(b300) b1, SUM(b1000) b2, SUM(b3000) b3, SUM(bmore) b4
                 FROM platform_load_minute WHERE minute_ts >= ? GROUP BY minute_ts ORDER BY minute_ts ASC'
            );
            $st->execute([gmdate('Y-m-d H:i:s', $now - $minutes * 60)]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $req = (int) $r['r'];
                $out[] = [
                    'minute' => (int) strtotime((string) $r['minute_ts'] . ' UTC'),
                    'requests' => $req,
                    'errors' => (int) $r['e'],
                    'shed' => (int) $r['s'],
                    'avg_ms' => $req > 0 ? (int) round(((int) $r['d']) / $req) : 0,
                    'p95_ms' => self::approxP95([(int) $r['b0'], (int) $r['b1'], (int) $r['b2'], (int) $r['b3'], (int) $r['b4']]),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Routes les plus chargées sur la période, avec la période précédente de même durée pour la tendance.
     *
     * @return list<array{route: string, requests: int, previous: int, errors: int, shed: int, avg_ms: int, p95_ms: ?int}>
     */
    public function topRoutes(int $minutes, int $now, int $limit = 15): array
    {
        $cut = gmdate('Y-m-d H:i:s', $now - $minutes * 60);
        $from = gmdate('Y-m-d H:i:s', $now - 2 * $minutes * 60);
        try {
            $st = $this->pdo()->prepare(
                'SELECT route,
                        SUM(CASE WHEN minute_ts >= :cut1 THEN requests ELSE 0 END) cur,
                        SUM(CASE WHEN minute_ts < :cut2 THEN requests ELSE 0 END) prev,
                        SUM(CASE WHEN minute_ts >= :cut3 THEN errors_5xx ELSE 0 END) err,
                        SUM(CASE WHEN minute_ts >= :cut4 THEN shed ELSE 0 END) shed,
                        SUM(CASE WHEN minute_ts >= :cut5 THEN dur_sum_ms ELSE 0 END) dsum,
                        SUM(CASE WHEN minute_ts >= :cut6 THEN b100 ELSE 0 END) b0,
                        SUM(CASE WHEN minute_ts >= :cut7 THEN b300 ELSE 0 END) b1,
                        SUM(CASE WHEN minute_ts >= :cut8 THEN b1000 ELSE 0 END) b2,
                        SUM(CASE WHEN minute_ts >= :cut9 THEN b3000 ELSE 0 END) b3,
                        SUM(CASE WHEN minute_ts >= :cut10 THEN bmore ELSE 0 END) b4
                 FROM platform_load_minute WHERE minute_ts >= :from
                 GROUP BY route HAVING cur > 0 ORDER BY cur DESC LIMIT ' . max(1, min(100, $limit))
            );
            $args = ['from' => $from];
            for ($i = 1; $i <= 10; $i++) {
                $args['cut' . $i] = $cut;
            }
            $st->execute($args);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $req = (int) $r['cur'];
                $out[] = [
                    'route' => (string) $r['route'],
                    'requests' => $req,
                    'previous' => (int) $r['prev'],
                    'errors' => (int) $r['err'],
                    'shed' => (int) $r['shed'],
                    'avg_ms' => $req > 0 ? (int) round(((int) $r['dsum']) / $req) : 0,
                    'p95_ms' => self::approxP95([(int) $r['b0'], (int) $r['b1'], (int) $r['b2'], (int) $r['b3'], (int) $r['b4']]),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Requêtes par heure (UTC) sur les dernières heures : tendance courte.
     *
     * @return list<array{hour: int, requests: int, errors: int, shed: int}>
     */
    public function hourSeries(int $hours, int $now): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT FLOOR(UNIX_TIMESTAMP(minute_ts) / 3600) * 3600 AS h, SUM(requests) r, SUM(errors_5xx) e, SUM(shed) s
                 FROM platform_load_minute WHERE minute_ts >= ? GROUP BY h ORDER BY h ASC'
            );
            $st->execute([gmdate('Y-m-d H:i:s', $now - $hours * 3600)]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[] = ['hour' => (int) $r['h'], 'requests' => (int) $r['r'], 'errors' => (int) $r['e'], 'shed' => (int) $r['s']];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Requêtes par communauté sur les dernières heures (formule effective calculée par l'appelant).
     *
     * @return list<array{tenant_id: int, name: string, requests: int, errors: int, shed: int, avg_ms: int, top_route: string, tenant: array<string, mixed>}>
     */
    public function byTenant(int $hours, int $now, int $limit = 20): array
    {
        $from = gmdate('Y-m-d H:i:s', $now - $hours * 3600);
        try {
            $st = $this->pdo()->prepare(
                'SELECT h.tenant_id, SUM(h.requests) r, SUM(h.errors_5xx) e, SUM(h.shed) s, SUM(h.dur_sum_ms) d
                 FROM platform_load_tenant_hour h WHERE h.hour_ts >= ? AND h.tenant_id > 0
                 GROUP BY h.tenant_id ORDER BY r DESC LIMIT ' . max(1, min(100, $limit))
            );
            $st->execute([$from]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows === []) {
                return [];
            }
            $ids = array_map(static fn (array $r): int => (int) $r['tenant_id'], $rows);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $t = $this->pdo()->prepare(
                'SELECT id, name, plan_slug, subscription_status, settings, stripe_subscription_id, paypal_subscription_id FROM tenants WHERE id IN (' . $in . ')'
            );
            $t->execute($ids);
            $tenants = [];
            foreach ($t->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $tenants[(int) $row['id']] = $row;
            }
            $top = $this->pdo()->prepare(
                'SELECT tenant_id, route, SUM(requests) n FROM platform_load_tenant_hour
                 WHERE hour_ts >= ? AND tenant_id IN (' . $in . ') GROUP BY tenant_id, route'
            );
            $top->execute(array_merge([$from], $ids));
            $best = [];
            foreach ($top->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) $row['tenant_id'];
                if (!isset($best[$id]) || (int) $row['n'] > $best[$id][1]) {
                    $best[$id] = [(string) $row['route'], (int) $row['n']];
                }
            }
            $out = [];
            foreach ($rows as $r) {
                $id = (int) $r['tenant_id'];
                $req = (int) $r['r'];
                $row = $tenants[$id] ?? [];
                $out[] = [
                    'tenant_id' => $id,
                    'name' => (string) ($row['name'] ?? ('#' . $id)),
                    'requests' => $req,
                    'errors' => (int) $r['e'],
                    'shed' => (int) $r['s'],
                    'avg_ms' => $req > 0 ? (int) round(((int) $r['d']) / $req) : 0,
                    'top_route' => $best[$id][0] ?? '',
                    'tenant' => $row,
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Purge bornée : minutes plus vieilles que $minuteDays, heures plus vieilles que $hourDays. Retourne [minutes, heures].
     *
     * @return array{0: int, 1: int}
     */
    public function purge(int $minuteDays, int $hourDays, int $now, int $batch = 2000, int $maxBatches = 20): array
    {
        return [
            $this->purgeTable('platform_load_minute', 'minute_ts', gmdate('Y-m-d H:i:s', $now - $minuteDays * 86400), $batch, $maxBatches),
            $this->purgeTable('platform_load_tenant_hour', 'hour_ts', gmdate('Y-m-d H:i:s', $now - $hourDays * 86400), $batch, $maxBatches),
        ];
    }

    private function purgeTable(string $table, string $column, string $before, int $batch, int $maxBatches): int
    {
        $total = 0;
        try {
            $st = $this->pdo()->prepare('DELETE FROM ' . $table . ' WHERE ' . $column . ' < ? LIMIT ' . max(1, $batch));
            for ($i = 0; $i < $maxBatches; $i++) {
                $st->execute([$before]);
                $n = $st->rowCount();
                $total += $n;
                if ($n < $batch) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return $total;
    }
}
