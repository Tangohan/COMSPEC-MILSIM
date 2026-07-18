<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AssistantController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Assistant',
            'content' => 'portal.assistant',
        ]);
    }

    public function ask(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return Response::json([
                'success' => false,
                'error' => 'Vous devez être connecté pour utiliser l’assistant.',
            ], 401);
        }

        $question = trim((string) $request->input('question', $request->input('q', '')));
        if ($question === '') {
            return Response::json([
                'success' => false,
                'error' => 'Posez une question pour obtenir une réponse.',
            ], 422);
        }

        if (mb_strlen($question) > 500) {
            $question = mb_substr($question, 0, 500);
        }

        return Response::json([
            'success' => true,
            'answer' => 'L’assistant est en cours de déploiement pour votre communauté. '
                . 'En attendant, utilisez la recherche du portail ou le guide intégré pour trouver documents, sujets et modules.',
            'suggestions' => [
                ['label' => 'Ouvrir la recherche', 'href' => url('search')],
                ['label' => 'Consulter le guide', 'href' => url('documentation')],
                ['label' => 'Centre de commandement', 'href' => url('hub')],
            ],
            'scoped' => true,
        ]);
    }
}
