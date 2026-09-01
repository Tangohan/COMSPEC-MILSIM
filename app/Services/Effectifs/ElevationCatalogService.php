<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Request;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Services\Documents\DocumentAccessService;

/**
 * Catalogue et validation d’une proposition d’élévation (même jeu de champs
 * que le bureau effectifs — réutilisé pour la demande personnelle du membre).
 */
final class ElevationCatalogService
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private RoleRepository $roleRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private UnitRepository $unitRepository,
    ) {
    }

    /**
     * @return array{
     *   grades: list<array<string,mixed>>,
     *   roles: list<array<string,mixed>>,
     *   job_roles: list<array{id:int,label:string}>,
     *   units: list<array<string,mixed>>,
     *   clearance_levels: array<string,string>
     * }
     */
    public function catalogForTenant(int $tenantId): array
    {
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $jobRoles = [];
        if ($this->personnelJobRoleRepository->tablesExist()) {
            $jobRoles = $this->personnelJobRoleRepository->listRoleOptionsForSelect($tenantId);
        }
        $unitMeta = $this->unitRepository->hierarchyMetaByUnitId($tenantId);
        $unitsRaw = $this->unitRepository->allForTenant($tenantId);
        $units = [];
        foreach ($unitsRaw as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $path = trim((string) ($unitMeta[$uid]['path'] ?? ''));
            $u['assignment_path'] = $path !== '' ? $path : trim((string) ($u['name'] ?? ''));
            $units[] = $u;
        }
        usort($units, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['assignment_path'] ?? ''), (string) ($b['assignment_path'] ?? ''));
        });

        return [
            'grades' => $grades,
            'roles' => $roles,
            'job_roles' => $jobRoles,
            'units' => $units,
            'clearance_levels' => DocumentAccessService::getClassificationLevelLabels(),
        ];
    }

    /**
     * @return array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,clearance_level:?string,role_apply_mode:string}
     */
    public function readProposalFromRequest(Request $request): array
    {
        $intOrNull = static function (mixed $raw): ?int {
            if ($raw === null || $raw === '') {
                return null;
            }
            $id = (int) $raw;

            return $id > 0 ? $id : null;
        };
        $clearance = trim((string) $request->input('proposed_clearance_level', $request->input('elevation_clearance_level', '')));

        return [
            'grade_id' => $intOrNull($request->input('proposed_grade_id', $request->input('elevation_grade_id'))),
            'role_id' => $intOrNull($request->input('proposed_role_id', $request->input('elevation_role_id'))),
            'job_role_id' => $intOrNull($request->input('proposed_job_role_id', $request->input('elevation_job_role_id'))),
            'unit_id' => $intOrNull($request->input('proposed_unit_id', $request->input('elevation_unit_id'))),
            'clearance_level' => $clearance !== '' ? $clearance : null,
            'role_apply_mode' => ElevationApprovalService::normalizeRoleApplyMode(
                (string) $request->input('role_apply_mode', ElevationApprovalService::ROLE_APPLY_REPLACE)
            ),
        ];
    }

    /**
     * @param array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,clearance_level?:?string,role_apply_mode?:string} $proposal
     * @return array{proposal: array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,clearance_level:?string,role_apply_mode:string}, error:?string}
     */
    public function validateProposal(int $tenantId, array $proposal): array
    {
        $proposal['clearance_level'] = $proposal['clearance_level'] ?? null;
        $proposal['role_apply_mode'] = ElevationApprovalService::normalizeRoleApplyMode(
            isset($proposal['role_apply_mode']) ? (string) $proposal['role_apply_mode'] : ElevationApprovalService::ROLE_APPLY_REPLACE
        );
        $gradeId = $proposal['grade_id'] ?? null;
        if ($gradeId !== null) {
            $allowed = array_map(
                static fn (array $g): int => (int) ($g['id'] ?? 0),
                $this->gradeRepository->listForTenant($tenantId)
            );
            if (!in_array($gradeId, $allowed, true)) {
                return ['proposal' => $proposal, 'error' => 'Le grade sélectionné n’est pas disponible pour cette communauté.'];
            }
        }

        $roleId = $proposal['role_id'] ?? null;
        if ($roleId !== null && !$this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            return ['proposal' => $proposal, 'error' => 'Ce rôle ne peut pas être demandé dans cette communauté.'];
        }

        $jobRoleId = $proposal['job_role_id'] ?? null;
        if ($jobRoleId !== null) {
            if (!$this->personnelJobRoleRepository->tablesExist()
                || !$this->personnelJobRoleRepository->findRoleById($jobRoleId, $tenantId)) {
                return ['proposal' => $proposal, 'error' => 'La fonction sélectionnée est introuvable.'];
            }
        }

        $unitId = $proposal['unit_id'] ?? null;
        if ($unitId !== null && !$this->unitRepository->findById($unitId, $tenantId)) {
            return ['proposal' => $proposal, 'error' => 'L’affectation sélectionnée est introuvable.'];
        }

        $clearanceLevel = $proposal['clearance_level'] ?? null;
        if ($clearanceLevel !== null
            && !array_key_exists($clearanceLevel, DocumentAccessService::getClassificationLevelLabels())) {
            return ['proposal' => $proposal, 'error' => 'Le niveau d’habilitation sélectionné n’est pas reconnu.'];
        }

        return ['proposal' => $proposal, 'error' => null];
    }
}
