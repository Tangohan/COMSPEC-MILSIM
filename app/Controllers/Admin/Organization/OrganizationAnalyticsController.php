<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformUsageRepository;
use App\Repositories\TenantAnalyticsRepository;
use App\Services\Platform\FeatureGateService;

final class OrganizationAnalyticsController
{
    public function __construct(
        private FeatureGateService $featureGate,
        private PlatformUsageRepository $usageRepository,
        private TenantAnalyticsRepository $tenantAnalyticsRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allows($tenantId, 'analytics')) {
            return Response::view('layout.main', [
                'title' => 'Analytics',
                'content' => 'platform.upgrade',
                'feature' => 'analytics',
                'planName' => 'pro',
            ]);
        }
        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }
        $since = (new \DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s');
        $pdo = Database::getPdo();
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE tenant_id = ? AND created_at >= ? AND user_id IS NOT NULL');
        $stmt->execute([$tenantId, $since]);
        $activeApprox = (int) $stmt->fetchColumn();
        $usage = $this->usageRepository->countByFeatureSince($tenantId, 'dashboard_visit', $since);

        $trainingRows = $this->tenantAnalyticsRepository->listTrainingCourseStats($tenantId, $since);
        $openingRows = $this->tenantAnalyticsRepository->listRecruitmentOpeningStats($tenantId, $since);
        $publicEngagement = $this->tenantAnalyticsRepository->getTenantPublicEngagement($tenantId, $since);
        $categoryBreakdown = $this->tenantAnalyticsRepository->getTenantCategoryBreakdown($tenantId, $since);
        $topActors = $this->tenantAnalyticsRepository->listTenantTopActors($tenantId, $since, 8);
        $tenantUsageSummary = $this->tenantAnalyticsRepository->getTenantUsageSummary($tenantId, $since);
        $tenantDailyEvents = $this->tenantAnalyticsRepository->getTenantDailyEventCountsFilled($tenantId, $since, $days);
        $tenantTopEventNames = $this->tenantAnalyticsRepository->getTenantTopEventNames($tenantId, $since, 12);
        $trainingCatalogViews = $this->tenantAnalyticsRepository->getTenantTrainingCatalogViews($tenantId, $since);
        $operationalKpis = $this->tenantAnalyticsRepository->getTenantOperationalKpis($tenantId, $since);
        $enlistmentStatusBreakdown = $this->tenantAnalyticsRepository->getTenantEnlistmentStatusBreakdownSince($tenantId, $since);
        $documentInsights = $this->tenantAnalyticsRepository->getTenantDocumentInsights($tenantId, $since);
        $conversionFunnel = $this->tenantAnalyticsRepository->getTenantConversionFunnel($tenantId, $since);
        $sevenDaysAgo = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
        $fourteenDaysAgo = (new \DateTimeImmutable('-14 days'))->format('Y-m-d H:i:s');
        $funnelLast7 = $this->tenantAnalyticsRepository->getTenantConversionFunnel($tenantId, $sevenDaysAgo);
        $funnelPrev7 = $this->tenantAnalyticsRepository->getTenantConversionFunnel($tenantId, $fourteenDaysAgo);
        $funnelPrev7Only = [
            'visits' => max(0, (int) ($funnelPrev7['visits'] ?? 0) - (int) ($funnelLast7['visits'] ?? 0)),
            'cta_clicks' => max(0, (int) ($funnelPrev7['cta_clicks'] ?? 0) - (int) ($funnelLast7['cta_clicks'] ?? 0)),
            'applications' => max(0, (int) ($funnelPrev7['applications'] ?? 0) - (int) ($funnelLast7['applications'] ?? 0)),
            'accepted' => max(0, (int) ($funnelPrev7['accepted'] ?? 0) - (int) ($funnelLast7['accepted'] ?? 0)),
        ];

        return Response::view('layout.main', [
            'title' => 'Indicateurs d’usage',
            'content' => 'admin.organization.analytics',
            'isBackOfficeShell' => true,
            'activeApprox' => $activeApprox,
            'dashboardEvents' => $usage,
            'since' => $since,
            'analyticsDays' => $days,
            'trainingCourseStats' => $trainingRows,
            'recruitmentOpeningStats' => $openingRows,
            'publicEngagement' => $publicEngagement,
            'tenantCategoryBreakdown' => $categoryBreakdown,
            'tenantTopActors' => $topActors,
            'tenantUsageSummary' => $tenantUsageSummary,
            'tenantDailyEvents' => $tenantDailyEvents,
            'tenantTopEventNames' => $tenantTopEventNames,
            'trainingCatalogViews' => $trainingCatalogViews,
            'operationalKpis' => $operationalKpis,
            'enlistmentStatusBreakdown' => $enlistmentStatusBreakdown,
            'documentInsights' => $documentInsights,
            'conversionFunnel' => $conversionFunnel,
            'funnelLast7' => $funnelLast7,
            'funnelPrev7Only' => $funnelPrev7Only,
        ]);
    }
}
