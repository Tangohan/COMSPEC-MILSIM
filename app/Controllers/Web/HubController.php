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

        $sections = [];

        $sections[] = [
            'id' => 'vue-ensemble',
            'title' => 'Vue d’ensemble',
            'subtitle' => 'Repères du jour et accès au tableau de bord principal.',
            'entries' => [
                [
                    'label' => 'Tableau de bord',
                    'url' => url('dashboard'),
                    'description' => 'Synthèse personnelle, raccourcis et actualités de votre espace.',
                    'icon' => 'dashboard',
                    'accent' => 'emerald',
                    'featured' => true,
                ],
                [
                    'label' => 'Mon activité',
                    'url' => url('activite'),
                    'description' => 'Suivi des échanges : forum, courrier et notifications récentes.',
                    'icon' => 'activity',
                    'accent' => 'sky',
                ],
            ],
        ];
        $canOpsBoardEdit = $gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
        if ($canOpsBoardEdit) {
            $sections[0]['entries'][] = [
                'label' => 'Pilotage du mur opérationnel',
                'url' => url('back-office/tableau-operationnel'),
                'description' => 'Publication, validation et mise à jour des entrées affichées au mur.',
                'icon' => 'dashboard',
                'accent' => 'amber',
            ];
        } elseif ($gate->allows('operational.board.view')) {
            $sections[0]['entries'][] = [
                'label' => 'Mur opérationnel',
                'url' => url('tableau-operationnel'),
                'description' => 'Permanences, consignes et informations publiées pour l’unité.',
                'icon' => 'dashboard',
                'accent' => 'amber',
            ];
        }

        $comm = [
            [
                'label' => 'Briefing & forum',
                'url' => url('forum'),
                'description' => 'Annonces, fils de discussion et briefings de la communauté.',
                'icon' => 'forum',
                'accent' => 'violet',
            ],
            [
                'label' => 'Messagerie',
                'url' => url('messages'),
                'description' => 'Messages officiels adressés à votre communauté.',
                'icon' => 'messages',
                'accent' => 'indigo',
            ],
            [
                'label' => 'Recherche',
                'url' => url('search'),
                'description' => 'Trouver un membre, un contenu ou une ressource sur le portail.',
                'icon' => 'search',
                'accent' => 'slate',
            ],
        ];
        if ($gate->allows('courrier.view')) {
            array_splice($comm, 1, 0, [[
                'label' => 'Bureau courrier',
                'url' => url('courrier'),
                'description' => 'Documents officiels, circuits de validation et signatures.',
                'icon' => 'courrier',
                'accent' => 'amber',
            ]]);
        }
        $sections[] = [
            'id' => 'communication',
            'title' => 'Communication & informations',
            'subtitle' => 'Échanger, s’informer et retrouver les canaux utiles.',
            'entries' => $comm,
        ];

        $personnelEntries = [
            [
                'label' => 'Ma fiche personnelle',
                'url' => url('personnel/me'),
                'description' => 'Identité opérationnelle, qualifications et formations.',
                'icon' => 'personnel',
                'accent' => 'emerald',
            ],
            [
                'label' => 'Pointage & présence',
                'url' => url('pointage'),
                'description' => 'Sessions, présence et activité du jour.',
                'icon' => 'pointage',
                'accent' => 'teal',
            ],
        ];
        if ($gate->allows('organization.orbat.view')) {
            $personnelEntries[] = [
                'label' => 'ORBAT',
                'url' => url('orbat'),
                'description' => 'Organigramme et rattachement aux unités.',
                'icon' => 'orbat',
                'accent' => 'slate',
            ];
        }
        $sections[] = [
            'id' => 'personnel',
            'title' => 'Personnel & organisation',
            'subtitle' => 'Votre dossier, la présence et la structure des unités.',
            'entries' => $personnelEntries,
        ];

        $terrain = [
            [
                'label' => 'ATAK / carte tactique',
                'url' => url('atak'),
                'description' => 'Carte, marqueurs et outils de coordination.',
                'icon' => 'atak',
                'accent' => 'orange',
            ],
            [
                'label' => 'Équipement',
                'url' => url('equipment'),
                'description' => 'Classes d’équipement et fiches matériel.',
                'icon' => 'equipment',
                'accent' => 'stone',
            ],
        ];
        $sections[] = [
            'id' => 'terrain',
            'title' => 'Terrain & matériel',
            'subtitle' => 'Outils tactiques et référentiels matériels.',
            'entries' => $terrain,
        ];

        $docsTrain = [
            [
                'label' => 'Formations',
                'url' => url('formations'),
                'description' => 'Catalogue des parcours et suivi de progression.',
                'icon' => 'training',
                'accent' => 'emerald',
            ],
        ];
        if ($gate->allows('documents.view')) {
            $docsTrain[] = [
                'label' => 'Documents',
                'url' => url('documents'),
                'description' => 'Consultation des documents publiés pour la communauté.',
                'icon' => 'documents',
                'accent' => 'blue',
            ];
        }
        if ($gate->allows('documents.upload')) {
            $docsTrain[] = [
                'label' => 'Gestion documentaire',
                'url' => url('documents/gestion'),
                'description' => 'Dépôt, classement et administration des fichiers.',
                'icon' => 'documents_admin',
                'accent' => 'cyan',
            ];
        }
        $sections[] = [
            'id' => 'ressources',
            'title' => 'Documents & formations',
            'subtitle' => 'Références, parcours pédagogiques et fichiers partagés.',
            'entries' => $docsTrain,
        ];

        $adminEntries = [];
        if ($gate->allows('admin.system')) {
            $adminEntries[] = [
                'label' => 'Administration plateforme',
                'url' => url('admin'),
                'description' => 'Paramètres globaux, rôles site et maintenance.',
                'icon' => 'admin_platform',
                'accent' => 'rose',
                'badge' => 'Plateforme',
            ];
        }
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            $adminEntries[] = [
                'label' => 'Back-office communauté',
                'url' => url('back-office'),
                'description' => 'Membres, invitations, structure et réglages de la communauté.',
                'icon' => 'admin_org',
                'accent' => 'purple',
                'badge' => 'Communauté',
            ];
        }
        if ($adminEntries !== []) {
            $sections[] = [
                'id' => 'administration',
                'title' => 'Administration',
                'subtitle' => 'Réservé aux rôles autorisés.',
                'entries' => $adminEntries,
            ];
        }

        return Response::view('layout.main', [
            'content' => 'hub.index',
            'title' => 'Centre opérationnel',
            'hubSections' => $sections,
        ]);
    }
}
