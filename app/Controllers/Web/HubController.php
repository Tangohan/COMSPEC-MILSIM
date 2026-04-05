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

        $canSystem = $gate->allows('admin.system');
        $canOrg = $gate->allows('admin.organization') || $gate->allows('admin.access');
        if ($canSystem) {
            $entries[] = [
                'label' => 'Admin plateforme',
                'url' => url('admin'),
                'description' => 'Super-administration : rôles site, paramètres, audit, maintenance.',
                'badge' => 'Plateforme',
                'letter' => 'A',
            ];
        }
        if ($canOrg) {
            $entries[] = [
                'label' => 'Back-office',
                'url' => url('back-office'),
                'description' => 'Gestion de la communauté : membres, invitations, structure.',
                'badge' => 'Communauté',
                'letter' => 'B',
            ];
        }

        return Response::view('layout.main', [
            'content' => 'hub.index',
            'title' => 'Sélection du hub',
            'entries' => $entries,
        ]);
    }
}
