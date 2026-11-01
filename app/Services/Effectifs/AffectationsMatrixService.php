<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use Throwable;

/**
 * Page « Affectations » : qui tient quoi, où et avec quel rôle.
 *
 * Une ligne par membre actif : unité (code + identifiant), rôle dans l'unité, emplois, accès.
 * Une fiche par unité : code, chemin, sous-unités et titulaires.
 */
final class AffectationsMatrixService
{
    public const MAX_MEMBERS = 1500;

    public function __construct(
        private UserRepository $users,
        private UnitRepository $units,
        private RoleRepository $roles,
        private PersonnelJobRoleRepository $jobs,
        private UnitCodeService $codes,
    ) {
    }

    /**
     * @return array{
     *   community: array<string, mixed>|null,
     *   members: list<array<string, mixed>>,
     *   units: list<array<string, mixed>>,
     *   filters: array{jobs: list<string>, roles: list<string>, access: list<string>},
     *   stats: array{members: int, assigned: int, without_unit: int, without_job: int, empty_units: int, truncated: bool}
     * }
     */
    public function build(int $tenantId): array
    {
        $codes = $this->codes->sync($tenantId);
        $unitRows = $this->units->allForTenant($tenantId);
        $meta = $this->units->hierarchyMetaByUnitId($tenantId);
        $commanders = [];
        try {
            $commanders = $this->units->commanderLabelByUnitForTenant($tenantId, $unitRows);
        } catch (Throwable) {
        }

        $ids = $this->users->listActiveUserIdsForTenant($tenantId);
        $truncated = count($ids) > self::MAX_MEMBERS;
        $ids = array_slice($ids, 0, self::MAX_MEMBERS);
        $rich = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            foreach ($this->users->listEffectifsRosterByIds($tenantId, $chunk) as $row) {
                $rich[(int) ($row['id'] ?? 0)] = $row;
            }
        }
        $jobsByUser = [];
        try {
            if ($ids !== [] && $this->jobs->pivotTableExists()) {
                foreach (array_chunk($ids, 400) as $chunk) {
                    foreach ($this->jobs->listPivotAssignmentsForUsers($tenantId, $chunk) as $uid => $list) {
                        foreach ($list as $a) {
                            $name = trim((string) ($a['role_name'] ?? ''));
                            if ($name !== '') {
                                $jobsByUser[(int) $uid][] = ['name' => $name, 'detail' => trim((string) ($a['role_detail'] ?? '')), 'primary' => !empty($a['is_primary'])];
                            }
                        }
                    }
                }
            }
        } catch (Throwable) {
        }
        $accessNames = [];
        try {
            foreach ($this->roles->forTenantAccessProfiles($tenantId) as $r) {
                $accessNames[(int) ($r['id'] ?? 0)] = trim((string) ($r['name'] ?? ''));
            }
        } catch (Throwable) {
        }
        $accessByUser = [];
        try {
            $accessByUser = $ids !== [] ? $this->users->listOrganizationRoleIdsForUsers($tenantId, $ids) : [];
        } catch (Throwable) {
        }

        $members = [];
        $holdersByUnit = [];
        $jobFilter = [];
        $roleFilter = [];
        $accessFilter = [];
        $assigned = 0;
        $withoutJob = 0;
        foreach ($ids as $id) {
            $r = $rich[$id] ?? null;
            if ($r === null) {
                continue;
            }
            $unitId = (int) ($r['unit_id'] ?? 0);
            $code = $codes['units'][$unitId] ?? null;
            $jobs = $jobsByUser[$id] ?? [];
            if ($jobs === [] && trim((string) ($r['job_role_display'] ?? '')) !== '') {
                $jobs[] = ['name' => trim((string) $r['job_role_display']), 'detail' => '', 'primary' => true];
            }
            foreach ($jobs as $j) {
                $jobFilter[$j['name']] = true;
            }
            $role = trim((string) ($r['assignment_role_name'] ?? ''));
            if ($role !== '') {
                $roleFilter[$role] = true;
            }
            $access = [];
            foreach ((array) ($accessByUser[$id] ?? []) as $rid) {
                $n = $accessNames[(int) $rid] ?? '';
                if ($n !== '') {
                    $access[] = $n;
                    $accessFilter[$n] = true;
                }
            }
            $name = trim((string) ($r['display_name'] ?? ''));
            $member = [
                'id' => $id,
                'name' => $name !== '' ? $name : 'Membre',
                'callsign' => trim((string) ($r['callsign'] ?? '')),
                'character_name' => trim((string) ($r['character_name'] ?? '')),
                'grade' => trim((string) ($r['grade_short'] ?? '')),
                'unit_id' => $unitId > 0 ? $unitId : null,
                'unit_name' => trim((string) ($r['unit_name'] ?? '')),
                'unit_path' => (string) ($meta[$unitId]['path'] ?? ''),
                'unit_code' => (string) ($code['code'] ?? ''),
                'unit_admin_id' => (string) ($code['admin_id'] ?? ''),
                'role' => $role,
                'jobs' => $jobs,
                'access' => array_values(array_unique($access)),
            ];
            if ($unitId > 0) {
                $assigned++;
                $holdersByUnit[$unitId][] = $member;
            }
            if ($jobs === []) {
                $withoutJob++;
            }
            $members[] = $member;
        }
        usort($members, static function (array $a, array $b): int {
            $ca = $a['unit_code'] !== '' ? $a['unit_code'] : "\u{FFFF}";
            $cb = $b['unit_code'] !== '' ? $b['unit_code'] : "\u{FFFF}";

            return [$ca, mb_strtolower($a['name'])] <=> [$cb, mb_strtolower($b['name'])];
        });

        $childCount = [];
        foreach ($unitRows as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            if ($pid > 0) {
                $childCount[$pid] = ($childCount[$pid] ?? 0) + 1;
            }
        }
        $units = [];
        $empty = 0;
        foreach ($unitRows as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $c = $codes['units'][$uid] ?? null;
            $holders = $holdersByUnit[$uid] ?? [];
            usort($holders, static fn (array $a, array $b): int => [$a['role'] === '' ? 1 : 0, mb_strtolower($a['name'])] <=> [$b['role'] === '' ? 1 : 0, mb_strtolower($b['name'])]);
            if ($holders === []) {
                $empty++;
            }
            $units[] = [
                'id' => $uid,
                'name' => trim((string) ($u['name'] ?? '')),
                'type' => trim((string) ($u['type'] ?? '')),
                'own_code' => trim((string) ($u['code'] ?? '')),
                'parent_id' => (int) ($u['parent_id'] ?? 0),
                'path' => (string) ($meta[$uid]['path'] ?? ''),
                'depth' => max(0, (int) ($meta[$uid]['depth'] ?? 1) - 1),
                'code' => (string) ($c['code'] ?? ''),
                'segment' => (string) ($c['segment'] ?? ''),
                'admin_id' => (string) ($c['admin_id'] ?? ''),
                'commander' => trim((string) ($commanders[$uid] ?? '')),
                'children' => (int) ($childCount[$uid] ?? 0),
                'holders' => $holders,
            ];
        }
        // Ordre de l'organigramme : le code suit l'arbre, il sert de clé de tri.
        usort($units, static fn (array $a, array $b): int => [$a['code'] === '' ? 1 : 0, $a['code'], $a['name']] <=> [$b['code'] === '' ? 1 : 0, $b['code'], $b['name']]);

        $sortKeys = static function (array $set): array {
            $keys = array_map('strval', array_keys($set));
            usort($keys, static fn (string $a, string $b): int => strcasecmp($a, $b));

            return $keys;
        };

        return [
            'community' => $codes['community'],
            'members' => $members,
            'units' => $units,
            'filters' => ['jobs' => $sortKeys($jobFilter), 'roles' => $sortKeys($roleFilter), 'access' => $sortKeys($accessFilter)],
            'stats' => [
                'members' => count($members),
                'assigned' => $assigned,
                'without_unit' => count($members) - $assigned,
                'without_job' => $withoutJob,
                'empty_units' => $empty,
                'truncated' => $truncated,
            ],
        ];
    }
}
