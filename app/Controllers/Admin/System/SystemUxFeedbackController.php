<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlatformUxFeedbackRepository;
use App\Repositories\TenantRepository;
use App\Support\UxFeedbackAdminPresentation as Ux;

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

        $typeFilter = Ux::normalizeType((string) $request->query('type', ''));
        $satisfactionFilter = Ux::normalizeSatisfaction((string) $request->query('satisfaction', ''));
        $screenFilter = Ux::normalizeScreen((string) $request->query('ecran', ''));

        $schemaReady = $this->feedbackRepository->isReady();
        $aggregates = $schemaReady ? $this->feedbackRepository->listPageAggregatesPlatform(100) : [];
        $recentRatings = $schemaReady ? $this->feedbackRepository->listRecentRatingsPlatform($tenantFilter, 40) : [];
        $recentSurveys = $schemaReady ? $this->feedbackRepository->listRecentSurveysPlatform($tenantFilter, 40) : [];

        $screenOptions = Ux::screenOptions(array_merge($aggregates, $recentRatings, $recentSurveys));
        if ($screenFilter !== '') {
            $known = false;
            foreach ($screenOptions as $opt) {
                if ($opt['key'] === $screenFilter) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                $screenFilter = '';
            }
        }

        $ratingsScoped = [];
        foreach ($recentRatings as $row) {
            if (Ux::rowMatchesScreen($row, $screenFilter)) {
                $ratingsScoped[] = $row;
            }
        }
        $surveysScoped = [];
        foreach ($recentSurveys as $row) {
            if (Ux::rowMatchesScreen($row, $screenFilter)) {
                $surveysScoped[] = $row;
            }
        }

        $ratingSum = 0;
        $weakCount = 0;
        foreach ($ratingsScoped as $row) {
            $score = (int) ($row['rating'] ?? 0);
            $ratingSum += $score;
            if (Ux::satisfactionFromScore($score)['key'] === Ux::SAT_WEAK) {
                $weakCount++;
            }
        }
        $ratingCount = count($ratingsScoped);
        $avgRating = $ratingCount > 0 ? round($ratingSum / $ratingCount, 1) : 0.0;

        $ratingsShown = [];
        foreach ($ratingsScoped as $row) {
            if (Ux::matchesSatisfaction((int) ($row['rating'] ?? 0), $satisfactionFilter)) {
                $ratingsShown[] = $row;
            }
        }
        $surveysShown = [];
        foreach ($surveysScoped as $row) {
            if (Ux::matchesSatisfaction(Ux::surveyScore($row), $satisfactionFilter)) {
                $surveysShown[] = $row;
            }
        }

        $showRatings = $typeFilter !== Ux::TYPE_SURVEYS;
        $showSurveys = $typeFilter !== Ux::TYPE_RATINGS;

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
            'title' => 'Retours sur l’interface',
            'content' => 'admin.system.ux_feedback_index',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'uxFeedbackSchemaReady' => $schemaReady,
            'uxPageAggregates' => $aggregates,
            'uxRecentRatings' => $showRatings ? $ratingsShown : [],
            'uxRecentSurveys' => $showSurveys ? $surveysShown : [],
            'uxShowRatings' => $showRatings,
            'uxShowSurveys' => $showSurveys,
            'uxTenantFilter' => $tenantFilter,
            'uxTenantOptions' => $tenantOptions,
            'uxTypeFilter' => $typeFilter,
            'uxSatisfactionFilter' => $satisfactionFilter,
            'uxScreenFilter' => $screenFilter,
            'uxScreenOptions' => $screenOptions,
            'uxStats' => [
                'ratings' => $ratingCount,
                'surveys' => count($surveysScoped),
                'avg' => $avgRating,
                'weak' => $weakCount,
            ],
        ]);
    }
}
