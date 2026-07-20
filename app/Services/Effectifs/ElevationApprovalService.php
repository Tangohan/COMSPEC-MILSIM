<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
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
        private ?PersonnelStructureChangeNotificationService $structureChangeNotification = null,
    ) {
    }

    public const ROLE_APPLY_REPLACE = 'replace';
    public const ROLE_APPLY_ADD = 'add';

    /**
     * Modes d’application du rôle proposés à l’approbation.
     *
     * @return list<string>
     */
    public static function roleApplyModes(): array
    {
        return [self::ROLE_APPLY_REPLACE, self::ROLE_APPLY_ADD];
    }

    public static function normalizeRoleApplyMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        // Alias métier : conserver les accès = cumuler le rôle (multi-rôles).
        if (in_array($mode, ['keep', 'keep_access', 'conserve', 'union'], true)) {
            return self::ROLE_APPLY_ADD;
        }

        return in_array($mode, self::roleApplyModes(), true) ? $mode : self::ROLE_APPLY_REPLACE;
    }

    /**
     * Calcule les IDs de rôles après application selon le mode.
     *
     * @param list<int> $currentRoleIds
     * @return list<int>
     */
    public function resolveRoleIdsAfterApply(array $currentRoleIds, ?int $proposedRoleId, string $mode = self::ROLE_APPLY_REPLACE): array
    {
        $mode = self::normalizeRoleApplyMode($mode);
        $currentRoleIds = array_values(array_unique(array_filter(
            array_map('intval', $currentRoleIds),
            static fn (int $id): bool => $id > 0
        )));
        $proposedRoleId = $proposedRoleId !== null && $proposedRoleId > 0 ? $proposedRoleId : null;
        if ($proposedRoleId === null) {
            return $currentRoleIds;
        }
        if ($mode === self::ROLE_APPLY_ADD) {
            if (in_array($proposedRoleId, $currentRoleIds, true)) {
                return $currentRoleIds;
            }

            return array_values(array_merge($currentRoleIds, [$proposedRoleId]));
        }

        return [$proposedRoleId];
    }

    /**
     * Diff des permissions catalogue avant / après selon le rôle proposé et le mode.
     * Ne invente aucun droit : lit uniquement `role_permissions` via RbacService.
     *
     * @param list<int> $currentRoleIds
     * @return array{
     *   before: list<array{id:int,name:string,slug:string,module:string}>,
     *   after: list<array{id:int,name:string,slug:string,module:string}>,
     *   gained: list<array{id:int,name:string,slug:string,module:string}>,
     *   lost: list<array{id:int,name:string,slug:string,module:string}>,
     *   rows: list<array{id:int,name:string,slug:string,module:string,before:bool,after:bool,change:string}>,
     *   unchanged_count: int,
     *   mode: string,
     *   after_role_ids: list<int>
     * }
     */
    public function permissionDiffForRoleChange(
        int $tenantId,
        array $currentRoleIds,
        ?int $proposedRoleId,
        string $mode = self::ROLE_APPLY_REPLACE
    ): array {
        $mode = self::normalizeRoleApplyMode($mode);
        $matrix = $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
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
        $afterRoleIds = $this->resolveRoleIdsAfterApply($currentRoleIds, $proposedRoleId, $mode);
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
        $rows = [];
        $unchanged = 0;
        foreach ($permById as $pid => $row) {
            $had = isset($beforeMap[$pid]);
            $will = isset($afterMap[$pid]);
            if (!$had && !$will) {
                continue;
            }
            $change = 'same';
            if ($had && $will) {
                $unchanged++;
            } elseif (!$had && $will) {
                $gained[] = $row;
                $change = 'gained';
            } elseif ($had && !$will) {
                $lost[] = $row;
                $change = 'lost';
            }
            $rows[] = array_merge($row, [
                'before' => $had,
                'after' => $will,
                'change' => $change,
            ]);
        }
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return [
            'before' => $toList($beforeMap),
            'after' => $toList($afterMap),
            'gained' => $gained,
            'lost' => $lost,
            'rows' => $rows,
            'unchanged_count' => $unchanged,
            'mode' => $mode,
            'after_role_ids' => $afterRoleIds,
        ];
    }

    /**
     * Libellés métier des choix proposés (pour confirmation / e-mail / compte).
     *
     * @param array{
     *   grade_id?: int|null,
     *   role_id?: int|null,
     *   job_role_id?: int|null,
     *   unit_id?: int|null
     * } $proposal
     * @return array{grade:?string,role:?string,job_role:?string,unit:?string}
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

        return [
            'grade' => $gradeLabel,
            'role' => $roleLabel,
            'job_role' => $jobLabel,
            'unit' => $unitLabel,
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
     *   role_apply_mode?: string|null
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
        $roleApplyMode = self::normalizeRoleApplyMode(
            isset($proposal['role_apply_mode']) ? (string) $proposal['role_apply_mode'] : self::ROLE_APPLY_REPLACE
        );

        $beforeSnap = null;
        if ($this->structureChangeNotification !== null) {
            $beforeSnap = $this->structureChangeNotification->snapshot($tenantId, $targetUserId);
        }

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
                $currentRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($targetUserId);
                $nextRoleIds = $this->resolveRoleIdsAfterApply($currentRoleIds, $roleId, $roleApplyMode);
                $this->userRepository->syncOrganizationRoles(
                    $targetUserId,
                    $tenantId,
                    $nextRoleIds,
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
                'message' => 'Demande acceptée. Aucun changement de grade, rôle, fonction ou affectation n’était sélectionné — seuls le statut et la note ont été enregistrés.',
                'applied' => [],
            ];
        }

        if ($this->structureChangeNotification !== null && $beforeSnap !== null) {
            try {
                $afterSnap = $this->structureChangeNotification->snapshot($tenantId, $targetUserId);
                $this->structureChangeNotification->notifyFromSnapshots(
                    $tenantId,
                    $targetUserId,
                    $actorUserId,
                    $beforeSnap,
                    $afterSnap
                );
            } catch (Throwable) {
                // L’e-mail ne doit pas faire échouer l’application.
            }
        }

        $labels = $this->proposalLabels($tenantId, $proposal);
        $parts = [];
        if (in_array('grade', $applied, true) && $labels['grade']) {
            $parts[] = 'grade « ' . $labels['grade'] . ' »';
        }
        if (in_array('role', $applied, true) && $labels['role']) {
            $parts[] = $roleApplyMode === self::ROLE_APPLY_ADD
                ? 'rôle « ' . $labels['role'] . ' » ajouté en plus des rôles actuels'
                : 'rôle remplacé par « ' . $labels['role'] . ' »';
        }
        if (in_array('job_role', $applied, true) && $labels['job_role']) {
            $parts[] = 'fonction « ' . $labels['job_role'] . ' »';
        }
        if (in_array('unit', $applied, true) && $labels['unit']) {
            $parts[] = 'affectation « ' . $labels['unit'] . ' »';
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
