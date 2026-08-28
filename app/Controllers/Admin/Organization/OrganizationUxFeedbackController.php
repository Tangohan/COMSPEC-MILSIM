<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformUxFeedbackRepository;

final class OrganizationUxFeedbackController
{
    public function __construct(
        private PlatformUxFeedbackRepository $feedbackRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }

        $schemaReady = $this->feedbackRepository->isReady();
        $aggregates = $schemaReady ? $this->feedbackRepository->listPageAggregates($tenantId, 100) : [];
        $recentRatings = $schemaReady ? $this->feedbackRepository->listRecentRatings($tenantId, 40) : [];
        $recentSurveys = $schemaReady ? $this->feedbackRepository->listRecentSurveys($tenantId, 40) : [];

        return Response::view('layout.main', [
            'title' => 'Retours interface',
            'content' => 'admin.organization.ux_feedback_index',
            'isBackOfficeShell' => true,
            'boPageKicker' => 'Expérience utilisateur',
            'boPageTitle' => 'Retours interface & notations',
            'boPageSubtitle' => 'Notes rapides et questionnaires détaillés remontés depuis le back-office.',
            'uxFeedbackSchemaReady' => $schemaReady,
            'uxPageAggregates' => $aggregates,
            'uxRecentRatings' => $recentRatings,
            'uxRecentSurveys' => $recentSurveys,
        ]);
    }
}
