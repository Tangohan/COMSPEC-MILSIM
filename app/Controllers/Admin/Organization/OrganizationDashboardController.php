<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Container;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\OrganizationRoleLabels;
use App\Support\OrbatRosterPayload;
use App\Repositories\AuditLogRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\OpsBoardRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\TrainingQuizRepository;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Platform\FeatureGateService;
use App\Services\Training\TrainingEnrollmentCompletionAnalytics;

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
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
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
        $workQueue = $this->metrics->getOrganizationWorkQueue($tenantId);
        $recent = [];
        $recentError = null;
        try {
            $recent = $this->auditLogs->recentForTenant($tenantId, 12);
        } catch (\Throwable) {
            $recentError = 'Activité récente indisponible.';
        }
        $orgEnlistmentCounts = [];
        $orgEnlistmentRecent = [];
        $orgEnlistmentError = null;
        try {
            $orgEnlistmentCounts = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
            $orgEnlistmentRecent = $this->enlistmentRepository->recentForTenantDashboard($tenantId, 10);
        } catch (\Throwable) {
            $orgEnlistmentError = 'Données candidatures indisponibles.';
        }
        $orgRhRecent = [];
        $orgRhRecentError = null;
        try {
            $orgRhRecent = $this->auditLogs->recentForTenantRhFocus($tenantId, 15);
        } catch (\Throwable) {
            $orgRhRecentError = 'Fil RH indisponible.';
        }
        $moderationRecent = [];
        $moderationError = null;
        try {
            $moderationRecent = $this->moderationRepository->listRecentActions($tenantId, 5, 'tenant');
        } catch (\Throwable) {
            $moderationError = 'Modération indisponible.';
        }
        $orgTrainingFeed = [];
        $orgTrainingFeedError = null;
        try {
            $orgTrainingFeed = $this->communityFeed->listRecentForTenant($tenantId, 15, 'training_');
        } catch (\Throwable) {
            $orgTrainingFeedError = 'Fil formations indisponible.';
        }
        $orgTrainingFeedCompletionAnalytics = [];
        if ($orgTrainingFeedError === null && $orgTrainingFeed !== []) {
            try {
                $orgTrainingFeedCompletionAnalytics = $this->trainingFeedCompletionAnalytics->buildForTrainingFeedRows(
                    $tenantId,
                    $orgTrainingFeed
                );
            } catch (\Throwable) {
                $orgTrainingFeedCompletionAnalytics = [];
            }
        }

        $orgIntegrationsPlanAllowed = false;
        if ($tenantId > 1) {
            try {
                $orgIntegrationsPlanAllowed = Container::get(FeatureGateService::class)->allows($tenantId, 'advanced_integrations');
            } catch (\Throwable) {
                $orgIntegrationsPlanAllowed = false;
            }
        }

        $orgAnnounceItems = [];
        $discordInviteMissing = false;
        try {
            $settings = (new TenantRepository())->getSettings($tenantId);
            $communityCfg = is_array($settings['community'] ?? null) ? $settings['community'] : [];
            $discordInviteMissing = \App\Services\Community\TenantCommunityProfileService::needsDiscordInviteAlert($communityCfg);
            if ($discordInviteMissing) {
                $orgAnnounceItems[] = [
                    'kind' => 'urgent',
                    'category' => 'Configuration',
                    'title' => 'Lien Discord manquant',
                    'body' => 'Le recrutement via Discord est actif, mais aucun lien d’invitation n’est renseigné. Les candidats ne peuvent pas rejoindre votre serveur depuis le formulaire.',
                    'cta_label' => 'Renseigner le lien',
                    'cta_url' => url('back-office/organisation/parametres') . '#contact',
                ];
            }
        } catch (\Throwable) {
            $discordInviteMissing = false;
        }
        try {
            foreach ($this->tenantAlertRepository->listActiveForTenantDisplay($tenantId) as $alert) {
                $style = \App\Support\AlertDisplayStyle::sanitizeTenant(
                    isset($alert['display_style']) ? (string) $alert['display_style'] : null
                );
                if (\App\Support\AlertDisplayStyle::isActivityFeedStyle($style)
                    || \App\Support\AlertDisplayStyle::isBackOfficeStyle($style)) {
                    continue;
                }
                $orgAnnounceItems[] = [
                    'scope' => 'tenant',
                    'kind' => (string) ($alert['kind'] ?? 'info'),
                    'title' => (string) ($alert['title'] ?? ''),
                    'body' => trim((string) ($alert['body'] ?? '')),
                    'cta_label' => isset($alert['cta_label']) && $alert['cta_label'] !== '' ? (string) $alert['cta_label'] : null,
                    'cta_url' => isset($alert['cta_url']) && $alert['cta_url'] !== '' ? (string) $alert['cta_url'] : null,
                    'accent_color' => isset($alert['accent_color']) ? (string) $alert['accent_color'] : null,
                    'image_url' => \App\Support\TenantAlertVisuals::publicUrl(isset($alert['image_path']) ? (string) $alert['image_path'] : null),
                ];
            }
        } catch (\Throwable) {
            // Les annonces publiées restent optionnelles ; l’alerte Discord ci-dessus est conservée.
        }
        try {
            foreach (\App\Core\Container::get(\App\Services\Alerts\AlertPresentationService::class)
                ->backOfficeForTenant($tenantId) as $alert) {
                $orgAnnounceItems[] = [
                    'scope' => 'tenant',
                    'kind' => (string) ($alert['kind'] ?? 'info'),
                    'title' => (string) ($alert['title'] ?? ''),
                    'body' => trim((string) ($alert['body'] ?? '')),
                    'cta_label' => $alert['cta_label'] ?? null,
                    'cta_url' => $alert['cta_url'] ?? null,
                    'accent_color' => $alert['accent_color'] ?? null,
                    'image_url' => $alert['image_url'] ?? null,
                ];
            }
        } catch (\Throwable) {
            // Annonces back-office optionnelles.
        }
        try {
            $pinRows = (new \App\Repositories\TenantDashboardPinRepository())->listOrderedForTenant($tenantId);
            foreach ($pinRows as $pin) {
                if ((string) ($pin['pin_type'] ?? '') !== 'notice') {
                    continue;
                }
                $body = trim((string) ($pin['notice_body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $label = trim((string) ($pin['title'] ?? ''));
                $orgAnnounceItems[] = [
                    'kind' => 'notice',
                    'category' => 'Annonce',
                    'title' => $label !== '' ? $label : 'Annonce',
                    'body' => $body,
                    'cta_label' => 'Gérer',
                    'cta_url' => url('back-office/dashboard-pins'),
                ];
            }
        } catch (\Throwable) {
            // Les consignes épinglées restent optionnelles.
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

        $chartDays = 14;
        $orgActivityChart = ['days' => [], 'portal' => [], 'atak' => [], 'max' => 1];
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

        $orgNextOperation = null;
        try {
            $upcoming = $this->eventRepository->upcomingForTenant($tenantId, 1);
            $event = $upcoming[0] ?? null;
            if (is_array($event) && (int) ($event['id'] ?? 0) > 0) {
                $eid = (int) $event['id'];
                $summaries = $this->eventRepository->rsvpSummariesForEvents([$eid]);
                $sum = $summaries[$eid] ?? ['yes' => [], 'maybe' => [], 'no' => []];
                $yes = count($sum['yes'] ?? []);
                $maybe = count($sum['maybe'] ?? []);
                $no = count($sum['no'] ?? []);
                $activeMembers = 0;
                try {
                    $activeMembers = $this->users->countActiveForTenant($tenantId);
                } catch (\Throwable) {
                    $activeMembers = 0;
                }
                $answered = $yes + $maybe + $no;
                $noReply = max(0, $activeMembers - $answered);
                $startsAt = (string) ($event['starts_at'] ?? '');
                $startsFmt = $startsAt !== '' ? date('d/m/Y H\hi', strtotime($startsAt) ?: time()) : '';
                $orgNextOperation = [
                    'id' => $eid,
                    'title' => trim((string) ($event['title'] ?? 'Manœuvre')),
                    'starts_at' => $startsAt,
                    'starts_label' => $startsFmt,
                    'href' => url('back-office/events/' . $eid),
                    'rsvp' => [
                        'yes' => $yes,
                        'maybe' => $maybe,
                        'no' => $no,
                        'no_reply' => $noReply,
                    ],
                ];
            }
        } catch (\Throwable) {
            $orgNextOperation = null;
        }

        $orgElevationOpen = [];
        $orgElevationOpenCount = 0;
        try {
            $orgElevationOpen = $this->elevationRequests->listOpenForTenant($tenantId, 6);
            $orgElevationOpenCount = $this->elevationRequests->countOpenForTenant($tenantId);
        } catch (\Throwable) {
            $orgElevationOpen = [];
            $orgElevationOpenCount = 0;
        }

        $orgMessagesRecent = [];
        $viewerId = (int) Session::get('user_id');
        if ($viewerId > 0) {
            try {
                $orgMessagesRecent = $this->tenantMessages->listActivityThreadsForUser($tenantId, $viewerId, 2);
            } catch (\Throwable) {
                $orgMessagesRecent = [];
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.dashboard',
            'title' => 'Tableau de bord',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Pilotage',
            'boPageTitle' => 'Tableau de bord',
            'boPageKicker' => 'PILOTAGE · VUE GÉNÉRALE',
            'boPageSubtitle' => 'Synthèse de votre communauté : effectifs, activité, formations et points nécessitant une décision.',
            'boPageAction' => 'Publier une consigne',
            'boPageActionUrl' => url('back-office/alerts'),
            'boPageQuick' => [
                ['label' => '7 jours', 'href' => url('back-office')],
                ['label' => '30 jours', 'href' => url('back-office')],
                ['label' => 'Exporter', 'href' => url('back-office/audit')],
            ],
            'backOfficePageCss' => ['back-office-dashboard.css', 'announce-tiles.css'],
            'adminKpis' => $metrics['kpis'],
            'adminKpiBlockError' => $metrics['blockError'],
            'adminRecentActivity' => $recent,
            'adminRecentActivityError' => $recentError,
            'adminRecentActivityMoreUrl' => url('back-office/audit'),
            'orgEnlistmentCounts' => $orgEnlistmentCounts,
            'orgEnlistmentRecent' => $orgEnlistmentRecent,
            'orgEnlistmentError' => $orgEnlistmentError,
            'orgRhRecent' => $orgRhRecent,
            'orgRhRecentError' => $orgRhRecentError,
            'orgWorkQueue' => $workQueue,
            'orgModerationRecent' => $moderationRecent,
            'orgModerationError' => $moderationError,
            'orgTrainingFeed' => $orgTrainingFeed,
            'orgTrainingFeedError' => $orgTrainingFeedError,
            'orgTrainingFeedCompletionAnalytics' => $orgTrainingFeedCompletionAnalytics,
            'orgIntegrationsPlanAllowed' => $orgIntegrationsPlanAllowed,
            'orgAnnounceItems' => $orgAnnounceItems,
            'skipGlobalAlertBanners' => true,
            'tenantName' => $tenantName,
            'initialSetupBanner' => $initialSetupBanner,
            'discordInviteMissing' => $discordInviteMissing,
            'orgActivityChart' => $orgActivityChart,
            'orgNextOperation' => $orgNextOperation,
            'orgElevationOpen' => $orgElevationOpen,
            'orgElevationOpenCount' => $orgElevationOpenCount,
            'orgElevationKindLabels' => EffectifsStaffAlertService::ELEVATION_KIND_LABELS,
            'orgMessagesRecent' => $orgMessagesRecent,
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

        $onboardingAnomalies = [
            'profils_incomplets' => count($workQueue['incomplete_profiles'] ?? []),
            'membres_sans_unite' => count($workQueue['users_without_unit'] ?? []),
            'membres_sans_role' => count($workQueue['users_without_role'] ?? []),
            'invitations_expirees' => count($workQueue['expired_invitations'] ?? []),
        ];

        $actionableAlerts = [
            [
                'id' => 'moderation_open',
                'type' => 'Contenu',
                'title' => 'Signalements modération à traiter',
                'impact_score' => min(100, 30 + ($moderationOpen * 6)),
                'sla_label' => 'SLA: 4h',
                'count' => $moderationOpen,
                'link' => url('back-office/forum-moderation'),
                'cta' => 'Traiter maintenant',
            ],
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
                'id' => 'onboarding_anomalies',
                'type' => 'Administration',
                'title' => 'Anomalies onboarding / droits',
                'impact_score' => min(100, 15 + (array_sum($onboardingAnomalies) * 3)),
                'sla_label' => 'SLA: 48h',
                'count' => array_sum($onboardingAnomalies),
                'link' => url('back-office/users'),
                'cta' => 'Corriger les anomalies',
            ],
        ];
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
            'canSeniorityAdmin' => $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support'),
        ]);
    }

    /**
     * Hub ORBAT + invitations / créations regroupements et équipes (back-office communauté).
     */
    public function structureRecruitmentHub(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        $canSeeOrbat = $gate->allows('organization.orbat.view')
            || $gate->allows('organization.orbat.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support');
        if (!$canSeeOrbat) {
            Session::flash('error', 'Vous n’avez pas accès à l’organigramme des unités.');

            return Response::redirect(url('back-office'));
        }
        $orbatCanManage = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');
        $viewerId = (int) Session::get('user_id');
        $unitRepository = new UnitRepository();
        $userRepository = new UserRepository();
        $rosterData = OrbatRosterPayload::buildForTenant($unitRepository, $tenantId, $viewerId, $orbatCanManage);
        $orbatCommanderOptions = [];
        if ($orbatCanManage) {
            foreach ($userRepository->allForTenant($tenantId) as $u) {
                if (($u['status'] ?? '') !== 'active') {
                    continue;
                }
                $id = (int) ($u['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $dn = trim((string) ($u['display_name'] ?? ''));
                $cs = trim((string) ($u['callsign'] ?? ''));
                $em = trim((string) ($u['email'] ?? ''));
                $label = $dn !== '' ? $dn : ($cs !== '' ? $cs : $em);
                if ($label === '') {
                    $label = 'Compte #' . $id;
                }
                $orbatCommanderOptions[] = ['id' => $id, 'label' => $label];
            }
        }
        $tenantRepository = new TenantRepository();
        $settings = $tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $tenantRow = $tenantRepository->findById($tenantId) ?: [];
        $organizationRoleLabelMode = OrganizationRoleLabels::mode($community, $tenantRow);

        $roleRepository = new RoleRepository();
        $gradeRepository = new GradeRepository();
        $gradeCategoryRepository = new GradeCategoryRepository();

        $rawOpen = strtolower(trim((string) $request->query('ouvrir', '')));
        $structureHubOpen = in_array($rawOpen, ['membre', 'groupe', 'equipe'], true) ? $rawOpen : '';

        return Response::view('layout.main', [
            'content' => 'admin.organization.structure_hub',
            'title' => 'Structure & recrutement',
            'isBackOfficeShell' => true,
            'orbatRosterData' => $rosterData,
            'orbatCanManage' => $orbatCanManage,
            'orbatCommanderOptions' => $orbatCommanderOptions,
            'orbatCsrfToken' => Csrf::token(),
            'orbatRecruitmentHub' => true,
            'orbatEmptyStateBackUrl' => url('back-office/organisation/structure'),
            'orbatPageEyebrow' => 'Structure',
            'orbatPageTitle' => 'Organigramme',
            'orbatPageLead' => 'Vue hiérarchique des unités ; utilisez la barre d’actions ou le clic droit sur une carte pour créer un regroupement ou une équipe.',
            'structureHubOpen' => $structureHubOpen,
            'groupParents' => $unitRepository->getGroups($tenantId),
            'teamParents' => $unitRepository->getTeams($tenantId),
            'usersForCommander' => $userRepository->allForTenant($tenantId),
            'roles' => $roleRepository->forTenantOrganization($tenantId),
            'roleMatrix' => $roleRepository->organizationRolesPermissionMatrix($tenantId),
            'grades' => $gradeRepository->listForTenant($tenantId),
            'gradeCategories' => $gradeCategoryRepository->listActive(),
            'organizationRoleLabelMode' => $organizationRoleLabelMode,
            'steamWebConfigured' => \App\Core\Container::get(\App\Services\Steam\SteamWebApiService::class)->isConfigured(),
        ]);
    }
}
