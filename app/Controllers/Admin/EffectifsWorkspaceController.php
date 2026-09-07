<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\BadgeRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\PersonnelAbsenceRepository;
use App\Repositories\PersonnelHrDocumentRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelServiceHistoryRepository;
use App\Repositories\PersonnelStageBilanRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SeniorityRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Repositories\MemberDepartureRepository;
use App\Services\Admin\AdminAuditService;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Effectifs\ElevationApprovalService;
use App\Services\Effectifs\MemberOffboardingService;
use App\Services\Effectifs\PersonnelCommandChainService;
use App\Services\Personnel\PersonnelDuplicateDetectionService;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
use App\Authorization\SystemReservedPermissions;
use App\Services\Admin\RolePermissionService;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Repositories\TenantAdminSettingsRepository;
use App\Support\EffectifsLmsAccess;
use App\Support\OrganizationRoleLabels;
use DateTimeImmutable;

/**
 * Bureau LMS de pilotage des effectifs (outil RH) — shell type mes-formations / recrutement.
 */
class EffectifsWorkspaceController
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private UnitRepository $unitRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private AdminAuditService $adminAuditService,
        private EffectifsStaffAlertService $effectifsStaffAlertService,
        private TenantRepository $tenantRepository,
        private GradeRepository $gradeRepository,
        private PermissionRepository $permissionRepository,
        private ElevationApprovalService $elevationApprovalService,
        private MemberOffboardingService $memberOffboardingService,
        private MemberDepartureRepository $memberDepartureRepository,
        private PersonnelStructureChangeNotificationService $structureChangeNotification,
        private ?ElevationRequestRepository $elevationRequestRepository = null,
        private ?PersonnelQualificationRepository $personnelQualificationRepository = null,
        private ?PersonnelDuplicateDetectionService $duplicateDetection = null,
        private ?TenantAdminSettingsRepository $adminSettings = null,
        private ?\App\Services\Personnel\SenioritySummaryService $senioritySummary = null,
        private ?\App\Services\Personnel\SeniorityPrePlatformService $seniorityPrePlatform = null,
        private ?\App\Services\Personnel\SeniorityEnrollmentBootstrapService $seniorityEnrollment = null,
        private ?PersonnelAbsenceRepository $personnelAbsenceRepository = null,
        private ?PersonnelHrDocumentRepository $personnelHrDocumentRepository = null,
        private ?PersonnelMobilityRequestRepository $personnelMobilityRequestRepository = null,
        private ?PersonnelOrgHistoryRepository $personnelOrgHistoryRepository = null,
        private ?PersonnelServiceHistoryRepository $personnelServiceHistoryRepository = null,
        private ?PersonnelStageBilanRepository $personnelStageBilanRepository = null,
        private ?BadgeRepository $badgeRepository = null,
    ) {
        $this->elevationRequestRepository ??= new ElevationRequestRepository();
        $this->personnelQualificationRepository ??= new PersonnelQualificationRepository();
        $this->duplicateDetection ??= new PersonnelDuplicateDetectionService();
        $this->adminSettings ??= new TenantAdminSettingsRepository();
        $this->senioritySummary ??= new \App\Services\Personnel\SenioritySummaryService(
            new SeniorityRepository(),
            new \App\Services\Personnel\SeniorityEngine()
        );
        $this->seniorityPrePlatform ??= \App\Core\Container::get(\App\Services\Personnel\SeniorityPrePlatformService::class);
        $this->seniorityEnrollment ??= \App\Core\Container::get(\App\Services\Personnel\SeniorityEnrollmentBootstrapService::class);
        $this->personnelAbsenceRepository ??= new PersonnelAbsenceRepository();
        $this->personnelHrDocumentRepository ??= new PersonnelHrDocumentRepository();
        $this->personnelMobilityRequestRepository ??= new PersonnelMobilityRequestRepository();
        $this->personnelOrgHistoryRepository ??= new PersonnelOrgHistoryRepository();
        $this->personnelServiceHistoryRepository ??= new PersonnelServiceHistoryRepository();
        $this->personnelStageBilanRepository ??= new PersonnelStageBilanRepository();
        $this->badgeRepository ??= new BadgeRepository();
    }

    /**
     * Tableau des recyclages : qualifications échues ou proches de l'échéance.
     *
     * Répond à la question métier « quelle qualification expire bientôt ? », restée sans
     * réponse tant que `personnel_qualifications` n'était jamais alimentée.
     */
    public function qualifications(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        // Fenêtre d'observation : 30 / 60 / 90 jours, 60 par défaut.
        $within = (int) $request->query('horizon', 60);
        if (!in_array($within, [30, 60, 90], true)) {
            $within = 60;
        }

        $ready = $this->personnelQualificationRepository->trainingLinkReady();
        $rows = $ready
            ? $this->personnelQualificationRepository->listExpiringForTenant($tenantId, $within, 300)
            : [];

        // Répartition : déjà échues vs à renouveler, pour hiérarchiser l'action.
        $expired = [];
        $expiring = [];
        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $exp = substr((string) ($row['expires_at'] ?? ''), 0, 10);
            if ($exp !== '' && $exp < $today) {
                $expired[] = $row;
            } else {
                $expiring[] = $row;
            }
        }

        return $this->shell('admin.effectifs_workspace.qualifications', [
            'title' => 'Qualifications',
            'effectifsNav' => 'qualifications',
            'qualificationsReady' => $ready,
            'qualificationsHorizon' => $within,
            'qualificationsExpired' => $expired,
            'qualificationsExpiring' => $expiring,
            'qualificationsExpiringCount' => count($expired) + count($expiring),
        ]);
    }

    public function roster(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        $roleId = max(0, (int) $request->query('role_id', 0));
        $onlyNoUnit = $request->query('sans_affectation') === '1';
        $onlyNoRole = $request->query('sans_role') === '1';
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 80;
        $sort = trim((string) $request->query('tri', 'nom'));
        $allowedSorts = ['nom', 'commandement', 'grade', 'anciennete', 'disponibilite', 'presence', 'completion'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'nom';
        }

        $statusFilter = $status !== '' ? $status : null;
        $roleFilter = $roleId > 0 ? $roleId : null;
        $onlyWithoutUnit = $onlyNoUnit ? true : null;
        $onlyWithoutRole = $onlyNoRole ? true : null;
        $searchFilter = $search !== '' ? $search : null;

        $total = $this->userRepository->countListForTenant(
            $tenantId,
            $searchFilter,
            $statusFilter,
            $roleFilter,
            true,
            $onlyWithoutUnit,
            $onlyWithoutRole
        );

        $needsFullLoad = $sort !== 'nom';
        if ($needsFullLoad) {
            $fetchLimit = min(2000, max($total, 1));
            $baseUsers = $this->userRepository->listForTenant(
                $tenantId,
                $searchFilter,
                $statusFilter,
                $roleFilter,
                $fetchLimit,
                0,
                true,
                $onlyWithoutUnit,
                $onlyWithoutRole
            );
            $allRows = $this->enrichRosterRows($tenantId, $baseUsers);
            $allRows = $this->sortRosterRows($allRows, $sort);
            $offset = ($page - 1) * $perPage;
            $rows = array_slice($allRows, $offset, $perPage);
        } else {
            $baseUsers = $this->userRepository->listForTenant(
                $tenantId,
                $searchFilter,
                $statusFilter,
                $roleFilter,
                $perPage,
                ($page - 1) * $perPage,
                true,
                $onlyWithoutUnit,
                $onlyWithoutRole
            );
            $rows = $this->enrichRosterRows($tenantId, $baseUsers);
        }

        $counts = $this->rosterCounts($tenantId);
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
        $gate = Gate::getInstance();
        $communityName = $this->communityNameForTenant($tenantId);
        $viewerId = (int) Session::get('user_id');
        $elevationRecipients = $this->effectifsStaffAlertService->listElevationRecipients($tenantId, $viewerId);
        $rowIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $badgesByUserId = $rowIds !== [] ? $this->badgeRepository->listForUsers($tenantId, $rowIds) : [];
        $elevationCooldownByUserId = $this->effectifsStaffAlertService->secondsBeforeNextElevationRequestBatch(
            $rowIds,
            $viewerId
        );

        return $this->shell('admin.effectifs_workspace.roster', [
            'title' => 'Tableur des effectifs',
            'effectifsNav' => 'roster',
            'rosterRows' => $rows,
            'rosterTotal' => $total,
            'rosterPage' => $page,
            'rosterPerPage' => $perPage,
            'rosterTotalPages' => max(1, (int) ceil($total / $perPage)),
            'rosterFilters' => [
                'q' => $search,
                'status' => $status,
                'role_id' => $roleId,
                'sans_affectation' => $onlyNoUnit,
                'sans_role' => $onlyNoRole,
                'tri' => $sort,
            ],
            'rosterSortOptions' => [
                'nom' => 'Nom',
                'commandement' => 'Ordre de commandement',
                'grade' => 'Ordre de grade',
                'anciennete' => 'Ordre d’ancienneté',
                'disponibilite' => 'Ordre de disponibilité',
                'presence' => 'Ordre de présence',
                'completion' => 'Ordre de complétion du dossier',
            ],
            'rosterCounts' => $counts,
            'orgRoles' => $this->roleRepository->forTenantAccessProfiles($tenantId),
            'orgUnits' => $units,
            'communityName' => $communityName,
            'elevationRecipientsCount' => count($elevationRecipients),
            'elevationCooldownByUserId' => $elevationCooldownByUserId,
            'badgesByUserId' => $badgesByUserId,
            'canEditProfiles' => EffectifsLmsAccess::canEditProfiles($gate),
            'canManageStatus' => EffectifsLmsAccess::canManageStatus($gate),
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'canManageGrades' => EffectifsLmsAccess::canManageGrades($gate),
            'canRequestElevation' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients !== [],
            'elevationNoRecipients' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients === [],
            'elevationCatalog' => $this->elevationCatalogForTenant($tenantId),
            'csrfToken' => Csrf::token(),
            'personnelDuplicateScan' => $this->duplicateDetection->scan($tenantId),
            'personnelDuplicateFieldLabels' => PersonnelDuplicateDetectionService::FIELD_LABELS,
            'orgFoundingDate' => $this->seniorityPrePlatform->getOrgFoundingDate($tenantId),
        ]);
    }

    /** Réglages de détection de doublons (matricule, nom, callsign…). */
    public function duplicateSettings(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $settings = $this->adminSettings->getForTenant($tenantId);
        $dup = is_array($settings['personnel_duplicates'] ?? null) ? $settings['personnel_duplicates'] : [];

        return $this->shell('admin.effectifs_workspace.duplicates', [
            'title' => 'Fiches jumelles',
            'effectifsNav' => 'duplicates',
            'duplicateSettings' => $dup,
            'duplicateFieldLabels' => PersonnelDuplicateDetectionService::FIELD_LABELS,
            'personnelDuplicateScan' => $this->duplicateDetection->scan($tenantId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function saveDuplicateSettings(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/ressources/effectifs/doublons'));
        }

        $fields = $request->input('fields');
        if (!is_array($fields)) {
            $fields = [];
        }
        $current = $this->adminSettings->getForTenant($tenantId);
        $current['personnel_duplicates'] = [
            'enabled' => $request->input('enabled') === '1' || $request->input('enabled') === 'on',
            'fields' => array_values(array_map('strval', $fields)),
        ];
        $this->adminSettings->saveForTenant($tenantId, $current);
        Session::flash('success', 'Réglages de détection des doublons enregistrés.');

        return Response::redirect(url('back-office/ressources/effectifs/doublons'));
    }

    /** Export CSV du tableur, avec les mêmes filtres que roster() mais sans pagination (borné à 5000 lignes). */
    public function exportCsv(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        $roleId = max(0, (int) $request->query('role_id', 0));
        $onlyNoUnit = $request->query('sans_affectation') === '1';
        $onlyNoRole = $request->query('sans_role') === '1';

        $statusFilter = $status !== '' ? $status : null;
        $roleFilter = $roleId > 0 ? $roleId : null;
        $onlyWithoutUnit = $onlyNoUnit ? true : null;
        $onlyWithoutRole = $onlyNoRole ? true : null;
        $searchFilter = $search !== '' ? $search : null;

        $total = $this->userRepository->countListForTenant(
            $tenantId,
            $searchFilter,
            $statusFilter,
            $roleFilter,
            true,
            $onlyWithoutUnit,
            $onlyWithoutRole
        );
        $fetchLimit = min(5000, max($total, 1));
        $baseUsers = $this->userRepository->listForTenant(
            $tenantId,
            $searchFilter,
            $statusFilter,
            $roleFilter,
            $fetchLimit,
            0,
            true,
            $onlyWithoutUnit,
            $onlyWithoutRole
        );
        $rows = $this->enrichRosterRows($tenantId, $baseUsers);
        $rows = $this->sortRosterRows($rows, 'nom');

        $statusLabels = [
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'pending_verification' => 'E-mail à vérifier',
        ];

        $fh = fopen('php://temp', 'r+');
        $sep = ';';
        fputcsv($fh, [
            'Nom affiché', 'Indicatif', 'E-mail', 'Grade', 'Fonction', 'Affectation', 'Statut',
            'Ancienneté', 'Disponibilité (%)', 'Présence (%)', 'Complétion du dossier (%)',
            'platform_number', 'tenant_member_number',
        ], $sep);
        foreach ($rows as $r) {
            $rStatus = (string) ($r['status'] ?? '');
            fputcsv($fh, [
                (string) ($r['display_name'] ?? ''),
                (string) ($r['callsign'] ?? ''),
                \App\Support\EmailPrivacy::display((string) ($r['email'] ?? '')),
                trim((string) ($r['grade_short'] ?? $r['grade_long'] ?? '')),
                trim((string) ($r['job_role_display'] ?? '')),
                trim((string) ($r['assignment_path'] ?? '')),
                $statusLabels[$rStatus] ?? $rStatus,
                (string) ($r['seniority_label'] ?? ''),
                (string) ($r['availability_score'] ?? ''),
                (string) ($r['presence_score'] ?? ''),
                (string) ($r['completion_score'] ?? ''),
                (string) ($r['athena_identifier'] ?? ''),
                (string) ($r['tenant_member_number'] ?? ''),
            ], $sep);
        }
        rewind($fh);
        $csv = "\xEF\xBB\xBF" . (stream_get_contents($fh) ?: '');
        fclose($fh);

        $filename = 'effectifs-' . date('Y-m-d') . '.csv';

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    public function member(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $enriched = $this->enrichRosterRows($tenantId, [$user]);
        $row = $enriched[0] ?? $user;
        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved($id);
        $personnelProfile = $this->personnelProfileRepository->getByUserId($id);
        $roleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        $allOrgRoles = $this->roleRepository->forTenantOrganization($tenantId);
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId);
        $roleNames = [];
        $roleSlugs = [];
        foreach ($allOrgRoles as $r) {
            if (in_array((int) ($r['id'] ?? 0), $roleIds, true)) {
                $roleSlugs[] = (string) ($r['slug'] ?? '');
            }
        }
        $currentAccessRoleId = 0;
        foreach ($roles as $r) {
            $rid = (int) ($r['id'] ?? 0);
            if ($rid > 0 && in_array($rid, $roleIds, true)) {
                $currentAccessRoleId = $rid;
                $roleNames[] = (string) ($r['name'] ?? '');
                break;
            }
        }
        $currentAccessKey = CommunityAccessProfiles::resolveFromSlugs($roleSlugs);
        if ($currentAccessRoleId < 1) {
            foreach ($roles as $r) {
                if (CommunityAccessProfiles::slugToKey((string) ($r['slug'] ?? '')) === $currentAccessKey) {
                    $currentAccessRoleId = (int) ($r['id'] ?? 0);
                    $roleNames[] = (string) ($r['name'] ?? '');
                    break;
                }
            }
        }
        $jobRoles = [];
        $jobRoleOptions = [];
        $jobRoleMax = 5;
        $jobRolesAvailable = false;
        if ($this->personnelJobRoleRepository->tablesExist()) {
            $byUser = $this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, [$id]);
            $jobRoles = $byUser[$id] ?? [];
            $jobRolesAvailable = $this->personnelJobRoleRepository->pivotTableExists();
            if ($jobRolesAvailable) {
                $tenantSettings = $this->tenantRepository->getSettings($tenantId);
                $jobRoleSettings = PersonnelJobRoleAssignmentsSettings::resolve($tenantSettings);
                $communitySettings = is_array($tenantSettings['community'] ?? null) ? $tenantSettings['community'] : [];
                $jobRoleMax = (int) $jobRoleSettings['max_roles_per_member'];
                $jobRoleOptions = $this->personnelJobRoleRepository->listRoleOptionsForMemberDossier(
                    $tenantId,
                    array_map(static fn (array $r): int => (int) ($r['personnel_job_role_id'] ?? 0), $jobRoles),
                    (bool) $jobRoleSettings['show_english_labels'],
                    (bool) $jobRoleSettings['show_category_in_role_picklist'],
                    OrganizationRoleLabels::mode($communitySettings, $this->tenantRepository->findById($tenantId) ?: [])
                );
            }
        }
        $gate = Gate::getInstance();
        $units = $this->unitRepository->allForTenant($tenantId);
        $viewerId = (int) Session::get('user_id');
        $elevationRecipients = $this->effectifsStaffAlertService->listElevationRecipients($tenantId, $viewerId);
        $elevationCooldownSeconds = $this->effectifsStaffAlertService->secondsBeforeNextElevationRequest($id, $viewerId);
        $elevationHistory = $this->elevationRequestRepository->listForTarget($tenantId, $id, 10);
        $latestDeparture = $this->memberDepartureRepository->findLatestForUser($tenantId, $id);
        // La fiche Effectifs est le point d'entrée RH unique : elle charge également les
        // volets auparavant dispersés dans le dossier personnel et les listes RH.
        $qualifications = $this->personnelQualificationRepository->listForUser($id);
        $absences = $this->personnelAbsenceRepository->tableExists()
            ? $this->personnelAbsenceRepository->listForUser($tenantId, $id, 40)
            : [];
        $hrDocuments = $this->personnelHrDocumentRepository->tableExists()
            ? $this->personnelHrDocumentRepository->listForUser($tenantId, $id, true)
            : [];
        $mobilityRequests = $this->personnelMobilityRequestRepository->tableExists()
            ? $this->personnelMobilityRequestRepository->listForUser($tenantId, $id, 30)
            : [];
        $orgHistory = $this->personnelOrgHistoryRepository->schemaReady()
            ? $this->personnelOrgHistoryRepository->listForUser($tenantId, $id, 30)
            : [];
        $serviceHistory = $this->personnelServiceHistoryRepository->listForUser($id, 40);
        $stageBilans = $this->personnelStageBilanRepository->tableExists()
            ? $this->personnelStageBilanRepository->listForUser($tenantId, $id, 40)
            : [];
        $dutyPosition = '';
        $remainingTrainingDays = 0;
        try {
            $duty = \App\Core\Container::get(\App\Services\Personnel\PersonnelDutyPositionService::class);
            $dutyPosition = $duty->currentDutyLabel($tenantId, $id);
            $remainingTrainingDays = $duty->remainingTrainingDays($tenantId, $id);
        } catch (\Throwable) {
        }
        $commandChain = [
            'reports_to_id' => 0,
            'reports_to_label' => '',
            'chain_labels' => [],
            'commands' => [],
            'unit_name' => '',
        ];
        try {
            $commandChain = (new PersonnelCommandChainService(
                $this->unitRepository,
                $this->userRepository,
                $this->personnelAssignmentRepository
            ))->chainForUser($tenantId, $id);
        } catch (\Throwable) {
        }

        $canEditProfiles = EffectifsLmsAccess::canEditProfiles($gate);
        $memberEditorHtml = '';
        if ($canEditProfiles) {
            $editor = \App\Core\Container::get(\App\Controllers\Web\PersonnelController::class)
                ->embeddedEditor($request, ['id' => $id]);
            if ($editor->statusCode() === 200) {
                $memberEditorHtml = $editor->body();
            }
        }

        return $this->shell('admin.effectifs_workspace.member', [
            'title' => 'Fiche membre',
            'effectifsNav' => 'roster',
            'member' => $row,
            'memberAssignments' => $assignments,
            'memberPersonnelProfile' => $personnelProfile,
            'memberRoleNames' => $roleNames,
            'currentAccessKey' => $currentAccessKey,
            'currentAccessRoleId' => $currentAccessRoleId,
            'accessProfiles' => CommunityAccessProfiles::definitions(),
            'memberJobRoles' => $jobRoles,
            'jobRoleOptions' => $jobRoleOptions,
            'jobRoleMax' => $jobRoleMax,
            'jobRolesAvailable' => $jobRolesAvailable,
            'orgRoles' => $roles,
            'orgUnits' => $units,
            'communityName' => $this->communityNameForTenant($tenantId),
            'elevationCooldownSeconds' => $elevationCooldownSeconds,
            'elevationHistory' => $elevationHistory,
            'latestDeparture' => $latestDeparture,
            'canEditProfiles' => $canEditProfiles,
            'memberEditorHtml' => $memberEditorHtml,
            'canManageStatus' => EffectifsLmsAccess::canManageStatus($gate),
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'canManageGrades' => EffectifsLmsAccess::canManageGrades($gate),
            'canRequestElevation' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients !== [],
            'elevationNoRecipients' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients === [],
            'elevationCatalog' => $this->elevationCatalogForTenant($tenantId),
            'csrfToken' => Csrf::token(),
            'orgFoundingDate' => $this->seniorityPrePlatform->getOrgFoundingDate($tenantId),
            'memberRoleIds' => $roleIds,
            'memberQualifications' => $qualifications,
            'memberAbsences' => $absences,
            'memberHrDocuments' => $hrDocuments,
            'memberMobilityRequests' => $mobilityRequests,
            'memberOrgHistory' => $orgHistory,
            'memberServiceHistory' => $serviceHistory,
            'memberStageBilans' => $stageBilans,
            'dutyPosition' => $dutyPosition,
            'remainingTrainingDays' => $remainingTrainingDays,
            'memberCommandChain' => $commandChain,
            'hrDocumentTypeLabels' => PersonnelHrDocumentRepository::DOC_TYPE_LABELS,
            'mobilityTypeLabels' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'absenceReasonLabels' => PersonnelAbsenceRepository::REASON_LABELS,
            'backOfficePageCss' => ['personnel-dossier.css'],
        ]);
    }

    /** Changement de statut groupé depuis la sélection multiple du tableur (borné à 200 membres). */
    public function bulkStatus(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier le statut des comptes.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $status = trim((string) $request->input('status', ''));
        $allowed = ['active', 'inactive', 'pending_verification'];
        $rawIds = $request->input('user_ids', []);
        $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        $ids = array_slice($ids, 0, 200);

        if (!in_array($status, $allowed, true) || $ids === []) {
            Session::flash('error', 'Sélectionnez au moins un membre et un statut valide.');

            return Response::redirect($this->redirectBackToRoster($request));
        }

        $actorId = (int) Session::get('user_id');
        $updated = 0;
        foreach ($ids as $id) {
            $user = $this->userRepository->findById($id, $tenantId);
            if (!$user) {
                continue;
            }
            $before = (string) ($user['status'] ?? '');
            if ($before === $status) {
                continue;
            }
            $this->userRepository->update($id, $tenantId, ['status' => $status]);
            $this->adminAuditService->logUserUpdated($tenantId, $actorId, $id, 'status:' . $before, 'status:' . $status);
            $updated++;
        }

        $label = match ($status) {
            'active' => 'Compte actif',
            'inactive' => 'Compte inactif',
            'pending_verification' => 'En attente de vérification de l’e-mail',
            default => $status,
        };
        Session::flash(
            'success',
            $updated > 0
                ? ($updated . ' compte' . ($updated > 1 ? 's' : '') . ' mis à jour : ' . $label . '.')
                : 'Aucun changement : les membres sélectionnés avaient déjà ce statut.'
        );

        return Response::redirect($this->redirectBackToRoster($request));
    }

    /** Affectation d’unité groupée depuis la sélection multiple du tableur (bornée à 200 membres). */
    public function bulkAssignment(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les affectations.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $unitId = (int) $request->input('unit_id', 0);
        $rawIds = $request->input('user_ids', []);
        $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        $ids = array_slice($ids, 0, 200);

        if ($ids === []) {
            Session::flash('error', 'Sélectionnez au moins un membre.');

            return Response::redirect($this->redirectBackToRoster($request));
        }

        $unitName = '';
        if ($unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if (!$unit) {
                Session::flash('error', 'Unité introuvable dans cette communauté.');

                return Response::redirect($this->redirectBackToRoster($request));
            }
            $unitName = trim((string) ($unit['name'] ?? ''));
        }

        $actorId = (int) Session::get('user_id');
        $assignmentReason = trim((string) $request->input('reason', ''));
        $updated = 0;
        foreach ($ids as $id) {
            $user = $this->userRepository->findById($id, $tenantId);
            if (!$user) {
                continue;
            }
            try {
                $this->personnelProfileRepository->ensureRecord($id);
                $this->personnelProfileRepository->update($id, [
                    'primary_unit_id' => $unitId > 0 ? $unitId : null,
                ]);
                $roleName = trim((string) ($user['display_name'] ?? ''));
                $profile = $this->personnelProfileRepository->getByUserId($id);
                if ($profile) {
                    $fromProfile = trim((string) ($profile['primary_role'] ?? ''));
                    if ($fromProfile !== '') {
                        $roleName = $fromProfile;
                    }
                }
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                    $id,
                    $unitId > 0 ? $unitId : null,
                    $roleName !== '' ? $roleName : 'Membre',
                    $assignmentReason !== '' ? $assignmentReason : null
                );
                $this->adminAuditService->logUserUpdated(
                    $tenantId,
                    $actorId,
                    $id,
                    'affectation',
                    $unitId > 0 ? ('unit:' . $unitId) : 'unit:none'
                );
                $updated++;
            } catch (\Throwable) {
                continue;
            }
        }

        Session::flash(
            'success',
            $updated > 0
                ? ($updated . ' membre' . ($updated > 1 ? 's' : '') . ' affecté' . ($updated > 1 ? 's' : '')
                    . ($unitName !== '' ? ' à ' . $unitName : ' — affectation retirée') . '.')
                : 'Aucune affectation n’a pu être mise à jour.'
        );

        return Response::redirect($this->redirectBackToRoster($request));
    }

    /** Enregistre un départ (offboarding structuré) — motif, date, et retrait d’accès optionnel. */
    public function recordDeparture(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à enregistrer un départ.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $reason = trim((string) $request->input('reason', 'other'));
        $reasonNote = trim((string) $request->input('reason_note', ''));
        if (mb_strlen($reasonNote) > 500) {
            $reasonNote = mb_substr($reasonNote, 0, 500);
        }
        $departedAt = trim((string) $request->input('departed_at', '')) ?: date('Y-m-d');
        $requestRevoke = $request->input('revoke_access') === '1';
        $revokeAccess = $requestRevoke && EffectifsLmsAccess::canManageRoles($gate);

        $result = $this->memberOffboardingService->recordDeparture(
            $tenantId,
            $id,
            (int) Session::get('user_id'),
            $reason,
            $reasonNote,
            $departedAt,
            $revokeAccess
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(effectifs_workspace_url('membres/' . $id));
    }

    /** Archive administrative du dossier lié à un départ. */
    public function archiveDepartureDossier(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageStatus(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à archiver un dossier.');

            return Response::redirect(effectifs_workspace_url('departs'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('departs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $result = $this->memberOffboardingService->archiveDossier($tenantId, $id, (int) Session::get('user_id'));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(effectifs_workspace_url('departs'));
    }

    /** Réintégration d’un ancien membre (compte réactivé). */
    public function reinstateDeparture(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageStatus(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à réintégrer un membre.');

            return Response::redirect(effectifs_workspace_url('departs'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('departs'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $result = $this->memberOffboardingService->reinstate($tenantId, $id, (int) Session::get('user_id'));
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(effectifs_workspace_url('departs'));
    }

    /** Vue « anciens membres » — historique des départs, filtrable par motif. */
    public function departures(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à consulter les départs.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $reasonFilter = trim((string) $request->query('motif', ''));
        if (!in_array($reasonFilter, MemberDepartureRepository::REASONS, true)) {
            $reasonFilter = null;
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $total = $this->memberDepartureRepository->countForTenant($tenantId, $reasonFilter);
        $departures = $this->memberDepartureRepository->listForTenant(
            $tenantId,
            $reasonFilter,
            $perPage,
            ($page - 1) * $perPage
        );

        return $this->shell('admin.effectifs_workspace.departures', [
            'title' => 'Anciens membres',
            'effectifsNav' => 'departures',
            'departures' => $departures,
            'departureReasonFilter' => $reasonFilter,
            'departureTotal' => $total,
            'departurePage' => $page,
            'departureTotalPages' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    /** Met à jour les fonctions métier directement depuis la fiche Effectifs. */
    public function updateMemberJobRoles(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $redirect = effectifs_workspace_url('membres/' . $id);
        if (!EffectifsLmsAccess::canManageAssignments(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à attribuer les fonctions.');

            return Response::redirect($redirect);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($redirect);
        }

        $tenantId = (int) Session::get('tenant_id');
        $user = $id > 0 ? $this->userRepository->findById($id, $tenantId) : null;
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!$this->personnelJobRoleRepository->tablesExist() || !$this->personnelJobRoleRepository->pivotTableExists()) {
            Session::flash('error', 'La gestion des fonctions est indisponible tant que les migrations ne sont pas appliquées.');

            return Response::redirect($redirect);
        }

        $rawIds = $request->input('job_role_ids', []);
        $ids = is_array($rawIds)
            ? array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn (int $roleId): bool => $roleId > 0)))
            : [];
        $settings = PersonnelJobRoleAssignmentsSettings::resolve($this->tenantRepository->getSettings($tenantId));
        $ids = array_slice($ids, 0, (int) $settings['max_roles_per_member']);
        $primaryId = (int) $request->input('primary_job_role_id', 0);
        if (!in_array($primaryId, $ids, true)) {
            $primaryId = $ids[0] ?? 0;
        }
        $current = $this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, [$id]);
        $details = [];
        foreach ($current[$id] ?? [] as $assigned) {
            $details[(int) ($assigned['personnel_job_role_id'] ?? 0)] = trim((string) ($assigned['role_detail'] ?? ''));
        }
        $slots = array_map(static fn (int $roleId): array => [
            'personnel_job_role_id' => $roleId,
            'role_detail' => $details[$roleId] ?? '',
            'is_primary' => $roleId === $primaryId,
        ], $ids);

        // La persistance des fonctions est l'opération principale. Les synchronisations
        // de confort qui suivent ne doivent pas transformer une sauvegarde réussie en
        // faux message d'échec (la table pivot a déjà validé sa transaction).
        $before = null;
        try {
            $before = $this->structureChangeNotification->snapshot($tenantId, $id);
        } catch (\Throwable $e) {
            error_log('Effectifs functions: pre-save snapshot failed: ' . $e->getMessage());
        }
        try {
            $result = $this->personnelJobRoleRepository->replaceUserPivotJobRoles($tenantId, $id, $slots);
        } catch (\Throwable $e) {
            error_log('Effectifs functions: pivot save failed: ' . $e->getMessage());
            Session::flash('error', 'Impossible d’enregistrer les fonctions. Vérifiez que le référentiel et ses migrations sont à jour.');

            return Response::redirect($redirect);
        }

        $display = (string) $result['primary_role_display'];
        if ($settings['append_secondaries_to_primary_display'] && $result['secondary_role_display'] !== '') {
            $display = $display !== '' ? $display . ' · ' . $result['secondary_role_display'] : $result['secondary_role_display'];
        }
        try {
            $profile = $this->personnelProfileRepository->getByUserId($id) ?? [];
            $unitId = (int) ($profile['primary_unit_id'] ?? 0);
            if ($unitId > 0) {
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier($id, $unitId, mb_substr($display, 0, 100));
            }
        } catch (\Throwable $e) {
            error_log('Effectifs functions: assignment synchronization failed: ' . $e->getMessage());
        }

        if ($before !== null) {
            try {
                $actorId = (int) Session::get('user_id');
                $this->structureChangeNotification->notifyFromSnapshots(
                    $tenantId,
                    $id,
                    $actorId > 0 ? $actorId : null,
                    $before,
                    $this->structureChangeNotification->snapshot($tenantId, $id)
                );
            } catch (\Throwable $e) {
                error_log('Effectifs functions: post-save notification failed: ' . $e->getMessage());
            }
        }

        Session::flash('success', 'Fonctions du membre mises à jour.');

        return Response::redirect($redirect);
    }

    public function quickStatus(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier le statut des comptes.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        $allowed = ['active', 'inactive', 'pending_verification'];
        if ($id < 1 || !in_array($status, $allowed, true)) {
            Session::flash('error', 'Action impossible : statut non reconnu.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $before = (string) ($user['status'] ?? '');
        $this->userRepository->update($id, $tenantId, ['status' => $status]);
        $actorId = (int) Session::get('user_id');
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            $actorId,
            $id,
            'status:' . $before,
            'status:' . $status
        );
        $label = match ($status) {
            'active' => 'Compte actif',
            'inactive' => 'Compte inactif',
            'pending_verification' => 'En attente de vérification de l’e-mail',
            default => $status,
        };
        Session::flash('success', 'Statut mis à jour : ' . $label . '.');

        return Response::redirect(effectifs_workspace_url('membres/' . $id));
    }

    public function activateDutyPosition(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) Session::get('tenant_id');
        if (!EffectifsLmsAccess::canManageStatus(Gate::getInstance()) || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Action refusée ou session expirée.');

            return Response::redirect(effectifs_workspace_url('membres/' . $id));
        }
        $duty = \App\Core\Container::get(\App\Services\Personnel\PersonnelDutyPositionService::class);
        $changed = $duty->applyActiveDuty($tenantId, $id, (int) Session::get('user_id'));
        $remaining = $duty->remainingTrainingDays($tenantId, $id);
        Session::flash(
            $changed ? 'success' : 'warning',
            $changed
                ? 'Le membre est maintenant en service actif. Ses rôles fonctionnels sont inchangés.'
                : ($remaining > 0 ? 'Formation obligatoire : encore ' . $remaining . ' jour(s).' : 'Le membre est déjà en service actif.')
        );

        return Response::redirect(effectifs_workspace_url('membres/' . $id));
    }

    public function updateOrgFounding(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canEditProfiles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier l’ancienneté.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $raw = trim((string) $request->input('org_founded_on', ''));
        $stats = $this->seniorityPrePlatform->syncOrgFoundingForAllActiveMembers(
            $tenantId,
            $raw !== '' ? $raw : null
        );
        if (($stats['invalid_date'] ?? 0) > 0 && ($stats['members'] ?? 0) === 0) {
            Session::flash('error', 'La date de création de l’organisation n’est pas valide.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        if ($raw === '') {
            Session::flash('success', 'Date de création de l’organisation retirée pour les membres actifs.');
        } else {
            Session::flash(
                'success',
                'Date de création de l’organisation enregistrée. Elle s’applique à tous les membres actifs, y compris les nouveaux arrivants.'
            );
        }

        return Response::redirect($this->redirectBackToRoster($request));
    }

    public function updateMemberSeniority(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canEditProfiles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier l’ancienneté.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1 || !$this->userRepository->findById($id, $tenantId)) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $enlistRaw = trim((string) $request->input('enlistment_date', ''));
        $preRaw = trim((string) $request->input('pre_platform_start_date', ''));
        $this->personnelProfileRepository->ensureRecord($id);
        $this->personnelProfileRepository->update($id, [
            'enlistment_date' => $enlistRaw !== '' ? $enlistRaw : null,
        ]);
        try {
            $this->seniorityEnrollment->alignTenureCommunityFromStaffEdit($tenantId, $id);
        } catch (\Throwable) {
        }
        $preResult = $this->seniorityPrePlatform->upsertPersonStartDate(
            $tenantId,
            $id,
            $preRaw !== '' ? $preRaw : null
        );
        if ($preResult === 'invalid_date') {
            Session::flash('error', 'La date d’arrivée avant le site n’est pas valide.');

            return Response::redirect($this->redirectBackToRoster($request));
        }

        $actorId = (int) Session::get('user_id');
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            $actorId,
            $id,
            'anciennete',
            'enlistment:' . ($enlistRaw !== '' ? $enlistRaw : 'vide') . ';pre:' . ($preRaw !== '' ? $preRaw : 'vide')
        );
        Session::flash('success', 'Ancienneté mise à jour.');

        return Response::redirect($this->redirectAfterMemberAction($request, $id));
    }

    public function updateMemberRoles(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les rôles.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectAfterMemberAction($request, (int) ($params['id'] ?? 0)));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1 || !$this->userRepository->findById($id, $tenantId)) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $rawRoleId = (int) $request->input('access_role_id', 0);
        $targetId = 0;
        if ($rawRoleId > 0) {
            $candidate = $this->roleRepository->findById($rawRoleId, $tenantId);
            if ($candidate && $this->roleRepository->canAssignInTenantAdminContext($rawRoleId, $tenantId)) {
                $targetId = $rawRoleId;
            }
        }
        if ($targetId < 1) {
            $rawKey = strtolower(trim((string) $request->input('access_key', '')));
            if (!in_array($rawKey, CommunityAccessProfiles::keys(), true)) {
                Session::flash('error', 'Choisissez un niveau d’accès.');

                return Response::redirect($this->redirectAfterMemberAction($request, $id));
            }
            $targetSlug = CommunityAccessProfiles::keyToSlug($rawKey);
            $targetId = (int) ($this->roleRepository->getIdBySlug($tenantId, $targetSlug) ?? 0);
        }
        if ($targetId < 1) {
            Session::flash('error', 'Ce niveau d’accès n’est pas encore disponible pour la communauté.');

            return Response::redirect($this->redirectAfterMemberAction($request, $id));
        }
        $roleIds = [$targetId];
        $oldRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        foreach ($roleIds as $rid) {
            if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                Session::flash('error', 'Un rôle sélectionné ne peut pas être attribué depuis Effectifs.');

                return Response::redirect($this->redirectAfterMemberAction($request, $id));
            }
        }
        $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
        if ($ownerRoleId !== null) {
            $hadOwner = in_array($ownerRoleId, $oldRoleIds, true);
            $hasOwnerNew = in_array($ownerRoleId, $roleIds, true);
            if ($hadOwner && !$hasOwnerNew) {
                $count = $this->userRepository->countUsersWithRole($ownerRoleId);
                if ($count <= 1) {
                    Session::flash('error', 'Impossible de retirer le rôle de responsable de communauté au dernier titulaire.');

                    return Response::redirect($this->redirectAfterMemberAction($request, $id));
                }
            }
        }
        try {
            $this->userRepository->syncOrganizationRoles($id, $tenantId, $roleIds, $actorId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($this->redirectAfterMemberAction($request, $id));
        }
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            $actorId,
            $id,
            'roles',
            implode(',', $roleIds)
        );
        Session::flash('success', 'Niveau d’accès mis à jour.');

        return Response::redirect($this->redirectAfterMemberAction($request, $id));
    }

    public function updateMemberGrade(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageGrades(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier le grade.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectAfterMemberAction($request, (int) ($params['id'] ?? 0)));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        $user = $id > 0 ? $this->userRepository->findById($id, $tenantId) : null;
        if ($user === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $raw = trim((string) $request->input('grade_id', ''));
        $gradeId = $raw !== '' ? (int) $raw : 0;
        $newGrade = $gradeId > 0 ? $gradeId : null;
        if ($newGrade !== null) {
            $found = false;
            foreach ($this->gradeRepository->listForTenant($tenantId) as $g) {
                if ((int) ($g['id'] ?? 0) === $newGrade) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                Session::flash('error', 'Ce grade n’appartient pas à la communauté.');

                return Response::redirect($this->redirectAfterMemberAction($request, $id));
            }
        }
        $before = isset($user['grade_id']) && $user['grade_id'] !== '' && $user['grade_id'] !== null
            ? (int) $user['grade_id']
            : null;
        if ($before === $newGrade) {
            Session::flash('success', 'Grade inchangé.');

            return Response::redirect($this->redirectAfterMemberAction($request, $id));
        }
        $this->userRepository->update($id, $tenantId, ['grade_id' => $newGrade]);
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            $actorId,
            $id,
            'grade',
            (string) ($newGrade ?? 'aucun')
        );
        Session::flash('success', 'Grade mis à jour.');

        return Response::redirect($this->redirectAfterMemberAction($request, $id));
    }

    public function quickAssignment(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les affectations.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $unitId = (int) $request->input('unit_id', 0);
        $assignmentReason = trim((string) $request->input('reason', ''));
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $unitName = '';
        if ($unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if (!$unit) {
                Session::flash('error', 'Unité introuvable dans cette communauté.');

                return Response::redirect($this->redirectBackToRoster($request));
            }
            $unitName = trim((string) ($unit['name'] ?? ''));
        }

        try {
            $beforeSnap = $this->structureChangeNotification->snapshot($tenantId, $id);
            $this->personnelProfileRepository->ensureRecord($id);
            $this->personnelProfileRepository->update($id, [
                'primary_unit_id' => $unitId > 0 ? $unitId : null,
            ]);
            $roleName = trim((string) ($user['display_name'] ?? ''));
            $profile = $this->personnelProfileRepository->getByUserId($id);
            if ($profile) {
                $fromProfile = trim((string) ($profile['primary_role'] ?? ''));
                if ($fromProfile !== '') {
                    $roleName = $fromProfile;
                }
            }
            $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                $id,
                $unitId > 0 ? $unitId : null,
                $roleName !== '' ? $roleName : 'Membre',
                $assignmentReason !== '' ? $assignmentReason : null
            );
            $actorId = (int) Session::get('user_id');
            $this->adminAuditService->logUserUpdated(
                $tenantId,
                $actorId,
                $id,
                'affectation',
                $unitId > 0 ? ('unit:' . $unitId) : 'unit:none'
            );
            try {
                $this->structureChangeNotification->notifyFromSnapshots(
                    $tenantId,
                    $id,
                    $actorId > 0 ? $actorId : null,
                    $beforeSnap,
                    $this->structureChangeNotification->snapshot($tenantId, $id)
                );
            } catch (\Throwable) {
            }
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’enregistrer l’affectation. Réessayez ou ouvrez le dossier personnel.');

            return Response::redirect($this->redirectBackToRoster($request));
        }

        Session::flash(
            'success',
            $unitId > 0
                ? ('Affectation enregistrée' . ($unitName !== '' ? ' : ' . $unitName : '') . '.')
                : 'Affectation retirée.'
        );

        $returnMember = $request->input('return_to') === 'member';

        return Response::redirect(
            $returnMember
                ? effectifs_workspace_url('membres/' . $id)
                : $this->redirectBackToRoster($request)
        );
    }

    public function requestElevation(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canRequestElevation($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à demander une élévation.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $kind = trim((string) $request->input('elevation_kind', 'general'));
        $note = trim((string) $request->input('elevation_note', ''));
        $proposal = $this->readElevationProposalFromRequest($request);
        if ($id < 1) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $validated = $this->validateElevationProposal($tenantId, $proposal);
        if ($validated['error'] !== null) {
            Session::flash('error', $validated['error']);

            return Response::redirect(
                $request->input('return_to') === 'member'
                    ? effectifs_workspace_url('membres/' . $id)
                    : $this->redirectBackToRoster($request)
            );
        }

        $result = $this->effectifsStaffAlertService->requestElevation(
            $tenantId,
            (int) Session::get('user_id'),
            $user,
            $kind,
            $note,
            $validated['proposal']
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        $returnMember = $request->input('return_to') === 'member';

        return Response::redirect(
            $returnMember
                ? effectifs_workspace_url('membres/' . $id)
                : $this->redirectBackToRoster($request)
        );
    }

    public function roles(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId);
        $gate = Gate::getInstance();
        $selectedId = (int) $request->query('role', 0);
        $selected = null;
        foreach ($roles as $row) {
            if ((int) ($row['id'] ?? 0) === $selectedId) {
                $selected = $row;
                break;
            }
        }
        if ($selected === null && $roles !== []) {
            $selected = $roles[0];
            $selectedId = (int) ($selected['id'] ?? 0);
        }
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        $checkedIds = $selectedId > 0 ? $permSvc->getPermissionIdsForRole($selectedId) : [];
        $groups = $this->accessPermissionGroups($tenantId);
        $counts = $this->roleRepository->countPermissionsByRoleIds(array_map(
            static fn (array $r): int => (int) ($r['id'] ?? 0),
            $roles
        ));
        $memberCounts = $this->roleRepository->countMembersByRoleIds($tenantId, array_keys($counts));

        return $this->shell('admin.effectifs_workspace.roles', [
            'title' => 'Accès',
            'effectifsNav' => 'roles',
            'orgRoles' => $roles,
            'selectedAccessRole' => $selected,
            'accessPermissionGroups' => $groups,
            'checkedPermissionIds' => $checkedIds,
            'accessPermissionCounts' => $counts,
            'accessMemberCounts' => $memberCounts,
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'rosterCounts' => $this->rosterCounts($tenantId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function createAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à créer un niveau d’accès.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $name = trim((string) $request->input('name', ''));
        $copyId = (int) $request->input('copy_from', 0);
        $newId = $this->roleRepository->createCustomAccessRole($tenantId, $name, trim((string) $request->input('description', '')));
        if ($newId < 1) {
            Session::flash('error', 'Indiquez un nom pour le nouveau niveau d’accès.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $sourceId = $copyId > 0 ? $copyId : (int) ($this->roleRepository->getIdBySlug($tenantId, CommunityAccessProfiles::SLUG_MEMBER) ?? 0);
        if ($sourceId > 0) {
            $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
            try {
                $permSvc->setPermissionsForAccessRole($tenantId, $newId, $permSvc->getPermissionIdsForRole($sourceId));
            } catch (\InvalidArgumentException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $newId);
            }
        }
        Session::flash('success', 'Niveau d’accès créé. Cochez ensuite ce qu’il a le droit de faire.');

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $newId);
    }

    public function saveAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les accès.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        if (!$this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            Session::flash('error', 'Ce niveau d’accès ne peut pas être modifié.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $this->roleRepository->updateAccessRoleLabel(
            $tenantId,
            $roleId,
            (string) $request->input('name', ''),
            (string) $request->input('description', '')
        );
        $rawIds = $request->input('permission_ids', []);
        $permissionIds = is_array($rawIds) ? array_map('intval', $rawIds) : [];
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        try {
            $permSvc->setPermissionsForAccessRole($tenantId, $roleId, $permissionIds);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        Session::flash('success', 'Niveau d’accès enregistré.');

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
    }

    public function restoreAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à rétablir un modèle.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        $role = $this->roleRepository->findById($roleId, $tenantId);
        if (!$role || !CommunityAccessProfiles::isAccessSlug((string) ($role['slug'] ?? ''))) {
            Session::flash('error', 'Seul un modèle de départ peut être rétabli.');

            return Response::redirect(effectifs_workspace_url('roles') . ($roleId > 0 ? '?role=' . $roleId : ''));
        }
        $key = CommunityAccessProfiles::slugToKey((string) $role['slug']);
        $def = CommunityAccessProfiles::definitionByKey($key);
        if ($def !== null) {
            $this->roleRepository->updateAccessRoleLabel($tenantId, $roleId, (string) $def['name'], (string) $def['description']);
        }
        $wanted = CommunityAccessProfiles::permissionSlugsFor($key);
        $ids = [];
        foreach ($this->permissionRepository->allForTenant($tenantId) as $row) {
            if (in_array((string) ($row['slug'] ?? ''), $wanted, true)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        $permSvc->setPermissionsForAccessRole($tenantId, $roleId, $ids);
        Session::flash('success', 'Le modèle de départ a été rétabli.');

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
    }

    public function deleteAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à supprimer un niveau d’accès.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        $role = $this->roleRepository->findById($roleId, $tenantId);
        if (!$role || !CommunityAccessProfiles::isCustomAccessSlug((string) ($role['slug'] ?? ''))) {
            Session::flash('error', 'Les trois modèles de départ ne peuvent pas être supprimés.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $memberId = (int) ($this->roleRepository->getIdBySlug($tenantId, CommunityAccessProfiles::SLUG_MEMBER) ?? 0);
        $actorId = (int) Session::get('user_id');
        if ($memberId > 0) {
            foreach ($this->roleRepository->userIdsWithRole($tenantId, $roleId) as $uid) {
                try {
                    $this->userRepository->syncOrganizationRoles($uid, $tenantId, [$memberId], $actorId);
                } catch (\Throwable) {
                }
            }
        }
        $this->roleRepository->deleteCustomAccessRole($tenantId, $roleId);
        Session::flash('success', 'Niveau d’accès retiré. Les personnes concernées sont revenues au niveau Membre.');

        return Response::redirect(effectifs_workspace_url('roles'));
    }

    public function droits(Request $request, array $params = []): Response
    {
        unset($request, $params);

        return Response::redirect(effectifs_workspace_url('roles'));
    }

    public function fonctions(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();
        $canManage = EffectifsLmsAccess::canManageAssignments($gate) || EffectifsLmsAccess::canManageRoles($gate);
        $vue = strtolower(trim((string) $request->query('vue', '')));
        if ($vue === 'attributions') {
            return $this->shell('admin.effectifs_workspace.fonctions', array_merge(
                $this->jobAssignmentPageData($request, $tenantId),
                [
                    'title' => 'Emplois',
                    'effectifsNav' => 'fonctions',
                    'jobsVue' => 'attributions',
                    'canManageAssignments' => $canManage,
                    'rosterCounts' => $this->rosterCounts($tenantId),
                    'csrfToken' => Csrf::token(),
                    'jobsWorkspaceEmbed' => true,
                ]
            ));
        }

        $jobRoles = [];
        $categories = [];
        if ($this->personnelJobRoleRepository->tablesExist()) {
            $jobRoles = $this->personnelJobRoleRepository->listRolesWithCategory($tenantId);
            $categories = $this->personnelJobRoleRepository->listCategories($tenantId);
            try {
                $kitSvc = \App\Core\Container::get(\App\Services\Personnel\PersonnelFunctionKitService::class);
                $jobRoles = $kitSvc->filterRolesWithCategory($tenantId, $jobRoles);
            } catch (\Throwable) {
            }
        }
        $creating = $request->query('nouveau') === '1';
        $selectedId = (int) $request->query('emploi', 0);
        $selected = null;
        foreach ($jobRoles as $row) {
            if ((int) ($row['id'] ?? 0) === $selectedId) {
                $selected = $row;
                break;
            }
        }
        if ($selected === null && $jobRoles !== []) {
            $selected = $jobRoles[0];
            $selectedId = (int) ($selected['id'] ?? 0);
        }
        $holdersByRole = [];
        if ($this->personnelJobRoleRepository->pivotTableExists() && $jobRoles !== []) {
            $holdersByRole = $this->personnelJobRoleRepository->listHoldersByJobRoleIds(
                $tenantId,
                array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $jobRoles)
            );
        }

        return $this->shell('admin.effectifs_workspace.fonctions', [
            'title' => 'Emplois',
            'effectifsNav' => 'fonctions',
            'jobsVue' => 'catalogue',
            'jobRoles' => $jobRoles,
            'jobCategories' => $categories,
            'selectedJobRole' => $selected,
            'creatingJobRole' => $creating,
            'jobHoldersByRole' => $holdersByRole,
            'canManageAssignments' => $canManage,
            'rosterCounts' => $this->rosterCounts($tenantId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function createJobRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à créer un emploi.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->personnelJobRoleRepository->tablesExist()) {
            Session::flash('error', 'Les emplois du dossier ne sont pas encore disponibles pour cette communauté.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez le nom de l’emploi.');

            return Response::redirect(effectifs_workspace_url('fonctions') . '?nouveau=1');
        }
        $categoryId = $this->resolveJobCategoryId($tenantId, (int) $request->input('category_id', 0));
        if ($categoryId < 1) {
            Session::flash('error', 'Choisissez une catégorie, ou créez-en une d’abord.');

            return Response::redirect(effectifs_workspace_url('fonctions') . '?nouveau=1');
        }
        $slug = $this->personnelJobRoleRepository->uniqueSlugFromName($tenantId, $name);
        $newId = $this->personnelJobRoleRepository->createRole(
            $tenantId,
            $categoryId,
            $name,
            $slug,
            trim((string) $request->input('description', '')) ?: null,
            0,
            false
        );
        Session::flash('success', 'Emploi créé. Vous pouvez préciser sa description, puis l’attribuer aux membres.');

        return Response::redirect(effectifs_workspace_url('fonctions') . '?emploi=' . $newId);
    }

    public function saveJobRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier un emploi.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id', 0);
        $existing = $id > 0 ? $this->personnelJobRoleRepository->findRoleById($id, $tenantId) : null;
        if ($id < 1 || !$existing) {
            Session::flash('error', 'Emploi introuvable.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez le nom de l’emploi.');

            return Response::redirect(effectifs_workspace_url('fonctions') . '?emploi=' . $id);
        }
        $categoryId = $this->resolveJobCategoryId($tenantId, (int) $request->input('category_id', 0));
        if ($categoryId < 1) {
            Session::flash('error', 'Choisissez une catégorie.');

            return Response::redirect(effectifs_workspace_url('fonctions') . '?emploi=' . $id);
        }
        $slug = trim((string) ($existing['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->personnelJobRoleRepository->uniqueSlugFromName($tenantId, $name, $id);
        }
        $mosCode = isset($existing['mos_code']) ? (is_string($existing['mos_code']) ? trim($existing['mos_code']) : null) : null;
        $mosCode = $mosCode !== '' ? $mosCode : null;
        $mosTitle = isset($existing['mos_specialty_title']) ? (is_string($existing['mos_specialty_title']) ? trim($existing['mos_specialty_title']) : null) : null;
        $mosTitle = $mosTitle !== '' ? $mosTitle : null;
        $this->personnelJobRoleRepository->updateRole(
            $id,
            $tenantId,
            $categoryId,
            $name,
            $slug,
            trim((string) $request->input('description', '')) ?: null,
            (int) ($existing['sort_order'] ?? 0),
            $mosCode,
            $mosTitle
        );
        Session::flash('success', 'Emploi enregistré.');

        return Response::redirect(effectifs_workspace_url('fonctions') . '?emploi=' . $id);
    }

    public function deleteJobRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à retirer un emploi.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('id', 0);
        if ($id > 0 && $this->personnelJobRoleRepository->deleteRole($id, $tenantId)) {
            Session::flash('success', 'Emploi retiré. Les dossiers concernés n’affichent plus ce libellé.');
        } else {
            Session::flash('error', 'Impossible de retirer cet emploi (modèle officiel ou introuvable).');
        }

        return Response::redirect(effectifs_workspace_url('fonctions'));
    }

    public function saveJobCategory(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à créer une catégorie.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url('fonctions'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $name = trim((string) $request->input('category_name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez le nom de la catégorie.');

            return Response::redirect(effectifs_workspace_url('fonctions') . '?nouveau=1');
        }
        $parentRaw = $request->input('parent_id');
        $parentId = ($parentRaw === null || $parentRaw === '') ? null : (int) $parentRaw;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }
        $slug = $this->personnelJobRoleRepository->uniqueCategorySlugFromName($tenantId, $name);
        $this->personnelJobRoleRepository->createCategory($tenantId, $parentId, $name, $slug, 0);
        Session::flash('success', 'Catégorie créée.');

        return Response::redirect(effectifs_workspace_url('fonctions') . '?nouveau=1');
    }

    public function affectations(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $units = $this->unitRepository->allForTenant($tenantId);
        $withoutUnit = $this->userRepository->countListForTenant($tenantId, null, 'active', null, true, true, null);
        $gate = Gate::getInstance();

        return $this->shell('admin.effectifs_workspace.affectations', [
            'title' => 'Affectations',
            'effectifsNav' => 'affectations',
            'units' => $units,
            'membersWithoutUnit' => $withoutUnit,
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function commandChain(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();
        $data = (new PersonnelCommandChainService(
            $this->unitRepository,
            $this->userRepository,
            $this->personnelAssignmentRepository
        ))->workspace($tenantId);

        return $this->shell('admin.effectifs_workspace.chaine', [
            'title' => 'Chaîne de commandement',
            'effectifsNav' => 'chaine',
            'chainUnits' => $data['units'],
            'chainMembers' => $data['members'],
            'chainMemberOptions' => $data['memberOptions'],
            'chainMissingCommanders' => $data['missing_commanders'],
            'chainMissingSuperiors' => $data['missing_superiors'],
            'chainWithoutUnit' => $data['without_unit'],
            'commandChainMissingCount' => $data['missing_commanders'],
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'csrfToken' => Csrf::token(),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function saveCommandChain(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page, puis enregistrez de nouveau.');

            return Response::redirect(effectifs_workspace_url('chaine'));
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à désigner les chefs d’unité.');

            return Response::redirect(effectifs_workspace_url('chaine'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $raw = $request->input('commanders', []);
        $commanders = is_array($raw) ? $raw : [];
        $allowed = $this->userRepository->listActiveUserIdsForTenant($tenantId);
        $result = (new PersonnelCommandChainService(
            $this->unitRepository,
            $this->userRepository,
            $this->personnelAssignmentRepository
        ))->saveCommanders($tenantId, $commanders, $allowed);
        if ($result['ok']) {
            try {
                $this->tenantRepository->updateSettings($tenantId, [
                    'personnel_command_chain' => ['reviewed' => true],
                ]);
                \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                    ->markCompleted($tenantId, 'PERSONNEL_COMMAND_CHAIN_V1', (int) Session::get('user_id'));
            } catch (\Throwable) {
            }
        }
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(effectifs_workspace_url('chaine'));
    }

    public function elevationRequests(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageRoles($gate) && !EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à traiter les demandes d’élévation.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $showAll = $request->query('all') === '1';
        $elevationPerPage = 50;
        $elevationPage = max(1, (int) $request->query('page', 1));
        $elevationTotal = 0;
        if ($showAll) {
            $elevationTotal = $this->elevationRequestRepository->countRecentForTenant($tenantId);
            $requests = $this->elevationRequestRepository->listRecentForTenant(
                $tenantId,
                $elevationPerPage,
                ($elevationPage - 1) * $elevationPerPage
            );
        } else {
            $requests = $this->elevationRequestRepository->listOpenForTenant($tenantId, 300);
            $elevationTotal = count($requests);
        }

        $catalog = $this->elevationCatalogForTenant($tenantId);
        $roleMatrix = $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
        $labelMaps = $this->elevationApprovalService->buildLabelMapsFromCatalog($catalog);
        $targetRoleIds = [];
        foreach ($requests as $r) {
            $tid = (int) ($r['target_user_id'] ?? 0);
            if ($tid > 0 && !isset($targetRoleIds[$tid])) {
                $targetRoleIds[$tid] = $this->elevationApprovalService->currentOrganizationRoleIds($tid);
            }
        }

        $enrichedRequests = [];
        foreach ($requests as $r) {
            $targetId = (int) ($r['target_user_id'] ?? 0);
            $currentRoles = $targetRoleIds[$targetId] ?? [];
            $proposedRoleId = (int) ($r['proposed_role_id'] ?? 0) ?: null;
            $diff = $this->elevationApprovalService->permissionDiffForRoleChange(
                $tenantId,
                $currentRoles,
                $proposedRoleId,
                ElevationApprovalService::ROLE_APPLY_REPLACE,
                $roleMatrix
            );
            $proposalLabels = $this->elevationApprovalService->proposalLabelsFromMaps($labelMaps, [
                'grade_id' => (int) ($r['proposed_grade_id'] ?? 0) ?: null,
                'role_id' => $proposedRoleId,
                'job_role_id' => (int) ($r['proposed_job_role_id'] ?? 0) ?: null,
                'unit_id' => (int) ($r['proposed_unit_id'] ?? 0) ?: null,
            ]);
            $permissionIds = json_decode((string) ($r['proposed_permission_ids'] ?? ''), true);
            $permissionIds = is_array($permissionIds)
                ? array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn (int $id): bool => $id > 0)))
                : [];
            $r['_current_role_ids'] = $currentRoles;
            $r['_permission_ids'] = $permissionIds;
            $r['_permission_diff'] = $diff;
            $r['_proposal_labels'] = $proposalLabels;
            $enrichedRequests[] = $r;
        }

        return $this->shell('admin.effectifs_workspace.elevation_requests', [
            'title' => 'Demandes d’élévation',
            'effectifsNav' => 'elevations',
            'elevationRequests' => $enrichedRequests,
            'elevationShowAll' => $showAll,
            'elevationPage' => $elevationPage,
            'elevationPerPage' => $elevationPerPage,
            'elevationTotal' => $elevationTotal,
            'elevationTotalPages' => $showAll ? max(1, (int) ceil($elevationTotal / $elevationPerPage)) : 1,
            'elevationKindLabels' => EffectifsStaffAlertService::ELEVATION_KIND_LABELS,
            'elevationCatalog' => $catalog,
            'elevationRoleMatrix' => $roleMatrix,
            'organizationRoleLabelMode' => OrganizationRoleLabels::MODE_FR,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function updateElevationRequestStatus(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageRoles($gate) && !EffectifsLmsAccess::canManageStatus($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à traiter les demandes d’élévation.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        $note = trim((string) $request->input('resolution_note', ''));
        if (mb_strlen($note) > 500) {
            $note = mb_substr($note, 0, 500);
        }
        $existing = $id > 0 ? $this->elevationRequestRepository->findByIdForTenant($id, $tenantId) : null;
        if (!$existing) {
            Session::flash('error', 'Demande introuvable.');

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }
        if (!in_array($status, ElevationRequestRepository::STATUSES, true)) {
            Session::flash('error', 'Statut non reconnu.');

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }
        $currentStatus = (string) ($existing['status'] ?? 'pending');
        if (in_array($currentStatus, ['approved', 'rejected'], true)) {
            Session::flash('error', 'Cette demande a déjà été traitée (' . ($currentStatus === 'approved' ? 'acceptée' : 'refusée') . ') — action impossible.');

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }

        $proposal = $this->readElevationProposalFromRequest($request);
        $validated = $this->validateElevationProposal($tenantId, $proposal);
        if ($validated['error'] !== null) {
            Session::flash('error', $validated['error']);

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }
        $proposal = $validated['proposal'];

        if ($status === 'approved') {
            if ($request->input('confirm_apply') !== '1') {
                Session::flash('error', 'Confirmez l’application des changements avant d’accepter la demande.');

                return Response::redirect(url('back-office/ressources/effectifs/elevations'));
            }
            $apply = $this->elevationApprovalService->applyApprovedChanges(
                $tenantId,
                (int) ($existing['target_user_id'] ?? 0),
                $proposal,
                (int) Session::get('user_id')
            );
            if (!$apply['ok']) {
                Session::flash('error', $apply['message']);

                return Response::redirect(url('back-office/ressources/effectifs/elevations'));
            }
            try {
                $this->elevationRequestRepository->saveProposalChoices($id, $tenantId, $proposal);
            } catch (\Throwable) {
            }
            $ok = $this->elevationRequestRepository->updateStatus(
                $id,
                $tenantId,
                $status,
                (int) Session::get('user_id'),
                $note
            );
            Session::flash(
                $ok ? 'success' : 'error',
                $ok ? $apply['message'] : 'Statut invalide ou mise à jour impossible.'
            );
            if ($ok) {
                $targetId = (int) ($existing['target_user_id'] ?? 0);
                if ($targetId > 0) {
                    (new \App\Support\PersonnelHrPdfService())->maybeAutoIssue(
                        $tenantId,
                        $targetId,
                        'elevation',
                        (int) Session::get('user_id'),
                        [
                            'title' => 'Décision d’élévation',
                            'detail' => (string) ($apply['message'] ?? ''),
                        ]
                    );
                }
            }

            return Response::redirect(url('back-office/ressources/effectifs/elevations'));
        }

        if (in_array($status, ['pending', 'in_review'], true)) {
            try {
                $this->elevationRequestRepository->saveProposalChoices($id, $tenantId, $proposal);
            } catch (\Throwable) {
            }
        }

        $ok = $this->elevationRequestRepository->updateStatus(
            $id,
            $tenantId,
            $status,
            (int) Session::get('user_id'),
            $note
        );
        Session::flash(
            $ok ? 'success' : 'error',
            $ok ? 'Le statut de la demande a été mis à jour.' : 'Statut invalide ou mise à jour impossible.'
        );

        return Response::redirect(url('back-office/ressources/effectifs/elevations'));
    }

    private function denyUnlessAccess(): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            Session::flash('error', 'Accès réservé au pilotage des effectifs.');

            return Response::redirect(url('dashboard'));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function shell(string $content, array $extra): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $counts = $extra['rosterCounts'] ?? $this->rosterCounts($tenantId);
        $elevationOpen = 0;
        try {
            $elevationOpen = $this->elevationRequestRepository->countOpenForTenant($tenantId);
        } catch (\Throwable) {
            $elevationOpen = 0;
        }

        // Badge « Qualifications » du rail : visible depuis toutes les pages de l'espace,
        // pas seulement depuis la page qualifications elle-même.
        $qualifExpiring = $extra['qualificationsExpiringCount'] ?? null;
        if ($qualifExpiring === null) {
            try {
                $qualifExpiring = count($this->personnelQualificationRepository->listExpiringForTenant($tenantId, 60, 300));
            } catch (\Throwable) {
                $qualifExpiring = 0;
            }
        }

        $dupScan = $extra['personnelDuplicateScan'] ?? null;
        if ($dupScan === null) {
            try {
                $dupScan = $this->duplicateDetection->scan($tenantId);
            } catch (\Throwable) {
                $dupScan = ['enabled' => false, 'fields' => [], 'groups' => [], 'group_count' => 0, 'member_count' => 0];
            }
        }

        $mobilityPending = $extra['mobilityPendingCount'] ?? null;
        if ($mobilityPending === null) {
            try {
                $mobilityPending = (new \App\Repositories\PersonnelMobilityRequestRepository())->countPending($tenantId);
            } catch (\Throwable) {
                $mobilityPending = 0;
            }
        }
        $rhAlertTotal = $extra['rhAlertTotalCount'] ?? null;
        if ($rhAlertTotal === null) {
            try {
                $hr = \App\Services\Effectifs\PersonnelHrWorkspaceSettings::forTenant($tenantId);
                $rhAlertTotal = (int) ((new \App\Services\Effectifs\RhAlertAggregatorService())->summarize(
                    $tenantId,
                    (int) ($hr['inactivity_days'] ?? null),
                    (int) ($hr['absence_days'] ?? null)
                )['total'] ?? 0);
            } catch (\Throwable) {
                $rhAlertTotal = 0;
            }
        }
        $chainMissing = $extra['commandChainMissingCount'] ?? null;
        if ($chainMissing === null) {
            try {
                $chainMissing = $this->unitRepository->countWithoutCommander($tenantId);
            } catch (\Throwable) {
                $chainMissing = 0;
            }
        }
        $extras = \App\Support\EffectifsWorkspaceShellExtras::counts($tenantId);

        return Response::view('layout.main', array_merge([
            'content' => 'admin.effectifs_workspace.shell',
            'effectifsContent' => $content,
            'isBackOfficeShell' => true,
            'boSkipPageHead' => true,
            // The member page embeds the personnel editor. Its response only contains
            // the view body, so its component stylesheet must be loaded by this shell.
            'backOfficePageCss' => ['effectifs_lms.css', 'personnel-dossier.css', 'back-office-effectifs-workspace.css'],
            'showPortalFooter' => false,
            'rosterCounts' => $counts,
            'elevationOpenCount' => $elevationOpen,
            'qualificationsExpiringCount' => $qualifExpiring,
            'personnelDuplicateScan' => $dupScan,
            'mobilityPendingCount' => $mobilityPending,
            'rhAlertTotalCount' => $rhAlertTotal,
            'commandChainMissingCount' => $chainMissing,
            'roleplayDueCount' => $extras['roleplayDueCount'],
            'integrationOpenCount' => $extras['integrationOpenCount'],
            'viewerName' => (string) (Session::get('display_name') ?? Session::get('email') ?? ''),
        ], $extra));
    }

    /**
     * @return array{total: int, active: int, inactive: int, pending: int, no_unit: int, no_role: int}
     */
    private function rosterCounts(int $tenantId): array
    {
        return [
            'total' => $this->userRepository->countListForTenant($tenantId, null, null, null, true),
            'active' => $this->userRepository->countListForTenant($tenantId, null, 'active', null, true),
            'inactive' => $this->userRepository->countListForTenant($tenantId, null, 'inactive', null, true),
            'pending' => $this->userRepository->countListForTenant($tenantId, null, 'pending_verification', null, true),
            'no_unit' => $this->userRepository->countListForTenant($tenantId, null, null, null, true, true, null),
            'no_role' => $this->userRepository->countListForTenant($tenantId, null, null, null, true, null, true),
        ];
    }

    private function communityNameForTenant(int $tenantId): string
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return 'Communauté';
        }
        $name = function_exists('community_display_name')
            ? community_display_name($tenant)
            : (string) ($tenant['name'] ?? '');

        return $name !== '' ? $name : 'Communauté';
    }

    /**
     * Catalogues pour formulaires de demande / traitement d’élévation.
     *
     * @return array{
     *   grades: list<array<string,mixed>>,
     *   roles: list<array<string,mixed>>,
     *   job_roles: list<array{id:int,label:string}>,
     *   units: list<array<string,mixed>>
     * }
     */
    private function elevationCatalogForTenant(int $tenantId): array
    {
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId);
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
            'permissions' => $this->permissionRepository->allRequestableForTenant($tenantId),
        ];
    }

    /**
     * @return array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,role_apply_mode:string}
     */
    private function readElevationProposalFromRequest(Request $request): array
    {
        $intOrNull = static function (mixed $raw): ?int {
            if ($raw === null || $raw === '') {
                return null;
            }
            $id = (int) $raw;

            return $id > 0 ? $id : null;
        };
        $permissionIds = $request->input('proposed_permission_ids', $request->input('elevation_permission_ids', []));
        $permissionIds = is_array($permissionIds) ? $permissionIds : [$permissionIds];

        return [
            'grade_id' => $intOrNull($request->input('proposed_grade_id', $request->input('elevation_grade_id'))),
            'role_id' => $intOrNull($request->input('proposed_role_id', $request->input('elevation_role_id'))),
            'job_role_id' => $intOrNull($request->input('proposed_job_role_id', $request->input('elevation_job_role_id'))),
            'unit_id' => $intOrNull($request->input('proposed_unit_id', $request->input('elevation_unit_id'))),
            'permission_ids' => array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn (int $id): bool => $id > 0))),
            'role_apply_mode' => ElevationApprovalService::normalizeRoleApplyMode(
                (string) $request->input('role_apply_mode', ElevationApprovalService::ROLE_APPLY_REPLACE)
            ),
        ];
    }

    /**
     * @param array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,role_apply_mode?:string} $proposal
     * @return array{proposal: array{grade_id:?int,role_id:?int,job_role_id:?int,unit_id:?int,role_apply_mode:string}, error:?string}
     */
    private function validateElevationProposal(int $tenantId, array $proposal): array
    {
        $proposal['permission_ids'] = is_array($proposal['permission_ids'] ?? null) ? $proposal['permission_ids'] : [];
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

        $requestedPermissionIds = array_values(array_unique(array_filter(array_map('intval', $proposal['permission_ids']), static fn (int $id): bool => $id > 0)));
        $allowedPermissionIds = array_map(static fn (array $permission): int => (int) ($permission['id'] ?? 0), $this->permissionRepository->allRequestableForTenant($tenantId));
        foreach ($requestedPermissionIds as $permissionId) {
            if (!in_array($permissionId, $allowedPermissionIds, true)) {
                return ['proposal' => $proposal, 'error' => 'Un des droits d’accès sélectionnés n’est pas disponible pour cette communauté.'];
            }
        }
        $proposal['permission_ids'] = $requestedPermissionIds;

        return ['proposal' => $proposal, 'error' => null];
    }

    private function redirectBackToRoster(Request $request): string
    {
        return $this->redirectAfterMemberAction($request, 0);
    }

    private function redirectAfterMemberAction(Request $request, int $memberId): string
    {
        $returnUrl = trim((string) $request->input('return_url', ''));
        if ($this->isAllowedMemberReturnUrl($returnUrl, $memberId)) {
            return $returnUrl;
        }
        if ($memberId > 0 && trim((string) $request->input('return_to', '')) === 'member') {
            return effectifs_workspace_url('membres/' . $memberId);
        }

        return effectifs_workspace_url();
    }

    private function isAllowedMemberReturnUrl(string $returnUrl, int $memberId): bool
    {
        if ($returnUrl === '') {
            return false;
        }
        $allowed = [effectifs_workspace_url()];
        if ($memberId > 0) {
            $allowed[] = url('back-office/users/' . $memberId);
            $allowed[] = url('personnel/' . $memberId);
            $allowed[] = effectifs_workspace_url('membres/' . $memberId);
        }
        foreach ($allowed as $prefix) {
            if ($prefix !== '' && str_starts_with($returnUrl, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enrichit les lignes utilisateurs avec grade, affectation hiérarchique et indicateurs RH.
     *
     * @param list<array<string, mixed>> $users
     * @return list<array<string, mixed>>
     */
    private function enrichRosterRows(int $tenantId, array $users): array
    {
        if ($users === []) {
            return [];
        }
        $ids = array_map(static fn (array $u): int => (int) ($u['id'] ?? 0), $users);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        $richById = [];
        if ($ids !== []) {
            foreach ($this->userRepository->listEffectifsRosterByIds($tenantId, $ids) as $rich) {
                $richById[(int) ($rich['id'] ?? 0)] = $rich;
            }
        }
        $unitMeta = $this->unitRepository->hierarchyMetaByUnitId($tenantId);
        $readinessByUser = $ids !== []
            ? $this->unitRepository->readinessByUsersForTenant($tenantId, $ids)
            : [];
        $seniorityByUser = $this->rosterSeniorityPacksByUser($tenantId, $ids, $richById);
        $communityFallback = $this->communityNameForTenant($tenantId);
        $accessRoleIdsByUser = $ids !== []
            ? $this->userRepository->listOrganizationRoleIdsForUsers($tenantId, $ids)
            : [];
        $out = [];
        foreach ($users as $u) {
            $id = (int) ($u['id'] ?? 0);
            $rich = $richById[$id] ?? [];
            $accessRoleIds = array_values(array_filter(
                array_map('intval', is_array($accessRoleIdsByUser[$id] ?? null) ? $accessRoleIdsByUser[$id] : []),
                static fn (int $rid): bool => $rid > 0
            ));
            if ($accessRoleIds === []) {
                $legacyRoleId = (int) ($u['role_id'] ?? 0);
                if ($legacyRoleId > 0) {
                    $accessRoleIds = [$legacyRoleId];
                }
            }
            $portraitPath = trim((string) ($rich['character_portrait_path'] ?? $u['character_portrait_path'] ?? ''));
            $extraCallsignsJson = $rich['extra_callsigns_json'] ?? ($u['extra_callsigns_json'] ?? null);
            $unitId = isset($rich['unit_id']) ? (int) $rich['unit_id'] : 0;
            $unitName = trim((string) ($rich['unit_name'] ?? ''));
            $path = trim((string) ($unitMeta[$unitId]['path'] ?? ''));
            if ($path === '' && $unitName !== '') {
                $path = $unitName;
            }
            $commandKey = (string) ($unitMeta[$unitId]['command_key'] ?? 'zzzzz');
            if ($unitId < 1) {
                $commandKey = 'zzzzz/' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
            }
            $readinessPack = $readinessByUser[$id] ?? null;
            $components = is_array($readinessPack['components'] ?? null) ? $readinessPack['components'] : [];
            $presenceScore = (int) ($components['presence'] ?? 50);
            $availabilityScore = (int) ($components['availability'] ?? 50);
            $readinessScoreProfile = (int) ($rich['readiness_score'] ?? 0);
            if ($readinessScoreProfile > 0) {
                $availabilityScore = max($availabilityScore, $readinessScoreProfile);
            }
            $pack = is_array($seniorityByUser[$id] ?? null) ? $seniorityByUser[$id] : [];
            $seniorityDays = (int) ($pack['days'] ?? 0);
            $completionScore = $this->rosterCompletionScore($u, $rich, $path !== '');
            $enlistmentResolved = trim((string) ($rich['enlistment_date_resolved'] ?? ($pack['community_start'] ?? '')));
            $out[] = array_merge($u, [
                'grade_short' => $rich['grade_short'] ?? null,
                'grade_long' => $rich['grade_long'] ?? null,
                'grade_sort_order' => (int) ($rich['grade_sort_order'] ?? 999),
                'unit_name' => $unitName !== '' ? $unitName : null,
                'unit_code' => $rich['unit_code'] ?? null,
                'unit_id' => $unitId > 0 ? $unitId : null,
                'assignment_path' => $path !== '' ? $path : null,
                'command_sort_key' => $commandKey,
                'community_name' => trim((string) ($rich['community_name'] ?? '')) !== ''
                    ? (string) $rich['community_name']
                    : $communityFallback,
                'job_role_display' => $rich['job_role_display'] ?? null,
                'character_name' => $rich['character_name'] ?? null,
                'matricule_internal' => $rich['matricule_internal'] ?? null,
                'service_number' => $rich['service_number'] ?? null,
                'radio_assigned' => $rich['radio_assigned'] ?? null,
                'enlistment_date_resolved' => $enlistmentResolved !== '' ? $enlistmentResolved : null,
                'pre_platform_start' => trim((string) ($pack['pre_platform_start'] ?? '')) ?: null,
                'seniority_days' => $seniorityDays,
                'seniority_label' => $this->formatSeniorityDays($seniorityDays),
                'seniority_community_label' => (string) ($pack['community_label'] ?? ''),
                'seniority_pre_platform_label' => (string) ($pack['pre_platform_label'] ?? ''),
                'availability_score' => $availabilityScore,
                'presence_score' => $presenceScore,
                'completion_score' => $completionScore,
                'roles_display' => $u['roles_display'] ?? ($u['role_name'] ?? null),
                'access_role_ids' => $accessRoleIds,
                'character_portrait_path' => $portraitPath !== '' ? $portraitPath : null,
                'extra_callsigns_json' => $extraCallsignsJson,
            ]);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortRosterRows(array $rows, string $sort): array
    {
        $nameKey = static function (array $row): string {
            $display = trim((string) ($row['display_name'] ?? ''));
            if ($display !== '') {
                return mb_strtolower($display, 'UTF-8');
            }
            $callsign = trim((string) ($row['callsign'] ?? ''));
            if ($callsign !== '') {
                return mb_strtolower($callsign, 'UTF-8');
            }

            return mb_strtolower((string) ($row['email'] ?? ''), 'UTF-8');
        };
        usort($rows, static function (array $a, array $b) use ($sort, $nameKey): int {
            $cmp = 0;
            switch ($sort) {
                case 'commandement':
                    $cmp = strcmp((string) ($a['command_sort_key'] ?? 'zzzzz'), (string) ($b['command_sort_key'] ?? 'zzzzz'));
                    break;
                case 'grade':
                    $cmp = ((int) ($a['grade_sort_order'] ?? 999)) <=> ((int) ($b['grade_sort_order'] ?? 999));
                    break;
                case 'anciennete':
                    $cmp = ((int) ($b['seniority_days'] ?? 0)) <=> ((int) ($a['seniority_days'] ?? 0));
                    break;
                case 'disponibilite':
                    $cmp = ((int) ($b['availability_score'] ?? 0)) <=> ((int) ($a['availability_score'] ?? 0));
                    break;
                case 'presence':
                    $cmp = ((int) ($b['presence_score'] ?? 0)) <=> ((int) ($a['presence_score'] ?? 0));
                    break;
                case 'completion':
                    $cmp = ((int) ($b['completion_score'] ?? 0)) <=> ((int) ($a['completion_score'] ?? 0));
                    break;
                default:
                    $cmp = strcmp($nameKey($a), $nameKey($b));
                    break;
            }
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($nameKey($a), $nameKey($b));
        });

        return $rows;
    }

    /**
     * @param list<int> $userIds
     * @param array<int, array<string, mixed>> $richById
     * @return array<int, array<string, mixed>>
     */
    private function rosterSeniorityPacksByUser(int $tenantId, array $userIds, array $richById): array
    {
        $enlistmentByUser = [];
        foreach ($userIds as $uid) {
            $start = trim((string) ($richById[$uid]['enlistment_date_resolved'] ?? ''));
            if ($start !== '') {
                $enlistmentByUser[$uid] = $start;
            }
        }
        try {
            return $this->senioritySummary->dashboardLabelsByUsers($tenantId, $userIds, $enlistmentByUser);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Score de complétion dossier / profil à partir des champs déjà chargés (pas de métrique inventée).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $rich
     */
    private function rosterCompletionScore(array $user, array $rich, bool $hasAssignment): int
    {
        return \App\Support\PersonnelDossierCompleteness::evaluate($user, $rich, $hasAssignment)['score'];
    }

    private function formatSeniorityDays(int $days): string
    {
        if ($days < 1) {
            return '—';
        }
        if ($days < 30) {
            return $days . ' j';
        }
        $months = intdiv($days, 30);
        if ($months < 24) {
            return $months . ' mois';
        }
        $years = intdiv($days, 365);
        $remMonths = intdiv($days % 365, 30);
        if ($remMonths > 0) {
            return $years . ' an' . ($years > 1 ? 's' : '') . ' ' . $remMonths . ' mois';
        }

        return $years . ' an' . ($years > 1 ? 's' : '');
    }

    /**
     * @return list<array{label: string, items: list<array{id: int, name: string}>}>
     */
    private function accessPermissionGroups(int $tenantId): array
    {
        $order = CommunityAccessProfiles::moduleOrder();
        $rank = array_flip($order);
        $buckets = [];
        foreach ($this->permissionRepository->allForTenant($tenantId) as $row) {
            $code = (string) ($row['slug'] ?? '');
            if ($code === '' || SystemReservedPermissions::isReserved($code)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $label = trim((string) ($row['name'] ?? ''));
            if ($id < 1 || $label === '') {
                continue;
            }
            $mod = strtolower(trim((string) ($row['module'] ?? '')));
            if ($mod === '') {
                $mod = 'other';
            }
            $buckets[$mod][] = ['id' => $id, 'name' => $label];
        }
        uksort($buckets, static function (string $a, string $b) use ($rank): int {
            $ra = $rank[$a] ?? 99;
            $rb = $rank[$b] ?? 99;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp($a, $b);
        });
        $out = [];
        foreach ($buckets as $mod => $items) {
            usort($items, static fn (array $x, array $y): int => strcmp((string) $x['name'], (string) $y['name']));
            $out[] = [
                'label' => CommunityAccessProfiles::moduleLabel($mod),
                'items' => $items,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobAssignmentPageData(Request $request, int $tenantId): array
    {
        $search = trim((string) $request->query('search', ''));
        $search = $search !== '' ? $search : null;
        $filterJobRoleId = (int) $request->query('job_role_id', 0);
        $filterJobRoleId = $filterJobRoleId > 0 ? $filterJobRoleId : null;
        $onlyUnassigned = $request->query('unassigned') === '1' || $request->query('unassigned') === 'true';
        if ($onlyUnassigned) {
            $filterJobRoleId = null;
        }
        $page = max(1, (int) $request->query('page', 1));
        $tenantSettings = $this->tenantRepository->getSettings($tenantId);
        $pjrAssignSettings = PersonnelJobRoleAssignmentsSettings::resolve($tenantSettings);
        $perPage = (int) ($pjrAssignSettings['assignments_page_size'] ?? 30);
        $total = 0;
        $rows = [];
        $assignmentPivot = [];
        $jobRoleOptions = [];
        if ($this->personnelJobRoleRepository->tablesExist() && $this->personnelJobRoleRepository->personnelProfilesHaveJobRoleColumns()) {
            $total = $this->personnelJobRoleRepository->countUsersForJobRoleAssignments($tenantId, $search, $filterJobRoleId, $onlyUnassigned);
            $rows = $this->personnelJobRoleRepository->listUsersForJobRoleAssignments(
                $tenantId,
                $search,
                $filterJobRoleId,
                $onlyUnassigned,
                $perPage,
                ($page - 1) * $perPage
            );
            $userIds = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows), static fn (int $id): bool => $id > 0));
            $assignmentPivot = $this->personnelJobRoleRepository->pivotTableExists()
                ? $this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, $userIds)
                : [];
            $community = is_array($tenantSettings['community'] ?? null) ? $tenantSettings['community'] : [];
            $jobRoleOptions = $this->personnelJobRoleRepository->listRoleOptionsForSelect(
                $tenantId,
                (bool) ($pjrAssignSettings['show_english_labels'] ?? false),
                (bool) ($pjrAssignSettings['show_category_in_role_picklist'] ?? false),
                OrganizationRoleLabels::mode($community, $this->tenantRepository->findById($tenantId) ?: [])
            );
        }
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));

        return [
            'assignmentRows' => $rows,
            'assignmentPivot' => $assignmentPivot,
            'jobRoleOptions' => $jobRoleOptions,
            'jobRolePermissionCounts' => $this->personnelJobRoleRepository->tablesExist()
                ? $this->personnelJobRoleRepository->permissionCountsForTenant($tenantId)
                : [],
            'pjrAssignSettings' => $pjrAssignSettings,
            'pivotEnabled' => $this->personnelJobRoleRepository->pivotTableExists(),
            'filters' => [
                'search' => $search ?? '',
                'job_role_id' => $filterJobRoleId ?? 0,
                'unassigned' => $onlyUnassigned,
            ],
            'assignmentsTotal' => $total,
            'assignmentsPage' => $page,
            'assignmentsPerPage' => $perPage,
            'assignmentsTotalPages' => $totalPages,
            'activeTab' => 'assignments',
        ];
    }

    private function resolveJobCategoryId(int $tenantId, int $categoryId): int
    {
        if ($categoryId > 0 && $this->personnelJobRoleRepository->findCategoryById($categoryId, $tenantId)) {
            return $categoryId;
        }
        $categories = $this->personnelJobRoleRepository->listCategories($tenantId);
        if ($categories !== []) {
            return (int) ($categories[0]['id'] ?? 0);
        }

        return $this->personnelJobRoleRepository->createCategory($tenantId, null, 'Organisation', 'organisation', 0);
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return array<string, array<string, mixed>>
     */
    private function accessRolesKeyed(array $roles): array
    {
        $out = [];
        foreach ($roles as $row) {
            $out[CommunityAccessProfiles::slugToKey((string) ($row['slug'] ?? ''))] = $row;
        }

        return $out;
    }
}
