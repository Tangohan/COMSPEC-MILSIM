<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class TrainingCompetencyController
{
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
        return Response::view('layout.main', [
            'title' => 'Mon parcours compétences',
            'content' => 'training.competency_journey',
        ]);
    }
}
