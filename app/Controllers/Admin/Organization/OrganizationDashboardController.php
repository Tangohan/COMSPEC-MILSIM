<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\CommunityEventRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\OpsBoardRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantRepository;
use App\Services\Admin\AdminDashboardMetricsService;

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
        private ?OpsBoardRepository $opsBoardRepository = null
    ) {
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
        $this->moderationRepository ??= new ModerationRepository();
        $this->enlistmentRepository ??= new EnlistmentRepository();
        $this->communityFeed ??= new TenantCommunityFeedRepository();
        $this->eventRepository ??= new CommunityEventRepository();
        $this->tenantAlertRepository ??= new TenantAlertRepository();
        $this->opsBoardRepository ??= new OpsBoardRepository();
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

        return Response::view('layout.main', [
            'content' => 'admin.organization.dashboard',
            'title' => 'Administration organisationnelle',
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
            'tenantName' => $tenantName,
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

        return Response::view('layout.main', [
            'content' => 'admin.organization.operations_center',
            'title' => 'Centre des opérations',
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
        ]);
    }

    /**
     * Tableau d’orientation : liens vers la structure des effectifs (rôles, grades, unités, etc.).
     */
    public function effectifsHub(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'content' => 'admin.organization.effectifs_hub',
            'title' => 'Organisation des effectifs',
            'canRolesList' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
            'canRolesCanvas' => $gate->allows('admin.organization') || $gate->allows('admin.roles.manage') || $gate->allows('admin.permissions.manage'),
            'canPresets' => $gate->allows('admin.organization') || $gate->allows('admin.roles.manage') || $gate->allows('admin.permissions.manage'),
            'canGrades' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
            'canStructure' => $gate->allows('admin.organization') || $gate->allows('admin.access'),
        ]);
    }
}
