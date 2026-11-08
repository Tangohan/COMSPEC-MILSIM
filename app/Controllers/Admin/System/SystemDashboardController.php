<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Repositories\ContentReportRepository;
use App\Repositories\MaintenanceRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\PlatformAlertRepository;
use App\Repositories\TenantAnalyticsRepository;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Admin\PlatformAccountsOverviewService;
use App\Services\Admin\PlatformHealthDigest;
use App\Services\Admin\PlatformHomeService;
use App\Services\Audit\AuditAction;

/**
 * Accueil de l'administration du site. La vue d'ensemble ne charge que quatre blocs courts (à traiter, santé,
 * chiffres clés, accès rapides) ; le détail est réparti en onglets à adresse propre, calculés seulement à l'ouverture.
 */
class SystemDashboardController
{
    /** Onglets de détail (sans double vérification) : adresse propre ?onglet=… */
    public const DETAIL_TABS = ['sante', 'chiffres', 'activite'];

    public function __construct(
        private ?AdminDashboardMetricsService $metrics = null,
        private ?AuditLogRepository $auditLogs = null,
        private ?ContentReportRepository $contentReports = null,
        private ?ModerationArtifactRepository $moderationArtifacts = null,
        private ?TenantAnalyticsRepository $tenantAnalytics = null,
        private ?PlatformHealthDigest $health = null,
        private ?PlatformAccountsOverviewService $accountsOverview = null,
    ) {
        $this->accountsOverview ??= new PlatformAccountsOverviewService();
        $this->health ??= new PlatformHealthDigest();
        $this->metrics ??= new AdminDashboardMetricsService();
        $this->auditLogs ??= new AuditLogRepository();
        $this->contentReports ??= new ContentReportRepository();
        $this->moderationArtifacts ??= new ModerationArtifactRepository();
        $this->tenantAnalytics ??= new TenantAnalyticsRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        // Onglets d'exploitation : la console garde sa propre double vérification.
        if (in_array((string) $request->query('onglet', ''), SystemOpsConsoleController::TABS, true)) {
            return (new SystemOpsConsoleController())->index($request, $params);
        }
        $tab = (string) $request->query('onglet', '');
        if (!in_array($tab, self::DETAIL_TABS, true)) {
            $tab = 'vue';
        }

        $appEnv = function_exists('env') ? (string) env('APP_ENV', 'local') : 'local';
        $data = [
            'content' => 'admin.system.dashboard',
            'title' => 'Pilotage du site',
            'homeTab' => $tab,
            'adminPlatformEnvRaw' => $appEnv,
            'adminPlatformEnvLabel' => app_environment_label_fr($appEnv),
            'adminHealthCheckUrl' => url('api/health'),
            'backOfficePageCss' => ['platform-admin.css', 'ops-console.css', 'platform-admin-users.css'],
        ];
        $data += match ($tab) {
            'sante' => $this->santeData(),
            'chiffres' => $this->chiffresData(),
            'activite' => $this->activiteData(),
            default => $this->vueData(),
        };

        return Response::view('layout.main', $data);
    }

    /** @return array<string, mixed> */
    private function vueData(): array
    {
        $home = new PlatformHomeService();

        return [
            'homeActions' => $home->actions(),
            'homeHealth' => $home->health(),
            'homeFigures' => $home->figures(),
            'homeQuickLinks' => PlatformHomeService::quickLinks(),
        ];
    }

    /** @return array<string, mixed> */
    private function santeData(): array
    {
        $activeAlerts = [];
        try {
            $activeAlerts = (new PlatformAlertRepository())->listActiveForDisplay();
        } catch (\Throwable) {
        }
        $maintenanceCounts = ['active' => 0, 'planned' => 0];
        try {
            $maintenance = new MaintenanceRepository();
            if ($maintenance->tableExists()) {
                foreach ($maintenance->listAll() as $row) {
                    $maintenanceCounts['active'] += (int) ($row['is_enabled'] ?? 0) === 1 ? 1 : 0;
                    $maintenanceCounts['planned'] += (string) ($row['starts_at'] ?? '') !== '' || (string) ($row['ends_at'] ?? '') !== '' ? 1 : 0;
                }
            }
        } catch (\Throwable) {
        }
        $healthRows = $this->health->rows();

        return [
            'adminHealthRows' => $healthRows,
            'adminHealthSummary' => PlatformHealthDigest::summarize($healthRows),
            'adminActiveAlerts' => $activeAlerts,
            'adminMaintenanceCounts' => $maintenanceCounts,
        ];
    }

    /** @return array<string, mixed> */
    private function chiffresData(): array
    {
        $metrics = $this->metrics->getSystemMetrics();
        $usage = [
            'error' => null,
            'snapshot' => ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []],
            'categories' => [],
            'kpis' => [],
        ];
        try {
            $usage['snapshot'] = $this->tenantAnalytics->getPlatformUsageSnapshot(7);
            $usage['categories'] = array_slice($this->tenantAnalytics->getPlatformCategoryBreakdown(7), 0, 3);
            $usage['kpis'] = $this->tenantAnalytics->getPlatformOperationalKpis(7);
        } catch (\Throwable) {
            $usage['error'] = 'L’aperçu d’usage sur 7 jours est momentanément indisponible.';
        }
        $accountsOverview = [
            'communities' => [],
            'accounts' => [],
            'inactivity' => ['available' => false, 'total' => 0, 'buckets' => [], 'oldest' => []],
            'error' => null,
        ];
        try {
            $accountsOverview = $this->accountsOverview->overview(6);
        } catch (\Throwable) {
            $accountsOverview['error'] = 'Les aperçus des comptes et communautés sont momentanément indisponibles.';
        }

        return [
            'adminKpis' => $metrics['kpis'],
            'adminKpiBlockError' => $metrics['blockError'],
            'adminPlatformUsagePreview' => $usage,
            'adminAccountsOverview' => $accountsOverview,
        ] + $this->moderationCounts();
    }

    /** @return array<string, mixed> */
    private function activiteData(): array
    {
        $recent = [];
        $recentError = null;
        try {
            $recent = $this->auditLogs->recentSystem(8);
        } catch (\Throwable) {
            $recentError = 'Activité récente indisponible.';
        }
        $content = [];
        $tenant = [];
        $auditError = null;
        try {
            $content = $this->auditLogs->recentSystemByActions([
                AuditAction::REPORT_MODERATION,
                AuditAction::MODERATION_ACTION,
                AuditAction::MODERATION_REVOKED,
            ], 8);
            $tenant = $this->auditLogs->recentSystemByActions([AuditAction::TENANT_CREATED, AuditAction::TENANT_SETUP_COMPLETED], 8);
        } catch (\Throwable) {
            $auditError = 'Extrait du journal indisponible pour le moment.';
        }

        return [
            'adminRecentActivity' => $recent,
            'adminRecentActivityError' => $recentError,
            'adminRecentActivityMoreUrl' => url('admin/audit'),
            'adminAuditRecentContent' => $content,
            'adminAuditRecentTenant' => $tenant,
            'adminAuditModerationError' => $auditError,
        ] + $this->moderationCounts();
    }

    /** @return array<string, mixed> */
    private function moderationCounts(): array
    {
        $reportsTotal = 0;
        $reportsByTenant = [];
        $reportsError = null;
        try {
            $reportsTotal = $this->contentReports->countPendingAllTenants();
            $reportsByTenant = $this->contentReports->pendingCountTopTenants(6);
        } catch (\Throwable) {
            $reportsError = 'Les totaux de signalements ne sont pas disponibles pour le moment.';
        }
        $queueTotal = 0;
        $queueByTenant = [];
        $queueError = null;
        try {
            if ($this->moderationArtifacts->tableExists()) {
                $queueTotal = $this->moderationArtifacts->countPendingQueueAllTenants();
                $queueByTenant = $this->moderationArtifacts->pendingQueueTopTenants(6);
            }
        } catch (\Throwable) {
            $queueError = 'La file de validation des fichiers ne peut pas être lue pour le moment.';
        }

        return [
            'adminReportsPendingTotal' => $reportsTotal,
            'adminReportsPendingByTenant' => $reportsByTenant,
            'adminReportsSnapshotError' => $reportsError,
            'adminContentQueueTotal' => $queueTotal,
            'adminContentQueueByTenant' => $queueByTenant,
            'adminContentQueueSnapshotError' => $queueError,
        ];
    }
}
