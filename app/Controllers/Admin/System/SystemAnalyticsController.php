<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TenantAnalyticsRepository;

final class SystemAnalyticsController
{
    public function __construct(
        private TenantAnalyticsRepository $tenantAnalyticsRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $days = (int) $request->query('days', 7);
        if (!in_array($days, [1, 7, 30], true)) {
            $days = 7;
        }
        $snapshot = $this->tenantAnalyticsRepository->getPlatformUsageSnapshot();
        $daily = $this->tenantAnalyticsRepository->getPlatformDailyEvents($days);
        $categories = $this->tenantAnalyticsRepository->getPlatformCategoryBreakdown($days);

        return Response::view('layout.main', [
            'title' => 'Indicateurs transverses',
            'content' => 'admin.system.analytics',
            'isPlatformAdminShell' => true,
            'platformAnalyticsSnapshot' => $snapshot,
            'platformDailyEvents' => $daily,
            'platformCategoryBreakdown' => $categories,
            'platformAnalyticsDays' => $days,
        ]);
    }
}
