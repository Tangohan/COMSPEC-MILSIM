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
        ]);
    }
}
