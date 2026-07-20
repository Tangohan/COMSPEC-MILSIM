<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Documents\DocumentAccessService;
use App\Services\Rbac\RbacService;
use App\Support\OrganizationRoleLabels;
use InvalidArgumentException;
use Throwable;

/**
 * Application d’une élévation approuvée + aperçu des droits (catalogue réel du rôle).
 */
class ElevationApprovalService
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private GradeRepository $gradeRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private UnitRepository $unitRepository,
        private RbacService $rbacService,
        private PersonnelProfileRepository $personnelProfileRepository,
    ) {
    }

    /**
     * Diff des permissions catalogue avant / après selon le rôle proposé.
     * Ne invente aucun droit : lit uniquement `role_permissions` via RbacService.
     *
     * @param list<int> $currentRoleIds
     * @return array{
     *   before: list<array{id:int,name:string,slug:string,module:string}>,
     *   after: list<array{id:int,name:string,slug:string,module:string}>,
     *   gained: list<array{id:int,name:string,slug:string,module:string}>,
     *   lost: list<array{id:int,name:string,slug:string,module:string}>,
     *   unchanged_count: int
     * }
     * @param array{permissions?:list<array<string,mixed>>,byRole?:array<int,array<int,bool>>}|null $matrix
     *   Matrice déjà chargée par l'appelant (évite de la recalculer à chaque ligne d'une liste).
     */
    public function permissionDiffForRoleChange(int $tenantId, array $currentRoleIds, ?int $proposedRoleId, ?array $matrix = null): array
    {
        $matrix ??= $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
        $permById = [];
        foreach ($matrix['permissions'] as $p) {
            $pid = (int) ($p['id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $permById[$pid] = [
                'id' => $pid,
                'name' => (string) ($p['name'] ?? ''),
                'slug' => (string) ($p['slug'] ?? ''),
                'module' => (string) ($p['module'] ?? ''),
            ];
        }

        $idsForRoles = static function (array $roleIds, array $byRole): array {
            $out = [];
            foreach ($roleIds as $rid) {
                $rid = (int) $rid;
                if ($rid < 1 || empty($byRole[$rid]) || !is_array($byRole[$rid])) {
                    continue;
                }
                foreach (array_keys($byRole[$rid]) as $pid) {
                    $out[(int) $pid] = true;
                }
            }

            return $out;
        };

        $byRole = is_array($matrix['byRole'] ?? null) ? $matrix['byRole'] : [];
        $beforeMap = $idsForRoles($currentRoleIds, $byRole);
        $afterRoleIds = $proposedRoleId !== null && $proposedRoleId > 0
            ? [$proposedRoleId]
            : $currentRoleIds;
        $afterMap = $idsForRoles($afterRoleIds, $byRole);

        $toList = static function (array $map) use ($permById): array {
            $list = [];
            foreach (array_keys($map) as $pid) {
                if (isset($permById[$pid])) {
                    $list[] = $permById[$pid];
                }
            }
            usort($list, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            return $list;
        };

        $gained = [];
        $lost = [];
        $unchanged = 0;
        foreach ($permById as $pid => $row) {
            $had = isset($beforeMap[$pid]);
            $will = isset($afterMap[$pid]);
            if ($had && $will) {
                $unchanged++;
            } elseif (!$had && $will) {
                $gained[] = $row;
            } elseif ($had && !$will) {
                $lost[] = $row;
            }
        }

        return [
            'before' => $toList($beforeMap),
            'after' => $toList($afterMap),
            'gained' => $gained,
            'lost' => $lost,
            'unchanged_count' => $unchanged,
        ];
    }

    /**
     * Libellés métier des choix proposés (pour confirmation / e-mail / compte).
     *
     * @param array{
     *   grade_id?: int|null,
     *   role_id?: int|null,
     *   job_role_id?: int|null,
     *   unit_id?: int|null,
     *   clearance_level?: string|null
     * } $proposal
     * @return array{grade:?string,role:?string,job_role:?string,unit:?string,clearance:?string}
     */
    public function proposalLabels(int $tenantId, array $proposal): array
    {
        $gradeLabel = null;
        $gradeId = (int) ($proposal['grade_id'] ?? 0);
        if ($gradeId > 0) {
            $g = $this->gradeRepository->findById($gradeId, $tenantId);
            if ($g) {
                $short = trim((string) ($g['label_short'] ?? ''));
                $long = trim((string) ($g['label_long'] ?? ''));
                $gradeLabel = $short !== '' ? $short : ($long !== '' ? $long : null);
            }
        }

        $roleLabel = null;
        $roleId = (int) ($proposal['role_id'] ?? 0);
        if ($roleId > 0) {
            $r = $this->roleRepository->findById($roleId, $tenantId);
            if ($r && $this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
                $roleLabel = OrganizationRoleLabels::displayName($r, OrganizationRoleLabels::MODE_FR);
            }
        }

        $jobLabel = null;
        $jobId = (int) ($proposal['job_role_id'] ?? 0);
        if ($jobId > 0 && $this->personnelJobRoleRepository->tablesExist()) {
            $jr = $this->personnelJobRoleRepository->findRoleById($jobId, $tenantId);
            if ($jr) {
                $jobLabel = trim((string) ($jr['name'] ?? '')) ?: null;
            }
        }

        $unitLabel = null;
        $unitId = (int) ($proposal['unit_id'] ?? 0);
        if ($unitId > 0) {
            $u = $this->unitRepository->findById($unitId, $tenantId);
            if ($u) {
                $unitLabel = trim((string) ($u['name'] ?? '')) ?: null;
            }
        }

        $clearanceLabel = null;
        $clearanceValue = trim((string) ($proposal['clearance_level'] ?? ''));
        if ($clearanceValue !== '') {
            $clearanceLabel = DocumentAccessService::getClassificationLevelLabels()[$clearanceValue] ?? null;
        }

        return [
            'grade' => $gradeLabel,
            'role' => $roleLabel,
            'job_role' => $jobLabel,
            'unit' => $unitLabel,
            'clearance' => $clearanceLabel,
        ];
    }

    /**
     * Précalcule les maps id => libellé à partir d’un catalogue déjà chargé (grades/roles/job_roles/units),
     * pour résoudre les libellés d’une liste de propositions sans requête SQL par ligne.
     *
     * @param array{
     *   grades: list<array<string,mixed>>,
     *   roles: list<array<string,mixed>>,
     *   job_roles: list<array{id:int,label:string}>,
     *   units: list<array<string,mixed>>,
     *   clearance_levels?: array<string,string>
     * } $catalog
     * @return array{grades:array<int,string>,roles:array<int,string>,job_roles:array<int,string>,units:array<int,string>,clearance_levels:array<string,string>}
     */
    public function buildLabelMapsFromCatalog(array $catalog): array
    {
        $grades = [];
        foreach ($catalog['grades'] ?? [] as $g) {
            $id = (int) ($g['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $short = trim((string) ($g['label_short'] ?? ''));
            $long = trim((string) ($g['label_long'] ?? ''));
            $label = $short !== '' ? $short : $long;
            if ($label !== '') {
                $grades[$id] = $label;
            }
        }

        $roles = [];
        foreach ($catalog['roles'] ?? [] as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $roles[$id] = OrganizationRoleLabels::displayName($r, OrganizationRoleLabels::MODE_FR);
        }

        $jobRoles = [];
        foreach ($catalog['job_roles'] ?? [] as $jr) {
            $id = (int) ($jr['id'] ?? 0);
            $label = trim((string) ($jr['label'] ?? ''));
            if ($id > 0 && $label !== '') {
                $jobRoles[$id] = $label;
            }
        }

        $units = [];
        foreach ($catalog['units'] ?? [] as $u) {
            $id = (int) ($u['id'] ?? 0);
            $label = trim((string) ($u['name'] ?? ''));
            if ($id > 0 && $label !== '') {
                $units[$id] = $label;
            }
        }

        $clearanceLevels = is_array($catalog['clearance_levels'] ?? null)
            ? $catalog['clearance_levels']
            : DocumentAccessService::getClassificationLevelLabels();

        return ['grades' => $grades, 'roles' => $roles, 'job_roles' => $jobRoles, 'units' => $units, 'clearance_levels' => $clearanceLevels];
    }

    /**
     * Équivalent de proposalLabels() mais résolu en mémoire à partir des maps de buildLabelMapsFromCatalog().
     * Ne fait aucune requête SQL — à utiliser pour enrichir une liste de plusieurs demandes.
     *
     * @param array{grades:array<int,string>,roles:array<int,string>,job_roles:array<int,string>,units:array<int,string>,clearance_levels?:array<string,string>} $maps
     * @param array{grade_id?:int|null,role_id?:int|null,job_role_id?:int|null,unit_id?:int|null,clearance_level?:string|null} $proposal
     * @return array{grade:?string,role:?string,job_role:?string,unit:?string,clearance:?string}
     */
    public function proposalLabelsFromMaps(array $maps, array $proposal): array
    {
        $gradeId = (int) ($proposal['grade_id'] ?? 0);
        $roleId = (int) ($proposal['role_id'] ?? 0);
        $jobId = (int) ($proposal['job_role_id'] ?? 0);
        $unitId = (int) ($proposal['unit_id'] ?? 0);
        $clearanceValue = trim((string) ($proposal['clearance_level'] ?? ''));
        $clearanceLevels = is_array($maps['clearance_levels'] ?? null) ? $maps['clearance_levels'] : [];

        return [
            'grade' => $gradeId > 0 ? ($maps['grades'][$gradeId] ?? null) : null,
            'role' => $roleId > 0 ? ($maps['roles'][$roleId] ?? null) : null,
            'job_role' => $jobId > 0 ? ($maps['job_roles'][$jobId] ?? null) : null,
            'unit' => $unitId > 0 ? ($maps['units'][$unitId] ?? null) : null,
            'clearance' => $clearanceValue !== '' ? ($clearanceLevels[$clearanceValue] ?? null) : null,
        ];
    }

    /**
     * Applique grade / rôle / fonction / affectation après confirmation d’approbation.
     *
     * @param array{
     *   grade_id?: int|null,
     *   role_id?: int|null,
     *   job_role_id?: int|null,
     *   unit_id?: int|null,
     *   clearance_level?: string|null
     * } $proposal
     * @return array{ok:bool,message:string,applied:list<string>}
     */
    public function applyApprovedChanges(
        int $tenantId,
        int $targetUserId,
        array $proposal,
        ?int $actorUserId = null
    ): array {
        $user = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$user) {
            return ['ok' => false, 'message' => 'Membre introuvable.', 'applied' => []];
        }

        $applied = [];
        $gradeId = (int) ($proposal['grade_id'] ?? 0);
        $roleId = (int) ($proposal['role_id'] ?? 0);
        $jobRoleId = (int) ($proposal['job_role_id'] ?? 0);
        $unitId = (int) ($proposal['unit_id'] ?? 0);
        $clearanceLevel = trim((string) ($proposal['clearance_level'] ?? ''));

        try {
            if ($gradeId > 0) {
                $allowed = array_map(
                    static fn (array $g): int => (int) ($g['id'] ?? 0),
                    $this->gradeRepository->listForTenant($tenantId)
                );
                if (!in_array($gradeId, $allowed, true)) {
                    return [
                        'ok' => false,
                        'message' => 'Le grade sélectionné n’est pas disponible pour cette communauté.',
                        'applied' => [],
                    ];
                }
                $this->userRepository->update($targetUserId, $tenantId, ['grade_id' => $gradeId]);
                $applied[] = 'grade';
            }

            if ($roleId > 0) {
                if (!$this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
                    return [
                        'ok' => false,
                        'message' => 'Ce rôle ne peut pas être attribué dans cette communauté.',
                        'applied' => $applied,
                    ];
                }
                $this->userRepository->syncOrganizationRoles(
                    $targetUserId,
                    $tenantId,
                    [$roleId],
                    $actorUserId
                );
                $applied[] = 'role';
            }

            if ($jobRoleId > 0 && $this->personnelJobRoleRepository->tablesExist()) {
                $jr = $this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId);
                if (!$jr) {
                    return [
                        'ok' => false,
                        'message' => 'La fonction sélectionnée est introuvable.',
                        'applied' => $applied,
                    ];
                }
                if ($this->personnelJobRoleRepository->pivotTableExists()) {
                    $this->personnelJobRoleRepository->replaceUserPivotJobRoles($tenantId, $targetUserId, [
                        ['personnel_job_role_id' => $jobRoleId, 'is_primary' => true, 'role_detail' => ''],
                    ]);
                }
                $applied[] = 'job_role';
            }

            if ($unitId > 0) {
                $unit = $this->unitRepository->findById($unitId, $tenantId);
                if (!$unit) {
                    return [
                        'ok' => false,
                        'message' => 'L’affectation sélectionnée est introuvable.',
                        'applied' => $applied,
                    ];
                }
                $roleName = 'Membre';
                if ($jobRoleId > 0 && $this->personnelJobRoleRepository->tablesExist()) {
                    $jr = $this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId);
                    if ($jr) {
                        $roleName = trim((string) ($jr['name'] ?? '')) ?: $roleName;
                    }
                }
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                    $targetUserId,
                    $unitId,
                    $roleName
                );
                $applied[] = 'unit';
            }

            if ($clearanceLevel !== '') {
                if (!array_key_exists($clearanceLevel, DocumentAccessService::getClassificationLevelLabels())) {
                    return [
                        'ok' => false,
                        'message' => 'Le niveau d’habilitation sélectionné n’est pas reconnu.',
                        'applied' => $applied,
                    ];
                }
                $this->personnelProfileRepository->ensureRecord($targetUserId);
                $this->personnelProfileRepository->update($targetUserId, [
                    'clearance_level' => $clearanceLevel,
                    'clearance_reviewed_at' => date('Y-m-d H:i:s'),
                ]);
                $applied[] = 'clearance';
            }
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'applied' => $applied];
        } catch (Throwable) {
            return [
                'ok' => false,
                'message' => 'L’application des changements a échoué. Vérifiez les choix puis réessayez.',
                'applied' => $applied,
            ];
        }

        if ($applied === []) {
            return [
                'ok' => true,
                'message' => 'Demande acceptée. Aucun changement de grade, rôle, fonction, affectation ou habilitation n’était sélectionné — seuls le statut et la note ont été enregistrés.',
                'applied' => [],
            ];
        }

        $labels = $this->proposalLabels($tenantId, $proposal);
        $parts = [];
        if (in_array('grade', $applied, true) && $labels['grade']) {
            $parts[] = 'grade « ' . $labels['grade'] . ' »';
        }
        if (in_array('role', $applied, true) && $labels['role']) {
            $parts[] = 'rôle « ' . $labels['role'] . ' »';
        }
        if (in_array('job_role', $applied, true) && $labels['job_role']) {
            $parts[] = 'fonction « ' . $labels['job_role'] . ' »';
        }
        if (in_array('unit', $applied, true) && $labels['unit']) {
            $parts[] = 'affectation « ' . $labels['unit'] . ' »';
        }
        if (in_array('clearance', $applied, true) && $labels['clearance']) {
            $parts[] = 'habilitation « ' . $labels['clearance'] . ' »';
        }

        return [
            'ok' => true,
            'message' => 'Élévation appliquée : ' . implode(', ', $parts) . '.',
            'applied' => $applied,
        ];
    }

    /**
     * @return list<int>
     */
    public function currentOrganizationRoleIds(int $userId): array
    {
        return $this->userRepository->listOrganizationRoleIdsForUser($userId);
    }

    /**
     * Union des slugs catalogue pour un ensemble de rôles (diagnostic / tests).
     *
     * @param list<int> $roleIds
     * @return list<string>
     */
    public function permissionSlugsForRoles(array $roleIds): array
    {
        return $this->rbacService->loadPermissionsForRoles($roleIds);
    }
}
