<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Services\Admin\AdminDashboardMetricsService;

class SystemDashboardController
{
    public function __construct(
        private ?AdminDashboardMetricsService $metrics = null,
        private ?AuditLogRepository $auditLogs = null
    ) {
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $metrics = $this->metrics->getSystemMetrics();
        $recent = [];
        $recentError = null;
        try {
            $recent = $this->auditLogs->recentSystem(8);
        } catch (\Throwable) {
            $recentError = 'Activité récente indisponible.';
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.dashboard',
            'title' => 'Administration système',
            'adminKpis' => $metrics['kpis'],
            'adminKpiBlockError' => $metrics['blockError'],
            'adminRecentActivity' => $recent,
            'adminRecentActivityError' => $recentError,
            'adminRecentActivityMoreUrl' => url('admin/audit'),
        ]);
    }
}
