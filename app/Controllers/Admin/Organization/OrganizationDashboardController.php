<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ModerationRepository;
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
        private ?TenantCommunityFeedRepository $communityFeed = null
    ) {
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
        $this->moderationRepository ??= new ModerationRepository();
        $this->enlistmentRepository ??= new EnlistmentRepository();
        $this->communityFeed ??= new TenantCommunityFeedRepository();
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
            $moderationRecent = $this->moderationRepository->listRecentActions($tenantId, 5);
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
