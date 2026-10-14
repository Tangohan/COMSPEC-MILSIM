<?php

declare(strict_types=1);

namespace App\Services\Ecosystem;

use App\Core\Database;
use App\Repositories\CommunityEcosystemRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Indicateurs publics comparables entre communautés (hub inter-communautés).
 *
 * Seules des valeurs agrégées sortent d’ici : aucun nom de membre, aucun contenu SSE.
 * Les communautés retirées du registre public sont exclues. Le calcul complet est mis
 * en cache 10 minutes dans storage/cache.
 */
final class CommunityStatsService
{
    private const CACHE_TTL = 600;

    /** Tables SSE dont on additionne les fiches (dossiers, personnes, documents, saisies, produits, notes). */
    private const SSE_TABLES = ['sse_cases', 'sse_persons', 'sse_documents', 'sse_seizures', 'sse_intel_products', 'sse_field_notes'];

    /** Indicateurs proposés au tri et à la comparaison, avec leur libellé. */
    public const METRICS = [
        'members' => 'Membres actifs',
        'activity' => 'Actifs sur 30 jours',
        'units' => 'Unités',
        'depth' => 'Niveaux d’organigramme',
        'ops_per_month' => 'Opérations par mois',
        'sse_records' => 'Fiches de renseignement (SSE)',
        'cooperations' => 'Coopérations menées',
        'recommendations' => 'Recommandations',
        'rating' => 'Note des partenaires',
        'age_days' => 'Ancienneté sur Athena',
    ];

    private PDO $pdo;

    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private CommunityEcosystemRepository $ecosystem,
        ?PDO $pdo = null,
    ) {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * @return list<array<string, mixed>> une ligne par communauté listée au registre
     */
    public function all(bool $fresh = false): array
    {
        $cached = $fresh ? null : $this->readCache();
        if ($cached !== null) {
            return $cached;
        }
        $rows = $this->compute();
        $this->writeCache($rows);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function forTenant(int $tenantId): ?array
    {
        foreach ($this->all() as $row) {
            if ($row['id'] === $tenantId) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function forSlug(string $slug): ?array
    {
        foreach ($this->all() as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Médiane de chaque indicateur sur l’ensemble des communautés (repère « vous / les autres »).
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, float|int|null>
     */
    public static function medians(array $rows): array
    {
        $out = [];
        foreach (array_keys(self::METRICS) as $metric) {
            $values = [];
            foreach ($rows as $row) {
                if (isset($row[$metric]) && $row[$metric] !== null) {
                    $values[] = (float) $row[$metric];
                }
            }
            if ($values === []) {
                $out[$metric] = null;

                continue;
            }
            sort($values);
            $n = count($values);
            $mid = intdiv($n, 2);
            $median = $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
            $out[$metric] = round($median, 1);
        }

        return $out;
    }

    /**
     * Rang d’une valeur (1 = meilleur) parmi les communautés qui ont cet indicateur.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function rank(array $rows, string $metric, int $tenantId): ?int
    {
        $values = [];
        $mine = null;
        foreach ($rows as $row) {
            if (!isset($row[$metric]) || $row[$metric] === null) {
                continue;
            }
            $values[] = (float) $row[$metric];
            if ($row['id'] === $tenantId) {
                $mine = (float) $row[$metric];
            }
        }
        if ($mine === null) {
            return null;
        }
        $better = 0;
        foreach ($values as $v) {
            if ($v > $mine) {
                $better++;
            }
        }

        return $better + 1;
    }

    /**
     * Organigramme agrégé d’une communauté : nombre d’unités par niveau et par type.
     * Les noms ne sont rendus que si la communauté publie son organigramme (visibilité « public »).
     *
     * @return array{levels: list<int>, types: array<string, int>, tree: list<array{name: string, depth: int, type: string, members: int}>, public: bool}
     */
    public function structure(int $tenantId, bool $includeNames): array
    {
        $result = ['levels' => [], 'types' => [], 'tree' => [], 'public' => $includeNames];
        try {
            $st = $this->pdo->prepare('SELECT id, parent_id, name, type, display_order, show_on_public_page FROM units WHERE tenant_id = ? ORDER BY display_order, name');
            $st->execute([$tenantId]);
            $units = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $result;
        }
        $members = [];
        try {
            $st = $this->pdo->prepare(
                'SELECT uu.unit_id, COUNT(DISTINCT uu.user_id) n FROM user_units uu JOIN units u ON u.id = uu.unit_id
                 WHERE u.tenant_id = ? GROUP BY uu.unit_id'
            );
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $members[(int) $r['unit_id']] = (int) $r['n'];
            }
        } catch (\Throwable) {
        }

        $byParent = [];
        $ids = [];
        foreach ($units as $u) {
            $ids[(int) $u['id']] = true;
        }
        foreach ($units as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $byParent[isset($ids[$pid]) ? $pid : 0][] = $u;
        }
        $walk = function (int $parent, int $depth) use (&$walk, &$result, $byParent, $members, $includeNames): void {
            foreach ($byParent[$parent] ?? [] as $u) {
                $type = (string) ($u['type'] ?? 'group');
                $result['levels'][$depth] = ($result['levels'][$depth] ?? 0) + 1;
                $result['types'][$type] = ($result['types'][$type] ?? 0) + 1;
                if ($includeNames && (int) ($u['show_on_public_page'] ?? 1) === 1) {
                    $result['tree'][] = [
                        'name' => (string) $u['name'],
                        'depth' => $depth,
                        'type' => $type,
                        'members' => $members[(int) $u['id']] ?? 0,
                    ];
                }
                if ($depth < 12) {
                    $walk((int) $u['id'], $depth + 1);
                }
            }
        };
        $walk(0, 0);
        ksort($result['levels']);
        $result['levels'] = array_values($result['levels']);

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function compute(): array
    {
        $registry = $this->tenants->listForRegistry();
        if ($registry === []) {
            return [];
        }
        $units = $this->unitStats();
        $ops = $this->countByTenant(
            'community_events',
            "starts_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND starts_at <= NOW() AND cancelled_at IS NULL"
        );
        $upcoming = $this->countByTenant('community_events', 'starts_at > NOW() AND cancelled_at IS NULL');
        $sse = [];
        foreach (self::SSE_TABLES as $table) {
            foreach ($this->countByTenant($table) as $tid => $n) {
                $sse[$tid] = ($sse[$tid] ?? 0) + $n;
            }
        }
        $coops = $this->cooperationCounts();
        $recos = $this->ecosystem->recommendationCounts();
        $reviews = $this->ecosystem->reviewSummaries();
        $twins = $this->ecosystem->twinCounts();

        $rows = [];
        foreach ($registry as $t) {
            $id = (int) $t['id'];
            $settings = json_decode((string) ($t['settings'] ?? ''), true);
            $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
            $members = 0;
            $activity = null;
            try {
                $members = $this->users->countActiveMembers($id);
                $activity = $this->users->activityRateLast30DaysPercent($id);
            } catch (\Throwable) {
            }
            $created = strtotime((string) ($t['created_at'] ?? '')) ?: null;
            $rows[] = [
                'id' => $id,
                'name' => (string) $t['name'],
                'slug' => (string) $t['slug'],
                'tagline' => (string) ($t['registry_tagline'] ?? ''),
                'game' => (string) ($t['game_label'] ?? ''),
                'locale' => (string) ($t['registry_locale_label'] ?? ''),
                'unit_label' => (string) ($t['registry_unit_affiliation_label'] ?? ''),
                'logo' => (string) ($t['logo_url'] ?? ''),
                'recruitment_open' => !empty($t['registry_recruitment_open']) && empty($t['registry_locked']),
                'orbat_public' => ($community['orbat_visibility'] ?? 'members') === 'public',
                'members' => $members,
                'activity' => $activity,
                'units' => $units[$id]['count'] ?? 0,
                'depth' => $units[$id]['depth'] ?? 0,
                'ops_per_month' => round(($ops[$id] ?? 0) / 3, 1),
                'upcoming_ops' => $upcoming[$id] ?? 0,
                'sse_records' => $sse[$id] ?? 0,
                'cooperations' => $coops[$id] ?? 0,
                'recommendations' => $recos[$id] ?? 0,
                'rating' => $reviews[$id]['avg'] ?? null,
                'rating_count' => $reviews[$id]['count'] ?? 0,
                'twins' => $twins[$id] ?? 0,
                'created_at' => $created !== null ? date('Y-m-d', $created) : null,
                'age_days' => $created !== null ? max(0, (int) floor((time() - $created) / 86400)) : null,
            ];
        }

        return $rows;
    }

    /** @return array<int, array{count: int, depth: int}> */
    private function unitStats(): array
    {
        try {
            $rows = $this->pdo->query('SELECT id, tenant_id, parent_id FROM units')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $parent = [];
        $tenantOf = [];
        foreach ($rows as $r) {
            $parent[(int) $r['id']] = (int) ($r['parent_id'] ?? 0);
            $tenantOf[(int) $r['id']] = (int) $r['tenant_id'];
        }
        $out = [];
        foreach ($parent as $id => $pid) {
            $depth = 1;
            $cur = $pid;
            $guard = 0;
            while ($cur > 0 && isset($parent[$cur]) && $guard++ < 20) {
                $depth++;
                $cur = $parent[$cur];
            }
            $tid = $tenantOf[$id];
            $out[$tid]['count'] = ($out[$tid]['count'] ?? 0) + 1;
            $out[$tid]['depth'] = max($out[$tid]['depth'] ?? 0, $depth);
        }

        return $out;
    }

    /** @return array<int, int> */
    private function cooperationCounts(): array
    {
        if (!$this->ecosystem->tableExists('interteam_missions') || !$this->ecosystem->tableExists('interteam_mission_participants')) {
            return [];
        }
        try {
            $rows = $this->pdo->query(
                "SELECT x.tenant_id, COUNT(DISTINCT x.mission_id) n FROM (
                    SELECT p.tenant_id, p.mission_id FROM interteam_mission_participants p
                    JOIN interteam_missions m ON m.id = p.mission_id
                    WHERE p.status = 'active' AND m.status IN ('active', 'archived')
                    UNION ALL
                    SELECT created_by_tenant_id, id FROM interteam_missions WHERE status IN ('active', 'archived')
                 ) x GROUP BY x.tenant_id"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['tenant_id']] = (int) $r['n'];
        }

        return $out;
    }

    /** @return array<int, int> */
    private function countByTenant(string $table, string $where = '1 = 1'): array
    {
        if (!$this->ecosystem->tableExists($table)) {
            return [];
        }
        try {
            $rows = $this->pdo->query("SELECT tenant_id, COUNT(*) n FROM `{$table}` WHERE {$where} GROUP BY tenant_id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['tenant_id']] = (int) $r['n'];
        }

        return $out;
    }

    private function cachePath(): string
    {
        return base_path('storage/cache/community_ecosystem_stats.json');
    }

    /** @return list<array<string, mixed>>|null */
    private function readCache(): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path) || filemtime($path) < time() - self::CACHE_TTL) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeCache(array $rows): void
    {
        $dir = dirname($this->cachePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->cachePath(), json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public function forgetCache(): void
    {
        @unlink($this->cachePath());
    }
}
