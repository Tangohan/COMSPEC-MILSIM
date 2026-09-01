<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\TenantAnalyticsRepository;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Audit\AuditAction;

class SystemDashboardController
{
    public function __construct(
        private ?AdminDashboardMetricsService $metrics = null,
        private ?AuditLogRepository $auditLogs = null,
        private ?ForumReportRepository $forumReports = null,
        private ?ModerationArtifactRepository $moderationArtifacts = null,
        private ?TenantAnalyticsRepository $tenantAnalytics = null,
    ) {
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
        $this->forumReports ??= new ForumReportRepository();
        $this->moderationArtifacts ??= new ModerationArtifactRepository();
        $this->tenantAnalytics ??= new TenantAnalyticsRepository();
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

        $forumPendingTotal = 0;
        $forumPendingByTenant = [];
        $forumModerationSnapshotError = null;
        try {
            $forumPendingTotal = $this->forumReports->countPendingAllTenants();
            $forumPendingByTenant = $this->forumReports->pendingCountTopTenants(6);
        } catch (\Throwable) {
            $forumModerationSnapshotError = 'Les totaux de signalements forum ne sont pas disponibles pour le moment.';
        }

        $contentQueueTotal = 0;
        $contentQueueByTenant = [];
        $contentQueueSnapshotError = null;
        try {
            if ($this->moderationArtifacts->tableExists()) {
                $contentQueueTotal = $this->moderationArtifacts->countPendingQueueAllTenants();
                $contentQueueByTenant = $this->moderationArtifacts->pendingQueueTopTenants(6);
            }
        } catch (\Throwable) {
            $contentQueueSnapshotError = 'La file de validation des fichiers ne peut pas être lue pour le moment.';
        }

        $auditContentActions = [
            AuditAction::FORUM_MODERATION,
            AuditAction::MODERATION_ACTION,
            AuditAction::MODERATION_REVOKED,
        ];
        $auditTenantActions = [
            AuditAction::TENANT_CREATED,
            AuditAction::TENANT_SETUP_COMPLETED,
        ];
        $adminAuditRecentContent = [];
        $adminAuditRecentTenant = [];
        $adminAuditModerationError = null;
        try {
            $adminAuditRecentContent = $this->auditLogs->recentSystemByActions($auditContentActions, 8);
            $adminAuditRecentTenant = $this->auditLogs->recentSystemByActions($auditTenantActions, 8);
        } catch (\Throwable) {
            $adminAuditModerationError = 'Extrait du journal indisponible pour le moment.';
        }

        $appEnv = function_exists('env') ? (string) env('APP_ENV', 'local') : 'local';
        $adminPlatformEnvLabel = app_environment_label_fr($appEnv);

        $adminPlatformUsagePreview = [
            'error' => null,
            'snapshot' => ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []],
            'categories' => [],
            'kpis' => [],
        ];
        try {
            $adminPlatformUsagePreview['snapshot'] = $this->tenantAnalytics->getPlatformUsageSnapshot(7);
            $adminPlatformUsagePreview['categories'] = array_slice($this->tenantAnalytics->getPlatformCategoryBreakdown(7), 0, 3);
            $adminPlatformUsagePreview['kpis'] = $this->tenantAnalytics->getPlatformOperationalKpis(7);
        } catch (\Throwable) {
            $adminPlatformUsagePreview['error'] = 'L’aperçu d’usage sur 7 jours est momentanément indisponible.';
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.dashboard',
            'title' => 'Administration plateforme',
            'adminKpis' => $metrics['kpis'],
            'adminKpiBlockError' => $metrics['blockError'],
            'adminRecentActivity' => $recent,
            'adminRecentActivityError' => $recentError,
            'adminRecentActivityMoreUrl' => url('admin/audit'),
            'adminPlatformEnvRaw' => $appEnv,
            'adminPlatformEnvLabel' => $adminPlatformEnvLabel,
            'adminHealthCheckUrl' => url('api/health'),
            'adminForumPendingTotal' => $forumPendingTotal,
            'adminForumPendingByTenant' => $forumPendingByTenant,
            'adminForumModerationSnapshotError' => $forumModerationSnapshotError,
            'adminContentQueueTotal' => $contentQueueTotal,
            'adminContentQueueByTenant' => $contentQueueByTenant,
            'adminContentQueueSnapshotError' => $contentQueueSnapshotError,
            'adminAuditRecentContent' => $adminAuditRecentContent,
            'adminAuditRecentTenant' => $adminAuditRecentTenant,
            'adminAuditModerationError' => $adminAuditModerationError,
            'adminPlatformUsagePreview' => $adminPlatformUsagePreview,
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }
}
