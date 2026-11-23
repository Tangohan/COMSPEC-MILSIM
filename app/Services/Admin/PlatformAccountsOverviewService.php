<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Services\Audit\AuditAction;
use App\Support\PortalAccessChoice;
use App\Support\ShortCache;
use DateTimeImmutable;
use PDO;

/**
 * Aperçus de la page d’accueil de l’administration du site : dernières communautés créées, derniers comptes
 * créés et comptes inactifs (par tranche de jours sans connexion).
 *
 * Les comptes anonymisés, fusionnés et techniques sont exclus ; une personne = une adresse e-mail (comme l’annuaire
 * admin/users). Lectures bornées (LIMIT sur la clé primaire) et résultat gardé quelques minutes (ShortCache).
 */
final class PlatformAccountsOverviewService
{
    public const CACHE_TTL = 300;
    /** Tranches affichées : nombre de jours sans connexion. */
    public const INACTIVITY_DAYS = [30, 90, 180];

    private ?PDO $pdo;
    /** @var array<string, bool> */
    private array $probes = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{
     *   communities: list<array<string, mixed>>,
     *   accounts: list<array<string, mixed>>,
     *   inactivity: array{available: bool, total: int, buckets: array<string, int>, oldest: list<array<string, mixed>>},
     *   error: ?string
     * }
     */
    public function overview(int $limit = 6): array
    {
        return ShortCache::remember('admin:accounts-overview:v1:' . $limit, self::CACHE_TTL, fn (): array => $this->compute($limit));
    }

    /** @return list<array{signup_day: ?string, age_band: string, login_band: string, total: int}> */
    public function engagement(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $from = $start->format('Y-m-d');
        $until = $end->format('Y-m-d');

        return ShortCache::remember("admin:account-engagement:v1:{$from}:{$until}", self::CACHE_TTL, function () use ($from, $until): array {
            [$where, $params] = $this->accountPredicate();
            $accounts = $this->db()->prepare(
                "SELECT CASE WHEN first_at >= ? AND first_at < ? THEN DATE(first_at) END AS signup_day,
                        CASE WHEN first_at IS NULL THEN 'unknown'
                             WHEN first_at > UTC_TIMESTAMP() - INTERVAL 30 DAY THEN 'new'
                             WHEN first_at > UTC_TIMESTAMP() - INTERVAL 180 DAY THEN 'established'
                             ELSE 'old' END AS age_band,
                        CASE WHEN last_at IS NULL THEN 'never'
                             WHEN last_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY THEN 'recent'
                             WHEN last_at >= UTC_TIMESTAMP() - INTERVAL 90 DAY THEN '30'
                             WHEN last_at >= UTC_TIMESTAMP() - INTERVAL 180 DAY THEN '90'
                             ELSE '180' END AS login_band,
                        COUNT(*) AS total
                 FROM (
                     SELECT MIN(u.created_at) AS first_at, MAX(u.last_login_at) AS last_at
                     FROM users u WHERE {$where} AND TRIM(u.email) <> ''
                     GROUP BY LOWER(TRIM(u.email))
                 ) AS people
                 GROUP BY signup_day, age_band, login_band"
            );
            $accounts->execute([$from, $until, ...$params]);

            return $accounts->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    /**
     * Répartition des comptes par région déclarée. Agrégée, jamais nominative : les régions de moins de
     * AccountRegions::PRIVACY_THRESHOLD comptes sont masquées (voir AccountRegions::applyThreshold). Une personne
     * (même e-mail sur plusieurs communautés) compte une fois ; les fiches sans compte ne comptent pas.
     *
     * @return array{available: bool, total: int, rows: list<array{code: string, label: string, count: int}>, others: int, none: int}
     */
    public function regionBreakdown(): array
    {
        return ShortCache::remember('admin:account-regions:v1', self::CACHE_TTL, function (): array {
            $empty = ['available' => false, 'total' => 0, 'rows' => [], 'others' => 0, 'none' => 0];
            if (!$this->hasTable('user_regions')) {
                return $empty;
            }
            [$where, $params] = $this->accountPredicate();
            $st = $this->db()->prepare(
                "SELECT region_code, COUNT(*) AS total
                 FROM (
                     SELECT LOWER(TRIM(u.email)) AS person, MAX(ur.region_code) AS region_code
                     FROM users u LEFT JOIN user_regions ur ON ur.user_id = u.id
                     WHERE {$where} AND TRIM(u.email) <> '' AND LOWER(u.email) NOT LIKE '%@sans-compte.invalid'
                     GROUP BY LOWER(TRIM(u.email))
                 ) AS people
                 GROUP BY region_code"
            );
            $st->execute($params);
            $counts = [];
            $total = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $total += (int) $row['total'];
                if ($row['region_code'] !== null && $row['region_code'] !== '') {
                    $counts[(string) $row['region_code']] = (int) $row['total'];
                }
            }

            return ['available' => true, 'total' => $total] + \App\Support\AccountRegions::applyThreshold($counts, $total);
        });
    }

    /** @return array{signed_in: int, returned: int, one_day: int, two_to_three: int, four_to_six: int, seven_plus: int, average_days: float} */
    public function observedReturns(string $from, string $until): array
    {
        return ShortCache::remember("admin:account-returns:v2:{$from}:{$until}", self::CACHE_TTL, function () use ($from, $until): array {
            [$where, $params] = $this->accountPredicate();
            $stmt = $this->db()->prepare(
                "SELECT COUNT(*) AS signed_in, SUM(login_days >= 2) AS returned,
                        SUM(login_days = 1) AS one_day, SUM(login_days BETWEEN 2 AND 3) AS two_to_three,
                        SUM(login_days BETWEEN 4 AND 6) AS four_to_six, SUM(login_days >= 7) AS seven_plus,
                        AVG(login_days) AS average_days
                 FROM (
                     SELECT COUNT(DISTINCT DATE(a.created_at)) AS login_days
                     FROM users u INNER JOIN audit_logs a ON a.user_id = u.id
                     WHERE {$where} AND TRIM(u.email) <> ''
                       AND a.action = ? AND a.created_at >= ? AND a.created_at < ?
                     GROUP BY LOWER(TRIM(u.email))
                 ) AS logins"
            );
            $stmt->execute([...$params, AuditAction::AUTH_LOGIN_SUCCESS, $from, $until]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'signed_in' => (int) $row['signed_in'],
                'returned' => (int) $row['returned'],
                'one_day' => (int) $row['one_day'],
                'two_to_three' => (int) $row['two_to_three'],
                'four_to_six' => (int) $row['four_to_six'],
                'seven_plus' => (int) $row['seven_plus'],
                'average_days' => (float) $row['average_days'],
            ];
        });
    }

    /** @return array{total: int, activated: int, returned: int} */
    public function signupFollowup(string $from, string $until): array
    {
        return ShortCache::remember("admin:signup-followup:v1:{$from}:{$until}", self::CACHE_TTL, function () use ($from, $until): array {
            [$where, $params] = $this->accountPredicate();
            $stmt = $this->db()->prepare(
                "WITH eligible AS (
                     SELECT u.id, LOWER(TRIM(u.email)) AS person, u.created_at
                     FROM users u WHERE {$where} AND TRIM(u.email) <> ''
                 ), cohort AS (
                     SELECT person, MIN(created_at) AS signup_at
                     FROM eligible GROUP BY person
                     HAVING signup_at >= ? AND signup_at < ?
                 )
                 SELECT COUNT(*) AS total, SUM(activated) AS activated, SUM(returned) AS returned
                 FROM (
                     SELECT c.person, MAX(a.user_id IS NOT NULL) AS activated,
                            MAX(CASE WHEN DATE(a.created_at) > DATE(c.signup_at) THEN 1 ELSE 0 END) AS returned
                     FROM cohort c JOIN eligible e ON e.person = c.person
                     LEFT JOIN audit_logs a ON a.user_id = e.id AND a.action = ?
                         AND a.created_at >= c.signup_at
                         AND a.created_at < DATE(c.signup_at) + INTERVAL 8 DAY
                     GROUP BY c.person
                 ) AS followup"
            );
            $stmt->execute([...$params, $from, $until, AuditAction::AUTH_LOGIN_SUCCESS]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return ['total' => (int) $row['total'], 'activated' => (int) $row['activated'], 'returned' => (int) $row['returned']];
        });
    }

    /** @return array<string, mixed> */
    private function compute(int $limit): array
    {
        $out = [
            'communities' => [],
            'accounts' => [],
            'inactivity' => ['available' => false, 'total' => 0, 'buckets' => [], 'oldest' => []],
            'error' => null,
        ];
        try {
            $out['communities'] = $this->latestCommunities($limit);
            $out['accounts'] = $this->latestAccounts($limit);
            $out['inactivity'] = $this->inactivity($limit);
        } catch (\Throwable) {
            $out['error'] = 'Les aperçus des comptes et communautés sont momentanément indisponibles.';
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function latestCommunities(int $limit = 6): array
    {
        $created = $this->hasColumn('tenants', 'created_at') ? 't.created_at' : 'NULL';
        $stmt = $this->db()->query(
            "SELECT t.id, t.name, t.slug, {$created} AS created_at FROM tenants t ORDER BY t.id DESC LIMIT " . ($limit * 2 + 2)
        );
        $rows = [];
        foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
            if (PortalAccessChoice::isPlaceholderTenant($row)) {
                continue;
            }
            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function latestAccounts(int $limit = 6): array
    {
        [$where, $params] = $this->accountPredicate();
        $last = $this->hasColumn('users', 'last_login_at') ? 'u.last_login_at' : 'NULL';
        $stmt = $this->db()->prepare(
            "SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.created_at, {$last} AS last_login_at
             FROM users u WHERE {$where} ORDER BY u.id DESC LIMIT " . ($limit * 3)
        );
        $stmt->execute($params);
        $rows = [];
        $seen = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = strtolower(trim((string) ($row['email'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $this->withCommunity($rows);
    }

    /**
     * Comptes par tranche d’inactivité (cumulées : « plus de 90 jours » inclut « plus de 180 jours ») et
     * les plus anciens inactifs (dernière connexion connue la plus ancienne).
     *
     * @return array{available: bool, total: int, buckets: array<string, int>, oldest: list<array<string, mixed>>}
     */
    public function inactivity(int $limit = 6, ?int $now = null): array
    {
        $empty = ['available' => false, 'total' => 0, 'buckets' => [], 'oldest' => []];
        if (!$this->hasColumn('users', 'last_login_at')) {
            return $empty;
        }
        $now ??= time();
        [$where, $params] = $this->accountPredicate();
        $cutoffs = [];
        $sums = [];
        foreach (self::INACTIVITY_DAYS as $days) {
            $cutoffs[$days] = date('Y-m-d H:i:s', $now - $days * 86400);
            $sums[] = "SUM(CASE WHEN g.last_at IS NOT NULL AND g.last_at < ? THEN 1 ELSE 0 END) AS d{$days}";
        }
        $grouped = "SELECT LOWER(TRIM(u.email)) AS email_key, MAX(u.last_login_at) AS last_at, MAX(u.id) AS user_id
                    FROM users u WHERE {$where} GROUP BY LOWER(TRIM(u.email))";

        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) AS total, ' . implode(', ', $sums) . ',
                    SUM(CASE WHEN g.last_at IS NULL THEN 1 ELSE 0 END) AS never
             FROM (' . $grouped . ') g'
        );
        $stmt->execute(array_merge(array_values($cutoffs), $params));
        $agg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $buckets = [];
        foreach (self::INACTIVITY_DAYS as $days) {
            $buckets[(string) $days] = (int) ($agg['d' . $days] ?? 0);
        }
        $buckets['jamais'] = (int) ($agg['never'] ?? 0);

        $first = $cutoffs[self::INACTIVITY_DAYS[0]];
        $oldestStmt = $this->db()->prepare(
            'SELECT g.user_id, g.last_at FROM (' . $grouped . ') g
             WHERE g.last_at IS NOT NULL AND g.last_at < ?
             ORDER BY g.last_at ASC, g.user_id ASC LIMIT ' . $limit
        );
        $oldestStmt->execute(array_merge($params, [$first]));
        $oldest = [];
        $lastById = [];
        foreach ($oldestStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $lastById[(int) $r['user_id']] = (string) $r['last_at'];
        }
        if ($lastById !== []) {
            $ids = array_keys($lastById);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $detail = $this->db()->prepare(
                "SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.created_at
                 FROM users u WHERE u.id IN ({$in})"
            );
            $detail->execute($ids);
            $byId = [];
            foreach ($detail->fetchAll(PDO::FETCH_ASSOC) ?: [] as $d) {
                $byId[(int) $d['id']] = $d;
            }
            foreach ($lastById as $id => $lastAt) {
                if (isset($byId[$id])) {
                    $oldest[] = $byId[$id] + ['last_login_at' => $lastAt];
                }
            }
        }

        return [
            'available' => true,
            'total' => (int) ($agg['total'] ?? 0),
            'buckets' => $buckets,
            'oldest' => $this->withCommunity($oldest),
        ];
    }

    /** Lien « voir tout » de l’annuaire des comptes pour une tranche (30, 90, 180 ou jamais). */
    public static function directoryUrl(string $bucket): string
    {
        return url('admin/users') . '?inactif=' . rawurlencode($bucket);
    }

    /**
     * Personnes réelles uniquement : ni anonymisées, ni fusionnées, ni comptes techniques.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function accountPredicate(): array
    {
        $parts = [
            "LOWER(u.email) NOT LIKE '%@deleted.invalid'",
            "LOWER(u.email) NOT LIKE '%@merged.invalid'",
            "LOWER(u.email) NOT LIKE '%@internal.local'",
            "(u.status IS NULL OR u.status <> 'merged')",
        ];
        if ($this->hasColumn('users', 'deleted_at')) {
            $parts[] = 'u.deleted_at IS NULL';
        }
        if ($this->hasColumn('users', 'is_service_account')) {
            $parts[] = '(u.is_service_account IS NULL OR u.is_service_account = 0)';
        }

        return [implode(' AND ', $parts), []];
    }

    /**
     * Ajoute « community_name » : première communauté active réelle de la personne, sinon sa communauté d’origine.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withCommunity(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $userIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
        $tenantIds = array_values(array_unique(array_map(static fn (array $r): int => (int) ($r['tenant_id'] ?? 0), $rows)));
        $byUser = [];
        if ($this->hasTable('user_community_memberships')) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $this->db()->prepare(
                "SELECT m.user_id, t.name, t.slug FROM user_community_memberships m
                 INNER JOIN tenants t ON t.id = m.tenant_id
                 WHERE m.user_id IN ({$in}) AND m.status = 'active' ORDER BY m.user_id, t.id"
            );
            $stmt->execute($userIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $m) {
                $uid = (int) $m['user_id'];
                if (!isset($byUser[$uid]) && !PortalAccessChoice::isPlaceholderTenant($m)) {
                    $byUser[$uid] = $m;
                }
            }
        }
        $tenants = [];
        $tenantIds = array_values(array_filter($tenantIds, static fn (int $id): bool => $id > 0));
        if ($tenantIds !== []) {
            $in = implode(',', array_fill(0, count($tenantIds), '?'));
            $stmt = $this->db()->prepare("SELECT id, name, slug FROM tenants WHERE id IN ({$in})");
            $stmt->execute($tenantIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $t) {
                $tenants[(int) $t['id']] = $t;
            }
        }
        foreach ($rows as &$row) {
            $t = $byUser[(int) $row['id']] ?? ($tenants[(int) ($row['tenant_id'] ?? 0)] ?? null);
            $row['community_name'] = $t !== null && !PortalAccessChoice::isPlaceholderTenant($t)
                ? (function_exists('community_display_name') ? community_display_name($t) : (string) $t['name'])
                : '';
        }
        unset($row);

        return $rows;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->probe("SELECT {$column} FROM {$table} WHERE 1 = 0");
    }

    private function hasTable(string $table): bool
    {
        return $this->probe("SELECT 1 FROM {$table} WHERE 1 = 0");
    }

    private function probe(string $sql): bool
    {
        if (!array_key_exists($sql, $this->probes)) {
            try {
                $this->probes[$sql] = $this->db()->query($sql) !== false;
            } catch (\Throwable) {
                $this->probes[$sql] = false;
            }
        }

        return $this->probes[$sql];
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }
}
