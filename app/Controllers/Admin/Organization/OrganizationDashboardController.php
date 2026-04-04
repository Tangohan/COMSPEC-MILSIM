<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\ModerationRepository;
use App\Services\Admin\AdminDashboardMetricsService;

class OrganizationDashboardController
{
    public function __construct(
        private ?AdminDashboardMetricsService $metrics = null,
        private ?AuditLogRepository $auditLogs = null,
        private ?ModerationRepository $moderationRepository = null
    ) {
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
        $this->moderationRepository ??= new ModerationRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $metrics = $this->metrics->getOrganizationMetrics($tenantId);
        $workQueue = $this->metrics->getOrganizationWorkQueue($tenantId);
        $recent = [];
        $recentError = null;
        try {
            $recent = $this->auditLogs->recentForTenant($tenantId, 8);
        } catch (\Throwable) {
            $recentError = 'Activité récente indisponible.';
        }
        $moderationRecent = [];
        $moderationError = null;
        try {
            $moderationRecent = $this->moderationRepository->listRecentActions($tenantId, 5);
        } catch (\Throwable) {
            $moderationError = 'Modération indisponible.';
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.dashboard',
            'title' => 'Administration organisationnelle',
            'adminKpis' => $metrics['kpis'],
            'adminKpiBlockError' => $metrics['blockError'],
            'adminRecentActivity' => $recent,
            'adminRecentActivityError' => $recentError,
            'adminRecentActivityMoreUrl' => url('admin/organization/audit'),
            'orgWorkQueue' => $workQueue,
            'orgModerationRecent' => $moderationRecent,
            'orgModerationError' => $moderationError,
        ]);
    }
}
