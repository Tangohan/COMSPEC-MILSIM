<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Training\CompetencyUserJourneyService;

final class TrainingCompetencyController
{
    public function __construct(
        private CompetencyUserJourneyService $competencyUserJourneyService,
    ) {}

    public function commandCenter(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Commandement — Compétences',
            'content' => 'admin.training.competency_command',
            'trainingAdminNav' => 'dashboard',
        ]);
    }

    public function instructorCenter(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Instructeur — Validation',
            'content' => 'admin.training.competency_instructor',
            'trainingAdminNav' => 'dashboard',
        ]);
    }

    public function userJourney(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $competencyJourney = $this->competencyUserJourneyService->buildForUser($tenantId, $userId);

        return Response::view('layout.main', [
            'title' => 'Mon parcours compétences',
            'content' => 'training.competency_journey',
            'competencyJourney' => $competencyJourney,
        ]);
    }
}
