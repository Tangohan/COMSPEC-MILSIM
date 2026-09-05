<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Csrf;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAbsenceRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Services\Documents\DocumentAccessService;
use App\Services\Effectifs\EffectifsStaffAlertService;

/**
 * Données du parcours RH en bas du tableau de bord (Absence, Élévation, Avancement).
 */
final class DashboardRhParcours
{
    public const STEPS = ['choice', 'absence', 'elevation', 'avancement'];

    /**
     * @return array<string, mixed>
     */
    public static function build(
        int $tenantId,
        int $userId,
        string $tenantSlug,
        bool $showPersonnel,
        bool $showOffers
    ): array {
        $offers = [];
        if ($showOffers && $tenantId > 0) {
            try {
                $openingRepo = Container::get(RecruitmentOpeningRepository::class);
                if ($openingRepo->tablesExist()) {
                    foreach ($openingRepo->listPublishedForTenant($tenantId) as $row) {
                        $avisSlug = trim((string) ($row['public_page_slug'] ?? ''));
                        $href = $avisSlug !== '' && $tenantSlug !== ''
                            ? url('c/' . rawurlencode($tenantSlug) . '/avis/' . rawurlencode($avisSlug))
                            : url('enlistment');
                        $title = trim((string) ($row['title'] ?? ''));
                        $unitName = trim((string) ($row['unit_name'] ?? ''));
                        $offers[] = [
                            'id' => (int) ($row['id'] ?? 0),
                            'title' => $title !== '' ? $title : 'Offre',
                            'unit_name' => $unitName,
                            'href' => $href,
                        ];
                    }
                }
            } catch (\Throwable) {
                $offers = [];
            }
        }

        $absenceReady = false;
        $activeAbsences = [];
        if ($showPersonnel && $tenantId > 0 && $userId > 0) {
            try {
                $absenceRepo = Container::get(PersonnelAbsenceRepository::class);
                $absenceReady = $absenceRepo->tableExists();
                if ($absenceReady) {
                    $activeAbsences = $absenceRepo->listActiveForUser($tenantId, $userId);
                }
            } catch (\Throwable) {
                $absenceReady = false;
                $activeAbsences = [];
            }
        }

        $mobilityReady = false;
        $myMobility = [];
        if ($showPersonnel && $tenantId > 0 && $userId > 0) {
            try {
                $mobRepo = new PersonnelMobilityRequestRepository();
                $mobilityReady = $mobRepo->tableExists();
                if ($mobilityReady) {
                    $myMobility = $mobRepo->listForUser($tenantId, $userId, 8);
                }
            } catch (\Throwable) {
                $mobilityReady = false;
                $myMobility = [];
            }
        }

        $elevationCatalog = [
            'grades' => [],
            'roles' => [],
            'job_roles' => [],
            'units' => [],
            'clearance_levels' => [],
            'permissions' => [],
        ];
        $elevationCooldown = null;
        $elevationHasRecipients = false;
        if ($showPersonnel && $tenantId > 0 && $userId > 0) {
            try {
                $elevationCatalog = self::elevationCatalogForTenant($tenantId);
            } catch (\Throwable) {
            }
            try {
                $alerts = Container::get(EffectifsStaffAlertService::class);
                $elevationCooldown = $alerts->secondsBeforeNextElevationRequest($userId, $userId);
                $elevationHasRecipients = $alerts->listElevationRecipients($tenantId, $userId) !== [];
            } catch (\Throwable) {
            }
        }

        return [
            'show_personnel' => $showPersonnel,
            'show_offers' => $showOffers,
            'csrf' => Csrf::token(),
            'workspace_url' => url('personnel/mon-espace-rh'),
            'offers' => $offers,
            'absence_ready' => $absenceReady,
            'active_absences' => $activeAbsences,
            'absence_reason_labels' => PersonnelAbsenceRepository::REASON_LABELS,
            'mobility_ready' => $mobilityReady,
            'mobility_type_labels' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'my_mobility' => $myMobility,
            'elevation_catalog' => $elevationCatalog,
            'elevation_cooldown_seconds' => $elevationCooldown,
            'elevation_has_recipients' => $elevationHasRecipients,
        ];
    }

    /**
     * @return array{
     *   grades: list<array<string,mixed>>,
     *   roles: list<array<string,mixed>>,
     *   job_roles: list<array<string,mixed>>,
     *   units: list<array<string,mixed>>,
     *   clearance_levels: array<string,string>
     * }
     */
    public static function elevationCatalogForTenant(int $tenantId): array
    {
        $grades = Container::get(GradeRepository::class)->listForTenant($tenantId);
        $roles = Container::get(RoleRepository::class)->forTenantOrganization($tenantId);
        $jobRoles = [];
        $jobRepo = Container::get(PersonnelJobRoleRepository::class);
        if ($jobRepo->tablesExist()) {
            $jobRoles = $jobRepo->listRoleOptionsForSelect($tenantId);
        }
        $unitRepo = Container::get(UnitRepository::class);
        $unitMeta = $unitRepo->hierarchyMetaByUnitId($tenantId);
        $units = [];
        foreach ($unitRepo->allForTenant($tenantId) as $u) {
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
            'permissions' => Container::get(PermissionRepository::class)->allRequestableForTenant($tenantId),
        ];
    }
}
