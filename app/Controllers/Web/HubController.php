<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Gate;

class HubController
{
    public function index(Request $request, array $params = []): Response
    {
        $gate = Gate::getInstance();
        $entries = [];

        $entries[] = [
            'label' => 'Dashboard',
            'url' => url('dashboard'),
            'description' => 'Vue d\'ensemble, modpack et accès rapides.',
            'badge' => null,
            'letter' => 'D',
        ];

        $entries[] = [
            'label' => 'Briefing',
            'url' => url('forum'),
            'description' => 'Forum, annonces et briefings opérationnels.',
            'badge' => null,
            'letter' => 'B',
        ];

        $entries[] = [
            'label' => 'Fiche',
            'url' => url('personnel/me'),
            'description' => 'Ma fiche personnel et profil opérateur.',
            'badge' => null,
            'letter' => 'F',
        ];

        $entries[] = [
            'label' => 'ORBAT',
            'url' => url('orbat'),
            'description' => 'Organisation et structure des unités.',
            'badge' => null,
            'letter' => 'O',
        ];

        $entries[] = [
            'label' => 'ATAK / TACMAP',
            'url' => url('atak'),
            'description' => 'Carte tactique, marqueurs et outils C2.',
            'badge' => null,
            'letter' => 'A',
        ];

        if ($gate->allows('documents.view')) {
            $entries[] = [
                'label' => 'Documents',
                'url' => url('documents'),
                'description' => 'Consultation des documents et fiches.',
                'badge' => null,
                'letter' => 'D',
            ];
        }

        if ($gate->allows('documents.upload')) {
            $entries[] = [
                'label' => 'Gestion documents',
                'url' => url('documents/gestion'),
                'description' => 'Upload, arborescence et gestion documentaire.',
                'badge' => null,
                'letter' => 'G',
            ];
        }

        $entries[] = [
            'label' => 'Formations',
            'url' => url('formations'),
            'description' => 'Catalogue et suivi des formations.',
            'badge' => null,
            'letter' => 'F',
        ];

        if ($gate->allows('admin.access') || $gate->allows('admin.system') || $gate->allows('admin.organization')) {
            $entries[] = [
                'label' => 'Administration',
                'url' => url('admin'),
                'description' => 'Centre d\'administration système et organisation.',
                'badge' => 'Administration',
                'letter' => 'A',
            ];
        }

        return Response::view('layout.main', [
            'content' => 'hub.index',
            'title' => 'Sélection du hub',
            'entries' => $entries,
        ]);
    }
}
