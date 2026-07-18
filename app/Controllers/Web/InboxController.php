<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class InboxController
{
    public function index(Request $request, array $params = []): Response
    {
        $sections = [
            [
                'title' => 'À traiter',
                'description' => 'Éléments qui appellent une action de votre part.',
                'links' => [
                    [
                        'label' => 'Centre d’actions',
                        'href' => url('centre-actions'),
                        'hint' => 'Synthèse des dossiers et files à suivre',
                    ],
                ],
            ],
            [
                'title' => 'Échanges',
                'description' => 'Messages et activité récente dans votre communauté.',
                'links' => [
                    [
                        'label' => 'Messagerie interne',
                        'href' => url('messages'),
                        'hint' => 'Conversations avec l’encadrement',
                    ],
                    [
                        'label' => 'Mon activité',
                        'href' => url('activite'),
                        'hint' => 'Forum, courrier et notifications',
                    ],
                ],
            ],
        ];

        return Response::view('layout.main', [
            'title' => 'Boîte de réception',
            'content' => 'portal.inbox',
            'inbox_sections' => $sections,
        ]);
    }
}
