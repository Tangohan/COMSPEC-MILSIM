<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class TrainingWizardController
{
    public function index(Request $request, array $params = []): Response
    {
        $step = max(1, min(4, (int) $request->query('etape', '1')));

        return Response::view('layout.main', [
            'title' => 'Créer un entraînement',
            'content' => 'portal.training_wizard',
            'wizardStep' => $step,
            'steps' => [
                ['label' => 'Scénario', 'active' => $step === 1, 'done' => $step > 1],
                ['label' => 'Modules', 'active' => $step === 2, 'done' => $step > 2],
                ['label' => 'Évaluation', 'active' => $step === 3, 'done' => $step > 3],
                ['label' => 'Publier', 'active' => $step === 4, 'done' => false],
            ],
        ]);
    }
}
