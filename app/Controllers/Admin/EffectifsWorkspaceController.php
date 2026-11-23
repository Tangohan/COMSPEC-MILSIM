<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
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
use App\Repositories\MemberLanguageRepository;
use App\Services\Admin\AdminAuditService;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Effectifs\ElevationApprovalService;
use App\Services\Effectifs\MemberChronology;
use App\Services\Effectifs\MemberOffboardingService;
use App\Services\Effectifs\PersonnelCommandChainService;
use App\Services\Effectifs\UnitCodeService;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\JobRolePickerService;
use App\Services\Personnel\PersonnelDuplicateDetectionService;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Services\Personnel\PersonnelJobRoleRecoveryService;
use App\Services\Personnel\PersonnelJobRoleHistoryService;
use App\Services\Personnel\PersonnelJobRoleStructureService;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
use App\Authorization\SystemReservedPermissions;
use App\Services\Admin\RolePermissionService;
use App\Services\Rbac\AccessRightsCatalog;
use App\Services\Rbac\AccessRoleColor;
use App\Services\Rbac\AccessRoleProfileForm;
use App\Services\Rbac\AccessRoleProfileService;
use App\Services\Rbac\AccessRoleProfileView;
use App\Services\Rbac\AccessRoleRules;
use App\Services\Audit\AuditAction;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Repositories\TenantAdminSettingsRepository;
use App\Services\Export\TablePdfExportService;
use App\Support\CommunityLanguages;
use App\Support\EffectifsLmsAccess;
use App\Support\GradeSelectOptions;
use App\Support\MemberFicheTabs;
use App\Support\OrganizationRoleLabels;
use App\Support\PlanFeatureDenial;
use App\Support\PersonnelJobCategoryTree;
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
     * Matrice des qualifications : chaque membre face à chaque qualification du référentiel (qualifié, expire bientôt,
     * expirée, en cours, rien), avec délivrance, mise en formation et retrait sur place. Toutes les qualifications,
     * pas seulement celles de l'Academy.
     */
    public function qualifications(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $service = \App\Core\Container::get(\App\Services\Personnel\QualificationAwardRuleService::class);
        try {
            $matrix = $service->matrix($tenantId);
        } catch (\Throwable) {
            $matrix = ['definitions' => [], 'members' => [], 'cells' => [], 'counts' => ['ok' => 0, 'soon' => 0, 'exp' => 0, 'train' => 0], 'units' => [], 'coverage' => [], 'totals' => [], 'unlinked_academy' => 0];
        }

        return $this->shell('admin.effectifs_workspace.qualifications', [
            'title' => 'Qualifications',
            'effectifsNav' => 'qualifications',
            'qualificationMatrix' => $matrix,
            'qualificationsExpiringCount' => (int) $matrix['counts']['soon'] + (int) $matrix['counts']['exp'],
            'backOfficePageCss' => ['effectifs_lms.css', 'personnel-dossier.css', 'back-office-effectifs-workspace.css', 'back-office-effectifs-refonte.css', 'back-office-effectifs-roster.css', 'effectifs-pages.css', 'back-office-effectifs-maquette.css', 'back-office-effectifs-invitations.css', 'qualifications-matrix.css'],
        ]);
    }

    /** Progression du membre vers les qualifications automatiques (fiche du membre). @return list<array<string, mixed>> */
    private function memberQualificationProgress(int $tenantId, int $userId): array
    {
        try {
            return \App\Core\Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->memberProgress($tenantId, $userId);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Qualifications et brevets suggérés d'après la fonction, l'unité, le grade et les qualifications détenues. @return array<string, mixed>|null */
    private function memberQualificationSuggestions(int $tenantId, int $userId): ?array
    {
        try {
            return \App\Core\Container::get(\App\Services\Personnel\MemberQualificationSuggestionService::class)
                ->forMember($tenantId, $userId, false, function_exists('locale') ? (string) locale() : 'fr');
        } catch (\Throwable $e) {
            error_log('Effectifs member: qualification suggestions failed: ' . $e->getMessage());

            return null;
        }
    }

    /** Actions rapides des suggestions permises au lecteur (mêmes droits que les pages qu'elles appellent). @return array{import: bool, award: bool, enroll: bool, dismiss: bool} */
    private function qualificationSuggestionRights(int $tenantId): array
    {
        $gate = Gate::getInstance();
        $referential = \App\Controllers\Admin\MemberQualificationSuggestionsController::canManageReferential($gate);
        $enroll = false;
        try {
            $enroll = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tenantId, 'training')
                && \App\Services\Academy\AcademyRights::current($tenantId, (int) Session::get('user_id'))->can('assign');
        } catch (\Throwable) {
            $enroll = false;
        }

        return [
            'import' => $referential,
            'award' => $referential,
            'enroll' => $enroll,
            'dismiss' => \App\Controllers\Admin\MemberQualificationSuggestionsController::canDismiss($gate),
        ];
    }

    /** @return array<string, mixed> */
    private function qualificationNames(int $tenantId): array
    {
        try {
            return \App\Core\Container::get(\App\Services\Personnel\QualificationAwardRuleService::class)->names($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function roster(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        // Les matricules manquants (comptes d'avant l'attribution automatique) sont attribués par la tâche
        // planifiée « tenant_data_upkeep » : afficher cette page n'écrit plus rien.

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
        $allowedSorts = ['nom', 'commandement', 'grade', 'anciennete', 'disponibilite', 'presence', 'completion', 'heures_vol', 'temps_mission'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'nom';
        }
        // Vue du tableur : colonnes générales, dossier médical, ou heures de vol par appareil.
        $rosterView = trim((string) $request->query('vue', ''));
        if (!in_array($rosterView, ['medical', 'aviation'], true)) {
            $rosterView = 'general';
        }
        $jobFilter = mb_substr(trim((string) $request->query('fonction', '')), 0, 120, 'UTF-8');
        // Filtre « étiquette de fonction » : membres qui occupent au moins une fonction portant l'étiquette.
        $jobTagFilter = max(0, (int) $request->query('etiquette_fonction', 0));
        // Regroupement de la maquette : par unité (ordre de commandement), par grade, ou liste simple.
        $explicitSort = trim((string) $request->query('tri', '')) !== '';
        $group = trim((string) $request->query('groupe', ''));
        if (!in_array($group, ['unite', 'grade', 'liste'], true)) {
            $group = match (true) {
                !$explicitSort || $sort === 'commandement' => 'unite',
                $sort === 'grade' => 'grade',
                default => 'liste',
            };
        }
        if ($group === 'unite') {
            $sort = 'commandement';
        } elseif ($group === 'grade') {
            $sort = 'grade';
        }
        $unitFilter = max(0, (int) $request->query('unite', 0));
        $gradeCategoryFilter = (string) $request->query('categorie', '');
        if (!in_array($gradeCategoryFilter, ['off', 'nco', 'enl'], true)) {
            $gradeCategoryFilter = '';
        }
        $gradeCategories = $this->gradeCategoriesById($tenantId);

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

        $languageCommunity = $this->communitySettingsFor($tenantId);
        $languageFilter = CommunityLanguages::normalize($request->query('langue', '')) ?? '';
        // Filtre « Actif : sessions de plus d'1 h » : au moins une session qui compte sur 30 jours (ou aucune).
        $sessionsRaw = $request->query('sessions', '');
        // « À vérifier » (verifierN) : aucune session qui compte et aucune vérification enregistrée depuis N jours.
        $sessionsFilter = is_string($sessionsRaw) && in_array($sessionsRaw, array_merge(['actif', 'inactif'], \App\Support\RosterAvailabilityFilter::values()), true) ? $sessionsRaw : '';
        $needsFullLoad = $sort !== 'nom' || $languageFilter !== '' || $jobFilter !== '' || $jobTagFilter > 0 || $unitFilter > 0 || $gradeCategoryFilter !== '' || $sessionsFilter !== '';
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
            if ($languageFilter !== '' || $jobFilter !== '' || $jobTagFilter > 0 || $unitFilter > 0 || $gradeCategoryFilter !== '') {
                $allRows = $this->applyRosterExtraFilters($tenantId, $allRows, $languageFilter, $jobFilter, $jobTagFilter, $unitFilter, $gradeCategoryFilter);
                $total = count($allRows);
            }
            if (in_array($sort, ['heures_vol', 'temps_mission'], true) || $sessionsFilter !== '') {
                $allRows = $this->attachRosterFieldData($tenantId, $allRows, false);
            }
            if (\App\Support\RosterAvailabilityFilter::days($sessionsFilter) > 0) {
                $latest = (new \App\Repositories\MemberAvailabilityCheckRepository())->latestForUsers($tenantId, array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $allRows));
                $allRows = \App\Support\RosterAvailabilityFilter::apply($allRows, $latest, \App\Support\RosterAvailabilityFilter::days($sessionsFilter), time());
                $total = count($allRows);
            } elseif ($sessionsFilter !== '') {
                $wantActive = $sessionsFilter === 'actif';
                $allRows = array_values(array_filter($allRows, static fn (array $r): bool => (!empty($r['server_sessions']['active']) || ($r['playtime_availability']['key'] ?? null) === \App\Services\Effectifs\Availability\AvailabilityCheck::AVAILABLE) === $wantActive));
                $total = count($allRows);
            }
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

        $rows = $this->attachRosterFieldData($tenantId, $rows, true);
        $counts = $this->rosterCounts($tenantId);
        $rowLanguages = $this->memberLanguages()->resolvedForUsers(
            $tenantId,
            array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows),
            $languageCommunity
        );
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
        $phaseBadgesByUserId = [];
        try {
            $phaseBadgesByUserId = \App\Core\Container::get(\App\Services\Personnel\PhaseRules\PhaseTransitionService::class)
                ->rosterBadgesForUsers($tenantId, $rowIds);
        } catch (\Throwable) {
            $phaseBadgesByUserId = [];
        }
        $elevationCooldownByUserId = $this->effectifsStaffAlertService->secondsBeforeNextElevationRequestBatch(
            $rowIds,
            $viewerId
        );
        $jobEdit = $this->rosterJobEditData($tenantId, $rowIds, EffectifsLmsAccess::canManageAssignments($gate));
        // Vérification de disponibilité : bouton par ligne et groupé (gérer le suivi), dernière vérification (le consulter).
        $canCheckAvailability = MemberAvailabilityCheckController::canCheck($gate);
        $availabilityLatest = [];
        $availabilityReady = false;
        if ($canCheckAvailability || MemberAvailabilityCheckController::canView($gate)) {
            try {
                $checkRepo = new \App\Repositories\MemberAvailabilityCheckRepository();
                $availabilityReady = $checkRepo->schemaReady();
                $availabilityLatest = $checkRepo->latestForUsers($tenantId, $rowIds);
            } catch (\Throwable $e) {
                error_log('Effectifs roster: vérifications de disponibilité : ' . $e->getMessage());
            }
        }

        return $this->shell('admin.effectifs_workspace.roster', [
            'canCheckAvailability' => $canCheckAvailability,
            'rosterAvailabilityChecks' => $availabilityLatest,
            'availabilityChecksReady' => $availabilityReady,
            'availabilitySettingsUrl' => \App\Support\BackOfficeRightGuard::allows(\App\Support\BackOfficeRightGuard::MANAGERS) ? url('back-office/roleplay/sessions') . '#regles' : '',
            'title' => 'Tableur des effectifs',
            'effectifsNav' => 'roster',
            'rosterRows' => $rows,
            'rosterIdentities' => $this->rosterIdentities($tenantId, $rowIds),
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
                'langue' => $languageFilter,
                'sessions' => $sessionsFilter,
                'vue' => $rosterView,
                'fonction' => $jobFilter,
                'etiquette_fonction' => $jobTagFilter,
            ],
            'rosterSessionRules' => \App\Services\Personnel\RoleplayGameSessionSettings::playerSessionRules($tenantId),
            'rosterJobTags' => (new JobRoleEnrichmentService())->tags($tenantId),
            'rosterView' => $rosterView,
            'canExportPdf' => self::pdfExportsAllowed($tenantId),
            'rosterJobRoleOptions' => $this->rosterJobRoleOptions($tenantId),
            'rosterLanguages' => $rowLanguages,
            'rosterLanguageCodes' => CommunityLanguages::communityLanguages($languageCommunity),
            'rosterSortOptions' => [
                'nom' => 'Nom',
                'commandement' => 'Ordre de commandement',
                'grade' => 'Ordre de grade',
                'anciennete' => 'Ordre d’ancienneté',
                'disponibilite' => 'Ordre de disponibilité',
                'presence' => 'Ordre de présence',
                'completion' => 'Ordre de complétion du dossier',
                'temps_mission' => 'Temps en mission',
                'heures_vol' => 'Heures de vol',
            ],
            'rosterCounts' => $counts,
            'orgRoles' => $this->roleRepository->forTenantAccessProfiles($tenantId),
            'orgUnits' => $units,
            'communityName' => $communityName,
            'elevationRecipientsCount' => count($elevationRecipients),
            'elevationCooldownByUserId' => $elevationCooldownByUserId,
            'badgesByUserId' => $badgesByUserId,
            'phaseBadgesByUserId' => $phaseBadgesByUserId,
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
            'rosterJobsByUserId' => $jobEdit['by_user'],
            'rosterJobPickerGroups' => $jobEdit['groups'],
            'rosterJobRoleMax' => $jobEdit['max'],
            'rosterUnitCodes' => (new UnitCodeService())->sync($tenantId)['units'],
            'rosterGroup' => $group,
            'rosterUnitFilter' => $unitFilter,
            'rosterGradeCategory' => $gradeCategoryFilter,
            'rosterGradeCategories' => $gradeCategories,
            'rosterUnitCommanders' => $this->safeCommanderLabels($tenantId, $unitsRaw),
            'effHero' => $this->rosterHeroData($tenantId, $unitsRaw, $counts),
        ]);
    }

    /**
     * Catégorie de chaque grade (officier, sous-officier, militaire du rang), d'après son code OTAN.
     * Un grade sans code OTAN n'a pas de catégorie : il n'apparaît dans aucun des trois filtres.
     *
     * @return array<int, string>
     */
    private function gradeCategoriesById(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->gradeRepository->listForTenant($tenantId) as $g) {
                $rank = GradeSelectOptions::natoRank($g);
                $id = (int) ($g['id'] ?? 0);
                if ($id < 1 || $rank < 1) {
                    continue;
                }
                $out[$id] = $rank >= 100 ? 'off' : ($rank >= 5 ? 'nco' : 'enl');
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $unitsRaw
     * @return array<int, string>
     */
    private function safeCommanderLabels(int $tenantId, array $unitsRaw): array
    {
        try {
            return $this->unitRepository->commanderLabelByUnitForTenant($tenantId, $unitsRaw);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Bandeau du tableur : composition par unité et compteurs, tous issus de la base.
     *
     * @param list<array<string, mixed>> $unitsRaw
     * @param array<string, int> $counts
     * @return array<string, mixed>
     */
    private function rosterHeroData(int $tenantId, array $unitsRaw, array $counts): array
    {
        $byUnit = [];
        try {
            $byUnit = $this->unitRepository->countDistinctMembersByUnitForTenant($tenantId);
        } catch (\Throwable) {
        }
        $names = [];
        foreach ($unitsRaw as $u) {
            $names[(int) ($u['id'] ?? 0)] = trim((string) ($u['name'] ?? ''));
        }
        arsort($byUnit);
        $parts = [];
        $other = 0;
        $i = 0;
        foreach ($byUnit as $unitId => $n) {
            if ($n < 1 || !isset($names[$unitId])) {
                continue;
            }
            if ($i++ < 6) {
                $parts[] = ['label' => $names[$unitId], 'count' => (int) $n, 'unit_id' => (int) $unitId];
            } else {
                $other += (int) $n;
            }
        }
        if ($other > 0) {
            $parts[] = ['label' => 'Autres unités', 'count' => $other, 'unit_id' => 0];
        }
        $noUnit = (int) ($counts['no_unit'] ?? 0);
        if ($noUnit > 0) {
            $parts[] = ['label' => 'Sans unité', 'count' => $noUnit, 'unit_id' => -1];
        }
        $absent = 0;
        try {
            $absent = count($this->personnelAbsenceRepository->userIdsAbsentToday($tenantId));
        } catch (\Throwable) {
        }

        return [
            'units_with_members' => count(array_filter($byUnit, static fn (int $n): bool => $n > 0)),
            'composition' => $parts,
            'absent_today' => $absent,
        ];
    }

    /**
     * Emplois des membres de la page et choix proposés dans la fenêtre « Emploi » du tableur.
     *
     * @param list<int> $userIds
     * @return array{by_user: array<int, list<array{id: int, label: string, primary: bool}>>, groups: list<array<string, mixed>>, max: int}
     */
    private function rosterJobEditData(int $tenantId, array $userIds, bool $canEdit): array
    {
        $out = ['by_user' => [], 'groups' => [], 'max' => 5];
        try {
            if (!$this->personnelJobRoleRepository->tablesExist() || !$this->personnelJobRoleRepository->pivotTableExists()) {
                return $out;
            }
            $decorations = (new JobRoleEnrichmentService())->decorations($tenantId);
            foreach ($this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, $userIds) as $uid => $assigned) {
                foreach ($assigned as $a) {
                    $name = trim((string) ($a['role_name'] ?? ''));
                    $detail = trim((string) ($a['role_detail'] ?? ''));
                    $out['by_user'][(int) $uid][] = [
                        'id' => (int) ($a['personnel_job_role_id'] ?? 0),
                        'label' => $detail !== '' && $name !== '' ? $name . ' — ' . $detail : ($name !== '' ? $name : $detail),
                        'primary' => !empty($a['is_primary']),
                        'decoration' => $decorations[(int) ($a['personnel_job_role_id'] ?? 0)] ?? null,
                    ];
                }
            }
            if ($canEdit) {
                $settings = PersonnelJobRoleAssignmentsSettings::resolve($this->tenantRepository->getSettings($tenantId));
                $out['max'] = max(1, (int) $settings['max_roles_per_member']);
                $out['groups'] = (new JobRolePickerService($this->personnelJobRoleRepository))->groups($tenantId, []);
            }
        } catch (\Throwable $e) {
            error_log('Effectifs roster: job data failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * PDF des effectifs : l'abrégé de la fonction principale précède son libellé (« CDG · Chef de groupe »).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withPrimaryJobAbbreviation(int $tenantId, array $rows): array
    {
        try {
            $decorations = (new JobRoleEnrichmentService())->decorations($tenantId);
            if ($decorations === [] || !$this->personnelJobRoleRepository->pivotTableExists()) {
                return $rows;
            }
            $assigned = $this->personnelJobRoleRepository->listPivotAssignmentsForUsers($tenantId, array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows));
        } catch (\Throwable) {
            return $rows;
        }
        foreach ($rows as &$row) {
            $primary = $assigned[(int) ($row['id'] ?? 0)][0] ?? null;
            $abbr = $primary !== null ? (string) ($decorations[(int) $primary['personnel_job_role_id']]['abbreviation'] ?? '') : '';
            $display = trim((string) ($row['job_role_display'] ?? ''));
            if ($abbr !== '' && $display !== '' && !str_starts_with($display, $abbr . ' · ')) {
                $row['job_role_display'] = $abbr . ' · ' . $display;
            }
        }
        unset($row);

        return $rows;
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

    /**
     * Filtres du tableur calculés après chargement (pas de colonne SQL dédiée) : langue, fonction, étiquette de fonction,
     * unité (et sous-unités), catégorie de grade. Partagés par roster() et exportCsv() pour qu'ils restent identiques.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function applyRosterExtraFilters(int $tenantId, array $rows, string $languageFilter, string $jobFilter, int $jobTagFilter, int $unitFilter, string $gradeCategoryFilter): array
    {
        if ($languageFilter !== '') {
            // Filtre « langue » : principale ou comprise.
            $resolvedAll = $this->memberLanguages()->resolvedForUsers(
                $tenantId,
                array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows),
                $this->communitySettingsFor($tenantId)
            );
            $rows = array_values(array_filter($rows, static function (array $r) use ($resolvedAll, $languageFilter): bool {
                $lang = $resolvedAll[(int) ($r['id'] ?? 0)] ?? null;

                return is_array($lang) && ($lang['primary'] === $languageFilter || in_array($languageFilter, $lang['others'], true));
            }));
        }
        if ($jobFilter !== '') {
            // Filtre « fonction » : la fonction principale commence par l'emploi choisi (ex. « Medic — CLS »).
            $rows = array_values(array_filter($rows, static fn (array $r): bool => mb_stripos(trim((string) ($r['job_role_display'] ?? '')), $jobFilter, 0, 'UTF-8') === 0));
        }
        if ($jobTagFilter > 0) {
            $taggedUsers = (new JobRoleEnrichmentService())->userIdsWithTag($tenantId, $jobTagFilter);
            $rows = array_values(array_filter($rows, static fn (array $r): bool => isset($taggedUsers[(int) ($r['id'] ?? 0)])));
        }
        if ($unitFilter > 0) {
            // Filtre « unité » : l'unité choisie et toutes ses sous-unités.
            $unitScope = array_flip($this->unitRepository->expandUnitIdsWithDescendants($tenantId, [$unitFilter]));
            $rows = array_values(array_filter($rows, static fn (array $r): bool => isset($unitScope[(int) ($r['unit_id'] ?? 0)])));
        }
        if ($gradeCategoryFilter !== '') {
            $gradeCategories = $this->gradeCategoriesById($tenantId);
            $rows = array_values(array_filter($rows, static fn (array $r): bool => ($gradeCategories[(int) ($r['grade_id'] ?? 0)] ?? '') === $gradeCategoryFilter));
        }

        return $rows;
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
        // Mêmes filtres calculés après chargement que le tableur (langue, fonction, étiquette, unité, catégorie de grade).
        $jobFilter = mb_substr(trim((string) $request->query('fonction', '')), 0, 120, 'UTF-8');
        $jobTagFilter = max(0, (int) $request->query('etiquette_fonction', 0));
        $unitFilter = max(0, (int) $request->query('unite', 0));
        $gradeCategoryFilter = (string) $request->query('categorie', '');
        if (!in_array($gradeCategoryFilter, ['off', 'nco', 'enl'], true)) {
            $gradeCategoryFilter = '';
        }
        $languageFilter = CommunityLanguages::normalize($request->query('langue', '')) ?? '';

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
        $rows = $this->applyRosterExtraFilters($tenantId, $rows, $languageFilter, $jobFilter, $jobTagFilter, $unitFilter, $gradeCategoryFilter);
        $rows = $this->sortRosterRows($rows, 'nom');
        $csvLanguages = $this->memberLanguages()->resolvedForUsers(
            $tenantId,
            array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows),
            $this->communitySettingsFor($tenantId)
        );

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
            'platform_number', 'tenant_member_number', 'Langue de jeu', 'Autres langues',
        ], $sep, '"', '\\');
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
                CommunityLanguages::label((string) ($csvLanguages[(int) ($r['id'] ?? 0)]['primary'] ?? '')),
                implode(', ', array_map(
                    [CommunityLanguages::class, 'label'],
                    $csvLanguages[(int) ($r['id'] ?? 0)]['others'] ?? []
                )),
            ], $sep, '"', '\\');
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

    /**
     * Export PDF du tableur (vue générale, médicale ou heures de vol) avec les filtres et le tri affichés.
     * Module de formule « pdf_exports » : les offres gratuites voient la page d'offre supérieure.
     */
    public function exportPdf(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!self::pdfExportsAllowed($tenantId)) {
            return PlanFeatureDenial::upgradeView('pdf_exports', 'Standard');
        }

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        $roleId = max(0, (int) $request->query('role_id', 0));
        $onlyNoUnit = $request->query('sans_affectation') === '1';
        $onlyNoRole = $request->query('sans_role') === '1';
        $sort = trim((string) $request->query('tri', 'nom'));
        $sortLabels = [
            'nom' => 'Nom',
            'commandement' => 'Ordre de commandement',
            'grade' => 'Ordre de grade',
            'anciennete' => 'Ordre d’ancienneté',
            'disponibilite' => 'Ordre de disponibilité',
            'presence' => 'Ordre de présence',
            'completion' => 'Ordre de complétion du dossier',
            'temps_mission' => 'Temps en mission',
            'heures_vol' => 'Heures de vol',
        ];
        if (!isset($sortLabels[$sort])) {
            $sort = 'nom';
        }
        $view = trim((string) $request->query('vue', ''));
        if (!in_array($view, ['medical', 'aviation'], true)) {
            $view = 'general';
        }
        $jobFilter = mb_substr(trim((string) $request->query('fonction', '')), 0, 120, 'UTF-8');
        $community = $this->communitySettingsFor($tenantId);
        $languageFilter = CommunityLanguages::normalize($request->query('langue', '')) ?? '';

        $statusFilter = $status !== '' ? $status : null;
        $roleFilter = $roleId > 0 ? $roleId : null;
        $total = $this->userRepository->countListForTenant($tenantId, $search !== '' ? $search : null, $statusFilter, $roleFilter, true, $onlyNoUnit ? true : null, $onlyNoRole ? true : null);
        $baseUsers = $this->userRepository->listForTenant(
            $tenantId,
            $search !== '' ? $search : null,
            $statusFilter,
            $roleFilter,
            min(TablePdfExportService::MAX_ROWS, max($total, 1)),
            0,
            true,
            $onlyNoUnit ? true : null,
            $onlyNoRole ? true : null
        );
        $rows = $this->enrichRosterRows($tenantId, $baseUsers);
        $languages = $this->memberLanguages()->resolvedForUsers(
            $tenantId,
            array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows),
            $community
        );
        if ($languageFilter !== '') {
            $rows = array_values(array_filter($rows, static function (array $r) use ($languages, $languageFilter): bool {
                $lang = $languages[(int) ($r['id'] ?? 0)] ?? null;

                return is_array($lang) && ($lang['primary'] === $languageFilter || in_array($languageFilter, $lang['others'], true));
            }));
        }
        if ($jobFilter !== '') {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => mb_stripos(trim((string) ($r['job_role_display'] ?? '')), $jobFilter, 0, 'UTF-8') === 0));
        }
        $jobTagFilter = max(0, (int) $request->query('etiquette_fonction', 0));
        if ($jobTagFilter > 0) {
            $taggedUsers = (new JobRoleEnrichmentService())->userIdsWithTag($tenantId, $jobTagFilter);
            $rows = array_values(array_filter($rows, static fn (array $r): bool => isset($taggedUsers[(int) ($r['id'] ?? 0)])));
        }
        $rows = $this->withPrimaryJobAbbreviation($tenantId, $rows);
        $rows = $this->attachRosterFieldData($tenantId, $rows, $view === 'medical');
        $rows = $this->sortRosterRows($rows, $sort);

        $roleName = '';
        if ($roleFilter !== null) {
            foreach ($this->roleRepository->forTenantAccessProfiles($tenantId) as $role) {
                if ((int) ($role['id'] ?? 0) === $roleFilter) {
                    $roleName = (string) ($role['name'] ?? '');
                }
            }
        }
        $statusLabels = ['active' => 'Compte actif', 'inactive' => 'Compte inactif', 'pending_verification' => 'E-mail à vérifier'];
        $filters = [
            ['Recherche', $search],
            ['Statut', $statusLabels[$status] ?? $status],
            ['Rôle', $roleName],
            ['Fonction', $jobFilter],
            ['Langue', $languageFilter !== '' ? CommunityLanguages::label($languageFilter) : ''],
            ['Affectation', $onlyNoUnit ? 'sans affectation' : ''],
            ['Rôle d’accès', $onlyNoRole ? 'sans rôle' : ''],
            ['Tri', $sort !== 'nom' ? $sortLabels[$sort] : ''],
        ];

        [$columns, $cells, $viewLabel] = self::rosterPdfTable($view, $rows);
        $communityName = $this->communityNameForTenant($tenantId);
        if ($view === 'medical') {
            $this->adminAuditService->logUserUpdated($tenantId, (int) Session::get('user_id'), (int) Session::get('user_id'), 'export_pdf_medical', (string) count($cells));
        }

        return \App\Core\Container::get(TablePdfExportService::class)->export([
            'title' => 'Effectifs · ' . $viewLabel,
            'community' => $communityName,
            'filters' => $filters,
            'columns' => $columns,
            'rows' => $cells,
            'total' => count($cells),
            'note' => $view === 'medical' ? 'Confidentiel : données médicales' : '',
            'filename' => 'effectifs-' . $view . '-' . date('Y-m-d'),
            'reference' => 'EFF-' . date('Y-m-d'),
        ]);
    }

    /** Module de formule « Exports PDF des tableaux » (valeur du catalogue si la formule n'a pas encore la clé). */
    public static function pdfExportsAllowed(int $tenantId): bool
    {
        try {
            return \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allowsWithCatalogDefault($tenantId, 'pdf_exports');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Colonnes et cellules texte du PDF pour une vue du tableur.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{0: list<array<string, mixed>>, 1: list<list<string>>, 2: string}
     */
    public static function rosterPdfTable(string $view, array $rows): array
    {
        $hours = static function (int $seconds): string {
            if ($seconds <= 0) {
                return '';
            }
            if ($seconds < 3600) {
                return max(1, intdiv($seconds, 60)) . ' min';
            }
            $m = intdiv($seconds, 60);

            return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
        };
        $member = static function (array $r): string {
            $name = trim((string) ($r['display_name'] ?? ''));
            $callsign = trim((string) ($r['callsign'] ?? ''));

            return $callsign !== '' && strcasecmp($callsign, $name) !== 0 ? $name . "\n" . $callsign : $name;
        };
        $grade = static fn (array $r): string => trim((string) ($r['grade_short'] ?? '') ?: (string) ($r['grade_long'] ?? ''));
        $job = static fn (array $r): string => trim((string) ($r['job_role_display'] ?? ''));
        $unit = static fn (array $r): string => trim((string) ($r['assignment_path'] ?? ''));

        if ($view === 'medical') {
            $columns = [
                ['label' => 'Membre', 'weight' => 2.2, 'bold' => true],
                ['label' => 'Grade', 'weight' => 0.9],
                ['label' => 'Fonction', 'weight' => 1.6],
                ['label' => 'Groupe sanguin', 'weight' => 1, 'align' => 'C', 'bold' => true, 'muted' => ['À renseigner']],
                ['label' => 'Poids', 'weight' => 0.8, 'align' => 'R'],
                ['label' => 'Taille', 'weight' => 0.8, 'align' => 'R'],
                ['label' => 'IMC', 'weight' => 0.7, 'align' => 'R'],
                ['label' => 'Intolérances médicamenteuses', 'weight' => 2.5, 'alert' => true, 'muted' => ['Aucune connue']],
                ['label' => 'Allergies', 'weight' => 2.5, 'alert' => true, 'muted' => ['Aucune connue']],
            ];
            $cells = [];
            foreach ($rows as $r) {
                $kg = (int) ($r['weight_kg'] ?? 0);
                $cm = (int) ($r['height_cm'] ?? 0);
                $intol = trim((string) ($r['drug_intolerances'] ?? ''));
                $allergies = trim((string) ($r['allergies'] ?? ''));
                $cells[] = [
                    $member($r),
                    $grade($r),
                    $job($r),
                    trim((string) ($r['blood_type'] ?? '')) ?: 'À renseigner',
                    $kg > 0 ? $kg . ' kg' : '',
                    $cm > 0 ? $cm . ' cm' : '',
                    $kg > 0 && $cm > 0 ? number_format($kg / (($cm / 100) ** 2), 1, ',', ' ') : '',
                    $intol !== '' ? $intol : 'Aucune connue',
                    $allergies !== '' ? $allergies : 'Aucune connue',
                ];
            }

            return [$columns, $cells, 'Médical'];
        }

        if ($view === 'aviation') {
            // Mêmes colonnes « type d'appareil » que l'écran : les 6 appareils les plus pilotés.
            $families = [];
            foreach ($rows as $r) {
                foreach (is_array($r['pilot_families'] ?? null) ? $r['pilot_families'] : [] as $fam) {
                    $fk = (string) ($fam['key'] ?? '');
                    if ($fk === '') {
                        continue;
                    }
                    $families[$fk] ??= ['label' => (string) ($fam['label'] ?? $fk), 'seconds' => 0];
                    $families[$fk]['seconds'] += (int) ($fam['seconds'] ?? 0);
                }
            }
            uasort($families, static fn (array $x, array $y): int => $y['seconds'] <=> $x['seconds']);
            $families = array_slice($families, 0, 6, true);
            $columns = [
                ['label' => 'Membre', 'weight' => 2.2, 'bold' => true],
                ['label' => 'Fonction', 'weight' => 1.6],
                ['label' => 'Affectation', 'weight' => 2],
                ['label' => 'Heures de vol', 'weight' => 1, 'align' => 'R', 'bold' => true],
            ];
            foreach ($families as $fam) {
                $columns[] = ['label' => (string) $fam['label'], 'weight' => 0.9, 'align' => 'R'];
            }
            $columns[] = ['label' => 'Dernier vol', 'weight' => 0.9, 'align' => 'C'];
            $cells = [];
            foreach ($rows as $r) {
                $bySecs = [];
                foreach (is_array($r['pilot_families'] ?? null) ? $r['pilot_families'] : [] as $fam) {
                    $bySecs[(string) ($fam['key'] ?? '')] = (int) ($fam['seconds'] ?? 0);
                }
                $last = trim((string) ($r['pilot_last_flown_at'] ?? ''));
                $lastTs = $last !== '' ? strtotime($last) : false;
                $line = [$member($r), $job($r), $unit($r), $hours((int) ($r['pilot_seconds'] ?? 0))];
                foreach (array_keys($families) as $fk) {
                    $line[] = $hours((int) ($bySecs[$fk] ?? 0));
                }
                $line[] = $lastTs !== false ? date('d/m/Y', $lastTs) : '';
                $cells[] = $line;
            }

            return [$columns, $cells, 'Heures de vol'];
        }

        $statusLabels = ['active' => 'Actif', 'inactive' => 'Inactif', 'pending_verification' => 'E-mail à vérifier'];
        $columns = [
            ['label' => 'Membre', 'weight' => 2.2, 'bold' => true],
            ['label' => 'Grade', 'weight' => 0.9],
            ['label' => 'Affectation', 'weight' => 2],
            ['label' => 'Fonction', 'weight' => 1.6],
            ['label' => 'Statut', 'weight' => 0.9, 'translate' => true],
            ['label' => 'Ancienneté', 'weight' => 1],
            ['label' => 'Temps en mission', 'weight' => 1, 'align' => 'R'],
            ['label' => 'Heures de vol', 'weight' => 0.9, 'align' => 'R'],
            ['label' => 'Dispo.', 'weight' => 0.7, 'align' => 'R'],
            ['label' => 'Présence', 'weight' => 0.8, 'align' => 'R'],
            ['label' => 'Dossier', 'weight' => 0.7, 'align' => 'R'],
        ];
        $cells = [];
        foreach ($rows as $r) {
            $st = (string) ($r['status'] ?? '');
            $cells[] = [
                $member($r),
                $grade($r),
                $unit($r) !== '' ? $unit($r) : 'Non affecté',
                $job($r),
                $statusLabels[$st] ?? $st,
                (string) ($r['seniority_label'] ?? ''),
                $hours((int) ($r['arma_playtime_seconds'] ?? 0)),
                $hours((int) ($r['pilot_seconds'] ?? 0)),
                (int) ($r['availability_score'] ?? 0) . ' %',
                (int) ($r['presence_score'] ?? 0) . ' %',
                (int) ($r['completion_score'] ?? 0) . ' %',
            ];
        }

        return [$columns, $cells, 'Vue générale'];
    }

    /**
     * Bureau des effectifs : importe un tableur (CSV) en fiches sans compte. Les colonnes du fichier
     * « Exporter » sont reconnues, on peut donc réimporter un export retouché dans Excel.
     */
    public function importRoster(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!\App\Services\Community\TenantTypeConfig::rosterWithoutAccounts((string) ($tenant['tenant_type'] ?? 'full'))) {
            Session::flash('error', 'L’import de tableur est réservé au profil « Bureau des effectifs ».');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!EffectifsLmsAccess::canEditProfiles(Gate::getInstance())) {
            Session::flash('error', 'Il faut le droit de modifier les fiches pour importer un tableur.');

            return Response::redirect(effectifs_workspace_url());
        }
        $file = $_FILES['roster_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Choisissez un fichier CSV (dans Excel : Fichier → Enregistrer sous → CSV).');

            return Response::redirect(effectifs_workspace_url());
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $content = $tmp !== '' && is_readable($tmp) && (int) ($file['size'] ?? 0) <= 2 * 1024 * 1024 ? (string) file_get_contents($tmp) : '';
        if ($content === '') {
            Session::flash('error', 'Fichier illisible ou trop lourd (2 Mo au plus).');

            return Response::redirect(effectifs_workspace_url());
        }
        $parsed = \App\Services\Effectifs\RosterOnlyMemberService::parseCsv($content);
        if ($parsed['error'] !== null) {
            Session::flash('error', $parsed['error']);

            return Response::redirect(effectifs_workspace_url());
        }
        $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$gate->canAddMember($tenantId)) {
            Session::flash('error', 'Limite de membres du plan atteinte.');

            return Response::redirect(effectifs_workspace_url());
        }
        $service = new \App\Services\Effectifs\RosterOnlyMemberService(
            $this->userRepository,
            \App\Core\Container::get(\App\Repositories\UserProfileRepository::class),
            $this->personnelProfileRepository,
            $this->personnelAssignmentRepository,
            $this->gradeRepository,
            $this->unitRepository,
            \App\Services\Effectifs\RosterFicheEmailService::forUsers($this->userRepository),
        );
        $result = $service->import($tenantId, $actorId, $parsed['rows']);

        $msg = $result['created'] . ' fiche(s) créée(s)' . ($result['updated'] > 0 ? ', ' . $result['updated'] . ' mise(s) à jour' : '') . '.';
        if ($result['skipped'] !== []) {
            $msg .= ' ' . count($result['skipped']) . ' ligne(s) ignorée(s) : ' . implode(' ', array_slice($result['skipped'], 0, 5));
            if (count($result['skipped']) > 5) {
                $msg .= ' …';
            }
        }
        if ($result['unknown_grades'] !== []) {
            $msg .= ' Grades non reconnus (laissés vides) : ' . implode(', ', array_slice($result['unknown_grades'], 0, 8)) . '.';
        }
        if ($result['unknown_units'] !== []) {
            $msg .= ' Unités inconnues (créez-les dans l’organigramme, puis réimportez ou affectez à la main) : ' . implode(', ', array_slice($result['unknown_units'], 0, 8)) . '.';
        }
        Session::flash($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $msg);
        try {
            $this->adminAuditService->logUserUpdated($tenantId, $actorId, $actorId, 'import_tableur', (string) $result['created']);
        } catch (\Throwable) {
        }

        return Response::redirect(effectifs_workspace_url());
    }

    /** Modèle CSV vierge pour l’import du Bureau des effectifs (s’ouvre dans Excel). */
    public function rosterTemplate(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $csv = \App\Services\Effectifs\RosterOnlyMemberService::templateCsv();

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="modele-effectifs.csv"')
            ->setBody($csv);
    }

    /**
     * Ajouter un membre : formulaire en étapes (identité de jeu, place dans l'unité, dossier, lien d'invitation, aperçu).
     * Chaque volet n'apparaît qu'avec le droit correspondant ; l'enregistrement est fait par UserAdminController::store.
     */
    public function newMember(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canCreateMembers($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à ajouter des membres.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId) ?: [];
        $grades = $this->gradeRepository->listActiveForDoctrinePicker($tenantId);
        $unitMeta = $this->unitRepository->hierarchyMetaByUnitId($tenantId);
        $codes = (new UnitCodeService())->sync($tenantId)['units'];
        $units = [];
        foreach ($this->unitRepository->allForTenant($tenantId) as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $path = trim((string) ($unitMeta[$uid]['path'] ?? ''));
            $units[] = [
                'id' => $uid,
                'name' => trim((string) ($u['name'] ?? '')),
                'path' => $path !== '' ? $path : trim((string) ($u['name'] ?? '')),
                'code' => (string) ($codes[$uid]['code'] ?? ''),
                'admin_id' => (string) ($codes[$uid]['admin_id'] ?? ''),
            ];
        }
        usort($units, static fn (array $a, array $b): int => strcasecmp($a['path'], $b['path']));
        $jobGroups = [];
        $jobMax = 5;
        if ($this->personnelJobRoleRepository->tablesExist() && $this->personnelJobRoleRepository->pivotTableExists()) {
            try {
                $jobGroups = (new JobRolePickerService($this->personnelJobRoleRepository))->groups($tenantId, []);
                $jobMax = (int) PersonnelJobRoleAssignmentsSettings::resolve($this->tenantRepository->getSettings($tenantId))['max_roles_per_member'];
            } catch (\Throwable $e) {
                error_log('Effectifs nouveau membre : emplois indisponibles : ' . $e->getMessage());
            }
        }
        $community = $this->communitySettingsFor($tenantId);
        $invites = new \App\Services\Effectifs\MemberProfileInviteService();
        $destinations = [];
        try {
            $destinations = (new \App\Services\Effectifs\MemberProfileInviteSender($invites, $this->userRepository, $this->tenantRepository))->destinations($tenantId);
        } catch (\Throwable) {
        }
        $old = Session::get('effectifs_new_member_old');

        return $this->shell('admin.effectifs_workspace.new_member', [
            'title' => 'Ajouter un membre',
            'effectifsNav' => 'nouveau',
            'nmRosterOnly' => \App\Services\Community\TenantTypeConfig::rosterWithoutAccounts((string) ($tenant['tenant_type'] ?? 'full')),
            'nmGradeGroups' => GradeSelectOptions::groups($grades, CommunityLanguages::preferredGradeCountry($this->tenantRepository->getSettings($tenantId))),
            'nmUnits' => $units,
            'nmAccessRoles' => $this->roleRepository->forTenantAccessProfiles($tenantId),
            'nmJobGroups' => $jobGroups,
            'nmJobMax' => max(1, $jobMax),
            'nmLanguages' => CommunityLanguages::communityLanguages($community),
            'nmDefaultLanguage' => CommunityLanguages::communityDefault($community),
            'nmCanGrades' => EffectifsLmsAccess::canManageGrades($gate),
            'nmCanAssign' => EffectifsLmsAccess::canManageAssignments($gate),
            'nmCanRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'nmCanProfiles' => EffectifsLmsAccess::canEditProfiles($gate),
            'nmAccessStatus' => (new \App\Services\Effectifs\NewMemberAccessStatus())->form($tenantId, (int) Session::get('user_id'), EffectifsLmsAccess::canManageStatus($gate)),
            'nmInvitesReady' => $invites->available(),
            'nmDestinations' => $destinations,
            'nmOld' => is_array($old) ? $old : [],
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** Effectifs › Invitations : tous les liens « compléter son profil » de la communauté, façon « partage ». */
    public function invitations(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $invites = new \App\Services\Effectifs\MemberProfileInviteService();
        $rows = (new \App\Services\Effectifs\MemberProfileInviteBoard($invites, null, $this->userRepository))->forTenant($tenantId, 100);
        $destinations = [];
        try {
            $destinations = (new \App\Services\Effectifs\MemberProfileInviteSender($invites, $this->userRepository, $this->tenantRepository))->destinations($tenantId);
        } catch (\Throwable) {
        }

        return $this->shell('admin.effectifs_workspace.invitations', [
            'title' => 'Invitations',
            'effectifsNav' => 'invitations',
            'invRows' => $rows,
            'invReady' => $invites->available(),
            'invCanManage' => EffectifsLmsAccess::canInviteMembers(Gate::getInstance()),
            'invDestinations' => $destinations,
            'invFilter' => (string) $request->query('etat', ''),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** Effectifs › Sans compte : personnes sans compte utilisable, filtrables, avec statut, origine et dernière activité. */
    public function sansComptes(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();
        $directory = new \App\Services\Effectifs\NoAccountDirectory($this->userRepository);
        $everyone = $directory->list($tenantId, true);
        $all = array_values(array_filter($everyone, static fn (array $r): bool => $r['status'] !== \App\Services\Effectifs\NoAccountDirectory::STATUS_ACCOUNT));
        $filters = $this->sansComptesFilters($request);
        $ficheId = (int) $request->query('fiche', 0);
        $selected = null;
        foreach ($everyone as $r) {
            if ($r['id'] === $ficheId) {
                $selected = $r;
                break;
            }
        }
        $history = [];
        $accounts = [];
        if ($selected !== null) {
            $history = (new \App\Repositories\RosterFicheEventRepository())->listForUser($tenantId, $ficheId, 60);
            $allUsers = $this->userRepository->allForTenant($tenantId);
            \App\Support\MemberIdentity::preload($tenantId, array_map(static fn (array $u): int => (int) ($u['id'] ?? 0), $allUsers));
            foreach ($allUsers as $u) {
                if (\App\Services\Effectifs\NoAccountDirectory::classify($u) === null && !\App\Support\RosterOnlyMember::isRosterEmail((string) ($u['email'] ?? '')) && (int) ($u['tenant_id'] ?? 0) === $tenantId) {
                    $label = member_label(['id' => (int) $u['id'], 'display_name' => (string) ($u['display_name'] ?? ''), 'callsign' => (string) ($u['callsign'] ?? '')], 'list', $tenantId);
                    if ($label !== '') {
                        $accounts[] = ['id' => (int) $u['id'], 'label' => $label . ' #' . (int) $u['id']];
                    }
                }
                if (count($accounts) >= 1500) {
                    break;
                }
            }
        }
        $counts = [];
        foreach ($everyone as $r) {
            $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
        }

        return $this->shell('admin.effectifs_workspace.sans_comptes', [
            'title' => 'Sans compte',
            'effectifsNav' => 'sans_comptes',
            'ncRows' => \App\Services\Effectifs\NoAccountDirectory::filter($everyone, $filters),
            'ncImportReport' => Session::getFlash('nc_import_report'),
            'ncEmailReady' => (new \App\Repositories\RosterFicheEmailRepository())->available(),
            'ncExpiredCount' => count(array_filter($all, static fn (array $r): bool => !empty($r['invite_expired']) && $r['email'] !== '')),
            'ncTotal' => count($all),
            'ncCounts' => $counts,
            'ncFilters' => $filters,
            'ncSelected' => $selected,
            'ncHistory' => $history,
            'ncAccounts' => $accounts,
            'ncCanCreate' => EffectifsLmsAccess::canCreateMembers($gate),
            'ncCanEdit' => EffectifsLmsAccess::canEditProfiles($gate),
            'ncCanInvite' => EffectifsLmsAccess::canInviteMembers($gate) && (new \App\Services\Effectifs\MemberProfileInviteService())->available(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** @return array{q: string, status: string, source: string, sort: string} */
    private function sansComptesFilters(Request $request): array
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80, 'UTF-8'),
            'status' => (string) $request->query('statut', ''),
            'source' => (string) $request->query('origine', ''),
            'sort' => (string) $request->query('tri', 'activite'),
        ];
        if (!isset(\App\Services\Effectifs\NoAccountDirectory::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }
        if (!isset(\App\Services\Effectifs\NoAccountDirectory::SOURCES[$filters['source']])) {
            $filters['source'] = '';
        }

        return $filters;
    }

    /** Effectifs › Sans compte › Exporter : le tableau filtré en CSV (mêmes colonnes que l'import, Excel). */
    public function sansComptesExport(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $rows = \App\Services\Effectifs\NoAccountDirectory::filter(
            (new \App\Services\Effectifs\NoAccountDirectory($this->userRepository))->list($tenantId, true),
            $this->sansComptesFilters($request)
        );
        $service = new \App\Services\Effectifs\RosterOnlyMemberService(
            $this->userRepository,
            \App\Core\Container::get(\App\Repositories\UserProfileRepository::class),
            $this->personnelProfileRepository,
            $this->personnelAssignmentRepository,
            $this->gradeRepository,
            $this->unitRepository,
        );
        $csv = $service->exportCsv($tenantId, array_map(static fn (array $r): int => (int) $r['id'], $rows), (new \App\Repositories\RosterFicheEmailRepository())->mapForTenant($tenantId));

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="sans-compte-' . date('Y-m-d') . '.csv"')
            ->header('Cache-Control', 'no-store')
            ->setBody($csv);
    }

    /** Effectifs › Tableaux : constructeur de tableaux de données (source, colonnes, filtres, tri, regroupement). */
    public function tableaux(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }

        return $this->shell('admin.effectifs_workspace.tableaux', array_merge(
            ['title' => 'Tableaux de données', 'effectifsNav' => 'tableaux'],
            (new EffectifsTablesController())->pageData($request)
        ));
    }

    /** Effectifs › Registre des droits : qui détient quel droit, depuis quand, jusqu'à quand (module de formule). */
    public function registreDroits(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!AccessRegisterController::allowedByPlan((int) Session::get('tenant_id'))) {
            return PlanFeatureDenial::upgradeView(AccessRegisterController::FEATURE, 'Standard');
        }

        return $this->shell('admin.effectifs_workspace.registre_droits', array_merge(
            ['title' => 'Registre des droits', 'effectifsNav' => 'registre_droits'],
            (new AccessRegisterController())->pageData($request)
        ));
    }

    /** Effectifs › Positions administratives : celles du site et celles de la communauté. */
    public function positions(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $controller = new AdministrativePositionsController();
        $data = $controller->pageData($tenantId);
        $editId = (int) $request->queryString('modifier', '0');
        foreach ($data['posCustom'] as $p) {
            if ($p['id'] === $editId) {
                $data['posEditing'] = $p;
            }
        }

        return $this->shell('admin.effectifs_workspace.positions', array_merge(
            ['title' => 'Positions administratives', 'effectifsNav' => 'positions'],
            $data
        ));
    }

    /** Effectifs › Circuits de validation (module de formule « workflow_circuits »). */
    public function circuits(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $controller = new WorkflowCircuitsController();
        if (!\App\Core\Container::get(\App\Services\Workflow\WorkflowCircuits::class)->allowedByPlan($tenantId)) {
            return PlanFeatureDenial::upgradeView(\App\Services\Workflow\WorkflowCircuits::FEATURE, 'Pro');
        }

        return $this->shell('admin.effectifs_workspace.circuits', array_merge(
            ['title' => 'Circuits de validation', 'effectifsNav' => 'circuits'],
            $controller->pageData($tenantId)
        ));
    }

    /**
     * Panneau « Invitations » de la fiche (liens, étapes faites, actions).
     *
     * @return array{rows: list<array<string, mixed>>, ready: bool, can_manage: bool, destinations: list<array{id:string, label:string, preferred:bool}>, needs_link: bool}
     */
    private function memberInvitePanel(int $tenantId, array $user): array
    {
        $invites = new \App\Services\Effectifs\MemberProfileInviteService();
        $out = ['rows' => [], 'ready' => $invites->available(), 'can_manage' => EffectifsLmsAccess::canInviteMembers(Gate::getInstance()), 'destinations' => [], 'needs_link' => false];
        if (!$out['ready']) {
            return $out;
        }
        try {
            $out['rows'] = (new \App\Services\Effectifs\MemberProfileInviteBoard($invites, null, $this->userRepository))->forMember($tenantId, (int) $user['id']);
            $out['destinations'] = (new \App\Services\Effectifs\MemberProfileInviteSender($invites, $this->userRepository, $this->tenantRepository))->destinations($tenantId);
        } catch (\Throwable $e) {
            error_log('Effectifs fiche : invitations indisponibles : ' . $e->getMessage());
        }
        $out['needs_link'] = \App\Controllers\Web\ProfileInviteController::needsActivation($user);

        return $out;
    }

    /**
     * Droits SSE effectifs du membre pour le bloc « Accès SSE » de la fiche, ou null : pas le droit de gestion SSE, membre
     * protégé par l'annuaire ou service indisponible (la fiche ne dépend jamais de ce bloc).
     *
     * @return array<string, mixed>|null
     */
    private function sseAccessOverview(int $tenantId, int $memberId): ?array
    {
        try {
            return (new \App\Services\Sse\SseAccessMemberRights())->overview($tenantId, (int) Session::get('user_id'), $memberId);
        } catch (\Throwable) {
            return null;
        }
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
        $assignments = $this->personnelAssignmentRepository->listActiveForUserResolved($id, $tenantId);
        // Demi-affectation (fiche affectée, rosters vides…) : signalée aux gestionnaires avec un bouton de réparation.
        $assignmentNeedsRepair = false;
        if (EffectifsLmsAccess::canManageAssignments(Gate::getInstance())) {
            try {
                $assignmentNeedsRepair = $this->personnelAssignmentRepository->consistency()->findInconsistent($id, $tenantId) !== [];
            } catch (\Throwable) {
                $assignmentNeedsRepair = false;
            }
        }
        $personnelProfile = $this->personnelProfileRepository->getByUserId($id);
        $roleIds = $this->userRepository->listOrganizationRoleIdsForUser($id, $tenantId);
        $allOrgRoles = $this->roleRepository->forTenantOrganization($tenantId);
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId);
        $roleNames = [];
        $roleSlugs = [];
        foreach ($allOrgRoles as $r) {
            if (in_array((int) ($r['id'] ?? 0), $roleIds, true)) {
                $roleSlugs[] = (string) ($r['slug'] ?? '');
            }
        }
        // Tout le monde porte Membre : afficher le niveau le plus élevé, pas le premier de la liste.
        $currentAccessRoleId = CommunityAccessProfiles::currentAccessRoleId($roles, $roleIds);
        foreach ($roles as $r) {
            if ((int) ($r['id'] ?? 0) === $currentAccessRoleId && $currentAccessRoleId > 0) {
                $roleNames[] = (string) ($r['name'] ?? '');
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
        $qualifications = [];
        $qualificationTemporal = null;
        $qualificationBadge = null;
        try {
            $awardRepo = \App\Core\Container::get(\App\Repositories\QualificationAwardRepository::class);
            $qualifications = $awardRepo->listForUser($id, $tenantId);
            $qualificationTemporal = \App\Core\Container::get(\App\Services\Personnel\QualificationTemporalStatusService::class);
            $qualificationBadge = \App\Core\Container::get(\App\Services\Personnel\QualificationBadgeStorageService::class);
        } catch (\Throwable) {
            $qualifications = $this->personnelQualificationRepository
                ? $this->personnelQualificationRepository->listForUser($id, (int) $tenantId)
                : [];
        }
        // Interrupteurs « Visible sur Arma » / « Visible sur le profil » : le membre sur sa propre fiche, ou un responsable
        // des fiches ou du référentiel. Les autres lecteurs voient l'état, sans pouvoir le changer.
        $qualificationDisplay = null;
        try {
            $qualificationDisplay = (new \App\Services\Personnel\QualificationDisplayService())->panel(
                $qualifications,
                url('back-office/ressources/effectifs/membres/' . $id . '/qualifications/affichage'),
                $viewerId === $id || MemberQualificationDisplayController::canManage($gate)
            );
        } catch (\Throwable) {
        }
        $absences = $this->personnelAbsenceRepository->tableExists()
            ? $this->personnelAbsenceRepository->listForUser($tenantId, $id, 40)
            : [];
        $hrDocuments = $this->personnelHrDocumentRepository->tableExists() && \App\Support\EffectifsLmsAccess::canViewHrDocuments(Gate::getInstance())
            ? $this->personnelHrDocumentRepository->listForUser($tenantId, $id, true)
            : [];
        $mobilityRequests = $this->personnelMobilityRequestRepository->tableExists()
            ? $this->personnelMobilityRequestRepository->listForUser($tenantId, $id, 30)
            : [];
        $orgHistory = $this->personnelOrgHistoryRepository->schemaReady()
            ? $this->personnelOrgHistoryRepository->listForUser($tenantId, $id, 30)
            : [];
        $serviceHistory = $this->personnelServiceHistoryRepository->listForUser($id, 40, (int) $tenantId);
        $individualBilansOn = \App\Services\Personnel\RoleplayFollowupSettings::individualBilansEnabled(
            \App\Services\Personnel\RoleplayFollowupSettings::forTenant($tenantId)
        );
        $stageBilans = $individualBilansOn && $this->personnelStageBilanRepository->tableExists()
            ? $this->personnelStageBilanRepository->listForUser($tenantId, $id, 40)
            : [];
        $dutyPosition = '';
        $dutyPositionSlug = null;
        $remainingTrainingDays = 0;
        try {
            $duty = \App\Core\Container::get(\App\Services\Personnel\PersonnelDutyPositionService::class);
            $dutyPositionSlug = $duty->currentDutySlug($tenantId, $id);
            $dutyPosition = \App\Services\Personnel\PersonnelDutyPositionService::labelForSlug((string) $dutyPositionSlug);
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
        $memberAdequacy = null;
        try {
            $memberAdequacy = \App\Services\Organization\OrgAdequacyView::forPerson(
                (new \App\Services\Organization\OrgAdequacyService(
                    $this->unitRepository,
                    $this->userRepository,
                    $this->personnelAssignmentRepository
                ))->evaluate($tenantId),
                $id
            );
        } catch (\Throwable) {
            $memberAdequacy = null;
        }

        $canEditProfiles = EffectifsLmsAccess::canEditProfiles($gate);
        $jobRolePickerGroups = [];
        if ($jobRolesAvailable) {
            try {
                $jobRolePickerGroups = (new JobRolePickerService($this->personnelJobRoleRepository))->groups(
                    $tenantId,
                    array_map(static fn (array $r): int => (int) ($r['personnel_job_role_id'] ?? 0), $jobRoles)
                );
            } catch (\Throwable $e) {
                error_log('Effectifs member: job role picker failed: ' . $e->getMessage());
            }
        }

        $languageCommunity = $this->communitySettingsFor($tenantId);
        $memberLanguage = $this->memberLanguages()->resolvedForUsers($tenantId, [$id], $languageCommunity)[$id]
            ?? CommunityLanguages::resolveMember(null, null, $languageCommunity);

        $canViewSanctions = EffectifsLmsAccess::canViewSanctions($gate);
        $insights = [];
        try {
            $insights = $this->memberInsights()->build($tenantId, $row, $qualifications, $assignments, $canViewSanctions);
        } catch (\Throwable $e) {
            error_log('Effectifs member: insights failed: ' . $e->getMessage());
        }

        // Décorations : liste du membre (visible comme l'étagère de la fiche) ; le choix des décorations à décerner
        // n'est chargé que pour qui peut en décerner.
        $canAwardDecorations = EffectifsLmsAccess::canAwardDecorations($gate);
        $memberAwards = [];
        $awardDefinitions = [];
        try {
            $memberAwards = (new \App\Repositories\PersonnelAwardRepository())->listForPersonnel($tenantId, $id);
            if ($canAwardDecorations) {
                $awardDefinitions = (new \App\Repositories\AwardDefinitionRepository())->listForTenant($tenantId, false);
            }
        } catch (\Throwable $e) {
            error_log('Effectifs member: decorations failed: ' . $e->getMessage());
        }

        // Chronologie : fusion en lecture de sources déjà chargées ci-dessus, restreintes à ce que le lecteur voit
        // sur la fiche (sanctions, documents RH et départ suivent leur droit).
        $canManageStatus = EffectifsLmsAccess::canManageStatus($gate);
        $chronologyType = MemberChronology::sanitizeType((string) $request->queryString('type', ''));
        // Fiabilisation des données : confirmations du membre (lecture seule) et mention « Fiabilisée le … » de la fiche.
        $campaignHistory = [];
        $dataReliability = null;
        try {
            $campaigns = \App\Core\Container::get(\App\Repositories\DataCampaignRepository::class);
            if ($campaigns->schemaReady()) {
                $campaignHistory = array_values(array_filter($campaigns->historyForUser($tenantId, $id), static fn (array $h): bool => trim((string) ($h['confirmed_at'] ?? '')) !== ''));
                $dataReliability = \App\Core\Container::get(\App\Services\DataCampaign\DataCampaignService::class)->ficheStatus($tenantId, $id);
            }
        } catch (\Throwable $e) {
            error_log('Effectifs member: fiabilisation failed: ' . $e->getMessage());
        }
        // Vérifications de disponibilité : lancer = gérer le suivi des dossiers ; lire l'historique = le consulter.
        $canCheckAvailability = MemberAvailabilityCheckController::canCheck($gate);
        $availabilityChecks = [];
        $availabilityChecksReady = false;
        if ($canCheckAvailability || MemberAvailabilityCheckController::canView($gate)) {
            try {
                $checkRepo = new \App\Repositories\MemberAvailabilityCheckRepository();
                $availabilityChecksReady = $checkRepo->schemaReady();
                $availabilityChecks = \App\Services\Effectifs\Availability\AvailabilityCheckHistory::present($tenantId, $checkRepo->listForUser($tenantId, $id, 5));
            } catch (\Throwable $e) {
                error_log('Effectifs member: vérifications de disponibilité : ' . $e->getMessage());
            }
        }
        $chronology = MemberChronology::build([
            'availability_checks' => $availabilityChecks,
            'grades' => is_array($insights['grades']['entries'] ?? null) ? $insights['grades']['entries'] : [],
            'org_history' => $orgHistory,
            'service_history' => $serviceHistory,
            'stage_bilans' => $stageBilans,
            'qualifications' => $qualifications,
            'awards' => $memberAwards,
            'elevations' => $elevationHistory,
            'sanctions' => $canViewSanctions && is_array($insights['sanctions']['items'] ?? null) ? $insights['sanctions']['items'] : [],
            'absences' => $absences,
            'mobility' => $mobilityRequests,
            'hr_documents' => $hrDocuments,
            'data_campaigns' => $campaignHistory,
            'departure' => $canManageStatus ? $latestDeparture : null,
            'absence_reasons' => PersonnelAbsenceRepository::REASON_LABELS,
            'mobility_types' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'document_types' => PersonnelHrDocumentRepository::DOC_TYPE_LABELS,
            'departure_reasons' => ['end_of_engagement' => 'Fin d’engagement', 'exclusion' => 'Exclusion', 'pause' => 'Pause', 'other' => 'Autre'],
        ]);

        // Onglet Disponibilité : sessions de jeu qui comptent (seuil de la communauté), lues en une requête.
        $memberAvailability = null;
        try {
            $sessionRepo = \App\Core\Container::get(\App\Repositories\PlayerSessionRepository::class);
            $sessionRules = \App\Services\Personnel\RoleplayGameSessionSettings::playerSessionRules($tenantId);
            $memberAvailability = $sessionRepo->panelForUser(
                $tenantId,
                $id,
                $sessionRules['min_minutes'] * 60,
                $sessionRules['gap_minutes'] * 60,
                time(),
                $sessionRepo->tenantTimezone($tenantId)
            );
        } catch (\Throwable $e) {
            error_log('Effectifs member: disponibilité failed: ' . $e->getMessage());
        }
        // Heures de jeu remontées par le mod (cumul) : repli de l'onglet quand aucune session détaillée n'existe.
        $memberPlaytime = null;
        try {
            $ptSummary = \App\Core\Container::get(\App\Repositories\ArmaPlaytimeRepository::class)->getSummaryForUser($tenantId, $id);
            if (is_array($ptSummary) && $ptSummary['total_seconds'] > 0) {
                $ptMin = (int) ($memberAvailability['min_minutes'] ?? 60) * 60;
                $ptTz = (string) ($memberAvailability['tz'] ?? 'Europe/Paris');
                $memberPlaytime = [
                    'summary' => $ptSummary,
                    'cell' => \App\Services\Effectifs\Availability\PlaytimeAvailability::cell($ptSummary, $ptMin, time(), $ptTz),
                    'tz' => $ptTz,
                ];
            }
        } catch (\Throwable $e) {
            error_log('Effectifs member: heures remontées failed: ' . $e->getMessage());
        }
        $availabilitySettingsUrl = \App\Support\BackOfficeRightGuard::allows(\App\Support\BackOfficeRightGuard::MANAGERS)
            ? url('back-office/roleplay/sessions') . '#regles'
            : '';

        $memberPapers = MemberIdentityPapersController::sectionFor($tenantId, $user);
        // Onglet « Suivi du dossier » : null (onglet absent) sans le droit de consulter ; une erreur de lecture ne casse pas la fiche.
        $memberSuivi = null;
        try {
            $suiviOwners = [];
            if ((int) ($commandChain['reports_to_id'] ?? 0) > 0) {
                $suiviOwners[] = ['id' => (int) $commandChain['reports_to_id'], 'label' => (string) ($commandChain['reports_to_label'] ?? '') . ' (supérieur direct)'];
            }
            $memberSuivi = MemberDossierSuiviController::tabData($tenantId, $id, $viewerId, (string) ($row['display_name'] ?? $row['callsign'] ?? 'membre'), $request, $suiviOwners);
        } catch (\Throwable $e) {
            error_log('Effectifs member: suivi du dossier failed: ' . $e->getMessage());
        }
        $ficheTabs = MemberFicheTabs::visible(['papers' => is_array($memberPapers), 'suivi' => is_array($memberSuivi)]);
        // ?ouvrir=suivi (liens des tâches et de la vue équipe) ouvre l'onglet du suivi, sans JavaScript.
        $requestedTab = (string) $request->queryString('onglet', '');
        if ($requestedTab === '' && $request->queryString('ouvrir', '') === 'suivi') {
            $requestedTab = 'suivi';
        }
        $ficheTab = MemberFicheTabs::resolve($requestedTab, $ficheTabs);

        return $this->shell('admin.effectifs_workspace.member', [
            'title' => 'Fiche membre',
            'memberSpecialties' => (new MilitarySpecialtyController())->memberPanel($tenantId, $id),
            // Prénom et nom du personnage (user_profiles), indicatif, trigramme : nom affiché par la règle unique.
            'memberIdentity' => $this->rosterIdentities($tenantId, [$id])[$id] ?? [],
            'memberSuivi' => $memberSuivi,
            'ficheTabs' => $ficheTabs,
            'ficheTab' => $ficheTab,
            'memberAvailability' => $memberAvailability,
            'memberPlaytime' => $memberPlaytime,
            'availabilitySettingsUrl' => $availabilitySettingsUrl,
            'canCheckAvailability' => $canCheckAvailability,
            'availabilityChecks' => $availabilityChecks,
            'availabilityChecksReady' => $availabilityChecksReady,
            'memberChronology' => $chronology,
            'dataReliability' => $dataReliability,
            'chronologyType' => $chronologyType,
            'canViewHrDocuments' => EffectifsLmsAccess::canViewHrDocuments($gate),
            // Bloc « Accès SSE » : réservé aux gestionnaires SSE (null sinon : rien n'est affiché ni calculé).
            'sseAccess' => $this->sseAccessOverview($tenantId, $id),
            'canAwardDecorations' => $canAwardDecorations,
            'memberAwards' => $memberAwards,
            'awardDefinitions' => $awardDefinitions,
            'memberLanguage' => $memberLanguage,
            'communityLanguageCodes' => CommunityLanguages::communityLanguages($languageCommunity),
            'effectifsNav' => 'roster',
            'member' => $row,
            'memberAssignments' => $assignments,
            'memberPersonnelProfile' => $personnelProfile,
            'memberRoleNames' => $roleNames,
            'currentAccessKey' => $currentAccessKey,
            'currentAccessRoleId' => $currentAccessRoleId,
            'accessProfiles' => CommunityAccessProfiles::definitions(),
            'memberJobRoles' => $jobRoles,
            'memberCareerDiscrepancies' => (new \App\Services\Personnel\CareerConsistencyService(\App\Core\Database::getPdo()))->listForTenant($tenantId, (int) $row['id']),
            'memberJobDecorations' => (new JobRoleEnrichmentService())->decorations($tenantId),
            'memberJobPrerequisites' => \App\Services\Personnel\JobPrerequisiteViewData::forMember($tenantId, (int) $row['id'], $jobRoles, $this->jobTenantSettings($tenantId)),
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
            'jobRolePickerGroups' => $jobRolePickerGroups,
            'civilStatusOptions' => $this->civilStatusOptions(),
            'canManageStatus' => $canManageStatus,
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'assignmentNeedsRepair' => $assignmentNeedsRepair,
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'canManageGrades' => EffectifsLmsAccess::canManageGrades($gate),
            'gradeSchedules' => $this->gradeSchedulesForMember($tenantId, (int) $row['id']),
            'gradeSchedulesReady' => \App\Core\Container::get(\App\Repositories\MemberGradeScheduleRepository::class)->tableExists(),
            'canRequestElevation' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients !== [],
            'elevationNoRecipients' => EffectifsLmsAccess::canRequestElevation($gate) && $elevationRecipients === [],
            'elevationCatalog' => $this->elevationCatalogForTenant($tenantId),
            'csrfToken' => Csrf::token(),
            'orgFoundingDate' => $this->seniorityPrePlatform->getOrgFoundingDate($tenantId),
            'memberRoleIds' => $roleIds,
            'memberQualifications' => $qualifications,
            'qualificationProgress' => $this->memberQualificationProgress($tenantId, $id),
            'qualificationSuggestions' => $this->memberQualificationSuggestions($tenantId, $id),
            'qualificationSuggestionRights' => $this->qualificationSuggestionRights($tenantId),
            'qualificationSuggestionUserId' => $id,
            'progressNames' => $this->qualificationNames($tenantId),
            'qualificationTemporal' => $qualificationTemporal,
            'qualificationBadge' => $qualificationBadge,
            'memberAbsences' => $absences,
            'memberHrDocuments' => $hrDocuments,
            'memberMobilityRequests' => $mobilityRequests,
            'memberOrgHistory' => $orgHistory,
            'memberServiceHistory' => $serviceHistory,
            'memberStageBilans' => $stageBilans,
            'memberIndividualBilansOn' => $individualBilansOn,
            'qualificationDisplay' => $qualificationDisplay,
            'dutyPosition' => $dutyPosition,
            'dutyPositionSlug' => $dutyPositionSlug,
            'remainingTrainingDays' => $remainingTrainingDays,
            'memberCommandChain' => $commandChain,
            'memberAdequacy' => $memberAdequacy,
            'memberInsights' => $insights,
            'canViewSanctions' => $canViewSanctions,
            'canManageSanctions' => EffectifsLmsAccess::canManageSanctions($gate),
            'canSetElevationCriteria' => EffectifsLmsAccess::canSetElevationCriteria($gate),
            'canEditOrbat' => $gate->allows('organization.orbat.manage') || $gate->allows('admin.organization'),
            'hrDocumentTypeLabels' => PersonnelHrDocumentRepository::DOC_TYPE_LABELS,
            'mobilityTypeLabels' => PersonnelMobilityRequestRepository::TYPE_LABELS,
            'absenceReasonLabels' => PersonnelAbsenceRepository::REASON_LABELS,
            'backOfficePageCss' => ['effectifs_lms.css', 'personnel-dossier.css', 'back-office-effectifs-workspace.css', 'back-office-effectifs-refonte.css', 'decorations-kit.css', 'effectifs-pages.css', 'back-office-effectifs-fiche.css', 'back-office-effectifs-maquette.css', 'back-office-effectifs-invitations.css', 'qualifications-matrix.css', 'effectifs-kit.css', 'sse-papers.css', 'back-office-member-papers.css', 'access-register.css', 'eff-member-sse-access.css', 'eff-suivi.css', 'qualification-display.css', 'eff-availability-check.css', 'back-office-bands.css', 'eff-civil-dialog.css'],
            'loadDecorationsKit' => true,
            'effMemberUnitCode' => (new UnitCodeService())->forUnit($tenantId, (int) ($row['unit_id'] ?? 0)),
            'memberInvitePanel' => $this->memberInvitePanel($tenantId, $user),
            // Onglet « Identité & papiers » (COMSPEC IDENTITY) : null si le responsable n'y a pas accès.
            'memberPapers' => $memberPapers,
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
            // Même notification de changement de structure que l'affectation d'un seul membre.
            $beforeSnap = null;
            try {
                $beforeSnap = $this->structureChangeNotification->snapshot($tenantId, $id);
            } catch (\Throwable) {
            }
            try {
                // Affectation, effectif de l'unité et unité du dossier écrits ensemble (une transaction).
                $this->personnelProfileRepository->ensureRecord($id);
                $currentAssignment = $this->personnelAssignmentRepository->getPrimaryAssignment($id, $tenantId);
                $roleName = trim((string) ($currentAssignment['role_name'] ?? ''));
                $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                    $id,
                    $unitId > 0 ? $unitId : null,
                    $roleName !== '' ? $roleName : 'Membre',
                    $assignmentReason !== '' ? $assignmentReason : null,
                    $tenantId
                );
                $this->adminAuditService->logUserUpdated(
                    $tenantId,
                    $actorId,
                    $id,
                    'affectation',
                    $unitId > 0 ? ('unit:' . $unitId) : 'unit:none'
                );
                $updated++;
                if ($beforeSnap !== null) {
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
                }
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
    /**
     * Suivi des dossiers : tous les membres actifs avec leur progression et leur prochaine étape, filtrables et triables.
     * Lecture : droit de consulter le suivi (effectifs.suivi.view ou gestion du dossier) ; la relance groupée demande le droit de modifier.
     */
    public function suivi(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canViewSuivi($gate)) {
            Session::flash('error', 'Vous n’êtes pas habilité à consulter le suivi des dossiers.');

            return Response::redirect(effectifs_workspace_url());
        }
        $tenantId = (int) Session::get('tenant_id');
        $board = (new MemberDossierSuiviController())->teamBoard($tenantId, $request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = \App\Services\Effectifs\Dossier\MemberDossierTeamBoard::PER_PAGE;
        $total = count($board['rows']);

        return $this->shell('admin.effectifs_workspace.suivi', [
            'title' => 'Étapes des dossiers',
            'effectifsNav' => 'suivi',
            'suiviRows' => array_slice($board['rows'], ($page - 1) * $perPage, $perPage),
            'suiviTotals' => $board['totals'],
            'suiviUnits' => $board['units'],
            'suiviFilters' => $board['filters'],
            'suiviCount' => $total,
            'suiviPage' => min($page, max(1, (int) ceil($total / $perPage))),
            'suiviPages' => max(1, (int) ceil($total / $perPage)),
            'suiviViewerId' => (int) Session::get('user_id'),
            'canManageSuivi' => EffectifsLmsAccess::canManageSuivi($gate),
            'bulkMax' => MemberDossierSuiviController::BULK_MAX,
            'csrfToken' => Csrf::token(),
            'backOfficePageCss' => ['effectifs_lms.css', 'personnel-dossier.css', 'back-office-effectifs-workspace.css', 'back-office-effectifs-refonte.css', 'effectifs-pages.css', 'back-office-effectifs-maquette.css', 'eff-suivi.css'],
        ]);
    }

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
        // Depuis le tableur (fenêtre « Emploi »), on revient au tableur ; sinon à la fiche.
        $returnUrl = trim((string) $request->input('return_url', ''));
        $redirect = $this->isAllowedMemberReturnUrl($returnUrl, $id) ? $returnUrl : effectifs_workspace_url('membres/' . $id);
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

        // Les valeurs « cat:slug » désignent une fonction du référentiel pas encore copiée dans la communauté :
        // elle est créée à la volée dans son domaine.
        $picker = new JobRolePickerService($this->personnelJobRoleRepository);
        $rawIds = $request->input('job_role_ids', []);
        $settings = PersonnelJobRoleAssignmentsSettings::resolve($this->tenantRepository->getSettings($tenantId));
        $maxRoles = (int) $settings['max_roles_per_member'];
        $rawIds = is_array($rawIds) ? array_slice(array_values($rawIds), 0, max(1, $maxRoles)) : [];
        try {
            $ids = $picker->resolveValues($tenantId, $rawIds);
        } catch (\Throwable $e) {
            error_log('Effectifs functions: catalog role materialization failed: ' . $e->getMessage());
            Session::flash('error', 'Impossible d’ajouter une fonction du référentiel. Réessayez.');

            return Response::redirect($redirect);
        }
        $ids = array_slice($ids, 0, $maxRoles);
        $rawPrimary = trim((string) $request->input('primary_job_role_id', ''));
        $primaryId = 0;
        if ($rawPrimary !== '') {
            $primaryId = str_starts_with($rawPrimary, JobRolePickerService::CATALOG_PREFIX)
                ? $picker->materializeCatalogRole($tenantId, substr($rawPrimary, strlen(JobRolePickerService::CATALOG_PREFIX)))
                : (int) $rawPrimary;
        }
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

        // Prérequis des emplois ajoutés : avertissement, refus (communauté en mode bloquant) ou dérogation motivée.
        $prereqWarning = '';
        try {
            $prereq = (new \App\Services\Personnel\JobPrerequisiteService())->gate(
                $tenantId,
                $id,
                array_values(array_diff($ids, array_keys($details))),
                (string) $request->input('prereq_waiver_reason', ''),
                \App\Services\Personnel\JobPrerequisiteViewData::canWaive(Gate::getInstance()),
                (bool) $settings['prerequisites_blocking'],
                (int) Session::get('user_id')
            );
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($redirect);
        } catch (\Throwable $e) {
            error_log('Effectifs functions: prerequisites check failed: ' . $e->getMessage());
            $prereq = ['allowed' => true, 'unmet' => [], 'waived' => [], 'warned' => []];
        }
        if (!$prereq['allowed']) {
            Session::flash('error', 'Prérequis non remplis pour ' . $this->jobRoleNames($tenantId, array_keys($prereq['unmet'])) . '. Indiquez un motif de dérogation pour attribuer quand même.');

            return Response::redirect($redirect);
        }
        if ($prereq['waived'] !== []) {
            $prereqWarning = 'Dérogation enregistrée pour ' . $this->jobRoleNames($tenantId, $prereq['waived']) . '.';
        } elseif ($prereq['warned'] !== []) {
            $prereqWarning = 'Attention : prérequis non remplis pour ' . $this->jobRoleNames($tenantId, $prereq['warned']) . '.';
        }

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

        // A dossier job is independent from the member’s ORBAT assignment.

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
        if ($prereqWarning !== '') {
            Session::flash('warning', $prereqWarning);
        }

        return Response::redirect($redirect);
    }

    /** Réglages de la communauté pour les prérequis ; une lecture impossible laisse les prérequis indicatifs. */
    private function jobTenantSettings(int $tenantId): array
    {
        try {
            return $this->tenantRepository->getSettings($tenantId);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Noms d’emplois pour un message (« Médecin, JTAC »). */
    private function jobRoleNames(int $tenantId, array $roleIds): string
    {
        $names = [];
        foreach ($roleIds as $roleId) {
            $role = $this->personnelJobRoleRepository->findRoleById((int) $roleId, $tenantId);
            $names[] = '« ' . (string) ($role['name'] ?? ('#' . (int) $roleId)) . ' »';
        }

        return implode(', ', $names);
    }

    /** Rôle tenu dans l’unité principale (fenêtre « Rôle » du tableur). Même droit que les affectations. */
    public function updateMemberAssignmentRole(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!EffectifsLmsAccess::canManageAssignments(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier le rôle dans l’unité.');

            return Response::redirect(effectifs_workspace_url());
        }
        $redirect = $this->redirectAfterMemberAction($request, $id);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($redirect);
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($id < 1 || !$this->userRepository->findById($id, $tenantId)) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $roleName = trim((string) $request->input('role_name', ''));
        if ($roleName === '') {
            Session::flash('error', 'Indiquez le rôle tenu dans l’unité (ex. chef de groupe, tireur, adjoint).');

            return Response::redirect($redirect);
        }
        $profile = $this->personnelProfileRepository->getByUserId($id, $tenantId) ?? [];
        $fallbackUnitId = (int) ($profile['primary_unit_id'] ?? 0);
        try {
            $before = $this->structureChangeNotification->snapshot($tenantId, $id);
            $ok = $this->personnelAssignmentRepository->changePrimaryRoleName($id, $tenantId, $roleName, $fallbackUnitId > 0 ? $fallbackUnitId : null);
            if (!$ok) {
                Session::flash('error', 'Affectez d’abord ce membre à une unité, puis indiquez son rôle.');

                return Response::redirect($redirect);
            }
            $actorId = (int) Session::get('user_id');
            $this->adminAuditService->logUserUpdated($tenantId, $actorId, $id, 'role_affectation', mb_substr($roleName, 0, 120));
            try {
                $this->structureChangeNotification->notifyFromSnapshots(
                    $tenantId,
                    $id,
                    $actorId > 0 ? $actorId : null,
                    $before,
                    $this->structureChangeNotification->snapshot($tenantId, $id)
                );
            } catch (\Throwable) {
            }
        } catch (\Throwable $e) {
            error_log('Effectifs assignment role: ' . $e->getMessage());
            Session::flash('error', 'Impossible d’enregistrer le rôle. Réessayez ou ouvrez le dossier.');

            return Response::redirect($redirect);
        }
        Session::flash('success', 'Rôle dans l’unité enregistré.');

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
        $wanted = trim((string) $request->input('position', ''));
        if ($wanted !== '') {
            [$level, $message] = self::positionChangeFlash($duty, $duty->applyPosition($tenantId, $id, $wanted, (int) Session::get('user_id')), $wanted, $tenantId, $id);
            Session::flash($level, $message);

            return Response::redirect(effectifs_workspace_url('membres/' . $id) . '#statut');
        }
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

    /**
     * Message après un changement de position choisi par un responsable (fiche effectifs ou fiche membre).
     *
     * @return array{0: string, 1: string}
     */
    public static function positionChangeFlash(\App\Services\Personnel\PersonnelDutyPositionService $duty, string $result, string $slug, int $tenantId, int $userId): array
    {
        $label = \App\Support\AdministrativePositionCatalog::label($slug);

        return match ($result) {
            \App\Services\Personnel\PersonnelDutyPositionService::RESULT_CHANGED => ['success', 'Position administrative mise à jour : ' . $label . '. Le grade, les fonctions et les niveaux d’accès sont inchangés.'],
            \App\Services\Personnel\PersonnelDutyPositionService::RESULT_UNCHANGED => ['warning', 'Le membre a déjà cette position.'],
            \App\Services\Personnel\PersonnelDutyPositionService::RESULT_TRAINING_MINIMUM => ['warning', 'Formation obligatoire : encore ' . $duty->remainingTrainingDays($tenantId, $userId) . ' jour(s) avant le passage en service actif.'],
            default => ['error', 'Position inconnue ou membre introuvable.'],
        };
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
        $oldRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id, $tenantId);
        // Un seul niveau d’accès : on remplace l’ancien, sans toucher aux autres rôles
        // (position de service, statuts) que la personne garde.
        $accessIds = array_map(
            static fn (array $r): int => (int) ($r['id'] ?? 0),
            $this->roleRepository->forTenantAccessProfiles($tenantId, true)
        );
        $roleIds = [$targetId];
        foreach ($oldRoleIds as $oldId) {
            if (!in_array($oldId, $accessIds, true) && !in_array($oldId, $roleIds, true)) {
                $roleIds[] = $oldId;
            }
        }
        if (!$this->roleRepository->canAssignInTenantAdminContext($targetId, $tenantId)) {
            Session::flash('error', 'Un rôle sélectionné ne peut pas être attribué depuis Effectifs.');

            return Response::redirect($this->redirectAfterMemberAction($request, $id));
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

    /**
     * État civil du personnage (lieu de naissance, situation familiale…) saisi par un gérant ou les RH
     * depuis la fiche membre. L'identité civile réelle reste dans « Mon compte ».
     */
    /**
     * Choix proposés dans la fenêtre « État civil » de la fiche membre.
     *
     * @return array<string, array<string, string>>
     */
    private function civilStatusOptions(): array
    {
        $out = [];
        foreach (\App\Services\Personnel\PersonnelCorrectionRequestService::choiceCatalog() as $key => $pairs) {
            if (!in_array($key, ['family_situation', 'sex', 'blood_type'], true) || !is_array($pairs)) {
                continue;
            }
            foreach ($pairs as $pair) {
                $value = trim((string) ($pair['value'] ?? ''));
                if ($value !== '') {
                    $out[$key][$value] = (string) ($pair['label'] ?? $value);
                }
            }
        }

        return $out;
    }

    /** Planifie, modifie (schedule_id fourni) ou annule (action=cancel) un changement de grade à une date. Mêmes droits que « Changer le grade ». */
    public function scheduleMemberGrade(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageGrades(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier le grade.');

            return Response::redirect(effectifs_workspace_url());
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectAfterMemberAction($request, $id));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($id < 1 || $this->userRepository->findById($id, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $service = \App\Core\Container::get(\App\Services\Personnel\MemberGradeScheduleService::class);
        $scheduleId = (int) $request->input('schedule_id', 0);
        $action = (string) $request->input('schedule_action', 'save');
        try {
            if ($scheduleId > 0 && !$this->scheduleBelongsToMember($tenantId, $scheduleId, $id)) {
                throw new \RuntimeException('Ce changement de grade n’est plus à venir.');
            }
            if ($action === 'cancel') {
                $service->cancel($tenantId, $scheduleId, $actorId);
                $this->adminAuditService->logUserUpdated($tenantId, $actorId, $id, 'grade_planifie_annule', (string) $scheduleId);
                Session::flash('success', 'Changement de grade planifié annulé.');
            } else {
                $args = [
                    $tenantId,
                    $scheduleId > 0 ? $scheduleId : $id,
                    (int) $request->input('grade_id', 0),
                    (string) $request->input('scheduled_date', ''),
                    (string) $request->input('scheduled_time', ''),
                    (string) $request->input('reason', ''),
                    $actorId,
                ];
                if ($scheduleId > 0) {
                    $service->update(...$args);
                    $this->adminAuditService->logUserUpdated($tenantId, $actorId, $id, 'grade_planifie_modifie', (string) $scheduleId);
                    Session::flash('success', 'Changement de grade planifié modifié.');
                } else {
                    $newId = $service->schedule($tenantId, $id, $args[2], $args[3], $args[4], $args[5], $actorId);
                    $this->adminAuditService->logUserUpdated($tenantId, $actorId, $id, 'grade_planifie', (string) $newId);
                    Session::flash('success', 'Changement de grade planifié.');
                }
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect($this->redirectAfterMemberAction($request, $id));
    }

    private function scheduleBelongsToMember(int $tenantId, int $scheduleId, int $userId): bool
    {
        $row = \App\Core\Container::get(\App\Repositories\MemberGradeScheduleRepository::class)->find($scheduleId, $tenantId);

        return $row !== null && (int) $row['user_id'] === $userId;
    }

    /** @return list<array<string, mixed>> */
    private function gradeSchedulesForMember(int $tenantId, int $userId): array
    {
        try {
            return \App\Core\Container::get(\App\Services\Personnel\MemberGradeScheduleService::class)->forMember($tenantId, $userId);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Langue de jeu du joueur (langue principale + autres langues comprises) dans la communauté. */
    public function updateMemberLanguages(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $redirect = effectifs_workspace_url('membres/' . $id) . '#langues';
        if (!EffectifsLmsAccess::canEditProfiles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les dossiers.');

            return Response::redirect($redirect);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($redirect);
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $id > 0 ? $this->userRepository->findById($id, $tenantId) : null;
        if ($user === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $others = $request->input('other_langs', []);
        $this->memberLanguages()->save(
            $tenantId,
            $id,
            CommunityLanguages::normalize($request->input('primary_lang', '')),
            CommunityLanguages::normalizeList(is_array($others) ? $others : []),
            (int) Session::get('user_id') ?: null
        );
        $this->adminAuditService->logUserUpdated($tenantId, (int) Session::get('user_id'), $id, 'langues', 'primary_lang, other_langs');
        Session::flash('success', 'Langue du joueur mise à jour.');

        return Response::redirect($redirect);
    }

    private function memberLanguages(): MemberLanguageRepository
    {
        return \App\Core\Container::get(MemberLanguageRepository::class);
    }

    private function memberInsights(): \App\Services\Effectifs\MemberFileInsightsService
    {
        return new \App\Services\Effectifs\MemberFileInsightsService(
            $this->userRepository,
            $this->unitRepository,
            $this->personnelAssignmentRepository,
            $this->personnelJobRoleRepository,
            $this->gradeRepository,
            $this->tenantRepository,
            $this->senioritySummary
        );
    }

    /**
     * Avertissement saisi depuis la fiche membre. Même registre que l’écran « Restrictions membres »
     * (mesure de niveau communauté, journalisée), réservé aux personnes habilitées aux restrictions.
     */
    public function declareSanction(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $back = effectifs_workspace_url('membres/' . $id) . '#sanctions';
        if (!EffectifsLmsAccess::canManageSanctions(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à enregistrer une mesure disciplinaire.');

            return Response::redirect($id > 0 ? $back : effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($id < 1 || $this->userRepository->findById($id, $tenantId) === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        if ($id === $actorId) {
            Session::flash('error', 'Vous ne pouvez pas vous sanctionner vous-même.');

            return Response::redirect($back);
        }
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            Session::flash('error', 'Indiquez le motif de l’avertissement.');

            return Response::redirect($back);
        }
        try {
            $this->moderationService()->applySanction($tenantId, $actorId, $id, 'warn', mb_substr($reason, 0, 1000), null, [], 'tenant');
            Session::flash('success', 'Avertissement enregistré sur le dossier du membre.');
        } catch (\Throwable $e) {
            error_log('Effectifs member: sanction failed: ' . $e->getMessage());
            Session::flash('error', 'L’avertissement n’a pas pu être enregistré.');
        }

        return Response::redirect($back);
    }

    /** Levée d’une mesure disciplinaire de la communauté depuis la fiche membre. */
    public function liftSanction(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $sanctionId = (int) ($params['sanctionId'] ?? 0);
        $back = effectifs_workspace_url('membres/' . $id) . '#sanctions';
        if (!EffectifsLmsAccess::canManageSanctions(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à lever une mesure disciplinaire.');

            return Response::redirect($id > 0 ? $back : effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        $action = $sanctionId > 0 ? $this->moderationRepository()->findActionById($tenantId, $sanctionId) : null;
        if ($action === null || (int) ($action['target_user_id'] ?? 0) !== $id) {
            Session::flash('error', 'Mesure introuvable pour ce membre.');

            return Response::redirect($back);
        }
        $ok = $this->moderationService()->revokeForScope($tenantId, $sanctionId, (int) Session::get('user_id'), 'tenant');
        Session::flash($ok ? 'success' : 'error', $ok ? 'Mesure levée. Elle reste visible dans l’historique du dossier.' : 'Cette mesure est déjà levée ou relève de l’administration du site.');

        return Response::redirect($back);
    }

    private function moderationService(): \App\Services\Moderation\ModerationService
    {
        return \App\Core\Container::get(\App\Services\Moderation\ModerationService::class);
    }

    private function moderationRepository(): \App\Repositories\ModerationRepository
    {
        return \App\Core\Container::get(\App\Repositories\ModerationRepository::class);
    }

    /** Réglage des critères d’éligibilité à une élévation (communauté courante). */
    public function elevationCriteria(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $qualifications = [];
        try {
            $qualifications = (new \App\Repositories\QualificationDefinitionRepository())->listForTenant($tenantId);
        } catch (\Throwable) {
        }

        return $this->shell('admin.effectifs_workspace.elevation_criteria', [
            'title' => 'Critères d’élévation',
            'effectifsNav' => 'elevations',
            'criteria' => \App\Services\Effectifs\ElevationEligibility::settings($this->tenantRepository->getSettings($tenantId)),
            'qualificationOptions' => $qualifications,
            'canSetElevationCriteria' => EffectifsLmsAccess::canSetElevationCriteria(Gate::getInstance()),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function saveElevationCriteria(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $target = effectifs_workspace_url('elevations/criteres');
        if (!EffectifsLmsAccess::canSetElevationCriteria(Gate::getInstance())) {
            Session::flash('error', 'Seules les personnes qui gèrent les grades peuvent régler les critères d’élévation.');

            return Response::redirect($target);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($target);
        }
        $tenantId = (int) Session::get('tenant_id');
        $allowed = [];
        try {
            foreach ((new \App\Repositories\QualificationDefinitionRepository())->listForTenant($tenantId) as $d) {
                $allowed[] = (int) ($d['id'] ?? 0);
            }
        } catch (\Throwable) {
        }
        $rawQuals = $request->input('required_qualification_ids');
        $criteria = \App\Services\Effectifs\ElevationEligibility::sanitize([
            'min_months_in_grade' => $request->input('min_months_in_grade', 0),
            'min_presences_90' => $request->input('min_presences_90', 0),
            'min_presence_rate_90' => $request->input('min_presence_rate_90', 0),
            'required_qualification_ids' => is_array($rawQuals) ? $rawQuals : [],
        ], $allowed);
        // Réglages relus puis réécrits en entier : la liste des qualifications remplace l’ancienne.
        $settings = $this->tenantRepository->getSettings($tenantId);
        $previous = \App\Services\Effectifs\ElevationEligibility::settings($settings);
        $settings[\App\Services\Effectifs\ElevationEligibility::SETTINGS_KEY] = $criteria;
        $this->tenantRepository->replaceSettings($tenantId, $settings);
        try {
            \App\Core\Container::get(\App\Services\Audit\AuditService::class)->log(
                \App\Services\Audit\AuditAction::ELEVATION_CRITERIA_UPDATED,
                $tenantId,
                (int) Session::get('user_id'),
                'tenant',
                $tenantId,
                json_encode($previous, JSON_UNESCAPED_UNICODE) ?: null,
                json_encode($criteria, JSON_UNESCAPED_UNICODE) ?: null
            );
        } catch (\Throwable) {
        }
        Session::flash('success', 'Critères d’élévation enregistrés. Ils s’affichent sur chaque fiche membre.');

        return Response::redirect($target);
    }

    /** @return array<string, mixed> */
    private function communitySettingsFor(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);

        return is_array($settings['community'] ?? null) ? $settings['community'] : [];
    }

    public function updateMemberCivilStatus(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $redirect = effectifs_workspace_url('membres/' . $id) . '#modifier-dossier';
        if (!EffectifsLmsAccess::canEditProfiles(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les dossiers.');

            return Response::redirect($redirect);
        }
        // Erreur de saisie : affichée dans la fenêtre « État civil du personnage », rouverte au retour sur la fiche.
        $dialogUrl = effectifs_member_url($id, 'etat-civil');
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('eff_civil_error', ['message' => 'Session expirée : rien n’a été enregistré. Saisissez de nouveau puis enregistrez.', 'fields' => []]);

            return Response::redirect($dialogUrl);
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $id > 0 ? $this->userRepository->findById($id, $tenantId) : null;
        if ($user === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $text = static function (mixed $raw, int $max): ?string {
            $value = trim((string) $raw);
            if ($value === '') {
                return null;
            }

            return mb_substr($value, 0, $max, 'UTF-8');
        };
        $existing = $this->personnelProfileRepository->getByUserId($id, $tenantId) ?? [];
        $choice = static function (string $key, mixed $raw, array $allowed) use ($existing): ?string {
            $value = trim((string) $raw);
            if ($value === '') {
                return null;
            }
            // Une ancienne valeur hors liste reste telle quelle si on ne la change pas.
            if (in_array($value, $allowed, true) || $value === trim((string) ($existing[$key] ?? ''))) {
                return $value;
            }

            return null;
        };
        $catalog = \App\Services\Personnel\PersonnelCorrectionRequestService::choiceCatalog();
        $values = static fn (string $key): array => array_values(array_filter(array_map(
            static fn (array $pair): string => trim((string) ($pair['value'] ?? '')),
            is_array($catalog[$key] ?? null) ? $catalog[$key] : []
        )));
        $weightRaw = trim((string) $request->input('weight_kg', ''));
        $weight = $weightRaw !== '' && ctype_digit($weightRaw) ? max(20, min(300, (int) $weightRaw)) : null;
        $heightRaw = trim((string) $request->input('height_cm', ''));
        $height = $heightRaw !== '' && ctype_digit($heightRaw) ? max(100, min(250, (int) $heightRaw)) : null;
        $data = [
            'birth_place' => $text($request->input('birth_place'), 150),
            'family_situation' => $choice('family_situation', $request->input('family_situation'), $values('family_situation')),
            'sex' => $choice('sex', $request->input('sex'), $values('sex')),
            'nationality' => $text($request->input('nationality'), 100),
            'blood_type' => $choice('blood_type', $request->input('blood_type'), $values('blood_type')),
            'languages' => $text($request->input('languages'), 255),
            'weight_kg' => $weight,
            'height_cm' => $height,
            'drug_intolerances' => $text($request->input('drug_intolerances'), 500),
            'allergies' => $text($request->input('allergies'), 500),
            'nickname_primary' => $text($request->input('nickname_primary'), 100),
            'motto' => $text($request->input('motto'), 255),
        ];
        // Prénom et nom du personnage (le nom que tout le monde voit) : user_profiles, comme la fiche du membre.
        // Une saisie vide laisse la valeur actuelle (jamais d'effacement par ce formulaire) ; du HTML est refusé.
        $profiles = \App\Core\Container::get(\App\Repositories\UserProfileRepository::class);
        $currentIdentity = $profiles->getByUserId($id) ?? [];
        $nameUpsert = [];
        $nameRejected = [];
        foreach (['first_name', 'last_name'] as $nameKey) {
            $clean = \App\Support\MemberIdentity::cleanNamePart($request->input($nameKey, ''));
            if ($clean === null) {
                $nameRejected[] = $nameKey;
            } elseif ($clean !== '' && $clean !== trim((string) ($currentIdentity[$nameKey] ?? ''))) {
                $nameUpsert[$nameKey] = $clean;
            }
        }
        $nameBefore = trim(trim((string) ($currentIdentity['first_name'] ?? '')) . ' ' . trim((string) ($currentIdentity['last_name'] ?? '')));
        $nameAfter = $nameBefore;
        if ($nameUpsert !== []) {
            $profiles->ensureRow($id);
            $profiles->upsert($id, $nameUpsert);
            $nameAfter = trim(
                (string) ($nameUpsert['first_name'] ?? trim((string) ($currentIdentity['first_name'] ?? '')))
                . ' ' . (string) ($nameUpsert['last_name'] ?? trim((string) ($currentIdentity['last_name'] ?? '')))
            );
            // Ancien champ « nom du personnage » : tenu égal à « Prénom Nom » comme à l'enregistrement du dossier.
            $data['character_name'] = $nameAfter;
        }
        $this->personnelProfileRepository->ensureRecord($id, $tenantId);
        $this->personnelProfileRepository->update($id, $data);
        $this->adminAuditService->logUserUpdated(
            $tenantId,
            (int) Session::get('user_id'),
            $id,
            'etat_civil',
            implode(', ', array_keys(array_filter($data, static fn ($v): bool => $v !== null))) . ($nameUpsert !== [] ? ', ' . implode(', ', array_keys($nameUpsert)) : '')
        );
        if ($nameUpsert !== []) {
            $this->adminAuditService->logUserUpdated(
                $tenantId,
                (int) Session::get('user_id'),
                $id,
                'nom_affiche:' . $nameBefore,
                $nameAfter
            );
        }
        if ($nameRejected !== []) {
            Session::flash('eff_civil_error', [
                'message' => 'Le prénom et le nom ne peuvent pas contenir de HTML ni de caractères spéciaux de contrôle ; ils n’ont pas été modifiés. Le reste de l’état civil est enregistré.',
                'fields' => $nameRejected,
            ]);

            return Response::redirect($dialogUrl);
        }
        Session::flash('success', 'État civil mis à jour.');

        return Response::redirect($redirect);
    }

    public function quickAssignment(Request $request, array $params = []): Response
    {
        $json = self::chainWantsJson($request);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $json ? Response::json(['ok' => false, 'message' => 'Accès réservé au pilotage des effectifs.'], 403) : $denied;
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate)) {
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Vous n’êtes pas habilité à modifier les affectations.'], 403);
            }
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les affectations.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Session expirée. Rechargez la page, puis réessayez.', 'reload' => true], 419);
            }
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($this->redirectBackToRoster($request));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $unitId = (int) $request->input('unit_id', 0);
        $assignmentReason = trim((string) $request->input('reason', ''));
        if ($id < 1) {
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Membre introuvable.'], 404);
            }
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Membre introuvable.'], 404);
            }
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }

        $unitName = '';
        if ($unitId > 0) {
            $unit = $this->unitRepository->findById($unitId, $tenantId);
            if (!$unit) {
                if ($json) {
                    return Response::json(['ok' => false, 'message' => 'Unité introuvable dans cette communauté.'], 404);
                }
                Session::flash('error', 'Unité introuvable dans cette communauté.');

                return Response::redirect($this->redirectBackToRoster($request));
            }
            $unitName = trim((string) ($unit['name'] ?? ''));
        }

        try {
            $beforeSnap = $this->structureChangeNotification->snapshot($tenantId, $id);
            // Affectation, effectif de l'unité et unité du dossier écrits ensemble (une transaction, membre verrouillé) :
            // un second envoi du formulaire relit l'état réel et ne laisse pas de demi-affectation.
            $this->personnelProfileRepository->ensureRecord($id);
            $currentAssignment = $this->personnelAssignmentRepository->getPrimaryAssignment($id, $tenantId);
            $roleName = trim((string) ($currentAssignment['role_name'] ?? ''));
            $this->personnelAssignmentRepository->syncPrimaryAssignmentFromDossier(
                $id,
                $unitId > 0 ? $unitId : null,
                $roleName !== '' ? $roleName : 'Membre',
                $assignmentReason !== '' ? $assignmentReason : null,
                $tenantId
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
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Impossible d’enregistrer l’affectation. Réessayez ou ouvrez le dossier personnel.'], 500);
            }
            Session::flash('error', 'Impossible d’enregistrer l’affectation. Réessayez ou ouvrez le dossier personnel.');

            return Response::redirect($this->redirectBackToRoster($request));
        }

        $doneMessage = $unitId > 0
            ? ('Affectation enregistrée' . ($unitName !== '' ? ' : ' . $unitName : '') . '.')
            : 'Affectation retirée.';
        if ($json) {
            return Response::json(['ok' => true, 'message' => $doneMessage]);
        }
        Session::flash('success', $doneMessage);

        $returnMember = $request->input('return_to') === 'member';

        return Response::redirect(
            $returnMember
                ? effectifs_workspace_url('membres/' . $id)
                : $this->redirectBackToRoster($request)
        );
    }

    /**
     * Réaligne l'affectation d'un membre resté dans un demi-état (fiche affectée mais absent des rosters,
     * doublon après un double envoi…). Sans effet sur un membre cohérent.
     */
    public function repairAssignment(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) ($params['id'] ?? 0);
        $back = effectifs_workspace_url($id > 0 ? 'membres/' . $id : '');
        if (!EffectifsLmsAccess::canManageAssignments(Gate::getInstance())) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les affectations.');

            return Response::redirect($back);
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($id < 1 || !$this->userRepository->findById($id, $tenantId)) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(effectifs_workspace_url());
        }
        try {
            $changes = $this->personnelAssignmentRepository->consistency()->repair($id, $tenantId);
        } catch (\Throwable) {
            Session::flash('error', 'La réparation de l’affectation n’a pas abouti. Réessayez.');

            return Response::redirect($back);
        }
        if ($changes === []) {
            Session::flash('success', 'L’affectation est déjà cohérente : rien à réparer.');

            return Response::redirect($back);
        }
        $this->adminAuditService->logUserUpdated($tenantId, (int) Session::get('user_id'), $id, 'affectation', mb_substr('réparée : ' . implode(' ; ', $changes), 0, 250));
        Session::flash('success', 'Affectation réparée : ' . implode(' ; ', $changes) . '.');

        return Response::redirect($back);
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
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
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
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        $checkedIds = $selectedId > 0 ? $permSvc->getPermissionIdsForRole($selectedId) : [];
        $groups = AccessRightsCatalog::group($index['rows']);
        $selectedKind = $this->accessRoleKind($selected);
        $lockedPermissionIds = $selectedKind === 'manager'
            ? $permSvc->managerProtectedPermissionIdsForTenant($tenantId)
            : [];
        $selectedSlugs = $snapshot['perms'][$selectedId] ?? [];

        $cards = [];
        foreach ($roles as $row) {
            $rid = (int) ($row['id'] ?? 0);
            $slugs = $snapshot['perms'][$rid] ?? [];
            $cards[$rid] = [
                'kind' => $this->accessRoleKind($row),
                'color' => AccessRoleColor::hexFor($row['badge_style'] ?? null),
                'hidden' => RoleRepository::isHiddenAccessRow($row),
                'members' => count($snapshot['members'][$rid] ?? []),
                'rights' => count($slugs),
                'scope' => AccessRightsCatalog::scopeOf($slugs),
                'critical' => count(array_filter($slugs, static fn (string $s): bool => AccessRightsCatalog::sensitivityFor($s) === AccessRightsCatalog::SENSITIVITY_CRITICAL)),
            ];
        }

        $holders = [];
        $holdersTotal = 0;
        if ($selectedId > 0) {
            $holderIds = $snapshot['members'][$selectedId] ?? [];
            $holdersTotal = count($holderIds);
            $holders = $this->roleRepository->memberIdentities($tenantId, $holderIds, 300);
        }

        $compareId = (int) $request->query('compare', 0);
        $compare = null;
        if ($compareId > 0 && $compareId !== $selectedId && isset($snapshot['perms'][$compareId])) {
            $compareName = '';
            foreach ($roles as $row) {
                if ((int) ($row['id'] ?? 0) === $compareId) {
                    $compareName = (string) ($row['name'] ?? '');
                }
            }
            $compare = ['id' => $compareId, 'name' => $compareName, 'diff' => AccessRoleRules::compare($selectedSlugs, $snapshot['perms'][$compareId])];
        }

        $spaces = [];
        try {
            $nav = require base_path('config/navigation.php');
            $spaces = AccessRightsCatalog::spacesOpenedBy(AccessRightsCatalog::spacesFromNavigation(is_array($nav) ? $nav : []), $selectedSlugs);
        } catch (\Throwable) {
            $spaces = [];
        }

        // Fiches des niveaux (abrégé, icône ou logo, étiquettes, qualifications…) : vide sans l'étape 4870, la page reste utilisable.
        $profileSvc = new AccessRoleProfileService();
        $profilesReady = $profileSvc->ready();
        $gradeLabels = [];
        try {
            [, $gradeLabels] = (new \App\Services\Organization\OrgAdequacyService())->gradeCatalog($tenantId);
        } catch (\Throwable) {
            $gradeLabels = [];
        }
        $membersByRole = [];
        foreach ($roles as $row) {
            $membersByRole[(int) ($row['id'] ?? 0)] = $snapshot['members'][(int) ($row['id'] ?? 0)] ?? [];
        }

        return $this->shell('admin.effectifs_workspace.roles', AccessRoleProfileView::data($request, $roles, $profilesReady ? $profileSvc->profiles($tenantId) : [], $membersByRole, [
            'ready' => $profilesReady,
            'tags_ready' => $profileSvc->tagsReady(),
            'qualifications' => $profilesReady ? $profileSvc->qualificationCatalog($tenantId) : [],
            'jobs' => $profilesReady ? $profileSvc->jobCatalog($tenantId) : [],
            'tags' => $profileSvc->tagCatalog($tenantId),
            'grade_labels' => $gradeLabels,
            'old' => Session::getFlash('rol_old'),
        ]) + [
            'title' => 'Accès',
            'effectifsNav' => 'roles',
            'orgRoles' => $roles,
            'selectedAccessRole' => $selected,
            'selectedAccessKind' => $selectedKind,
            'accessPermissionGroups' => $groups,
            'checkedPermissionIds' => $checkedIds,
            'lockedPermissionIds' => $lockedPermissionIds,
            'accessRoleCards' => $cards,
            'accessHolders' => $holders,
            'accessHoldersTotal' => $holdersTotal,
            'accessSelectedColor' => AccessRoleColor::inputFor($selected['badge_style'] ?? null),
            'accessColorPresets' => AccessRoleColor::presets(),
            'accessCompare' => $compare,
            'accessSpaces' => $spaces,
            'accessTemplates' => AccessRoleRules::templates(),
            'accessMissingRights' => AccessRightsCatalog::missingFromTenant($index['rows']),
            'accessRequirementGaps' => AccessRoleRules::missingRequirements($selectedSlugs),
            'accessHistory' => $this->accessRoleHistory($tenantId, $selectedId),
            'accessRightNames' => $index['nameBySlug'],
            'canManageRoles' => EffectifsLmsAccess::canManageRoles($gate),
            'rosterCounts' => $this->rosterCounts($tenantId),
            'csrfToken' => Csrf::token(),
            'backOfficePageCss' => ['effectifs_lms.css', 'personnel-dossier.css', 'back-office-effectifs-workspace.css', 'back-office-effectifs-refonte.css', 'effectifs-pages.css', 'back-office-effectifs-maquette.css', 'effectifs-roles.css', 'back-office-bands.css', 'effectifs-roles-profile.css'],
        ]);
    }

    /**
     * Droits de la communauté (jamais ceux de la plateforme), indexés pour l’écran.
     *
     * @return array{rows: list<array<string, mixed>>, slugById: array<int, string>, idBySlug: array<string, int>, nameBySlug: array<string, string>}
     */
    private function accessPermissionIndex(int $tenantId): array
    {
        $rows = [];
        $slugById = [];
        $idBySlug = [];
        $nameBySlug = [];
        foreach ($this->permissionRepository->allForTenant($tenantId) as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            if ($slug === '' || $id < 1 || SystemReservedPermissions::isReserved($slug)) {
                continue;
            }
            $rows[] = $row;
            $slugById[$id] = $slug;
            $idBySlug[$slug] = $id;
            $nameBySlug[$slug] = trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : $slug;
        }

        return ['rows' => $rows, 'slugById' => $slugById, 'idBySlug' => $idBySlug, 'nameBySlug' => $nameBySlug];
    }

    /**
     * Droits et titulaires de chaque niveau de la communauté (une seule lecture pour l’écran, l’aperçu et les gardes).
     *
     * @param list<array<string, mixed>> $roles
     * @param array<int, string> $slugById
     * @return array{perms: array<int, list<string>>, members: array<int, list<int>>}
     */
    private function accessSnapshot(int $tenantId, array $roles, array $slugById): array
    {
        $perms = [];
        $members = [];
        foreach ($roles as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid < 1) {
                continue;
            }
            $slugs = [];
            foreach ($this->permissionRepository->getPermissionIdsForRole($rid) as $pid) {
                if (isset($slugById[$pid])) {
                    $slugs[] = $slugById[$pid];
                }
            }
            $perms[$rid] = array_values(array_unique($slugs));
            $members[$rid] = $this->roleRepository->userIdsWithRole($tenantId, $rid);
        }

        return ['perms' => $perms, 'members' => $members];
    }

    /**
     * Derniers changements d’un niveau (même communauté, même niveau).
     *
     * @return list<array{when: string, who: string, what: string, added: list<string>, removed: list<string>}>
     */
    private function accessRoleHistory(int $tenantId, int $roleId): array
    {
        if ($tenantId < 1 || $roleId < 1) {
            return [];
        }
        try {
            $res = (new \App\Repositories\AuditLogRepository())->listForTenant($tenantId, ['entity_type' => 'role', 'entity_id' => $roleId], 1, 15);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($res['rows'] as $row) {
            if ((string) ($row['entity_type'] ?? '') !== 'role') {
                continue;
            }
            $payload = json_decode((string) ($row['new_value'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $who = trim((string) ($row['actor_display_name'] ?? ''));
            if ($who === '') {
                $who = trim((string) ($row['actor_callsign'] ?? '')) ?: 'Système';
            }
            $out[] = [
                'when' => (string) ($row['created_at'] ?? ''),
                'who' => $who,
                'what' => \App\Services\Audit\AuditActionLabel::toFrench((string) ($row['action'] ?? '')),
                'added' => array_values(array_map('strval', (array) ($payload['added'] ?? []))),
                'removed' => array_values(array_map('strval', (array) ($payload['removed'] ?? []))),
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $added
     * @param list<string> $removed
     */
    private function logAccessRoleChange(int $tenantId, int $roleId, string $action, array $added, array $removed, array $extra = []): void
    {
        $actorId = (int) Session::get('user_id');
        if ($actorId < 1) {
            return;
        }
        try {
            $payload = json_encode($extra + ['added' => $added, 'removed' => $removed, 'scope' => 'access_roles'], JSON_UNESCAPED_UNICODE);
            (new \App\Services\Audit\AuditService())->log($action, $tenantId, $actorId, 'role', $roleId, null, $payload !== false ? $payload : null);
        } catch (\Throwable $e) {
            error_log('[acces] journal : ' . $e->getMessage());
        }
    }

    /**
     * Libellés lisibles de droits, pour les messages d’erreur.
     *
     * @param list<string> $slugs
     * @param array<string, string> $nameBySlug
     */
    private function accessRightNames(array $slugs, array $nameBySlug): string
    {
        $names = array_map(static fn (string $s): string => '« ' . ($nameBySlug[$s] ?? $s) . ' »', array_slice($slugs, 0, 4));

        return implode(', ', $names) . (count($slugs) > 4 ? ', …' : '');
    }

    /**
     * @param list<array<string, mixed>> $roles
     */
    private function accessNameTaken(array $roles, string $name, int $exceptId = 0): bool
    {
        $needle = mb_strtolower(trim($name));
        foreach ($roles as $row) {
            if ((int) ($row['id'] ?? 0) !== $exceptId && mb_strtolower(trim((string) ($row['name'] ?? ''))) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $role
     */
    private function accessRoleKind(?array $role): string
    {
        $slug = strtolower(trim((string) ($role['slug'] ?? '')));

        return match (true) {
            $slug === CommunityAccessProfiles::SLUG_MEMBER => 'member',
            $slug === CommunityAccessProfiles::SLUG_MANAGER => 'manager',
            CommunityAccessProfiles::isHideablePresetSlug($slug) => 'hideable',
            default => 'custom',
        };
    }

    /** Contrôles communs des écritures : droit de gérer les accès puis jeton CSRF. */
    private function denyAccessWrite(Request $request, string $deniedMessage): ?Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', $deniedMessage);

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Rechargez la page puis recommencez : rien n’a été enregistré.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }

        return null;
    }

    public function createAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyAccessWrite($request, 'Vous n’êtes pas habilité à créer un niveau d’accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        $name = trim((string) $request->input('name', ''));
        $template = trim((string) $request->input('template', ''));
        $templates = AccessRoleRules::templates();
        $copyId = (int) $request->input('copy_from', 0);
        $description = trim((string) $request->input('description', ''));
        if ($name === '' && isset($templates[$template])) {
            $name = $templates[$template]['name'];
        }
        if ($name === '') {
            Session::flash('error', 'Indiquez un nom pour le nouveau niveau d’accès.');
            Session::flash('rol_old', ['name' => $name, 'description' => $description, 'template' => $template, 'copy_from' => $copyId] + AccessRoleProfileView::oldInput($request));

            return Response::redirect(effectifs_workspace_url('roles') . '?nouveau=1#rol-new');
        }
        if ($this->accessNameTaken($roles, $name)) {
            Session::flash('error', 'Un niveau d’accès porte déjà ce nom. Choisissez-en un autre pour ne pas les confondre.');
            Session::flash('rol_old', ['name' => $name, 'description' => $description, 'template' => $template, 'copy_from' => $copyId] + AccessRoleProfileView::oldInput($request));

            return Response::redirect(effectifs_workspace_url('roles') . '?nouveau=1#rol-new');
        }
        // Source des droits : un modèle prêt à l’emploi, sinon un niveau de CETTE communauté (jamais un identifiant libre), sinon Membre.
        $newSlugs = null;
        if (isset($templates[$template])) {
            $newSlugs = $templates[$template]['slugs'];
            $description = $description !== '' ? $description : $templates[$template]['description'];
        } else {
            $sourceId = isset($snapshot['perms'][$copyId]) ? $copyId : (int) ($this->roleRepository->getIdBySlug($tenantId, CommunityAccessProfiles::SLUG_MEMBER) ?? 0);
            $newSlugs = $snapshot['perms'][$sourceId] ?? [];
        }
        $gate = Gate::getInstance();
        $blocked = AccessRoleRules::escalation([], $newSlugs, static fn (string $s): bool => $gate->allows($s));
        if ($blocked !== []) {
            Session::flash('error', 'Vous ne pouvez pas créer ce niveau : il donnerait des droits que vous n’avez pas vous-même (' . $this->accessRightNames($blocked, $index['nameBySlug']) . ').');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        // Fiche saisie dans la fenêtre de création : validée AVANT de créer le niveau (rien n'est créé si elle est refusée).
        $profileForm = new AccessRoleProfileForm();
        $profileInput = AccessRoleProfileView::oldInput($request);
        $profileCheck = $profileForm->check($tenantId, 0, $profileInput);
        $createColor = AccessRoleColor::normalize((string) ($profileInput['role_color'] ?? ''));
        if ($profileCheck['error'] !== null || $createColor === null) {
            Session::flash('error', ($profileCheck['error'] ?? 'Cette couleur n’est pas valide (choisissez une pastille ou un code #RRGGBB).') . ' Le niveau n’a pas été créé.');
            Session::flash('rol_old', $profileInput + ['name' => $name, 'description' => $description, 'template' => $template, 'copy_from' => $copyId]);

            return Response::redirect(effectifs_workspace_url('roles') . '?nouveau=1#rol-new');
        }
        $newId = $this->roleRepository->createCustomAccessRole($tenantId, $name, $description);
        if ($newId < 1) {
            Session::flash('error', 'Le niveau d’accès n’a pas pu être créé. Réessayez dans un instant.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $ids = [];
        foreach ($newSlugs as $slug) {
            if (isset($index['idBySlug'][$slug])) {
                $ids[] = $index['idBySlug'][$slug];
            }
        }
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        try {
            $permSvc->setPermissionsForAccessRole($tenantId, $newId, $ids);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $newId);
        }
        $this->logAccessRoleChange($tenantId, $newId, AuditAction::ACCESS_ROLE_CREATED, $newSlugs, [], ['name' => $name, 'from' => isset($templates[$template]) ? 'template:' . $template : 'role:' . $copyId]);
        if ($createColor !== []) {
            $this->roleRepository->updateOrganizationRoleBadgeStyle($tenantId, $newId, $createColor);
        }
        $actorId = (int) Session::get('user_id');
        if ($profileCheck['data'] === null && (string) $request->input('copy_profile', '') === '1' && isset($snapshot['perms'][$copyId])) {
            // « Dupliquer » : la fiche du niveau copié suit (sans l'abrégé, unique, ni le logo).
            $profileForm->profiles()->copy($tenantId, $copyId, $newId, $actorId);
        }
        $profileApplied = $profileForm->apply($tenantId, $newId, $profileCheck['data'], $profileInput, is_array($_FILES['logo_file'] ?? null) ? $_FILES['logo_file'] : null, $actorId);
        if (!$profileApplied['ok']) {
            Session::flash('error', 'Niveau d’accès créé, mais le logo n’a pas été pris : ' . (string) $profileApplied['error']);

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $newId . '&fiche=1#rol-edit');
        }
        Session::flash('success', 'Niveau d’accès créé. Vérifiez ses droits ci-dessous, puis enregistrez si vous les modifiez.');

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $newId);
    }

    public function saveAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyAccessWrite($request, 'Vous n’êtes pas habilité à modifier les accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        if (!$this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            Session::flash('error', 'Ce niveau d’accès n’existe pas dans votre communauté ou ne peut pas être modifié.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Le nom du niveau ne peut pas être vide : rien n’a été enregistré.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        if ($this->accessNameTaken($roles, $name, $roleId)) {
            Session::flash('error', 'Un autre niveau d’accès porte déjà ce nom : rien n’a été enregistré.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        $rawIds = $request->input('permission_ids', []);
        $permissionIds = is_array($rawIds) ? array_map('intval', $rawIds) : [];
        $role = $this->roleRepository->findById($roleId, $tenantId);
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        $finalIds = $permSvc->withManagerProtectedPermissions(
            $tenantId,
            (string) ($role['slug'] ?? ''),
            $permSvc->assignablePermissionIdsForTenant($tenantId, $permissionIds)
        );
        $newSlugs = [];
        foreach ($finalIds as $pid) {
            if (isset($index['slugById'][$pid])) {
                $newSlugs[] = $index['slugById'][$pid];
            }
        }
        $oldSlugs = $snapshot['perms'][$roleId] ?? [];
        $gate = Gate::getInstance();
        $blocked = AccessRoleRules::escalation($oldSlugs, $newSlugs, static fn (string $s): bool => $gate->allows($s));
        if ($blocked !== []) {
            Session::flash('error', 'Vous ne pouvez pas donner des droits que vous n’avez pas vous-même (' . $this->accessRightNames($blocked, $index['nameBySlug']) . '). Rien n’a été enregistré.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        if (AccessRoleRules::removesLastAdmin($snapshot['perms'], $snapshot['members'], ['type' => 'save', 'role_id' => $roleId, 'slugs' => $newSlugs])) {
            Session::flash('error', 'Ce changement retirerait « Gérer les rôles » à la dernière personne qui peut gérer les accès de la communauté. Rien n’a été enregistré.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        $color = AccessRoleColor::normalize((string) $request->input('role_color', ''));
        if ($color === null) {
            Session::flash('error', 'Cette couleur n’est pas valide (choisissez une pastille ou un code #RRGGBB) : rien n’a été enregistré.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        $this->roleRepository->updateAccessRoleLabel($tenantId, $roleId, $name, (string) $request->input('description', ''));
        $current = [];
        if (is_string($role['badge_style'] ?? null) && $role['badge_style'] !== '') {
            $decoded = json_decode((string) $role['badge_style'], true);
            $current = is_array($decoded) ? $decoded : [];
        }
        unset($current['color'], $current['hex']);
        $style = $current + $color;
        $this->roleRepository->updateOrganizationRoleBadgeStyle($tenantId, $roleId, $style === [] ? null : $style);
        try {
            $permSvc->setPermissionsForAccessRole($tenantId, $roleId, $permissionIds);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        $added = array_values(array_diff($newSlugs, $oldSlugs));
        $removed = array_values(array_diff($oldSlugs, $newSlugs));
        $this->logAccessRoleChange($tenantId, $roleId, AuditAction::ROLE_PERMISSIONS_UPDATED, $added, $removed, ['event' => 'save']);
        Session::flash('success', $added === [] && $removed === []
            ? 'Niveau d’accès enregistré (droits inchangés).'
            : 'Niveau d’accès enregistré. Droits ajoutés : ' . count($added) . ', droits retirés : ' . count($removed) . '.');

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
    }

    /**
     * Aperçu d’impact avant enregistrement : qui gagnerait ou perdrait quel droit. N’écrit rien.
     * La communauté vient de la session ; le niveau doit appartenir à cette communauté.
     */
    public function previewAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 401);
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance()) || !EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants.'], 403);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], 419);
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
        $role = null;
        foreach ($roles as $row) {
            if ((int) ($row['id'] ?? 0) === $roleId) {
                $role = $row;
                break;
            }
        }
        if ($role === null) {
            return Response::json(['ok' => false, 'error' => 'Niveau introuvable dans votre communauté.'], 404);
        }
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        $rawIds = $request->input('permission_ids', []);
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        $finalIds = $permSvc->withManagerProtectedPermissions(
            $tenantId,
            (string) ($role['slug'] ?? ''),
            $permSvc->assignablePermissionIdsForTenant($tenantId, is_array($rawIds) ? array_map('intval', $rawIds) : [])
        );
        $newSlugs = [];
        foreach ($finalIds as $pid) {
            if (isset($index['slugById'][$pid])) {
                $newSlugs[] = $index['slugById'][$pid];
            }
        }
        $impact = AccessRoleRules::impact($roleId, $newSlugs, $snapshot['perms'], $snapshot['members']);
        $gate = Gate::getInstance();
        $blocked = AccessRoleRules::escalation($snapshot['perms'][$roleId] ?? [], $newSlugs, static fn (string $s): bool => $gate->allows($s));
        $lastAdmin = AccessRoleRules::removesLastAdmin($snapshot['perms'], $snapshot['members'], ['type' => 'save', 'role_id' => $roleId, 'slugs' => $newSlugs]);
        $requirements = [];
        foreach (AccessRoleRules::missingRequirements($newSlugs) as $slug => $missing) {
            $requirements[] = ['right' => $index['nameBySlug'][$slug] ?? $slug, 'needs' => array_map(static fn (string $s): string => $index['nameBySlug'][$s] ?? $s, $missing)];
        }
        $shape = function (array $bySlug) use ($tenantId, $index): array {
            $out = [];
            foreach ($bySlug as $slug => $userIds) {
                $out[] = [
                    'slug' => $slug,
                    'name' => $index['nameBySlug'][$slug] ?? $slug,
                    'count' => count($userIds),
                    'sensitivity' => AccessRightsCatalog::sensitivityFor($slug),
                    'people' => array_map(static fn (array $p): array => ['name' => $p['name'], 'url' => effectifs_member_url($p['id'])], $this->roleRepository->memberIdentities($tenantId, $userIds, 3)),
                ];
            }

            return $out;
        };

        return Response::json([
            'ok' => true,
            'members' => $impact['members'],
            'gain' => $shape($impact['gain']),
            'lose' => $shape($impact['lose']),
            'escalation' => array_map(static fn (string $s): string => $index['nameBySlug'][$s] ?? $s, $blocked),
            'last_admin' => $lastAdmin,
            'requirements' => $requirements,
        ]);
    }

    /**
     * Plan de copie ou de déplacement de membres (sans rien écrire) : titulaires du niveau source seulement.
     *
     * @return array<string, mixed>
     */
    private function roleMembersPlan(Request $request, int $tenantId): array
    {
        $sourceId = (int) $request->input('source_id', 0);
        $targetId = (int) $request->input('target_id', 0);
        $op = (string) $request->input('op', 'copy_sel');
        $move = $op === 'move_sel';
        $all = $op === 'copy_all';
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
        $byId = [];
        foreach ($roles as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }
        if (!isset($byId[$sourceId]) || !isset($byId[$targetId])) {
            return ['error' => 'Niveau introuvable dans votre communauté.'];
        }
        if ($sourceId === $targetId) {
            return ['error' => 'Choisissez un autre niveau comme destination.'];
        }
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        $rawIds = $request->input('user_ids', []);
        $selected = $all ? null : (is_array($rawIds) ? array_map('intval', $rawIds) : []);
        if ($selected !== null && $selected === []) {
            return ['error' => 'Cochez au moins un membre à copier ou à déplacer.'];
        }
        $plan = AccessRoleRules::planTransfer($snapshot['members'][$sourceId] ?? [], $snapshot['members'][$targetId] ?? [], $selected, $move);

        return [
            'source' => $byId[$sourceId], 'target' => $byId[$targetId], 'plan' => $plan, 'move' => $move,
            'perms' => $snapshot['perms'], 'members' => $snapshot['members'], 'names' => $index['nameBySlug'],
        ];
    }

    /** Aperçu « N membres recevront ce niveau » avant copie ou déplacement. N'écrit rien. */
    public function previewRoleMembers(Request $request, array $params = []): Response
    {
        unset($params);
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 401);
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance()) || !EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            return Response::json(['ok' => false, 'error' => 'Droits insuffisants.'], 403);
        }
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], 419);
        }
        $tenantId = (int) Session::get('tenant_id');
        $ctx = $this->roleMembersPlan($request, $tenantId);
        if (isset($ctx['error'])) {
            return Response::json(['ok' => false, 'error' => $ctx['error']], 422);
        }
        $gate = Gate::getInstance();
        $blocked = AccessRoleRules::escalation([], $ctx['perms'][(int) $ctx['target']['id']] ?? [], static fn (string $s): bool => $gate->allows($s));

        return Response::json([
            'ok' => true,
            'receive' => count($ctx['plan']['receive']),
            'already' => count($ctx['plan']['already']),
            'leave' => count($ctx['plan']['leave']),
            'escalation' => array_map(static fn (string $s): string => $ctx['names'][$s] ?? $s, $blocked),
            'target' => (string) ($ctx['target']['name'] ?? ''),
        ]);
    }

    /** Copie ou déplace des membres d'un niveau vers un autre (cohérence des rôles exclusifs vérifiée par personne). */
    public function moveRoleMembers(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyAccessWrite($request, 'Vous n’êtes pas habilité à attribuer des niveaux d’accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $back = effectifs_workspace_url('roles') . '?role=' . (int) $request->input('source_id', 0) . '#rl-members';
        $ctx = $this->roleMembersPlan($request, $tenantId);
        if (isset($ctx['error'])) {
            Session::flash('error', $ctx['error']);

            return Response::redirect($back);
        }
        $source = $ctx['source'];
        $target = $ctx['target'];
        $sourceId = (int) $source['id'];
        $targetId = (int) $target['id'];
        $plan = $ctx['plan'];
        $move = (bool) $ctx['move'];
        $gate = Gate::getInstance();
        $blocked = AccessRoleRules::escalation([], $ctx['perms'][$targetId] ?? [], static fn (string $s): bool => $gate->allows($s));
        if ($blocked !== []) {
            Session::flash('error', 'Vous ne pouvez pas attribuer ce niveau : il donne des droits que vous n’avez pas vous-même (' . $this->accessRightNames($blocked, $ctx['names']) . ').');

            return Response::redirect($back);
        }
        if ($move && strtolower((string) ($source['slug'] ?? '')) === CommunityAccessProfiles::SLUG_MEMBER) {
            Session::flash('error', 'Tout le monde porte Membre : on ne le retire à personne. Utilisez « Copier ».');

            return Response::redirect($back);
        }
        $newMembers = $ctx['members'];
        $newMembers[$targetId] = array_values(array_unique(array_merge($newMembers[$targetId] ?? [], $plan['receive'])));
        if ($move) {
            $newMembers[$sourceId] = array_values(array_diff($newMembers[$sourceId] ?? [], $plan['leave']));
        }
        if (AccessRoleRules::membersChangeRemovesLastAdmin($ctx['perms'], $ctx['members'], $newMembers)) {
            Session::flash('error', 'Ce déplacement retirerait « Gérer les rôles » à la dernière personne qui peut gérer les accès. Rien n’a été modifié.');

            return Response::redirect($back);
        }
        $actorId = (int) Session::get('user_id');
        $done = 0;
        $refused = 0;
        $firstError = '';
        $touched = array_values(array_unique(array_merge($plan['receive'], $move ? $plan['leave'] : [])));
        foreach ($touched as $uid) {
            $ids = $this->userRepository->listOrganizationRoleIdsForUser($uid, $tenantId);
            if (in_array($uid, $plan['receive'], true)) {
                $ids[] = $targetId;
            }
            if ($move && in_array($uid, $plan['leave'], true)) {
                $ids = array_values(array_filter($ids, static fn (int $rid): bool => $rid !== $sourceId));
            }
            try {
                $this->userRepository->syncOrganizationRoles($uid, $tenantId, $ids, $actorId);
                $done++;
            } catch (\InvalidArgumentException $e) {
                $refused++;
                $firstError = $firstError !== '' ? $firstError : $e->getMessage();
            }
        }
        $this->logAccessRoleChange($tenantId, $targetId, AuditAction::ACCESS_ROLE_MEMBERS, [], [], [
            'event' => $move ? 'move' : 'copy', 'from' => $sourceId, 'members' => $done, 'refused' => $refused,
        ]);
        if ($done === 0 && $refused > 0) {
            Session::flash('error', 'Aucun membre n’a été modifié : ' . $firstError);
        } elseif ($done === 0) {
            Session::flash('success', 'Rien à faire : les membres choisis ont déjà ce niveau.');
        } else {
            Session::flash('success', ($move ? 'Membres déplacés' : 'Membres copiés') . ' vers « ' . (string) ($target['name'] ?? '') . ' » : ' . $done . ($refused > 0 ? '. Refusés (rôles incompatibles) : ' . $refused : '') . '.');
        }

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $sourceId . '#rl-members');
    }

    public function restoreAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyAccessWrite($request, 'Vous n’êtes pas habilité à rétablir un modèle.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        $role = $this->roleRepository->findById($roleId, $tenantId);
        if (!$role || !CommunityAccessProfiles::isAccessSlug((string) ($role['slug'] ?? ''))) {
            Session::flash('error', 'Seul un modèle de départ (Membre, Ressources humaines, Gestionnaire) peut être rétabli.');

            return Response::redirect(effectifs_workspace_url('roles') . ($roleId > 0 ? '?role=' . $roleId : ''));
        }
        $key = CommunityAccessProfiles::slugToKey((string) $role['slug']);
        $def = CommunityAccessProfiles::definitionByKey($key);
        $wanted = CommunityAccessProfiles::permissionSlugsFor($key);
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        $ids = [];
        $newSlugs = [];
        foreach ($wanted as $slug) {
            if (isset($index['idBySlug'][$slug])) {
                $ids[] = $index['idBySlug'][$slug];
                $newSlugs[] = $slug;
            }
        }
        $oldSlugs = $snapshot['perms'][$roleId] ?? [];
        $gate = Gate::getInstance();
        $blocked = AccessRoleRules::escalation($oldSlugs, $newSlugs, static fn (string $s): bool => $gate->allows($s));
        if ($blocked !== []) {
            Session::flash('error', 'Vous ne pouvez pas rétablir ce modèle : il redonnerait des droits que vous n’avez pas vous-même (' . $this->accessRightNames($blocked, $index['nameBySlug']) . ').');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        if (AccessRoleRules::removesLastAdmin($snapshot['perms'], $snapshot['members'], ['type' => 'save', 'role_id' => $roleId, 'slugs' => $newSlugs])) {
            Session::flash('error', 'Rétablir ce modèle retirerait « Gérer les rôles » à la dernière personne qui peut gérer les accès. Rien n’a été modifié.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        if ($def !== null) {
            $this->roleRepository->updateAccessRoleLabel($tenantId, $roleId, (string) $def['name'], (string) $def['description']);
        }
        $permSvc = new RolePermissionService($this->roleRepository, $this->permissionRepository);
        $permSvc->setPermissionsForAccessRole($tenantId, $roleId, $ids);
        $this->logAccessRoleChange($tenantId, $roleId, AuditAction::ROLE_PERMISSIONS_UPDATED, array_values(array_diff($newSlugs, $oldSlugs)), array_values(array_diff($oldSlugs, $newSlugs)), ['event' => 'restore']);
        Session::flash('success', 'Le modèle de départ a été rétabli.');

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
    }

    public function deleteAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyAccessWrite($request, 'Vous n’êtes pas habilité à supprimer un niveau d’accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        $role = $this->roleRepository->findById($roleId, $tenantId);
        $slug = (string) ($role['slug'] ?? '');
        $isCustom = $role !== null && CommunityAccessProfiles::isCustomAccessSlug($slug);
        $isHideable = $role !== null && CommunityAccessProfiles::isHideablePresetSlug($slug);
        if (!$isCustom && !$isHideable) {
            Session::flash('error', 'Membre et Gestionnaire ne peuvent pas être supprimés : Membre est le socle que tout le monde porte, Gestionnaire tient les clés de la communauté. Renommez-les ou changez leurs droits.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        $roles = $this->roleRepository->forTenantAccessProfiles($tenantId, true);
        $index = $this->accessPermissionIndex($tenantId);
        $snapshot = $this->accessSnapshot($tenantId, $roles, $index['slugById']);
        if (AccessRoleRules::removesLastAdmin($snapshot['perms'], $snapshot['members'], ['type' => 'delete', 'role_id' => $roleId])) {
            Session::flash('error', 'Ce niveau porte « Gérer les rôles » pour la dernière personne qui peut gérer les accès. Donnez d’abord ce droit à un autre niveau (ou attribuez-lui Gestionnaire), puis recommencez.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
        }
        $holderCount = count($snapshot['members'][$roleId] ?? []);
        if ($isCustom && $holderCount > 0) {
            // Suppression réservée à un niveau sans titulaire : sinon on déplace d'abord les membres, ou on l'archive.
            Session::flash('error', 'Ce niveau a encore des titulaires (' . $holderCount . ') : il ne peut pas être supprimé. Déplacez-les vers un autre niveau (bloc « Membres de ce niveau ») ou archivez-le pour le ranger sans rien changer.');

            return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId . '#rl-members');
        }
        $memberId = (int) ($this->roleRepository->getIdBySlug($tenantId, CommunityAccessProfiles::SLUG_MEMBER) ?? 0);
        $actorId = (int) Session::get('user_id');
        if ($memberId > 0) {
            foreach ($this->roleRepository->userIdsWithRole($tenantId, $roleId) as $uid) {
                $kept = array_values(array_filter(
                    $this->userRepository->listOrganizationRoleIdsForUser($uid, $tenantId),
                    static fn (int $rid): bool => $rid !== $roleId
                ));
                try {
                    $this->userRepository->syncOrganizationRoles($uid, $tenantId, array_merge([$memberId], $kept), $actorId);
                } catch (\Throwable) {
                }
            }
        }
        $moved = count($snapshot['members'][$roleId] ?? []);
        if ($isHideable) {
            // Le socle de la communauté recrée RH : on le masque au lieu de le supprimer.
            if (!$this->roleRepository->setAccessPresetHidden($tenantId, $roleId, true)) {
                Session::flash('error', 'Ce niveau ne peut pas être masqué sur ce serveur.');

                return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
            }
            $this->logAccessRoleChange($tenantId, $roleId, AuditAction::ACCESS_ROLE_REMOVED, [], [], ['event' => 'hide', 'members_moved' => $moved]);
            Session::flash('success', 'Niveau masqué : il n’est plus proposé. Personnes revenues au niveau Membre : ' . $moved . '. Vous pouvez le réafficher à tout moment.');

            return Response::redirect(effectifs_workspace_url('roles'));
        }
        $this->roleRepository->deleteCustomAccessRole($tenantId, $roleId);
        (new AccessRoleProfileForm())->forget($tenantId, $roleId);
        $this->logAccessRoleChange($tenantId, $roleId, AuditAction::ACCESS_ROLE_REMOVED, [], $snapshot['perms'][$roleId] ?? [], ['event' => 'delete', 'name' => (string) ($role['name'] ?? ''), 'members_moved' => $moved]);
        Session::flash('success', 'Niveau d’accès retiré. Personnes revenues au niveau Membre : ' . $moved . '.');

        return Response::redirect(effectifs_workspace_url('roles'));
    }

    public function showAccessRole(Request $request, array $params = []): Response
    {
        unset($params);
        $denied = $this->denyAccessWrite($request, 'Vous n’êtes pas habilité à modifier les niveaux d’accès.');
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $roleId = (int) $request->input('role_id', 0);
        if ($this->roleRepository->setAccessPresetHidden($tenantId, $roleId, false)) {
            $this->logAccessRoleChange($tenantId, $roleId, AuditAction::ACCESS_ROLE_CREATED, [], [], ['event' => 'show']);
            Session::flash('success', 'Niveau réaffiché : il est de nouveau proposé.');
        } else {
            Session::flash('error', 'Ce niveau ne peut pas être réaffiché : il n’est pas masqué dans votre communauté.');
        }

        return Response::redirect(effectifs_workspace_url('roles') . '?role=' . $roleId);
    }

    public function droits(Request $request, array $params = []): Response
    {
        unset($request, $params);

        return Response::redirect(effectifs_workspace_url('roles'));
    }

    public function specialties(Request $request, array $params = []): Response
    {
        $data = (new MilitarySpecialtyController())->pageData($request);
        return $data instanceof Response ? $data : $this->shell('admin.effectifs_workspace.specialties',
            $data + ['backOfficePageCss' => ['military-specialties.css']]);
    }

    public function fonctions(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) return $denied;
        $tenantId = (int) Session::get('tenant_id');
        $canManage = EffectifsLmsAccess::canManageAssignments(Gate::getInstance()) || EffectifsLmsAccess::canManageRoles(Gate::getInstance());
        $vue = $request->queryString('vue', 'catalogue');
        if ($vue === 'attributions') {
            return $this->shell('admin.effectifs_workspace.fonctions', $this->jobAssignmentPageData($request, $tenantId) + [
                'title'=>'Emplois et fonctions', 'effectifsNav'=>'fonctions', 'jobsVue'=>'attributions',
                'canManageAssignments'=>$canManage, 'csrfToken'=>Csrf::token(), 'jobsWorkspaceEmbed'=>true,
                'jobsReferenceWorkspace'=>true,
            ]);
        }
        if (!in_array($vue, ['catalogue','categories','recuperation','historique','reglages','site'], true)) $vue='catalogue';
        if ($vue === 'site') {
            return $this->jobWorkspaceResponse($request, $this->siteJobCatalogData($request, $tenantId) + [
                'title'=>'Emplois et fonctions', 'effectifsNav'=>'fonctions', 'jobsVue'=>'site',
                'canManageAssignments'=>$canManage, 'csrfToken'=>Csrf::token(),
            ]);
        }
        if (in_array($vue, ['historique','recuperation','reglages'], true)) {
            if (!$canManage) return $this->jobReply($request, false, 'La gestion des emplois est réservée aux responsables habilités.', [], 403);
            $data = ['title'=>'Emplois et fonctions', 'effectifsNav'=>'fonctions', 'jobsVue'=>$vue,
                'canManageAssignments'=>$canManage, 'csrfToken'=>Csrf::token(), 'jobTenantId'=>$tenantId];
            if ($vue === 'historique') {
                $history = new PersonnelJobRoleHistoryService();
                $data['jobHistory'] = $history->recent($tenantId, max(0, (int)$request->queryString('avant', '0')));
                $data['jobHistoryCursor'] = max(0, (int)$request->queryString('avant', '0'));
                $data['jobArchiveCursor'] = max(0, (int)$request->queryString('avant_archive', '0'));
                $data['jobArchives'] = $history->archives($tenantId, $data['jobArchiveCursor']);
            } elseif ($vue === 'reglages') {
                $data['jobSyncSettings'] = PersonnelJobRoleAssignmentsSettings::resolve($this->tenantRepository->getSettings($tenantId));
                $tagService = new JobRoleEnrichmentService();
                $data['jobTags'] = $tagService->tags($tenantId);
                $data['jobEnrichmentReady'] = $tagService->schemaReady();
                $data['jobPrereqBlocking'] = $data['jobSyncSettings']['prerequisites_blocking'];
                $data['jobPrereqReady'] = (new \App\Services\Personnel\JobPrerequisiteService())->schemaReady();
            } else {
                $data['jobRecoveryLabels'] = $this->jobRecoveryLabels($request);
                try {
                    $data['jobRecovery'] = (new PersonnelJobRoleRecoveryService(Database::getPdo()))->preview($tenantId, $data['jobRecoveryLabels']);
                } catch (\Throwable $e) {
                    error_log('Effectifs jobs recovery preview: '.$e->getMessage());
                    $data['jobRecovery'] = ['ready'=>false,'candidates'=>[],'missing_labels'=>$data['jobRecoveryLabels']];
                }
            }
            return $this->jobWorkspaceResponse($request, $data);
        }
        $categories = $this->personnelJobRoleRepository->listCategoriesWithJobCounts($tenantId);
        $roles = $this->personnelJobRoleRepository->listRolesWithCategory($tenantId);
        $structure = new PersonnelJobRoleStructureService();
        $metadata = $structure->listMetadata($tenantId);
        $enrichment = new JobRoleEnrichmentService();
        $decorations = $enrichment->decorations($tenantId);
        foreach ($roles as &$role) {
            $role['structure'] = $metadata[(int)$role['id']] ?? [];
            $role['decoration'] = $decorations[(int)$role['id']] ?? ['abbreviation'=>null,'icon'=>null,'icon_is_default'=>false,'description'=>'','tags'=>[],'origin'=>JobRoleEnrichmentService::origin($role),'hidden'=>false];
        }
        unset($role);
        // Fonctions créées par la communauté en tête, puis celles du site et de l’ORBAT (ordre d’origine conservé).
        $originRank = [JobRoleEnrichmentService::ORIGIN_COMMUNITY=>0, JobRoleEnrichmentService::ORIGIN_SITE=>1, JobRoleEnrichmentService::ORIGIN_ORBAT=>2];
        $position = 0;
        foreach ($roles as &$role) $role['_position'] = $position++;
        unset($role);
        usort($roles, static fn(array $a, array $b): int => [$originRank[$a['decoration']['origin']] ?? 3, $a['_position']] <=> [$originRank[$b['decoration']['origin']] ?? 3, $b['_position']]);
        $showHidden = $request->queryString('masquees', '0') === '1';
        $tagFilter = max(0, (int)$request->queryString('etiquette', '0'));
        $hiddenCount = count(array_filter($roles, static fn(array $r): bool => !empty($r['decoration']['hidden'])));
        // Old category URLs open the same browser and its category editor.
        $categoryFilter = (int)$request->queryString('categorie_emplois', $request->queryString('categorie', '0'));
        $filterCategory = null;
        foreach ($categories as $category) if ((int)$category['id'] === $categoryFilter) $filterCategory=$category;
        $categoryFilter = (int)($filterCategory['id'] ?? 0);
        $creatingCategory = $request->query('nouvelle_categorie') === '1';
        $editingCategory = $creatingCategory || $request->query('modifier_categorie') === '1' || ($vue === 'categories' && $filterCategory !== null);
        $creating = !$editingCategory && $request->query('nouveau') === '1';
        $selected = null;
        $selectedId = (int)$request->queryString('emploi', '0');
        foreach ($roles as $role) if (!$creating && !$editingCategory && (int)$role['id'] === $selectedId) $selected=$role;
        // A selected job must stay in the visible branch; stale or foreign IDs never select another job.
        $included = $categoryFilter > 0 ? array_fill_keys(PersonnelJobCategoryTree::descendantIds($categories, $categoryFilter), true) : null;
        $visible = $included === null ? $roles : array_values(array_filter($roles, static fn(array $r):bool=>isset($included[(int)$r['category_id']])));
        // Les fonctions du site masquées restent consultables (filtre « Afficher les masquées » ou fiche ouverte).
        if (!$showHidden) $visible = array_values(array_filter($visible, static fn(array $r): bool => empty($r['decoration']['hidden']) || (int)$r['id'] === $selectedId));
        if ($tagFilter > 0) $visible = array_values(array_filter($visible, static fn(array $r): bool => in_array($tagFilter, array_column($r['decoration']['tags'], 'id'), true)));
        if ($selected && $included !== null && !isset($included[(int)$selected['category_id']])) $selected=null;
        $options = ['ready'=>$structure->schemaReady(),'roles'=>$roles,'units'=>[],'grades'=>[],'specialties'=>[],'kinds'=>PersonnelJobRoleStructureService::KINDS];
        // Grades, specialties and units are only needed while editing a job.
        if ($canManage && ($creating || $selected)) $options=$structure->options($tenantId);
        $holders = $selected && $this->personnelJobRoleRepository->pivotTableExists()
            ? $this->personnelJobRoleRepository->listHoldersByJobRoleIds($tenantId, [(int)$selected['id']]) : [];
        $data = ['title'=>'Emplois et fonctions','effectifsNav'=>'fonctions','jobsVue'=>'catalogue',
            'jobRoles'=>$visible,'allJobRoles'=>$roles,'jobCategories'=>$categories,'categoryTreeRows'=>PersonnelJobCategoryTree::rows($categories),
            'selectedJobRole'=>$selected,'creatingJobRole'=>$creating,'jobHoldersByRole'=>$holders,
            'canManageAssignments'=>$canManage,'csrfToken'=>Csrf::token(),'jobStructureOptions'=>$options,
            'jobCatalogueCategory'=>$filterCategory,'selectedJobCategory'=>$editingCategory?$filterCategory:null,
            'editingJobCategory'=>$editingCategory,'creatingJobCategory'=>$creatingCategory,
            'newCategoryParent'=>(int)$request->queryString('parent','0'),
            'newJobParent'=>(int)$request->queryString('sous_emploi','0'),
            'jobTags'=>$enrichment->tags($tenantId),'jobEnrichmentReady'=>$enrichment->schemaReady(),
            'jobShowHidden'=>$showHidden,'jobTagFilter'=>$tagFilter,'jobHiddenCount'=>$hiddenCount,
            'jobIconLibrary'=>\App\Support\JobRoleIconLibrary::all(),
            'jobHolderCount'=>$selected ? count($holders[(int)$selected['id']] ?? []) : 0,
            'jobCatalogAutoOpen'=>$creating && $request->queryString('catalogue', '0') === '1',
            'jobCatalogHints'=>$this->jobCatalogHints($tenantId, $roles),
            'jobCatalogDetectUrl'=>effectifs_workspace_url('fonctions/catalogue/detecter'),
        ];
        // Prérequis de l’emploi ouvert : critères, titulaires qui les remplissent, dérogations (JobPrerequisiteViewData).
        $data += \App\Services\Personnel\JobPrerequisiteViewData::forJobSheet($tenantId, $selected, $holders, $canManage, $selected ? $this->jobTenantSettings($tenantId) : []);
        return $this->jobWorkspaceResponse($request, $data);
    }

    /**
     * Note orange des emplois créés par la communauté qui existent dans le référentiel (JobCatalogMatcher, seuil NOTE) :
     * emploi id => suggestion. Un emploi déjà rempli depuis ce référentiel n'en a pas.
     *
     * @param list<array<string, mixed>> $roles
     * @return array<int, array<string, mixed>>
     */
    private function jobCatalogHints(int $tenantId, array $roles): array
    {
        $community = array_values(array_filter($roles, static fn (array $r): bool => ($r['decoration']['origin'] ?? '') === JobRoleEnrichmentService::ORIGIN_COMMUNITY));
        if ($community === []) {
            return [];
        }
        try {
            $lang = \App\Support\JobCatalog\JobCatalog::lang(function_exists('locale') ? locale() : 'fr');
            $country = (new \App\Controllers\Admin\JobCatalogController())->tenantCountry($tenantId);
            $units = [];
            $stmt = Database::getPdo()->prepare('SELECT id, name FROM units WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $u) {
                $units[(int) $u['id']] = (string) $u['name'];
            }
            $out = [];
            foreach (array_slice($community, 0, 200) as $role) {
                $decor = $role['decoration'] ?? [];
                $meta = $role['structure'] ?? [];
                $unitId = (int) ($meta['linked_unit_id'] ?? 0) ?: (int) ($role['source_unit_id'] ?? 0);
                $hints = \App\Support\JobCatalog\JobCatalogHints::for([
                    'name' => (string) $role['name'],
                    'abbreviation' => (string) ($decor['abbreviation'] ?? ''),
                    'code' => (string) ($meta['code'] ?? ''),
                    'unit' => $units[$unitId] ?? '',
                    'country' => $country,
                ], $lang, 1, \App\Support\JobCatalog\JobCatalogMatcher::NOTE);
                if ($hints === [] || \App\Support\JobCatalog\JobCatalogHints::alreadyImported((string) $role['name'], (string) ($decor['description'] ?? ''), (string) $hints[0]['id'], $lang)) {
                    continue;
                }
                $out[(int) $role['id']] = $hints[0] + ['gains' => \App\Support\JobCatalog\JobCatalogHints::gains($hints[0]['counts'])];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('Effectifs jobs catalogue hints: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Onglet « Fonctions du site » : le référentiel fourni par Athena, avec l’état masqué / affiché pour la communauté
     * et le nombre de membres qui occupent chaque fonction (ils la gardent si elle est masquée).
     *
     * @return array<string, mixed>
     */
    private function siteJobCatalogData(Request $request, int $tenantId): array
    {
        $enrichment = new JobRoleEnrichmentService();
        $hidden = $enrichment->hiddenCatalogSlugs($tenantId);
        $holders = $enrichment->holderCounts($tenantId);
        $decorations = $enrichment->decorations($tenantId);
        $roleIdBySlug = [];
        foreach ($this->personnelJobRoleRepository->listRolesWithCategory($tenantId) as $role) {
            $roleIdBySlug[(string)($role['slug'] ?? '')] = (int)$role['id'];
        }
        // Toutes les fonctions sont envoyées : la page filtre visibles / masquées sans rechargement.
        $showHidden = $request->queryString('masquees', '0') === '1';
        $groups = [];
        foreach (JobRoleEnrichmentService::catalogBySlug() as $slug => $entry) {
            $roleId = $roleIdBySlug[$slug] ?? 0;
            $category = trim((string)($entry['category'] ?? '')) ?: 'Autres';
            $groups[$category][] = [
                'slug'=>$slug, 'name'=>(string)$entry['name'], 'subcategory'=>(string)($entry['subcategory'] ?? ''),
                'description'=>(string)($entry['description'] ?? ''), 'icon'=>JobRoleEnrichmentService::defaultIconForCatalogSlug($slug),
                'abbreviation'=>$roleId > 0 ? (string)($decorations[$roleId]['abbreviation'] ?? '') : '',
                'hidden'=>isset($hidden[$slug]), 'role_id'=>$roleId, 'holders'=>$roleId > 0 ? (int)($holders[$roleId] ?? 0) : 0,
            ];
        }
        return ['siteJobGroups'=>$groups, 'jobShowHidden'=>$showHidden, 'jobHiddenCount'=>count($hidden),
            'jobEnrichmentReady'=>$enrichment->hidingReady()];
    }

    /** Masquer / réafficher des fonctions du site pour la communauté (action groupée ou depuis la fiche). */
    public function hideSiteJobRoles(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $tenantId=(int)Session::get('tenant_id');
        $raw=$request->input('slugs',[]);
        $slugs=is_array($raw)?array_values($raw):[];
        $hide=$request->input('action')!=='show';
        $emploi=(int)$request->inputString('emploi','0');
        $back=$emploi>0 ? ['emploi'=>$emploi,'masquees'=>1] : ['vue'=>'site']+($request->inputString('masquees','0')==='1'?['masquees'=>1]:[]);
        if ($slugs===[]) return $this->jobReply($request,false,'Cochez au moins une fonction.',$back);
        $enrichment=new JobRoleEnrichmentService();
        if (!$enrichment->hidingReady()) return $this->jobReply($request,false,'Le masquage des fonctions arrive avec la prochaine mise à jour du site.',$back,503);
        try {
            $changed=$enrichment->setCatalogHidden($tenantId,$slugs,$hide,(int)Session::get('user_id'));
        } catch (\PDOException $e) {
            error_log('Effectifs jobs hide: '.$e->getMessage());
            return $this->jobReply($request,false,'Le changement n’a pas été enregistré. Réessayez.',$back,503);
        }
        $message=$hide
            ? sprintf('%d fonction(s) masquée(s). Les membres qui les occupent les gardent.', $changed)
            : sprintf('%d fonction(s) de nouveau proposée(s).', $changed);
        return $this->jobReply($request,true,$message,$back);
    }

    /** Couleur ou suppression des étiquettes de fonctions de la communauté (onglet Réglages). */
    public function saveJobTags(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $tenantId=(int)Session::get('tenant_id');
        $enrichment=new JobRoleEnrichmentService();
        if (!$enrichment->tagsReady()) return $this->jobReply($request,false,'Les étiquettes arrivent avec la prochaine mise à jour du site.',['vue'=>'reglages'],503);
        $delete=(int)$request->inputString('delete_tag','0');
        if ($delete>0) {
            $ok=$enrichment->deleteTag($tenantId,$delete);
            return $this->jobReply($request,$ok,$ok?'Étiquette supprimée. Les fonctions restent inchangées.':'Étiquette introuvable.',['vue'=>'reglages'],404);
        }
        $colors=$request->input('tag_color',[]);
        $owned=array_column($enrichment->tags($tenantId),'id');
        foreach (is_array($colors)?$colors:[] as $tagId=>$color) {
            if (!in_array((int)$tagId,$owned,true)) continue;
            $enrichment->updateTagColor($tenantId,(int)$tagId,is_scalar($color)?(string)$color:null);
        }
        return $this->jobReply($request,true,'Couleurs des étiquettes enregistrées.',['vue'=>'reglages']);
    }

    private function jobWorkspaceResponse(Request $request, array $data): Response
    {
        $data['jobsReferenceWorkspace'] = true;
        $data['jobsFragment'] = $request->header('X-Effectifs-Fragment') === 'jobs';
        if ($request->header('X-Effectifs-Fragment') === 'jobs') {
            return Response::view('admin.effectifs_workspace.fonctions',$data)
                ->header('Cache-Control','private, no-store')->header('Vary', 'X-Effectifs-Fragment');
        }
        return $this->shell('admin.effectifs_workspace.fonctions',$data)
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'X-Effectifs-Fragment');
    }

    private function jobWriteGuard(Request $request): ?Response
    {
        $denied=$this->denyUnlessAccess();
        if ($denied !== null) return $denied;
        $gate=Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate) && !EffectifsLmsAccess::canManageRoles($gate)) {
            return $this->jobReply($request,false,'Vous n’êtes pas habilité à modifier les emplois.',[],403);
        }
        if (!Csrf::verify($request)) return $this->jobReply($request,false,'Session expirée. Rechargez la page avant de réessayer.',[],419);
        return null;
    }

    private function jobReply(Request $request, bool $ok, string $message, array $query = [], int $status = 422): Response
    {
        $context = $request->inputString('_workspace_category', '0');
        if (ctype_digit($context) && (int)$context>0 && !isset($query['vue']) && !isset($query['categorie_emplois'])) $query['categorie_emplois']=(int)$context;
        $target=effectifs_workspace_url('fonctions').($query!==[]?'?'.http_build_query($query):'');
        if ($request->header('X-Effectifs-Fragment') === 'jobs') {
            return Response::json(['ok'=>$ok,'message'=>$message,'redirect'=>$target],$ok?200:$status)->header('Cache-Control','private, no-store');
        }
        Session::flash($ok?'success':'error',$message);
        return Response::redirect($target,303);
    }

    private function jobValues(Request $request, int $tenantId, int $roleId): array
    {
        $name=$request->input('name','');
        $description=$request->input('description','');
        if (!is_string($name) || trim($name)==='' || mb_strlen(trim($name))>120) throw new \InvalidArgumentException('Le nom doit comporter entre 1 et 120 caractères.');
        if (!is_string($description) || mb_strlen($description)>500) throw new \InvalidArgumentException('La description est limitée à 500 caractères.');
        $rawCategory=$request->input('category_id','0');
        if (!is_scalar($rawCategory) || !ctype_digit((string)$rawCategory)) throw new \InvalidArgumentException('Choisissez une catégorie valide.');
        $categoryId=(int)$rawCategory;
        if ($categoryId>0 && !$this->personnelJobRoleRepository->findCategoryById($categoryId,$tenantId)) throw new \InvalidArgumentException('Cette catégorie n’appartient pas à votre communauté.');
        $metadata=null;
        if ($request->input('structure_enabled') === '1') {
            $input=[];
            foreach (['parent_job_role_id','linked_unit_id','grade_id','specialty_id','code','kind'] as $field) $input[$field]=$request->input($field);
            $check=(new PersonnelJobRoleStructureService())->validate($tenantId,$roleId,$input);
            if ($check['errors'] !== []) throw new \InvalidArgumentException(implode(' ',array_values($check['errors'])));
            $metadata=$check['metadata'];
        }
        $enrichment=null;
        if ($request->input('enrichment_enabled') === '1') {
            $enrichment=(new JobRoleEnrichmentService())->validate($tenantId,$roleId,[
                'abbreviation'=>$request->input('abbreviation',''),'icon'=>$request->input('icon',''),'tags'=>$request->input('tags',''),
            ]);
        }
        return [trim($name),trim($description),$categoryId,$metadata,$enrichment];
    }

    public function createJobRole(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $tenantId=(int)Session::get('tenant_id');
        $pdo=Database::getPdo();
        try {
            $pdo->beginTransaction();
            $this->lockJobTenant($pdo, $tenantId);
            [$name,$description,$categoryId,$metadata,$enrichment]=$this->jobValues($request,$tenantId,0);
            if ($categoryId===0) $categoryId=$this->resolveJobCategoryId($tenantId,0);
            $slug=$this->personnelJobRoleRepository->uniqueSlugFromName($tenantId,$name);
            $id=$this->personnelJobRoleRepository->createRole($tenantId,$categoryId,$name,$slug,$description?:null,0,false);
            if ($metadata!==null) (new PersonnelJobRoleStructureService())->save($tenantId,$id,$metadata);
            if ($enrichment!==null) (new JobRoleEnrichmentService())->save($tenantId,$id,$enrichment);
            $this->applyCatalogSpecialties($request, $tenantId, $id);
            $this->recordJobChange('job_role', $id, 'created', [], ['name'=>$name,'slug'=>$slug,'description'=>$description,'category_id'=>$categoryId,'structure'=>$metadata ?? []]+($enrichment ?? []));
            $pdo->commit();
            $linked=$this->applyCatalogQualifications($request,$tenantId,$id);
            return $this->jobReply($request,true,$linked>0?'Emploi créé, '.$linked.' qualification(s) du catalogue ajoutée(s) à ses prérequis. Aucun membre ni droit d’accès n’a été modifié.':'Emploi créé. Aucun membre ni droit d’accès n’a été modifié.',['emploi'=>$id]);
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->jobReply($request,false,$e->getMessage(),['nouveau'=>1]);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Effectifs jobs create: '.$e->getMessage());
            return $this->jobReply($request,false,'L’emploi n’a pas été créé. Réessayez après avoir vérifié les migrations.',[],503);
        }
    }

    public function saveJobRole(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $tenantId=(int)Session::get('tenant_id');
        $id=(int)$request->inputString('id','0');
        $pdo=Database::getPdo();
        try {
            $pdo->beginTransaction();
            $this->lockJobTenant($pdo, $tenantId);
            $existing=$this->personnelJobRoleRepository->findRoleById($id,$tenantId);
            if (!$existing) {
                $pdo->rollBack();
                return $this->jobReply($request,false,'Emploi introuvable.',[],404);
            }
            $existing['structure']=(new PersonnelJobRoleStructureService())->listMetadata($tenantId)[$id] ?? [];
            [$name,$description,$categoryId,$metadata,$enrichment]=$this->jobValues($request,$tenantId,$id);
            if ($categoryId===0) $categoryId=$this->resolveJobCategoryId($tenantId,0);
            $this->personnelJobRoleRepository->updateRole($id,$tenantId,$categoryId,$name,(string)$existing['slug'],$description?:null,(int)($existing['sort_order']??0),$existing['mos_code']??null,$existing['mos_specialty_title']??null);
            if ($metadata!==null) (new PersonnelJobRoleStructureService())->save($tenantId,$id,$metadata);
            if ($enrichment!==null) (new JobRoleEnrichmentService())->save($tenantId,$id,$enrichment);
            $this->applyCatalogSpecialties($request, $tenantId, $id);
            $this->recordJobChange('job_role', $id, 'updated', $existing, array_replace($existing, ['name'=>$name,'description'=>$description,'category_id'=>$categoryId,'structure'=>$metadata ?? $existing['structure']], $enrichment ?? []));
            $pdo->commit();
            $linked=$this->applyCatalogQualifications($request,$tenantId,$id);
            return $this->jobReply($request,true,$linked>0?'Emploi enregistré, '.$linked.' qualification(s) du catalogue ajoutée(s) à ses prérequis. Les affectations et les droits restent indépendants.':'Emploi enregistré. Les affectations et les droits restent indépendants.',['emploi'=>$id]);
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->jobReply($request,false,$e->getMessage(),['emploi'=>$id]);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Effectifs jobs save: '.$e->getMessage());
            return $this->jobReply($request,false,'Enregistrement impossible. Vos modifications n’ont pas été appliquées.',['emploi'=>$id],503);
        }
    }

    /**
     * « Copier tout » depuis le catalogue avec la case « importer les qualifications liées » cochée : qualifications du
     * catalogue importées dans le référentiel (sans doublon) et ajoutées aux prérequis de l’emploi. Ne bloque jamais
     * l’enregistrement de l’emploi. Renvoie le nombre de prérequis ajoutés.
     */
    private function applyCatalogQualifications(Request $request, int $tenantId, int $roleId): int
    {
        $refs=$request->input('catalog_qualification_refs',[]);
        $refs=(string)$request->input('catalog_qualifications_prereq','')==='1' && is_array($refs) ? array_values($refs) : [];
        // « Rattacher à la fonction » d’un brevet ou d’une doctrine de la fiche du catalogue.
        $keys=$request->input('catalog_reference_keys',[]);
        $keys=is_array($keys) ? array_values($keys) : [];
        if ($refs===[] && $keys===[]) return 0;
        try {
            $pdo=Database::getPdo();
            $linker=new \App\Services\Personnel\JobCatalogQualificationLinker(
                new \App\Services\Personnel\QualificationCatalogImporter(new \App\Services\Personnel\QualificationReferenceWriter($pdo)),
                new \App\Services\Personnel\JobPrerequisiteService($pdo)
            );
            $lang=function_exists('locale')?(string)locale():'fr';
            $actor=(int)Session::get('user_id');
            $added=$refs!==[] ? $linker->apply($tenantId,$roleId,$refs,$lang,$actor)['added'] : 0;
            return $added+($keys!==[] ? $linker->applyReferences($tenantId,$roleId,$keys,$lang,$actor)['added'] : 0);
        } catch (\Throwable $e) {
            error_log('Effectifs jobs catalog qualifications: '.$e->getMessage());
            return 0;
        }
    }

    private function applyCatalogSpecialties(Request $request, int $tenantId, int $jobId): void
    {
        $refs = $request->input('catalog_specialty_refs', []);
        if ($refs === []) return;
        if (!is_array($refs) || count($refs) > 64 || count(array_filter($refs, 'is_string')) !== count($refs)) {
            throw new \InvalidArgumentException('Les références de spécialité copiées sont invalides.');
        }
        $service = new \App\Services\Personnel\MilitarySpecialtyService(Database::getPdo());
        if (!$service->ready()) return; // Compatibilité avant le passage de la migration additive.
        $byRef = array_column($service->catalog(), 'id', 'reference_key');
        $ids = $service->jobCodeIds($tenantId, $jobId);
        foreach ($refs as $ref) {
            if (!isset($byRef[$ref])) throw new \InvalidArgumentException('Une référence de spécialité copiée est indisponible. Choisissez-la à nouveau dans le catalogue.');
            $ids[] = (int) $byRef[$ref];
        }
        $service->saveJobCodes($tenantId, $jobId, array_values(array_unique($ids)), (int) Session::get('user_id'));
    }

    private function recordJobChange(string $entity, int $id, string $operation, array $before, array $after): void
    {
        (new PersonnelJobRoleHistoryService())->record((int)Session::get('tenant_id'), (int)Session::get('user_id'), $entity, $id, $operation, $before, $after);
    }

    public function deleteJobRole(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $tenantId=(int)Session::get('tenant_id');
        $id=(int)$request->inputString('id','0');
        $pdo=Database::getPdo();
        try {
            $pdo->beginTransaction();
            $this->lockJobTenant($pdo, $tenantId);
            $role=$this->personnelJobRoleRepository->findRoleById($id,$tenantId);
            if (!$role) throw new \InvalidArgumentException('Emploi introuvable.');
            $role['structure']=(new PersonnelJobRoleStructureService())->listMetadata($tenantId)[$id] ?? [];
            if (!$this->personnelJobRoleRepository->deleteRole($id,$tenantId)) {
                throw new \InvalidArgumentException('Cet emploi est protégé ou encore utilisé (attribution, sous-emploi ou poste ORBAT). Retirez d’abord ces liens explicitement.');
            }
            // Failure to save the archive rolls the deletion back too.
            $this->recordJobChange('job_role', $id, 'archived', $role, []);
            $pdo->commit();
            return $this->jobReply($request,true,'Emploi archivé. Vous pouvez le restaurer dans « Historique et archives ».');
        } catch (\InvalidArgumentException | \PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \PDOException) error_log('Effectifs jobs archive: '.$e->getMessage());
            return $this->jobReply($request,false,$e instanceof \InvalidArgumentException ? $e->getMessage() : 'Archivage impossible. L’emploi a été conservé.', ['emploi'=>$id], $e instanceof \PDOException ? 503 : 422);
        }
    }

    public function restoreJobRole(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $pdo=Database::getPdo();
        try {
            $pdo->beginTransaction();
            $tenantId=(int)Session::get('tenant_id');
            $this->lockJobTenant($pdo,$tenantId);
            $id=(new PersonnelJobRoleHistoryService())->restore($tenantId,(int)Session::get('user_id'),(int)$request->inputString('archive_id','0'),$this->personnelJobRoleRepository);
            $pdo->commit();
            return $this->jobReply($request,true,'Définition restaurée. Aucun titulaire ni droit d’accès n’a été réattribué.', ['emploi'=>$id]);
        } catch (\InvalidArgumentException | \PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \PDOException) error_log('Effectifs jobs restore: '.$e->getMessage());
            return $this->jobReply($request,false,$e instanceof \InvalidArgumentException ? $e->getMessage() : 'La restauration n’a pas abouti. L’archive a été conservée.', ['vue'=>'historique'], $e instanceof \PDOException ? 503 : 422);
        }
    }

    public function saveJobCategory(Request $request, array $params = []): Response
    {
        if ($denied = $this->jobWriteGuard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->inputString('category_edit_id', '0');
        $query = ['vue' => 'categories'];
        $pdo = Database::getPdo();
        try {
            $pdo->beginTransaction();
            // Serialise parent changes: two concurrent edits must not create a cycle.
            $this->lockJobTenant($pdo, $tenantId);
            $existing = $id > 0 ? $this->personnelJobRoleRepository->findCategoryById($id, $tenantId) : null;
            if ($id > 0 && !$existing) {
                $pdo->rollBack();
                return $this->jobReply($request, false, 'Catégorie introuvable.', $query, 404);
            }
            if ($request->input('operation') === 'delete') {
                if ($id < 1 || !$this->personnelJobRoleRepository->deleteCategory($id, $tenantId)) {
                    throw new \InvalidArgumentException('Une catégorie contenant des emplois ou des sous-catégories ne peut pas être supprimée.');
                }
                $this->recordJobChange('job_category', $id, 'deleted', $existing ?? [], []);
                $pdo->commit();
                return $this->jobReply($request, true, 'Catégorie vide supprimée.', []);
            }
            $name = $request->input('category_name', '');
            if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 120) {
                throw new \InvalidArgumentException('Le nom de catégorie doit comporter entre 1 et 120 caractères.');
            }
            $raw = $request->input('parent_id', '0');
            if ($raw === '') {
                $raw = '0';
            }
            if (!is_scalar($raw) || !ctype_digit((string) $raw)) {
                throw new \InvalidArgumentException('Choisissez une catégorie parente valide.');
            }
            $parent = (int) $raw;
            $seen = [];
            for ($cursor = $parent; $cursor > 0; $cursor = (int) ($row['parent_id'] ?? 0)) {
                if ($cursor === $id || isset($seen[$cursor])) {
                    throw new \InvalidArgumentException('Une catégorie ne peut pas être rangée sous elle-même ou un de ses descendants.');
                }
                $row = $this->personnelJobRoleRepository->findCategoryById($cursor, $tenantId);
                if (!$row) {
                    throw new \InvalidArgumentException('La catégorie parente n’appartient pas à votre communauté.');
                }
                $seen[$cursor] = true;
            }
            if ($existing) {
                $this->personnelJobRoleRepository->updateCategory($id, $tenantId, $parent ?: null, trim($name), (string) $existing['slug'], (int) ($existing['sort_order'] ?? 0));
            } else {
                $slug = $this->personnelJobRoleRepository->uniqueCategorySlugFromName($tenantId, trim($name));
                $id = $this->personnelJobRoleRepository->createCategory($tenantId, $parent ?: null, trim($name), $slug, 0);
            }
            $this->recordJobChange('job_category', $id, $existing ? 'updated' : 'created', $existing ?? [], array_replace($existing ?? [], ['name'=>trim($name),'parent_id'=>$parent ?: null]));
            $pdo->commit();
            $query = ['categorie_emplois'=>$id,'modifier_categorie'=>1];
            return $this->jobReply($request, true, $existing ? 'Catégorie enregistrée.' : 'Catégorie créée.', $query);
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->jobReply($request, false, $e->getMessage(), $query);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Effectifs job category: ' . $e->getMessage());
            return $this->jobReply($request, false, 'La catégorie n’a pas été modifiée. Réessayez après avoir rechargé la liste.', $query, 503);
        }
    }

    private function lockJobTenant(\PDO $pdo, int $tenantId): void
    {
        $sql = 'SELECT id FROM tenants WHERE id = ?';
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $lock = $pdo->prepare($sql);
        $lock->execute([$tenantId]);
        if (!$lock->fetchColumn()) {
            throw new \InvalidArgumentException('Communauté introuvable.');
        }
    }

    public function saveJobSettings(Request $request, array $params = []): Response
    {
        if ($denied = $this->jobWriteGuard($request)) return $denied;
        $tenantId = (int) Session::get('tenant_id');
        $pdo = Database::getPdo();
        try {
            $pdo->beginTransaction();
            $this->lockJobTenant($pdo, $tenantId);
            $settings = PersonnelJobRoleAssignmentsSettings::mergePatch($this->tenantRepository->getSettings($tenantId), [
                'sync_jobs_from_assignments' => $request->input('sync_jobs_from_assignments') === '1',
            ]);
            $this->tenantRepository->updateSettings($tenantId, $settings);
            $pdo->commit();
            return $this->jobReply($request, true, 'Réglage enregistré. Aucun emploi existant n’a été retiré.', ['vue'=>'reglages']);
        } catch (\PDOException | \InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Effectifs jobs settings: ' . $e->getMessage());
            return $this->jobReply($request, false, 'Le réglage n’a pas pu être enregistré.', [], 503);
        }
    }

    private function jobRecoveryLabels(Request $request): array
    {
        $raw=$request->method()==='POST'?$request->input('labels','CNOS,RFL'):$request->query('labels','CNOS,RFL');
        if (!is_string($raw) || mb_strlen($raw)>1000) return $request->isPost() ? [] : ['CNOS','RFL'];
        return array_slice(array_values(array_unique(array_filter(array_map('trim',preg_split('/[,;\r\n]+/',$raw)?:[])))),0,20);
    }

    public function recoverJobRoles(Request $request, array $params = []): Response
    {
        if ($denied=$this->jobWriteGuard($request)) return $denied;
        $tenantId=(int)Session::get('tenant_id');
        $labels=$this->jobRecoveryLabels($request);
        if ($labels===[]) return $this->jobReply($request,false,'Indiquez les libellés à rechercher.',['vue'=>'recuperation']);
        try {
            $result=(new PersonnelJobRoleRecoveryService(Database::getPdo()))->apply($tenantId,$labels,(int)Session::get('user_id'));
        } catch (\Throwable $e) {
            error_log('Effectifs jobs recovery apply: ' . $e->getMessage());
            return $this->jobReply($request,false,'La récupération n’a pas abouti. Vérifiez les migrations et relancez l’analyse avant de réessayer.',['vue'=>'recuperation','labels'=>implode(',',$labels)],503);
        }
        $jobs=(int)($result['restored_jobs']??0);
        $assignments=(int)($result['restored_assignments']??0);
        return $this->jobReply($request,true,$jobs.' emploi(s) et '.$assignments.' attribution(s) restaurés depuis les traces conservées. Les éléments sans preuve ou en conflit ne sont pas remplacés.',['vue'=>'recuperation','labels'=>implode(',',$labels)]);
    }

    public function affectations(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();
        $matrix = (new \App\Services\Effectifs\AffectationsMatrixService(
            $this->userRepository,
            $this->unitRepository,
            $this->roleRepository,
            $this->personnelJobRoleRepository,
            new \App\Services\Effectifs\UnitCodeService()
        ))->build($tenantId);
        $view = (string) $request->query('vue', '');
        try {
            $movements = $this->personnelAssignmentRepository->listRecentPrimaryMovementsForTenant($tenantId, 30);
        } catch (\Throwable) {
            $movements = [];
        }

        return $this->shell('admin.effectifs_workspace.affectations', [
            'title' => 'Affectations',
            'effectifsNav' => 'affectations',
            'affMatrix' => $matrix,
            'affMovements' => $movements,
            // Tableau par unité (maquette 2026-11-02) par défaut ; « ?vue=membres » ouvre la liste par membre.
            'affView' => $view === 'membres' ? 'members' : 'units',
            'affInitialQuery' => mb_substr(trim((string) $request->query('q', '')), 0, 80, 'UTF-8'),
            'affInitialUnit' => max(0, (int) $request->query('unite', 0)),
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'canEditCommunityCode' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
            'csrfToken' => Csrf::token(),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    public function commandChain(Request $request, array $params = []): Response
    {
        $json = self::chainWantsJson($request);
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $json ? Response::json(['ok' => false, 'message' => 'Accès réservé au pilotage des effectifs.'], 403) : $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $vars = $this->commandChainViewData($request, $tenantId);

        // Rafraîchissement de la page sans rechargement : uniquement la zone vivante, mêmes droits que la page.
        if ($json && $request->queryString('fragment') === '1') {
            $html = Response::view('admin.effectifs_workspace.chaine', $vars + ['chainFragment' => true])->body();
            if (function_exists('locale') && locale() === 'en') {
                $html = \App\Support\HtmlTranslator::translate($html);
            }

            return Response::json(['ok' => true, 'html' => $html, 'missing' => (int) $vars['chainMissingCommanders'], 'gaps' => self::adequacyIssueCount($vars['chainAdequacy'])]);
        }

        return $this->shell('admin.effectifs_workspace.chaine', $vars + [
            'title' => 'Chaîne de commandement',
            'effectifsNav' => 'chaine',
            'commandChainMissingCount' => $vars['chainMissingCommanders'],
            'adequacyIssueCount' => self::adequacyIssueCount($vars['chainAdequacy']),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    /** @return array<string, mixed> variables de la vue, pour la communauté de la session uniquement */
    private function commandChainViewData(Request $request, int $tenantId): array
    {
        $gate = Gate::getInstance();
        $data = (new PersonnelCommandChainService(
            $this->unitRepository,
            $this->userRepository,
            $this->personnelAssignmentRepository
        ))->workspace($tenantId);
        $adequacy = null;
        try {
            $adequacy = (new \App\Services\Organization\OrgAdequacyService(
                $this->unitRepository,
                $this->userRepository,
                $this->personnelAssignmentRepository
            ))->evaluate($tenantId);
        } catch (\Throwable) {
            $adequacy = null;
        }
        try {
            $activeIds = $this->userRepository->listActiveUserIdsForTenant($tenantId);
        } catch (\Throwable) {
            $activeIds = [];
        }

        return [
            'chainAdequacy' => $adequacy,
            'adequacyOnlyGaps' => $request->query('ecarts') === '1',
            // Onglet de la page ouvert au chargement (?vue=organigramme|corriger|chefs|releve).
            'chainView' => (string) $request->query('vue', ''),
            'chainUnits' => $data['units'],
            'chainMembers' => $data['members'],
            'chainMemberOptions' => $data['memberOptions'],
            'chainMissingCommanders' => $data['missing_commanders'],
            'chainMissingSuperiors' => $data['missing_superiors'],
            'chainWithoutUnit' => $data['without_unit'],
            'chainActiveIds' => array_values(array_map('intval', $activeIds)),
            'chainAdvice' => \App\Services\Effectifs\CommandChainAdvisor::analyze($data['units'], $data['members'], $adequacy, $activeIds),
            'canManageAssignments' => EffectifsLmsAccess::canManageAssignments($gate),
            'csrfToken' => Csrf::token(),
        ];
    }

    public function saveCommandChain(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return self::chainWantsJson($request) ? Response::json(['ok' => false, 'message' => 'Accès réservé au pilotage des effectifs.'], 403) : $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->commandChainReply($request, false, 'Session expirée. Rechargez la page, puis enregistrez de nouveau.', 419, ['reload' => true]);
        }
        $gate = Gate::getInstance();
        if (!EffectifsLmsAccess::canManageAssignments($gate)) {
            return $this->commandChainReply($request, false, 'Vous n’êtes pas habilité à désigner les chefs d’unité.', 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        $raw = $request->input('commanders', []);
        $commanders = is_array($raw) ? $raw : [];
        $rawExpected = $request->input('expected_commanders', []);
        $expected = is_array($rawExpected) ? $rawExpected : [];
        $pdo = Database::getPdo();
        try {
            $allowed = $this->userRepository->listActiveUserIdsForTenant($tenantId);
            // Plusieurs chefs d’un coup : tout ou rien, pour ne jamais annoncer un échec après une écriture partielle.
            $pdo->beginTransaction();
            $result = (new PersonnelCommandChainService(
                $this->unitRepository,
                $this->userRepository,
                $this->personnelAssignmentRepository
            ))->saveCommanders($tenantId, $commanders, $allowed, $expected);
            $pdo->commit();
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->commandChainReply($request, false, 'L’enregistrement des chefs a échoué : rien n’a été modifié. Réessayez dans un instant.', 500);
        }
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

        return $this->commandChainReply($request, $result['ok'], $result['message'], $result['ok'] ? 200 : ($result['conflict'] ? 409 : 422), [
            'updated' => $result['updated'],
            'conflict' => $result['conflict'],
        ]);
    }

    /**
     * Réponse d’une écriture de la chaîne : JSON pour un envoi en arrière-plan, sinon message et retour à la page
     * (repli sans JavaScript).
     *
     * @param array<string, mixed> $extra
     */
    private function commandChainReply(Request $request, bool $ok, string $message, int $status, array $extra = []): Response
    {
        if (self::chainWantsJson($request)) {
            return Response::json(['ok' => $ok, 'message' => $message] + $extra, $status);
        }
        Session::flash($ok ? 'success' : 'error', $message);

        return Response::redirect(effectifs_workspace_url('chaine'));
    }

    /** Écarts d’adéquation de l’évaluation (0 si le contrôle est désactivé ou indisponible), pour les pastilles. */
    private static function adequacyIssueCount(mixed $result): int
    {
        if (!is_array($result) || empty($result['enabled'])) {
            return 0;
        }

        return is_array($result['issues'] ?? null) ? count($result['issues']) : 0;
    }

    private static function chainWantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Référentiel des échelons et réglages de l’adéquation de l’organisation (attentes par échelon, règles).
     */
    public function adequacySettings(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $settings = \App\Services\Organization\OrgAdequacySettings::forTenant($tenantId);
        $profile = \App\Services\Organization\OrgAdequacySettings::profileForTenant($tenantId, $settings);
        $service = new \App\Services\Organization\OrgAdequacyService(
            $this->unitRepository,
            $this->userRepository,
            $this->personnelAssignmentRepository
        );
        [, $gradeLabels] = $service->gradeCatalog($tenantId);
        $qualifications = [];
        try {
            foreach ((new \App\Repositories\QualificationDefinitionRepository())->listForTenant($tenantId) as $d) {
                $qualifications[] = ['id' => (int) $d['id'], 'label' => trim((string) ($d['name'] ?? ''))];
            }
        } catch (\Throwable) {
        }
        $jobRoles = [];
        try {
            foreach ((new \App\Repositories\PersonnelJobRoleRepository())->listRolesWithCategory($tenantId) as $r) {
                $jobRoles[] = ['id' => (int) $r['id'], 'label' => trim((string) ($r['name'] ?? ''))];
            }
        } catch (\Throwable) {
        }
        // Écarts actuels (vue d’ensemble) : même évaluation que la chaîne, la fiche membre et les alertes RH.
        try {
            $result = $service->evaluate($tenantId);
        } catch (\Throwable) {
            $result = null;
        }
        // Retour d’un enregistrement refusé depuis la fenêtre d’un échelon : l’erreur s’affiche dans la fenêtre.
        $openEchelon = (string) $request->query('echelon', '');
        $openEchelon = in_array($openEchelon, \App\Services\Organization\EchelonStandards::keys(), true) ? $openEchelon : '';
        $modalError = '';
        if ($openEchelon !== '') {
            $modalError = trim((string) Session::getFlash('error', ''));
        }

        return $this->shell('admin.effectifs_workspace.adequation', [
            'title' => 'Adéquation de l’organisation',
            'effectifsNav' => 'adequation',
            'adequacyIssueCount' => !empty($settings['enabled']) ? self::adequacyIssueCount($result) : 0,
            'adequacySettings' => $settings,
            'adequacyProfile' => $profile,
            'adequacyStandards' => \App\Services\Organization\OrgAdequacySettings::effectiveStandards($settings, $profile),
            'adequacyGradeLabels' => $gradeLabels,
            'adequacyQualifications' => $qualifications,
            'adequacyJobRoles' => $jobRoles,
            'adequacyResult' => $result,
            'adequacyTab' => (string) $request->query('onglet', ''),
            'adequacyOpenEchelon' => $openEchelon,
            'adequacyModalError' => $modalError,
            'adequacyViewerId' => (int) Session::get('user_id'),
            'canEditAdequacy' => $this->canEditAdequacy(),
            'csrfToken' => Csrf::token(),
            'rosterCounts' => $this->rosterCounts($tenantId),
        ]);
    }

    /**
     * « Ce que ça change » : écarts par échelon avec les réglages en cours de saisie, comparés aux réglages
     * enregistrés. Rien n’est enregistré ; mêmes services que l’évaluation de la chaîne.
     */
    public function previewAdequacySettings(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'message' => 'Session expirée. Rechargez la page.'], 419);
        }
        $tenantId = (int) Session::get('tenant_id');
        $raw = $request->input('org_adequacy', []);
        $proposed = \App\Services\Organization\OrgAdequacySettings::sanitize(is_array($raw) ? $raw : []);
        $proposed['enabled'] = true;
        $saved = \App\Services\Organization\OrgAdequacySettings::forTenant($tenantId);
        $saved['enabled'] = true;
        $service = new \App\Services\Organization\OrgAdequacyService(
            $this->unitRepository,
            $this->userRepository,
            $this->personnelAssignmentRepository
        );
        try {
            $evaluate = static function (array $settings) use ($service, $tenantId): array {
                $profile = \App\Services\Organization\OrgAdequacySettings::profileForTenant($tenantId, $settings);

                return \App\Services\Organization\OrgAdequacyEngine::evaluate($service->loadInput($tenantId, $settings, $profile));
            };
            $before = $evaluate($saved);
            $after = $evaluate($proposed);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'message' => 'Aperçu indisponible pour le moment.'], 500);
        }

        return Response::json([
            'ok' => true,
            'current' => \App\Services\Organization\OrgAdequacyImpact::byEchelon($before),
            'proposed' => \App\Services\Organization\OrgAdequacyImpact::byEchelon($after),
            'total' => ['current' => count($before['issues']), 'proposed' => count($after['issues'])],
        ]);
    }

    /** Adresse de retour de la page d’adéquation : onglet et fenêtre d’échelon d’où vient l’enregistrement. */
    private static function adequacyReturnUrl(Request $request, bool $reopen): string
    {
        $query = [];
        $tab = (string) $request->input('adqp_tab', '');
        if (in_array($tab, ['regles', 'echelons'], true)) {
            $query['onglet'] = $tab;
        }
        $echelon = (string) $request->input('adqp_echelon', '');
        if ($reopen && in_array($echelon, \App\Services\Organization\EchelonStandards::keys(), true)) {
            $query['onglet'] = 'echelons';
            $query['echelon'] = $echelon;
        }

        return effectifs_workspace_url('chaine/adequation') . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    public function saveAdequacySettings(Request $request, array $params = []): Response
    {
        $denied = $this->denyUnlessAccess();
        if ($denied !== null) {
            return $denied;
        }
        // Erreur : retour dans la fenêtre de l’échelon en cours ; succès : retour à l’onglet.
        $errorBack = self::adequacyReturnUrl($request, true);
        $back = self::adequacyReturnUrl($request, false);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Rechargez la page, puis enregistrez de nouveau.');

            return Response::redirect($errorBack);
        }
        if (!$this->canEditAdequacy()) {
            Session::flash('error', 'Vous n’êtes pas habilité à modifier les attentes de l’organisation.');

            return Response::redirect($errorBack);
        }
        $tenantId = (int) Session::get('tenant_id');
        $raw = $request->input('org_adequacy', []);
        $raw = is_array($raw) ? $raw : [];
        if ($request->input('reset') === '1') {
            $raw = \App\Services\Organization\OrgAdequacySettings::defaults();
        }
        $adminSettings = new \App\Repositories\TenantAdminSettingsRepository($this->tenantRepository);
        $all = $adminSettings->getForTenant($tenantId);
        $all['org_adequacy'] = \App\Services\Organization\OrgAdequacySettings::sanitize($raw);
        $adminSettings->saveForTenant($tenantId, $all);
        \App\Services\Organization\OrgAdequacyService::forget();
        Session::flash('success', $request->input('reset') === '1'
            ? 'Les valeurs par défaut du référentiel sont rétablies.'
            : 'Réglages de l’adéquation enregistrés. La chaîne de commandement en tient compte dès maintenant.');

        return Response::redirect($back);
    }

    private function canEditAdequacy(): bool
    {
        $gate = Gate::getInstance();

        return EffectifsLmsAccess::canManageAssignments($gate)
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
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
            'workflowSummaries' => \App\Services\Workflow\WorkflowHooks::summaries(
                $tenantId,
                \App\Services\Workflow\WorkflowEngine::SUBJECT_ELEVATION,
                $enrichedRequests
            ),
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

            return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
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

            return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
        }
        if (!in_array($status, ElevationRequestRepository::STATUSES, true)) {
            Session::flash('error', 'Statut non reconnu.');

            return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
        }
        $currentStatus = (string) ($existing['status'] ?? 'pending');
        if (in_array($currentStatus, ['approved', 'rejected'], true)) {
            Session::flash('error', 'Cette demande a déjà été traitée (' . ($currentStatus === 'approved' ? 'acceptée' : 'refusée') . ') — action impossible.');

            return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
        }

        $proposal = $this->readElevationProposalFromRequest($request);
        $validated = $this->validateElevationProposal($tenantId, $proposal);
        if ($validated['error'] !== null) {
            Session::flash('error', $validated['error']);

            return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
        }
        $proposal = $validated['proposal'];
        // Droits demandés à l'origine : pour dire au membre ce qui est accordé et ce qui ne l'est pas.
        $requestedPermissionIds = json_decode((string) ($existing['proposed_permission_ids'] ?? ''), true);
        $requestedPermissionIds = is_array($requestedPermissionIds) ? array_values(array_map('intval', $requestedPermissionIds)) : [];
        $afterDecision = static function (string $decided, array $grantedIds) use ($tenantId, $existing, $requestedPermissionIds, $note): void {
            try {
                (new \App\Services\Operator\OperatorAccessRequestService())->afterDecision(
                    $tenantId, $existing, $decided, $requestedPermissionIds, $grantedIds, $note, (int) Session::get('user_id')
                );
            } catch (\Throwable $e) {
                error_log('Demande d’élévation : journal ou notification de la décision impossible : ' . $e->getMessage());
            }
        };

        if ($status === 'approved') {
            // Accorder un niveau d'accès ou des droits est l'affaire des responsables des accès : qui ne gère que les
            // statuts (RH) peut accepter un grade, un emploi ou une unité, pas donner un rôle ni des permissions.
            if (\App\Services\Effectifs\ElevationApprovalService::proposalGrantsAccess($proposal) && !EffectifsLmsAccess::canManageRoles($gate)) {
                Session::flash('error', 'Cette demande accorde un niveau d’accès ou des droits : seuls les responsables des accès peuvent l’accepter. Retirez-les de la proposition, ou passez la demande à un responsable des accès.');

                return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
            }
            if ($request->input('confirm_apply') !== '1') {
                Session::flash('error', 'Confirmez l’application des changements avant d’accepter la demande.');

                return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
            }
            $apply = $this->elevationApprovalService->applyApprovedChanges(
                $tenantId,
                (int) ($existing['target_user_id'] ?? 0),
                $proposal,
                (int) Session::get('user_id')
            );
            if (!$apply['ok']) {
                Session::flash('error', $apply['message']);

                return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
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
                $afterDecision('approved', is_array($proposal['permission_ids'] ?? null) ? $proposal['permission_ids'] : []);
                $targetId = (int) ($existing['target_user_id'] ?? 0);
                if ($targetId > 0) {
                    (new \App\Support\PersonnelHrPdfService())->maybeAutoIssue(
                        $tenantId,
                        $targetId,
                        'elevation',
                        (int) Session::get('user_id'),
                        [
                            'title' => 'Décision d’élévation',
                            'detail' => (string) ($apply['record'] ?? $apply['message'] ?? ''),
                        ]
                    );
                }
            }

            return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
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
        if ($ok && $status === 'rejected') {
            $afterDecision('rejected', []);
        }
        Session::flash(
            $ok ? 'success' : 'error',
            $ok ? 'Le statut de la demande a été mis à jour.' : 'Statut invalide ou mise à jour impossible.'
        );

        return Response::redirect(\App\Support\BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'));
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
        $referenceOnly = !empty($extra['jobsReferenceWorkspace']);
        if ($referenceOnly) {
            // Reference editing does not need dossier scans, inbox assembly or phase calculations.
            $extra += ['rosterCounts'=>[], 'qualificationsExpiringCount'=>0, 'personnelDuplicateScan'=>[],
                'mobilityPendingCount'=>0, 'rhAlertTotalCount'=>0, 'rhInboxActionCount'=>0, 'commandChainMissingCount'=>0];
        }
        $counts = $extra['rosterCounts'] ?? $this->rosterCounts($tenantId);
        $elevationOpen = 0;
        if (!$referenceOnly) {
            try {
                $elevationOpen = $this->elevationRequestRepository->countOpenForTenant($tenantId);
            } catch (\Throwable) {
                $elevationOpen = 0;
            }
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
        $rhInboxTotal = $extra['rhInboxActionCount'] ?? null;
        if ($rhInboxTotal === null) {
            try {
                $hr = \App\Services\Effectifs\PersonnelHrWorkspaceSettings::forTenant($tenantId);
                $rhInboxTotal = (int) (\App\Core\Container::get(\App\Services\Effectifs\RhActionInboxService::class)->build(
                    $tenantId,
                    (int) ($hr['inactivity_days'] ?? null),
                    (int) ($hr['absence_days'] ?? null)
                )['total'] ?? 0);
            } catch (\Throwable) {
                $rhInboxTotal = (int) $rhAlertTotal + (int) $elevationOpen + (int) $mobilityPending;
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
        $kpi = ['no_account' => 0, 'arrivals' => 0, 'departures' => 0];
        if (!$referenceOnly) {
            $since = date('Y-m-d H:i:s', strtotime('-30 days'));
            try {
                $kpi['no_account'] = count((new \App\Services\Effectifs\NoAccountDirectory($this->userRepository))->list($tenantId));
                $kpi['arrivals'] = $this->userRepository->countJoinedSince($tenantId, $since);
                $kpi['departures'] = $this->memberDepartureRepository->countSince($tenantId, $since);
            } catch (\Throwable) {
            }
        }
        $extras = $referenceOnly ? ['roleplayDueCount'=>0, 'integrationOpenCount'=>0, 'phaseGateCount'=>0, 'phaseAutoErrorCount'=>0]
            : \App\Support\EffectifsWorkspaceShellExtras::counts($tenantId);

        return Response::view('layout.main', array_merge([
            'content' => 'admin.effectifs_workspace.shell',
            'effectifsContent' => $content,
            'isBackOfficeShell' => true,
            'boSkipPageHead' => true,
            // The member page embeds the personnel editor. Its response only contains
            // the view body, so its component stylesheet must be loaded by this shell.
            'backOfficePageCss' => ['effectifs_lms.css', 'personnel-dossier.css', 'back-office-effectifs-workspace.css', 'back-office-effectifs-refonte.css', 'back-office-effectifs-roster.css', 'effectifs-pages.css', 'back-office-effectifs-maquette.css', 'back-office-effectifs-invitations.css', 'back-office-effectifs-sanscompte.css', 'back-office-tableaux.css', 'effectifs-kit.css', 'access-register.css', 'eff-availability-check.css', 'eff-roster-lisible.css'],
            'showPortalFooter' => false,
            'rosterCounts' => $counts,
            'effKpi' => $kpi,
            'elevationOpenCount' => $elevationOpen,
            'qualificationsExpiringCount' => $qualifExpiring,
            'personnelDuplicateScan' => $dupScan,
            'mobilityPendingCount' => $mobilityPending,
            'rhAlertTotalCount' => $rhAlertTotal,
            'rhInboxActionCount' => $rhInboxTotal,
            'commandChainMissingCount' => $chainMissing,
            'roleplayDueCount' => $extras['roleplayDueCount'],
            'integrationOpenCount' => $extras['integrationOpenCount'],
            'phaseGateCount' => $extras['phaseGateCount'],
            'phaseAutoErrorCount' => $extras['phaseAutoErrorCount'],
            'viewerName' => (string) (Session::get('display_name') ?? Session::get('callsign') ?? ''),
            'communityName' => $referenceOnly ? '' : $this->communityNameForTenant($tenantId),
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

        // Pays de l’unité d’abord (France ou États-Unis), sinon le système de grades de la communauté.
        $preferredGradeCountry = CommunityLanguages::preferredGradeCountry($this->tenantRepository->getSettings($tenantId));

        return [
            'grades' => $grades,
            // Grades triés et regroupés (pays, catégorie, du plus élevé au plus bas) pour les listes déroulantes.
            'grade_groups' => GradeSelectOptions::groups($grades, $preferredGradeCountry),
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
        $qualSummaryByUser = $this->rosterQualificationSummaryByUser($tenantId, $ids);
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
                'assignment_role_name' => trim((string) ($rich['assignment_role_name'] ?? '')) ?: null,
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
                'athena_identifier' => trim((string) ($rich['athena_identifier'] ?? $u['athena_identifier'] ?? '')) ?: null,
                'qualifications_summary' => $qualSummaryByUser[$id]['summary'] ?? '—',
                'qualifications_alert' => $qualSummaryByUser[$id]['alert'] ?? null,
                'qualifications_count' => (int) ($qualSummaryByUser[$id]['count'] ?? 0),
                'qualifications_items' => is_array($qualSummaryByUser[$id]['items'] ?? null)
                    ? $qualSummaryByUser[$id]['items']
                    : [],
            ]);
        }

        return $out;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{summary: string, alert: ?string, count: int, items: list<array{name: string, level: string, admin: string, temporal: string}>}>
     */
    private function rosterQualificationSummaryByUser(int $tenantId, array $userIds): array
    {
        $out = [];
        if ($userIds === []) {
            return $out;
        }
        try {
            $awardRepo = \App\Core\Container::get(\App\Repositories\QualificationAwardRepository::class);
            $temporal = \App\Core\Container::get(\App\Services\Personnel\QualificationTemporalStatusService::class);
        } catch (\Throwable) {
            return $out;
        }
        // Une lecture pour tous les membres chargés (au lieu d’une par membre) ; en cas d’erreur, repli sur
        // l’ancienne lecture membre par membre.
        $rowsByUser = null;
        try {
            $rowsByUser = $awardRepo->listForUsers($userIds, $tenantId);
        } catch (\Throwable) {
            $rowsByUser = null;
        }
        foreach ($userIds as $uid) {
            if ($rowsByUser !== null) {
                $rows = $rowsByUser[(int) $uid] ?? [];
            } else {
                try {
                    $rows = $awardRepo->listForUser($uid, $tenantId);
                } catch (\Throwable) {
                    continue;
                }
            }
            $labels = [];
            $items = [];
            $alert = null;
            $seen = [];
            foreach ($rows as $row) {
                $admin = \App\Support\QualificationAdminStatus::normalize(
                    (string) ($row['admin_status'] ?? $row['status'] ?? '')
                );
                if ($admin !== \App\Support\QualificationAdminStatus::OBTAINED) {
                    continue;
                }
                $t = $temporal->resolve($row);
                if ($t['code'] === \App\Services\Personnel\QualificationTemporalStatusService::EXPIRED) {
                    continue;
                }
                $name = trim((string) ($row['definition_short_name'] ?? $row['definition_name'] ?? $row['qualification_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $level = trim((string) ($row['level_short_name'] ?? $row['level_name'] ?? ''));
                $key = mb_strtolower($name . '|' . $level);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $labels[] = $name;
                $items[] = [
                    'name' => $name,
                    'level' => $level,
                    'admin' => \App\Support\QualificationAdminStatus::label($admin),
                    'temporal' => trim((string) ($t['label'] ?? '')),
                ];
                if ($alert === null && in_array($t['code'], [
                    \App\Services\Personnel\QualificationTemporalStatusService::EXPIRING_SOON,
                    \App\Services\Personnel\QualificationTemporalStatusService::EXPIRED_GRACE,
                ], true)) {
                    $alert = $t['label'];
                }
            }
            $summaryLabels = array_slice($labels, 0, 4);
            $out[$uid] = [
                'summary' => $summaryLabels !== [] ? implode(' · ', $summaryLabels) : '—',
                'alert' => $alert,
                'count' => count($items),
                'items' => $items,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    /**
     * Colonnes du tableur alimentées par le jeu et le dossier : temps en mission, heures de vol (total et par type
     * d'appareil) et, pour la page affichée, données médicales. Chaque source manquante laisse la colonne vide.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function attachRosterFieldData(int $tenantId, array $rows, bool $withMedical): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return $rows;
        }
        $playtime = [];
        $pilot = [];
        $medical = [];
        try {
            $playtime = \App\Core\Container::get(\App\Repositories\ArmaPlaytimeRepository::class)->summariesForUsers($tenantId, $ids);
        } catch (\Throwable) {
            $playtime = [];
        }
        try {
            $pilot = \App\Core\Container::get(\App\Repositories\AtakDistanceRepository::class)->pilotByUsers($tenantId, $ids);
        } catch (\Throwable) {
            $pilot = [];
        }
        if ($withMedical) {
            try {
                $medical = $this->personnelProfileRepository->medicalForUsers($tenantId, $ids);
            } catch (\Throwable) {
                $medical = [];
            }
        }
        // Sessions de jeu qui comptent (seuil de la communauté) : une lecture pour toute la page, rien sans la table.
        $serverSessions = [];
        try {
            $sessionRepo = \App\Core\Container::get(\App\Repositories\PlayerSessionRepository::class);
            if ($sessionRepo->schemaReady()) {
                $rules = \App\Services\Personnel\RoleplayGameSessionSettings::playerSessionRules($tenantId);
                $serverSessions = $sessionRepo->summariesForUsers($tenantId, $ids, $rules['min_minutes'] * 60, $rules['gap_minutes'] * 60, time(), $sessionRepo->tenantTimezone($tenantId));
            }
        } catch (\Throwable) {
            $serverSessions = [];
        }
        // Heures remontées par le mod : repli de la colonne « Sessions » pour qui n'a aucune session détaillée (cumul déjà lu ci-dessus).
        $playtimeMin = 3600;
        $playtimeTz = 'Europe/Paris';
        try {
            $playtimeMin = \App\Services\Personnel\RoleplayGameSessionSettings::playerSessionRules($tenantId)['min_minutes'] * 60;
            $playtimeTz = \App\Core\Container::get(\App\Repositories\PlayerSessionRepository::class)->tenantTimezone($tenantId);
        } catch (\Throwable) {
        }
        $playtimeNow = time();
        foreach ($rows as &$row) {
            $uid = (int) ($row['id'] ?? 0);
            if (isset($serverSessions[$uid])) {
                $row['server_sessions'] = \App\Services\Personnel\PlayerSessionService::rosterCell($serverSessions[$uid]);
            }
            $sum = is_array($playtime[$uid] ?? null) ? $playtime[$uid] : [];
            $secs = (int) ($sum['total_seconds'] ?? 0);
            if ($secs > 0 && empty($row['server_sessions']['has_data'])) {
                $row['playtime_availability'] = \App\Services\Effectifs\Availability\PlaytimeAvailability::cell($sum, $playtimeMin, $playtimeNow, $playtimeTz);
            }
            $row['arma_playtime_seconds'] = $secs;
            $row['arma_playtime_label'] = $secs > 0 && function_exists('format_arma_playtime_french') ? format_arma_playtime_french($secs) : null;
            $row['pilot_seconds'] = (int) ($pilot[$uid]['seconds'] ?? 0);
            $row['pilot_families'] = is_array($pilot[$uid]['families'] ?? null) ? $pilot[$uid]['families'] : [];
            $row['pilot_last_flown_at'] = (string) ($pilot[$uid]['last_flown_at'] ?? '');
            if ($withMedical) {
                foreach (PersonnelProfileRepository::MEDICAL_FIELDS as $field) {
                    $row[$field] = (string) ($medical[$uid][$field] ?? '');
                }
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Emplois de la communauté proposés dans le filtre « Fonction » du tableur (Medic, Chef de groupe…).
     *
     * @return list<string>
     */
    private function rosterJobRoleOptions(int $tenantId): array
    {
        try {
            $names = array_map(static fn (array $r): string => trim((string) ($r['name'] ?? '')), $this->personnelJobRoleRepository->listRolesWithCategory($tenantId));
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique(array_filter($names, static fn (string $n): bool => $n !== '')));
    }

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
                case 'heures_vol':
                    $cmp = ((int) ($b['pilot_seconds'] ?? 0)) <=> ((int) ($a['pilot_seconds'] ?? 0));
                    break;
                case 'temps_mission':
                    $cmp = ((int) ($b['arma_playtime_seconds'] ?? 0)) <=> ((int) ($a['arma_playtime_seconds'] ?? 0));
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
        // Libellé composé en une seule langue : la traduction automatique ne reconnaît pas
        // « 7 ans 4 mois » et ne traduisait que la fin (« 7 ans 4 months »).
        $en = locale() === 'en';
        if ($days < 30) {
            return $days . ($en ? ' d' : ' j');
        }
        $months = intdiv($days, 30);
        if ($months < 24) {
            return $months . ($en ? ($months > 1 ? ' months' : ' month') : ' mois');
        }
        $years = intdiv($days, 365);
        $remMonths = intdiv($days % 365, 30);
        $label = $en
            ? $years . ($years > 1 ? ' years' : ' year')
            : $years . ' an' . ($years > 1 ? 's' : '');
        if ($remMonths > 0) {
            $label .= ' ' . $remMonths . ($en ? ($remMonths > 1 ? ' months' : ' month') : ' mois');
        }

        return $label;
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
                'module' => $mod,
                'label' => CommunityAccessProfiles::moduleLabel($mod),
                'description' => CommunityAccessProfiles::moduleDescription($mod),
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
        // Tableau « Qui tient quel emploi » : filtres, confidentialité et actions groupées dans JobAssignmentBoardController.
        return \App\Core\Container::get(JobAssignmentBoardController::class)->boardData($request, $tenantId);
    }

    private function resolveJobCategoryId(int $tenantId, int $categoryId): int
    {
        if ($categoryId > 0 && $this->personnelJobRoleRepository->findCategoryById($categoryId, $tenantId)) {
            return $categoryId;
        }
        $existing = $this->personnelJobRoleRepository->findCategoryIdBySlug($tenantId, 'organisation');
        return $existing ?? $this->personnelJobRoleRepository->createCategory($tenantId, null, 'Organisation', 'organisation', 0);
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

    /**
     * Prénom, nom du personnage et trigramme des membres du tableur (affichage « Prénom N. »).
     *
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>>
     */
    private function rosterIdentities(int $tenantId, array $userIds): array
    {
        try {
            return (new \App\Services\Personnel\MemberNameService())->identitiesFor($tenantId, $userIds);
        } catch (\Throwable $e) {
            error_log('[noms] tableur des effectifs : ' . $e->getMessage());

            return [];
        }
    }
}
