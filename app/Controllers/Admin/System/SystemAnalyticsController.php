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
        $snapshot = $this->tenantAnalyticsRepository->getPlatformUsageSnapshot();

        return Response::view('layout.main', [
            'title' => 'Indicateurs transverses',
            'content' => 'admin.system.analytics',
            'isPlatformAdminShell' => true,
            'platformAnalyticsSnapshot' => $snapshot,
        ]);
    }
}
