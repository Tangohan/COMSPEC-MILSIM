<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlatformUxFeedbackRepository;
use App\Repositories\TenantRepository;

final class SystemUxFeedbackController
{
    public function __construct(
        private PlatformUxFeedbackRepository $feedbackRepository,
        private TenantRepository $tenants,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantFilter = (int) $request->query('tenant', 0);
        if ($tenantFilter < 1) {
            $tenantFilter = null;
        }

        $schemaReady = $this->feedbackRepository->isReady();
        $aggregates = $schemaReady ? $this->feedbackRepository->listPageAggregatesPlatform(100) : [];
        $recentRatings = $schemaReady ? $this->feedbackRepository->listRecentRatingsPlatform($tenantFilter, 40) : [];
        $recentSurveys = $schemaReady ? $this->feedbackRepository->listRecentSurveysPlatform($tenantFilter, 40) : [];

        $tenantOptions = [];
        try {
            foreach ($this->tenants->listBasicAll() as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $tenantOptions[] = [
                    'id' => $id,
                    'name' => trim((string) ($row['name'] ?? 'Communauté')),
                ];
            }
        } catch (\Throwable) {
            $tenantOptions = [];
        }

        return Response::view('layout.main', [
            'title' => 'Retours interface',
            'content' => 'admin.system.ux_feedback_index',
            'isPlatformAdminShell' => true,
            'uxFeedbackSchemaReady' => $schemaReady,
            'uxPageAggregates' => $aggregates,
            'uxRecentRatings' => $recentRatings,
            'uxRecentSurveys' => $recentSurveys,
            'uxTenantFilter' => $tenantFilter,
            'uxTenantOptions' => $tenantOptions,
        ]);
    }
}
