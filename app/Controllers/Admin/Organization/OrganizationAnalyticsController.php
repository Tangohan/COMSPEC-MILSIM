<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformUsageRepository;
use App\Services\Platform\FeatureGateService;

final class OrganizationAnalyticsController
{
    public function __construct(
        private FeatureGateService $featureGate,
        private PlatformUsageRepository $usageRepository
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
        $pdo = Database::getPdo();
        $since = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE tenant_id = ? AND created_at >= ? AND user_id IS NOT NULL');
        $stmt->execute([$tenantId, $since]);
        $activeApprox = (int) $stmt->fetchColumn();
        $usage = $this->usageRepository->countByFeatureSince($tenantId, 'dashboard_visit', $since);

        return Response::view('layout.main', [
            'title' => 'Analytics communauté',
            'content' => 'admin.organization.analytics',
            'activeApprox' => $activeApprox,
            'dashboardEvents' => $usage,
            'since' => $since,
        ]);
    }
}
