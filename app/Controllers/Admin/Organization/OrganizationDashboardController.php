<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\MissionPlanRepository;
use App\Repositories\PersonnelAbsenceRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelRoleplayTimelineRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingQuizRepository;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Admin\PilotageDashboardService;
use App\Services\Community\MemberOnboardingService;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\GradeDisplayService;
use App\Services\Notifications\PersonalMessageUnreadCounter;
use App\Services\Personnel\PersonnelDutyPositionService;
use App\Services\Personnel\PersonnelMemberFollowupSnapshot;
use App\Services\Personnel\PersonnelProfileGapScanService;
use App\Services\Personnel\PhaseRules\PhaseTransitionService;
use App\Services\Personnel\RoleplayFollowupSettings;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingEnrollmentCompletionAnalytics;
use App\Support\AlertDisplayStyle;
use App\Support\PilotageDashboardPresenter;

class OrganizationDashboardController
{
    public function __construct(
        private ?AdminDashboardMetricsService $metrics = null,
        private ?AuditLogRepository $auditLogs = null,
        private ?ModerationRepository $moderationRepository = null,
        private ?EnlistmentRepository $enlistmentRepository = null,
        private ?TenantCommunityFeedRepository $communityFeed = null,
        private ?CommunityEventRepository $eventRepository = null,
        private ?TenantAlertRepository $tenantAlertRepository = null,
        private ?TrainingEnrollmentCompletionAnalytics $trainingFeedCompletionAnalytics = null,
        private ?ElevationRequestRepository $elevationRequests = null,
        private ?TenantMessageRepository $tenantMessages = null,
        private ?AtakRealismRepository $atakRealism = null,
        private ?UserRepository $users = null,
        private ?PersonnelRoleplayTimelineRepository $personnelRoleplayTimelineRepository = null,
        private ?PersonnelProfileGapScanService $profileGaps = null,
        private ?PilotageDashboardService $pilotage = null,
    ) {
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
        $this->moderationRepository ??= new ModerationRepository();
        $this->enlistmentRepository ??= new EnlistmentRepository();
        $this->communityFeed ??= new TenantCommunityFeedRepository();
        $this->eventRepository ??= new CommunityEventRepository();
        $this->tenantAlertRepository ??= new TenantAlertRepository();
        $this->trainingFeedCompletionAnalytics ??= new TrainingEnrollmentCompletionAnalytics(
            new TrainingEnrollmentRepository(),
            new TrainingQuizRepository()
        );
        $this->elevationRequests ??= new ElevationRequestRepository();
        $this->tenantMessages ??= new TenantMessageRepository();
        $this->atakRealism ??= new AtakRealismRepository();
        $this->users ??= new UserRepository();
        $this->personnelRoleplayTimelineRepository ??= new PersonnelRoleplayTimelineRepository();
        $this->profileGaps ??= new PersonnelProfileGapScanService();
        $this->pilotage ??= new PilotageDashboardService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();
        $canAdminister = $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support');
        if (!$canAdminister) {
            return $this->operatorOverview($tenantId, (int) Session::get('user_id'));
        }

        $tenantName = '';
        try {
            $tenantRow = (new TenantRepository())->findById($tenantId);
            if ($tenantRow !== null) {
                $tenantName = (string) ($tenantRow['name'] ?? '');
            }
        } catch (\Throwable) {
            $tenantName = '';
        }
        $metrics = $this->metrics->getOrganizationMetrics($tenantId);
        $recent = [];
        try {
            $recent = $this->auditLogs->recentForTenant($tenantId, 20);
        } catch (\Throwable) {
            $recent = [];
        }
        $orgEnlistmentCounts = [];
        $orgEnlistmentRecent = [];
        try {
            $orgEnlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
            $orgEnlistmentRecent = $this->enlistmentRepository->recentForTenantDashboard($tenantId, 10);
        } catch (\Throwable) {
            $orgEnlistmentCounts = [];
            $orgEnlistmentRecent = [];
        }

        $discordInviteMissing = false;
        try {
            $settings = (new TenantRepository())->getSettings($tenantId);
            $communityCfg = is_array($settings['community'] ?? null) ? $settings['community'] : [];
            $discordInviteMissing = \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($communityCfg);
        } catch (\Throwable) {
            $discordInviteMissing = false;
        }
        $missingMediaCount = 0;
        try {
            $mediaScan = (new \App\Services\Media\MissingUserMediaScanner())->scanTenant($tenantId, 300);
            $missingMediaCount = (int) ($mediaScan['total_users'] ?? 0);
        } catch (\Throwable) {
            $missingMediaCount = 0;
        }

        $initialSetupBanner = null;
        try {
            $setupAnalysis = (new \App\Services\Community\TenantInitialSetupService())->analyze($tenantId);
            if (!empty($setupAnalysis['show_banner'])) {
                $initialSetupBanner = [
                    'percent' => (int) ($setupAnalysis['percent'] ?? 0),
                    'done' => (int) ($setupAnalysis['done'] ?? 0),
                    'total' => (int) ($setupAnalysis['total'] ?? 0),
                ];
            }
        } catch (\Throwable) {
            $initialSetupBanner = null;
        }

        $configurationUpdateBadge = 0;
        try {
            $cfgSummary = \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class)
                ->hubSummary($tenantId);
            $configurationUpdateBadge = (int) ($cfgSummary['nav_badge'] ?? 0);
        } catch (\Throwable) {
            $configurationUpdateBadge = 0;
        }

        $chartDays = 14;
        $orgActivityChart = ['days' => [], 'portal' => [], 'atak' => [], 'max' => 1, 'period_days' => $chartDays];
        try {
            $portalSeries = $this->auditLogs->dailyLoginCountsForTenant($tenantId, $chartDays);
            $atakSeries = $this->atakRealism->dailyTerminalSeenCountsForTenant($tenantId, $chartDays);
            $days = [];
            $portal = [];
            $atak = [];
            $max = 1;
            $n = max(count($portalSeries), count($atakSeries));
            for ($i = 0; $i < $n; $i++) {
                $pRow = $portalSeries[$i] ?? null;
                $aRow = $atakSeries[$i] ?? null;
                $day = is_array($pRow) ? (string) ($pRow['day'] ?? '') : (is_array($aRow) ? (string) ($aRow['day'] ?? '') : '');
                $p = is_array($pRow) ? (int) ($pRow['count'] ?? 0) : 0;
                $a = is_array($aRow) ? (int) ($aRow['count'] ?? 0) : 0;
                $days[] = $day !== '' ? date('d', strtotime($day) ?: time()) : '';
                $portal[] = $p;
                $atak[] = $a;
                $max = max($max, $p, $a);
            }
            $orgActivityChart = [
                'days' => $days,
                'portal' => $portal,
                'atak' => $atak,
                'max' => $max,
                'period_days' => $chartDays,
            ];
        } catch (\Throwable) {
            $orgActivityChart = ['days' => [], 'portal' => [], 'atak' => [], 'max' => 1, 'period_days' => $chartDays];
        }

        // Agenda + prochaine opération : une seule lecture des créneaux à venir, une seule pour les réponses.
        $upcomingEvents = [];
        $upcomingRsvp = [];
        try {
            $upcomingEvents = $this->eventRepository->upcomingForTenant($tenantId, 8);
            $eventIds = array_values(array_filter(array_map(
                static fn (array $e): int => (int) ($e['id'] ?? 0),
                $upcomingEvents
            )));
            if ($eventIds !== []) {
                $upcomingRsvp = $this->eventRepository->rsvpSummariesForEvents($eventIds);
            }
        } catch (\Throwable) {
            $upcomingEvents = [];
            $upcomingRsvp = [];
        }

        $orgElevationOpen = [];
        $orgElevationOpenCount = 0;
        try {
            $orgElevationOpen = $this->elevationRequests->listOpenForTenant($tenantId, 4);
            $orgElevationOpenCount = $this->elevationRequests->countOpenForTenant($tenantId);
        } catch (\Throwable) {
            $orgElevationOpen = [];
            $orgElevationOpenCount = 0;
        }

        $orgMessagesRecent = [];
        $viewerId = (int) Session::get('user_id');
        if ($viewerId > 0) {
            try {
                $orgMessagesRecent = $this->tenantMessages->listActivityThreadsForUser($tenantId, $viewerId, 3);
            } catch (\Throwable) {
                $orgMessagesRecent = [];
            }
        }

        $orgProfileGaps = [
            'rows' => [],
            'total' => 0,
            'shown' => 0,
            'truncated' => false,
            'counts' => [
                'function' => 0,
                'rank' => 0,
                'role' => 0,
                'operator_image' => 0,
            ],
            'error' => null,
        ];
        try {
            $orgProfileGaps = $this->profileGaps->listForTenant($tenantId, 80);
        } catch (\Throwable) {
            $orgProfileGaps['error'] = 'La liste des profils à compléter n’est pas disponible pour le moment.';
        }

        $workQueue = [];
        try {
            $workQueue = $this->metrics->getOrganizationWorkQueue($tenantId);
        } catch (\Throwable) {
            $workQueue = [];
        }

        $now = time();
        $pilotage = PilotageDashboardPresenter::build([
            'now' => $now,
            'tenant_name' => $tenantName,
            'viewer_name' => trim((string) (Session::get('display_name') ?? Session::get('callsign') ?? '')),
            'kpis' => $metrics['kpis'],
            'profile_gaps_total' => ($orgProfileGaps['error'] ?? null) === null ? (int) ($orgProfileGaps['total'] ?? 0) : null,
            'work_queue' => $workQueue,
            'upcoming' => $upcomingEvents,
            'rsvp' => $upcomingRsvp,
            'elevations' => $orgElevationOpen,
            'elevation_count' => $orgElevationOpenCount,
            'elevation_kind_labels' => EffectifsStaffAlertService::ELEVATION_KIND_LABELS,
            'enlistment_counts' => $orgEnlistmentCounts,
            'enlistments' => $orgEnlistmentRecent,
            'participation' => $this->pilotage->participation($tenantId, 10),
            'presence' => $this->pilotage->presence($tenantId, 30),
            'month_events' => $this->pilotage->monthEvents($tenantId, $now),
            'units' => $this->pilotage->unitReadiness($tenantId),
            'activity' => $recent,
            'link_allowed' => static fn (string $href): bool => !function_exists('portal_nav_href_permission_allowed')
                || portal_nav_href_permission_allowed($href),
        ]);

        return Response::view('layout.main', [
            'content' => 'admin.organization.dashboard',
            'title' => 'Tableau de bord',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Pilotage',
            'boPageTitle' => 'Tableau de bord',
            // L’en-tête (salutation, résumé du jour) est porté par la vue elle-même.
            'boSkipPageHead' => true,
            'boPageAction' => 'Publier une annonce',
            'boPageActionUrl' => url('back-office/alerts/create'),
            'backOfficePageCss' => ['back-office-dashboard.css', 'pilotage-dashboard.css', 'back-office-home.css'],
            'home' => $this->homeData($tenantId, $viewerId, $request->queryString('personnaliser') === '1'),
            'adminKpiBlockError' => $metrics['blockError'],
            'pilotage' => $pilotage,
            'skipGlobalAlertBanners' => true,
            'tenantName' => $tenantName,
            'initialSetupBanner' => $initialSetupBanner,
            'configurationUpdateBadge' => $configurationUpdateBadge,
            'discordInviteMissing' => $discordInviteMissing,
            'missingMediaCount' => $missingMediaCount,
            'orgActivityChart' => $orgActivityChart,
            'orgMessagesRecent' => $orgMessagesRecent,
            'orgProfileGaps' => $orgProfileGaps,
        ]);
    }

    /**
     * Accueil personnalisable : disposition de la personne (ou celle de son rôle), tableaux affichés, compteurs du
     * menu pour le bloc « Tâches en attente ». En cas d'erreur : disposition par défaut, jamais une page cassée.
     *
     * @return array<string, mixed>
     */
    private function homeData(int $tenantId, int $userId, bool $customize): array
    {
        $role = \App\Controllers\Admin\BackOfficeHomeController::role();
        $home = [
            'items' => \App\Services\BackOffice\Home\HomeLayout::defaults($role),
            'readable' => [],
            'sheets' => [],
            'tasks' => [],
            'customize' => $customize,
            'can_share' => \App\Controllers\Admin\BackOfficeHomeController::canShare(),
            'catalog' => \App\Services\BackOffice\Home\HomeLayout::catalog(),
        ];
        try {
            $layout = Container::get(\App\Controllers\Admin\BackOfficeHomeController::class)->layoutFor($tenantId, $userId);
            $home['items'] = $layout['items'];
            $home['readable'] = $layout['readable'];
            $sheetRepo = Container::get(\App\Repositories\BoSheetRepository::class);
            foreach ($home['items'] as $item) {
                $sid = \App\Services\BackOffice\Home\HomeLayout::sheetId($item['id']);
                if ($sid > 0 && !$item['hidden']) {
                    $sheet = $sheetRepo->find($tenantId, $sid);
                    if ($sheet !== null && ($sheet['owner_user_id'] === $userId || $sheet['shared'])) {
                        $home['sheets'][$sid] = \App\Controllers\Admin\BackOfficeHomeController::view($sheet, $userId);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[bo-home] ' . $e->getMessage());
        }
        try {
            $gate = Gate::getInstance();
            $badges = Container::get(\App\Services\BackOffice\Menu\BackOfficeMenuState::class)->badges();
            $rows = $badges->compute(
                $tenantId,
                $userId,
                static fn (string $perm): bool => $gate->allows($perm),
                static fn (string $path): bool => \App\Controllers\Admin\BackOfficeMenuController::visible($path)
            );
            foreach ($rows as $row) {
                if ($row['count'] > 0) {
                    $home['tasks'][] = $row;
                }
            }
        } catch (\Throwable $e) {
            error_log('[bo-home] tâches : ' . $e->getMessage());
        }

        return $home;
    }

    /**
     * Synthèse personnelle, volontairement limitée aux données de l'opérateur connecté.
     */
    private function operatorOverview(int $tenantId, int $userId): Response
    {
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        $tenant = [];
        $user = [];
        $profile = [];
        $units = [];
        $terminals = [];
        $mission = null;
        try {
            $tenant = (new TenantRepository())->findById($tenantId) ?: [];
        } catch (\Throwable) {
        }
        try {
            $user = $this->users->findById($userId, $tenantId) ?: [];
        } catch (\Throwable) {
        }
        try {
            $profile = (new PersonnelProfileRepository())->getByUserId($userId, $tenantId) ?: [];
        } catch (\Throwable) {
        }
        try {
            $unitRepository = new UnitRepository();
            foreach ($unitRepository->unitIdsForUser($tenantId, $userId) as $unitId) {
                $unit = $unitRepository->findById($unitId, $tenantId);
                if (is_array($unit)) {
                    $units[] = $unit;
                }
            }
        } catch (\Throwable) {
            $units = [];
        }
        try {
            foreach ($this->atakRealism->listTerminals($tenantId) as $terminal) {
                if ((int) ($terminal['user_id'] ?? 0) === $userId
                    && !AtakRealismRepository::isWebSessionTerminal($terminal)) {
                    $terminals[] = $terminal;
                }
            }
        } catch (\Throwable) {
            $terminals = [];
        }
        try {
            $mission = (new MissionPlanRepository())->findLiveForTenant($tenantId);
        } catch (\Throwable) {
            $mission = null;
        }

        $unitNameLookup = [];
        foreach ($units as $unitRow) {
            $uid = (int) ($unitRow['id'] ?? 0);
            if ($uid > 0) {
                $unitNameLookup[$uid] = trim((string) ($unitRow['name'] ?? ''));
            }
        }

        $gradeLabel = '';
        try {
            $gradeId = (int) ($user['grade_id'] ?? 0);
            if ($gradeId > 0) {
                $gradeRow = (new GradeRepository())->findById($gradeId, $tenantId);
                if (is_array($gradeRow)) {
                    $gradeDisplay = Container::get(GradeDisplayService::class);
                    $gradeLong = trim($gradeDisplay->headerTitle($gradeRow, $profile !== [] ? $profile : null));
                    $gradeOtan = trim((string) ($gradeDisplay->headerShortCode($gradeRow, $profile !== [] ? $profile : null) ?? ''));
                    if ($gradeLong !== '' && $gradeOtan !== '') {
                        $gradeLabel = $gradeLong . ' · ' . $gradeOtan;
                    } else {
                        $gradeLabel = $gradeLong !== '' ? $gradeLong : $gradeOtan;
                    }
                }
            }
        } catch (\Throwable) {
            $gradeLabel = '';
        }

        $dutyLabel = '';
        try {
            $dutySlug = Container::get(PersonnelDutyPositionService::class)->currentDutySlug($tenantId, $userId);
            $dutyLabel = PersonnelDutyPositionService::labelForSlug((string) $dutySlug);
        } catch (\Throwable) {
            $dutyLabel = '';
        }

        // Fonction = intitulé du poste ORBAT (billet), pas le nom d’unité.
        $functionLabel = '';
        try {
            $billetRepo = Container::get(\App\Repositories\OrbatBilletRepository::class);
            if ($billetRepo->schemaReady()) {
                foreach ($billetRepo->primaryBilletsForUser($tenantId, $userId) as $billetRow) {
                    $title = trim((string) ($billetRow['billet_title'] ?? $billetRow['function_label'] ?? ''));
                    $unitName = $unitNameLookup[(int) ($billetRow['unit_id'] ?? 0)] ?? '';
                    if ($title !== '' && strcasecmp($title, $unitName) !== 0) {
                        $functionLabel = $title;
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            $functionLabel = '';
        }
        if ($functionLabel === '') {
            try {
                $assignments = (new PersonnelAssignmentRepository())->listActiveForUserResolved($userId);
                foreach ($assignments as $assignment) {
                    if (!is_array($assignment)) {
                        continue;
                    }
                    $roleName = trim((string) ($assignment['role_name'] ?? ''));
                    $unitName = $unitNameLookup[(int) ($assignment['unit_id'] ?? 0)] ?? '';
                    if ($roleName === '' || strcasecmp($roleName, $unitName) === 0) {
                        continue;
                    }
                    // Évite les libellés génériques qui ne disent rien.
                    if (in_array(mb_strtolower($roleName), ['membre', 'member', 'opérateur', 'operateur'], true)) {
                        if ($functionLabel === '') {
                            $functionLabel = $roleName;
                        }
                        continue;
                    }
                    $functionLabel = $roleName;
                    if (!empty($assignment['is_primary'])) {
                        break;
                    }
                }
            } catch (\Throwable) {
                // conserve billet si déjà trouvé
            }
        }
        if ($functionLabel === '') {
            $functionLabel = trim((string) ($profile['rp_operational_function'] ?? ''));
            $primaryUnitName = $units[0]['name'] ?? '';
            if ($functionLabel !== '' && is_string($primaryUnitName) && strcasecmp($functionLabel, trim((string) $primaryUnitName)) === 0) {
                $functionLabel = '';
            }
        }

        $absences = [];
        try {
            $absenceRepo = new PersonnelAbsenceRepository();
            if ($absenceRepo->tableExists()) {
                $absences = $absenceRepo->listActiveForUser($tenantId, $userId);
            }
        } catch (\Throwable) {
            $absences = [];
        }

        $elevations = [];
        try {
            $open = [];
            foreach (array_merge(
                $this->elevationRequests->listForTarget($tenantId, $userId, 12),
                $this->elevationRequests->listForRequester($tenantId, $userId, 12)
            ) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? '');
                if ($id > 0 && in_array($status, ['pending', 'in_review'], true)) {
                    $open[$id] = $row;
                }
            }
            $elevations = array_values($open);
        } catch (\Throwable) {
            $elevations = [];
        }

        $mobility = [];
        try {
            $mobilityRepo = new PersonnelMobilityRequestRepository();
            if ($mobilityRepo->tableExists()) {
                foreach ($mobilityRepo->listForUser($tenantId, $userId, 12) as $row) {
                    if ((string) ($row['status'] ?? '') === 'pending') {
                        $mobility[] = $row;
                    }
                }
            }
        } catch (\Throwable) {
            $mobility = [];
        }

        $events = [];
        try {
            $events = $this->eventRepository->upcomingForTenantWithUserRsvp($tenantId, $userId, 5);
        } catch (\Throwable) {
            $events = [];
        }

        $alerts = [];
        try {
            foreach ($this->tenantAlertRepository->listActiveForTenantDisplay($tenantId) as $alert) {
                if (!is_array($alert)) {
                    continue;
                }
                $style = (string) ($alert['display_style'] ?? '');
                if (AlertDisplayStyle::isBackOfficeStyle($style)) {
                    continue;
                }
                $title = trim((string) ($alert['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $alerts[] = $alert;
                if (count($alerts) >= 4) {
                    break;
                }
            }
        } catch (\Throwable) {
            $alerts = [];
        }

        $inboxUnread = 0;
        try {
            $inboxUnread = (int) (Container::get(PersonalMessageUnreadCounter::class)
                ->countsForUser($tenantId, $userId, Gate::getInstance())['total'] ?? 0);
        } catch (\Throwable) {
            try {
                $inboxUnread = $this->tenantMessages->unreadCountForUser($tenantId, $userId);
            } catch (\Throwable) {
                $inboxUnread = 0;
            }
        }

        $onboardingRemaining = [];
        $onboardingNudge = '';
        try {
            $snapshot = (new MemberOnboardingService())->buildMemberSnapshot(
                $userId,
                $tenantId,
                (string) ($user['created_at'] ?? '')
            );
            if ((int) ($snapshot['percent'] ?? 0) < 100) {
                $onboardingNudge = trim((string) ($snapshot['nudge'] ?? ''));
                foreach ($snapshot['steps'] ?? [] as $step) {
                    if (!is_array($step) || !empty($step['done'])) {
                        continue;
                    }
                    $onboardingRemaining[] = $step;
                }
            }
        } catch (\Throwable) {
            $onboardingRemaining = [];
            $onboardingNudge = '';
        }

        $operatorFollowup = [
            'visible' => false,
            'attention' => false,
            'attention_items' => [],
        ];
        try {
            $rpCfg = RoleplayFollowupSettings::forTenant($tenantId);
            $phaseCheck = null;
            try {
                $phaseCheck = Container::get(PhaseTransitionService::class)->checklistForMember($tenantId, $userId);
            } catch (\Throwable) {
                $phaseCheck = null;
            }
            $tutorLabel = null;
            $tutorId = (int) ($profile['rp_tutor_user_id'] ?? 0);
            if ($tutorId > 0) {
                $tutor = $this->users->findById($tutorId, $tenantId);
                if (is_array($tutor)) {
                    $tutorLabel = trim((string) ($tutor['display_name'] ?? ''))
                        ?: trim((string) ($tutor['callsign'] ?? ''));
                }
            }
            $operatorFollowup = PersonnelMemberFollowupSnapshot::build(
                is_array($profile) ? $profile : [],
                $rpCfg,
                is_array($phaseCheck) ? $phaseCheck : null,
                trim((string) ($user['created_at'] ?? '')) ?: null,
                $tutorLabel
            );
        } catch (\Throwable) {
            $operatorFollowup = [
                'visible' => false,
                'attention' => false,
                'attention_items' => [],
            ];
        }

        $portraitUrl = null;
        if (function_exists('personnel_operator_portrait_url')) {
            $portraitUrl = personnel_operator_portrait_url($profile);
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.operator_overview',
            'title' => 'Tableau de bord',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Pilotage',
            'boPageTitle' => 'Tableau de bord',
            'boPageKicker' => 'OPÉRATEUR · PILOTAGE',
            'boPageSubtitle' => 'Ce qui vous concerne : communauté, démarches, manœuvres et liaison ATAK.',
            'operatorTenant' => $tenant,
            'operatorUser' => $user,
            'operatorProfile' => $profile,
            'operatorUnits' => $units,
            'operatorTerminals' => $terminals,
            'operatorMission' => $mission,
            'operatorGradeLabel' => $gradeLabel,
            'operatorDutyLabel' => $dutyLabel,
            'operatorFunctionLabel' => $functionLabel,
            'operatorPortraitUrl' => $portraitUrl,
            'operatorAbsences' => $absences,
            'operatorElevations' => $elevations,
            'operatorMobility' => $mobility,
            'operatorEvents' => $events,
            'operatorAlerts' => $alerts,
            'operatorInboxUnread' => $inboxUnread,
            'operatorOnboardingRemaining' => $onboardingRemaining,
            'operatorOnboardingNudge' => $onboardingNudge,
            'operatorFollowup' => $operatorFollowup,
        ]);
    }
}
