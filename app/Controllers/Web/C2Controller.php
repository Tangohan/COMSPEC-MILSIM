<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class C2Controller
{
    public function index(Request $request, array $params = []): Response
    {
        $modes = [
            [
                'id' => 'atak',
                'label' => 'ATAK',
                'description' => 'Situation tactique et marqueurs partagés.',
                'href' => url('atak'),
            ],
            [
                'id' => 'overwatch',
                'label' => 'Overwatch',
                'description' => 'Situation en carte, urgences médicales et outils de commandement.',
                'href' => url('overwatch'),
            ],
            [
                'id' => 'operateur',
                'label' => 'Opérateur',
                'description' => 'Dossier et outils opérateur.',
                'href' => url('dossier-operateur/accreditation'),
            ],
            [
                'id' => 'terrain',
                'label' => 'Terrain',
                'description' => 'Mode terrain et accès rapides.',
                'href' => url('operateur/terrain'),
            ],
        ];

        return Response::view('layout.main', [
            'title' => 'Poste de commandement',
            'content' => 'portal.c2',
            'c2_modes' => $modes,
        ]);
    }
}
