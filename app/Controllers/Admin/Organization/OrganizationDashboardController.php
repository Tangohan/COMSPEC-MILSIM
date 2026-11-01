<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\MissionPlanRepository;
use App\Repositories\OpsBoardRepository;
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
        private ?OpsBoardRepository $opsBoardRepository = null,
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
        $this->opsBoardRepository ??= new OpsBoardRepository();
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
                'absence' => 0,
            ],
            'error' => null,
        ];
        try {
            $orgProfileGaps = $this->profileGaps->listForTenant($tenantId, 80);
        } catch (\Throwable) {
            $orgProfileGaps['error'] = 'La liste des profils à compléter n’est pas disponible pour le moment.';
        }

        $now = time();
        $pilotage = PilotageDashboardPresenter::build([
            'now' => $now,
            'tenant_name' => $tenantName,
            'viewer_name' => trim((string) (Session::get('display_name') ?? Session::get('callsign') ?? '')),
            'kpis' => $metrics['kpis'],
            'profile_gaps_total' => ($orgProfileGaps['error'] ?? null) === null ? (int) ($orgProfileGaps['total'] ?? 0) : null,
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
            'backOfficePageCss' => ['back-office-dashboard.css', 'pilotage-dashboard.css'],
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


    public function operationsCenter(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        $profile = strtolower(trim((string) $request->query('profile', 'commandement')));
        $allowedProfiles = ['commandement', 'rh', 'moderation', 'formation'];
        if (!in_array($profile, $allowedProfiles, true)) {
            $profile = 'commandement';
        }

        $workQueue = $this->metrics->getOrganizationWorkQueue($tenantId);
        $kpis = $this->metrics->getOrganizationMetrics($tenantId)['kpis'] ?? [];

        $moderationOpen = 0;
        foreach ($kpis as $kpi) {
            if (($kpi['id'] ?? '') === 'moderation_open' && empty($kpi['error']) && isset($kpi['value']) && is_numeric((string) $kpi['value'])) {
                $moderationOpen = (int) $kpi['value'];
                break;
            }
        }

        $pendingRecruitments = [];
        $pendingRecruitmentsError = null;
        try {
            $pendingRecruitments = $this->enlistmentRepository->listPendingSubmittedForTenant($tenantId, 6);
        } catch (\Throwable) {
            $pendingRecruitmentsError = 'Candidatures indisponibles.';
        }

        $upcomingEvents = [];
        $eventsError = null;
        try {
            $upcomingEvents = $this->eventRepository->upcomingForTenant($tenantId, 20);
        } catch (\Throwable) {
            $eventsError = 'Événements indisponibles.';
        }

        $eventsJ1 = [];
        $eventsJ7 = [];
        $now = time();
        foreach ($upcomingEvents as $event) {
            $startsAt = strtotime((string) ($event['starts_at'] ?? ''));
            if (!$startsAt) {
                continue;
            }
            $days = (int) floor(($startsAt - $now) / 86400);
            if ($days <= 1) {
                $eventsJ1[] = $event;
            }
            if ($days <= 7) {
                $eventsJ7[] = $event;
            }
        }

        $alerts = [];
        $alertsError = null;
        try {
            $alerts = $this->tenantAlertRepository->listActiveForTenantDisplay($tenantId);
        } catch (\Throwable) {
            $alertsError = 'Alertes locales indisponibles.';
        }

        $opsBoardFilters = [
            'unit_id' => trim((string) $request->query('unit', '')),
            'period_start' => trim((string) $request->query('from', '')),
            'period_end' => trim((string) $request->query('to', '')),
            'block_type' => trim((string) $request->query('type', '')),
            'visibility_level' => trim((string) $request->query('visibility', '')),
            'priority' => trim((string) $request->query('priority', '')),
        ];

        $opsBoardItems = [];
        $opsBoardError = null;
        try {
            $this->opsBoardRepository->expirePastItems($tenantId);
            $opsBoardItems = $this->opsBoardRepository->listBoardItemsForTenant($tenantId, $opsBoardFilters);
        } catch (\Throwable) {
            $opsBoardError = 'Mur opérationnel indisponible (tables absentes ou erreur SQL).';
        }

        $opsByType = [
            'permanence_speciale' => [],
            'info_pratique' => [],
            'manifestation' => [],
            'flash_info' => [],
        ];
        foreach ($opsBoardItems as $item) {
            $type = (string) ($item['block_type'] ?? '');
            if (!array_key_exists($type, $opsByType)) {
                continue;
            }
            $opsByType[$type][] = $item;
        }

        $mediaScan = (new \App\Services\Media\MissingUserMediaScanner())->scanTenant((int) $tenantId, 300);
        $onboardingAnomalies = [
            'profils_incomplets' => count($workQueue['incomplete_profiles'] ?? []),
            'membres_sans_unite' => count($workQueue['users_without_unit'] ?? []),
            'membres_sans_role' => count($workQueue['users_without_role'] ?? []),
            'invitations_expirees' => count($workQueue['expired_invitations'] ?? []),
            'medias_manquants' => (int) ($mediaScan['total_users'] ?? 0),
        ];
        $missingMediaUsers = is_array($mediaScan['users'] ?? null) ? $mediaScan['users'] : [];

        $actionableAlerts = [
            [
                'id' => 'moderation_open',
                'type' => 'Contenu',
                'title' => 'Signalements membres à traiter',
                'impact_score' => min(100, 30 + ($moderationOpen * 6)),
                'sla_label' => 'SLA: 4h',
                'count' => $moderationOpen,
                'link' => url('back-office/signalements'),
                'cta' => 'Traiter maintenant',
            ],
        ];
        $actionableAlerts = array_merge($actionableAlerts, [
            [
                'id' => 'alerts_active',
                'type' => 'Sécurité',
                'title' => 'Alertes locales actives',
                'impact_score' => min(100, 25 + (count($alerts) * 8)),
                'sla_label' => 'SLA: 1h',
                'count' => count($alerts),
                'link' => url('back-office/alerts'),
                'cta' => 'Examiner les alertes',
            ],
            [
                'id' => 'recruitments_pending',
                'type' => 'RH',
                'title' => 'Candidatures en attente',
                'impact_score' => min(100, 20 + (count($pendingRecruitments) * 5)),
                'sla_label' => 'SLA: 24h',
                'count' => count($pendingRecruitments),
                'link' => url('back-office/recruitments'),
                'cta' => 'Ouvrir les dossiers',
            ],
            [
                'id' => 'events_j1',
                'type' => 'Formation',
                'title' => 'Événements J+1 à sécuriser',
                'impact_score' => min(100, 20 + (count($eventsJ1) * 7)),
                'sla_label' => 'SLA: 8h',
                'count' => count($eventsJ1),
                'link' => url('back-office/events'),
                'cta' => 'Préparer les événements',
            ],
            [
                'id' => 'missing_media',
                'type' => 'RH',
                'title' => 'Photos perdues — demander un re-téléversement',
                'impact_score' => min(100, 22 + ((int) ($onboardingAnomalies['medias_manquants'] ?? 0) * 5)),
                'sla_label' => 'Après migration',
                'count' => (int) ($onboardingAnomalies['medias_manquants'] ?? 0),
                'link' => url('back-office/centre-operations') . '#anomalies-medias',
                'cta' => 'Voir les comptes',
            ],
            [
                'id' => 'onboarding_anomalies',
                'type' => 'Administration',
                'title' => 'Anomalies onboarding / droits',
                'impact_score' => min(100, 15 + (array_sum($onboardingAnomalies) * 3)),
                'sla_label' => 'SLA: 48h',
                'count' => array_sum($onboardingAnomalies),
                'link' => url('back-office/centre-operations'),
                'cta' => 'Corriger les anomalies',
            ],
        ]);
        usort($actionableAlerts, static fn (array $a, array $b): int => (int) ($b['impact_score'] ?? 0) <=> (int) ($a['impact_score'] ?? 0));

        $playbookCatalog = [
            [
                'slug' => 'spam',
                'title' => 'Playbook Spam',
                'summary' => 'Qualifier la source, limiter la diffusion, notifier et vérifier la récidive sous 24h.',
                'steps' => ['Qualifier', 'Endiguer', 'Notifier', 'Contrôler'],
                'resolved_count' => 0,
            ],
            [
                'slug' => 'permissions',
                'title' => 'Playbook Permissions',
                'summary' => 'Diagnostiquer les rôles, corriger les droits, puis journaliser automatiquement.',
                'steps' => ['Diagnostiquer', 'Corriger', 'Valider sécurité', 'Tracer'],
                'resolved_count' => 0,
            ],
            [
                'slug' => 'module_outage',
                'title' => 'Playbook Panne module',
                'summary' => 'Triage technique, mitigation temporaire, escalade normalisée et suivi.',
                'steps' => ['Trier', 'Mitiger', 'Escalader', 'Clôturer'],
                'resolved_count' => 0,
            ],
            [
                'slug' => 'dispute',
                'title' => 'Playbook Litige',
                'summary' => 'Qualifier le dossier, arbitrer, documenter la décision et notifier les parties.',
                'steps' => ['Instruire', 'Arbitrer', 'Documenter', 'Notifier'],
                'resolved_count' => 0,
            ],
        ];

        $auditScenarios = [
            ['key' => 'security', 'label' => 'Sécurité', 'description' => 'Accès sensibles, élévations, révocations.', 'count' => count($alerts)],
            ['key' => 'rh', 'label' => 'RH', 'description' => 'Affectations, anomalies de profil, rôles manquants.', 'count' => ($onboardingAnomalies['membres_sans_unite'] ?? 0) + ($onboardingAnomalies['membres_sans_role'] ?? 0)],
            ['key' => 'formation', 'label' => 'Formation', 'description' => 'Sessions proches et blocages de parcours.', 'count' => count($eventsJ7)],
            ['key' => 'content', 'label' => 'Contenu', 'description' => 'Signalements, modération et décisions.', 'count' => $moderationOpen],
        ];

        $weeklyGoals = [
            [
                'title' => 'Réduire le backlog critique modération',
                'state' => $moderationOpen > 10 ? 'à risque' : ($moderationOpen > 3 ? 'en cours' : 'atteint'),
                'variation' => sprintf('%+d vs cible', 5 - $moderationOpen),
                'kpi' => 'Signalements ouverts',
                'value' => (string) $moderationOpen,
            ],
            [
                'title' => 'Réduire les anomalies de droits',
                'state' => array_sum($onboardingAnomalies) > 8 ? 'à risque' : (array_sum($onboardingAnomalies) > 0 ? 'en cours' : 'atteint'),
                'variation' => sprintf('%+d vs cible', 3 - array_sum($onboardingAnomalies)),
                'kpi' => 'Anomalies onboarding',
                'value' => (string) array_sum($onboardingAnomalies),
            ],
            [
                'title' => 'Sécuriser les alertes locales actives',
                'state' => count($alerts) > 4 ? 'à risque' : (count($alerts) > 0 ? 'en cours' : 'atteint'),
                'variation' => sprintf('%+d vs cible', 2 - count($alerts)),
                'kpi' => 'Alertes actives',
                'value' => (string) count($alerts),
            ],
        ];

        $operationsKpiSnapshot = [
            ['id' => 'mtta', 'label' => 'MTTA alertes admin', 'value' => 'N/D', 'trend' => 'Instrumentation requise'],
            ['id' => 'mttr', 'label' => 'MTTR alertes admin', 'value' => 'N/D', 'trend' => 'Instrumentation requise'],
            ['id' => 'playbook_resolved', 'label' => 'Incidents résolus via playbook', 'value' => '0', 'trend' => 'Initialisation'],
            ['id' => 'sla_completion', 'label' => 'Actions admin terminées dans le SLA', 'value' => 'N/D', 'trend' => 'Instrumentation requise'],
        ];

        return Response::view('layout.main', [
            'content' => 'admin.organization.operations_center',
            'title' => 'Centre des opérations',
            'isBackOfficeShell' => true,
            'operationsProfile' => $profile,
            'operationsProfiles' => $allowedProfiles,
            'operationsModerationOpen' => $moderationOpen,
            'operationsPendingRecruitments' => $pendingRecruitments,
            'operationsPendingRecruitmentsError' => $pendingRecruitmentsError,
            'operationsEventsJ1' => $eventsJ1,
            'operationsEventsJ7' => $eventsJ7,
            'operationsEventsError' => $eventsError,
            'operationsActiveAlerts' => $alerts,
            'operationsAlertsError' => $alertsError,
            'operationsOnboardingAnomalies' => $onboardingAnomalies,
            'operationsMissingMediaUsers' => $missingMediaUsers,
            'operationsWorkQueue' => $workQueue,
            'operationsOpsBoardItemsByType' => $opsByType,
            'operationsOpsBoardFilters' => $opsBoardFilters,
            'operationsOpsBoardError' => $opsBoardError,
            'operationsActionableAlerts' => $actionableAlerts,
            'operationsPlaybookCatalog' => $playbookCatalog,
            'operationsAuditScenarios' => $auditScenarios,
            'operationsWeeklyGoals' => $weeklyGoals,
            'operationsKpiSnapshot' => $operationsKpiSnapshot,
        ]);
    }

    /**
     * Tableau d’orientation : liens vers la structure des effectifs (rôles, grades, unités, etc.).
     */
    public function effectifsHub(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();

        $hubStats = [
            'members_active' => 0,
            'groups' => 0,
            'teams' => 0,
            'roles' => 0,
            'grades' => 0,
            'job_roles' => 0,
        ];
        try {
            $hubStats['members_active'] = (new UserRepository())->countActiveForTenant($tenantId);
            $unitRepository = new UnitRepository();
            $hubStats['groups'] = count($unitRepository->getGroups($tenantId));
            $hubStats['teams'] = count($unitRepository->getTeams($tenantId));
            $hubStats['roles'] = count((new RoleRepository())->forTenantOrganization($tenantId));
            $hubStats['grades'] = count((new GradeRepository())->listForTenant($tenantId));
            $hubStats['job_roles'] = count((new PersonnelJobRoleRepository())->listRolesWithCategory($tenantId));
        } catch (\Throwable) {
            // Indicateurs optionnels : la page reste utilisable sans volumes.
        }

        $tenantName = '';
        try {
            $tenantRow = (new TenantRepository())->findById($tenantId);
            if (is_array($tenantRow)) {
                $tenantName = trim((string) ($tenantRow['name'] ?? ''));
            }
        } catch (\Throwable) {
            $tenantName = '';
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.effectifs_hub',
            'title' => 'Structure & effectifs',
            'isBackOfficeShell' => true,
            'hubStats' => $hubStats,
            'communityName' => $tenantName !== '' ? $tenantName : 'Communauté',
            'canRolesList' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
            'canRolesCanvas' => $gate->allows('admin.organization') || $gate->allows('admin.roles.manage') || $gate->allows('admin.permissions.manage'),
            'canPresets' => $gate->allows('admin.organization') || $gate->allows('admin.roles.manage') || $gate->allows('admin.permissions.manage'),
            'canGrades' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
            'canStructure' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
            'canStructureRecruitmentHub' => $gate->allows('organization.orbat.view')
                || $gate->allows('organization.orbat.manage')
                || $gate->allows('admin.organization')
                || $gate->allows('admin.access')
                || $gate->allows('site.support'),
            'canCatalog' => $gate->allows('organization.catalog.manage')
                || $gate->allows('organization.orbat.manage')
                || $gate->allows('admin.organization')
                || $gate->allows('admin.access'),
            'canSeniorityAdmin' => $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support'),
            'canProgressionAdmin' => $gate->allows('personnel.progression.view')
                || $gate->allows('personnel.progression.configure')
                || $gate->allows('personnel.callsign.manage')
                || $gate->allows('admin.organization')
                || $gate->allows('admin.access'),
        ]);
    }
}
