<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Support\UnitCodeBuilder;
use PDO;
use Throwable;

/**
 * Recherche de la fenêtre « Rechercher » des effectifs (Ctrl+K).
 *
 * Bornée (8 résultats par groupe au plus, 2 à 80 caractères), limitée à la communauté
 * passée en paramètre ; le contrôleur vérifie l'accès aux effectifs avant d'appeler.
 * Index utilisés : units (tenant_id), unit_codes (tenant_id, code_path) et (tenant_id, admin_number),
 * personnel_job_roles (tenant_id, …), roles (tenant_id) ; les noms restent des « contient »,
 * bornés au périmètre d'une communauté.
 */
final class EffectifsSearchService
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 80;

    public const LIMIT = 8;

    public function __construct(
        private UserRepository $users,
        private UnitCodeService $codes,
        private ?PDO $pdo = null,
    ) {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array{query: string, members: list<array<string, mixed>>, units: list<array<string, mixed>>, jobs: list<array<string, mixed>>}
     */
    public function search(int $tenantId, string $query, int $limit = self::LIMIT): array
    {
        $q = trim((string) preg_replace('/\s+/u', ' ', $query));
        if (mb_strlen($q, 'UTF-8') > self::MAX_LENGTH) {
            $q = mb_substr($q, 0, self::MAX_LENGTH, 'UTF-8');
        }
        $out = ['query' => $q, 'members' => [], 'units' => [], 'jobs' => []];
        $isNumber = UnitCodeBuilder::parseAdminQuery($q) !== null;
        if ($tenantId < 1 || (mb_strlen($q, 'UTF-8') < self::MIN_LENGTH && !$isNumber)) {
            return $out;
        }
        $limit = max(1, min(self::LIMIT, $limit));
        $codes = $this->codes->sync($tenantId);
        $out['units'] = $this->units($tenantId, $q, $codes, $limit);
        $out['members'] = $this->members($tenantId, $q, $codes, $limit);
        $out['jobs'] = $this->jobs($tenantId, $q, $limit);

        return $out;
    }

    /**
     * @param array{community: array<string, mixed>|null, units: array<int, array<string, mixed>>} $codes
     * @return list<array<string, mixed>>
     */
    private function units(int $tenantId, string $q, array $codes, int $limit): array
    {
        $ids = [];
        $community = $codes['community'] ?? null;
        $admin = UnitCodeBuilder::parseAdminQuery($q);
        $number = null;
        if ($admin !== null && $community !== null) {
            [$tenantPart, $unitPart] = $admin;
            if ($tenantPart === null || $tenantPart === (int) $community['admin_number']) {
                $number = $unitPart;
            }
        }
        $prefix = strtoupper((string) preg_replace('/\s+/', '-', trim($q)));
        $prefixes = [$prefix];
        if ($community !== null && !str_starts_with($prefix, (string) $community['code'])) {
            // « B-A » trouve « SOAR-B-A » : le code de la communauté est sous-entendu.
            $prefixes[] = $community['code'] . '-' . $prefix;
        }
        try {
            $repo = $this->codes->repository();
            if ($repo->tablesExist()) {
                foreach ($prefixes as $i => $p) {
                    $visible = (string) ($community['code'] ?? '');
                    if ($visible !== '' && ($p === $visible || str_starts_with($p, $visible . '-'))) {
                        $p = ($community['administrative_code'] ?? $visible) . substr($p, strlen($visible));
                    }
                    foreach ($repo->searchUnitIds($tenantId, $p, $i === 0 ? $number : null, $limit) as $id) {
                        $ids[$id] = true;
                    }
                }
            }
        } catch (Throwable) {
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT id FROM units WHERE tenant_id = ? AND (name LIKE ? ESCAPE '!' OR code LIKE ? ESCAPE '!')
                 ORDER BY display_order ASC, name ASC LIMIT " . ($limit * 2)
            );
            $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $st->execute([$tenantId, $like, $like]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                $ids[(int) $id] = true;
            }
        } catch (Throwable) {
        }
        if ($ids === []) {
            return [];
        }
        $idList = array_slice(array_keys($ids), 0, $limit * 2);
        $ph = implode(',', array_fill(0, count($idList), '?'));
        try {
            $st = $this->pdo()->prepare('SELECT id, name, parent_id FROM units WHERE tenant_id = ? AND id IN (' . $ph . ')');
            $st->execute(array_merge([$tenantId], $idList));
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $counts = $this->memberCountsByUnit($tenantId, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $order = array_flip($idList);
        usort($rows, static fn (array $a, array $b): int => ($order[(int) $a['id']] ?? 0) <=> ($order[(int) $b['id']] ?? 0));
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $r) {
            $id = (int) $r['id'];
            $c = $codes['units'][$id] ?? null;
            $out[] = [
                'id' => $id,
                'name' => (string) ($r['name'] ?? ''),
                'code' => $c['code'] ?? '',
                'admin_id' => $c['admin_id'] ?? '',
                'members' => (int) ($counts[$id] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $unitIds
     * @return array<int, int>
     */
    private function memberCountsByUnit(int $tenantId, array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($unitIds), '?'));
        try {
            $st = $this->pdo()->prepare(
                'SELECT uu.unit_id, COUNT(DISTINCT uu.user_id) AS n FROM user_units uu
                 INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ?
                 WHERE uu.unit_id IN (' . $ph . ') AND uu.ended_at IS NULL GROUP BY uu.unit_id'
            );
            $st->execute(array_merge([$tenantId], $unitIds));
            $out = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[(int) $row['unit_id']] = (int) $row['n'];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array{community: array<string, mixed>|null, units: array<int, array<string, mixed>>} $codes
     * @return list<array<string, mixed>>
     */
    private function members(int $tenantId, string $q, array $codes, int $limit): array
    {
        try {
            $found = $this->users->searchEffectifsMembers($tenantId, $q, $limit);
        } catch (Throwable $e) {
            error_log('[effectifs] recherche membres : ' . $e->getMessage());

            return [];
        }
        if ($found === []) {
            return [];
        }
        $rich = [];
        try {
            foreach ($this->users->listEffectifsRosterByIds($tenantId, array_map(static fn (array $m): int => (int) $m['id'], $found)) as $row) {
                $rich[(int) ($row['id'] ?? 0)] = $row;
            }
        } catch (Throwable) {
        }
        $out = [];
        foreach ($found as $m) {
            $id = (int) $m['id'];
            $r = $rich[$id] ?? [];
            $unitId = (int) ($r['unit_id'] ?? 0);
            $name = $m['display_name'] !== '' ? $m['display_name'] : ($m['character_name'] !== '' ? $m['character_name'] : 'Membre');
            $out[] = [
                'id' => $id,
                'name' => $name,
                'callsign' => $m['callsign'],
                'character_name' => $m['character_name'] !== $name ? $m['character_name'] : '',
                'grade' => trim((string) ($r['grade_short'] ?? '')),
                'unit' => trim((string) ($r['unit_name'] ?? '')),
                'unit_code' => (string) ($codes['units'][$unitId]['code'] ?? ''),
                'job' => trim((string) ($r['job_role_display'] ?? '')),
                'role' => trim((string) ($r['assignment_role_name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Emplois et rôles tenus dans une unité (ex. « Chef de groupe »).
     *
     * @return list<array<string, mixed>>
     */
    private function jobs(int $tenantId, string $q, int $limit): array
    {
        $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $out = [];
        try {
            $st = $this->pdo()->prepare(
                "SELECT r.id, r.name, (SELECT COUNT(DISTINCT pj.user_id) FROM personnel_profile_job_roles pj
                         WHERE pj.personnel_job_role_id = r.id AND pj.tenant_id = r.tenant_id) AS holders
                 FROM personnel_job_roles r
                 WHERE r.tenant_id = ? AND r.name LIKE ? ESCAPE '!'
                 ORDER BY r.name ASC LIMIT " . $limit
            );
            $st->execute([$tenantId, $like]);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[] = ['kind' => 'job', 'id' => (int) $row['id'], 'name' => (string) $row['name'], 'holders' => (int) $row['holders']];
            }
        } catch (Throwable) {
        }
        if (count($out) < $limit) {
            try {
                $st = $this->pdo()->prepare(
                    "SELECT pa.role_name AS name, COUNT(DISTINCT pa.user_id) AS holders
                     FROM personnel_assignments pa
                     INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ?
                     WHERE pa.status = 'active' AND pa.role_name LIKE ? ESCAPE '!'
                     GROUP BY pa.role_name ORDER BY pa.role_name ASC LIMIT " . ($limit - count($out))
                );
                $st->execute([$tenantId, $like]);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $name = trim((string) $row['name']);
                    if ($name !== '') {
                        $out[] = ['kind' => 'unit_role', 'id' => 0, 'name' => $name, 'holders' => (int) $row['holders']];
                    }
                }
            } catch (Throwable) {
            }
        }

        return $out;
    }
}
