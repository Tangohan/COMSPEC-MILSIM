<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Gate;
use App\Services\Portal\PortalNextStepsService;

class HubController
{
    public function index(Request $request, array $params = []): Response
    {
        $gate = Gate::getInstance();

        $sections = [];

        $priorityEntries = [
            [
                'label' => 'Centre d’actions',
                'url' => url('centre-actions'),
                'description' => 'Ce qui demande votre attention : notifications, dossiers et files à traiter.',
                'icon' => 'activity',
                'accent' => 'emerald',
                'featured' => true,
            ],
            [
                'label' => 'Tableau de bord',
                'url' => url('dashboard'),
                'description' => 'Synthèse du jour : briefing, manœuvres à venir et raccourcis personnels.',
                'icon' => 'dashboard',
                'accent' => 'emerald',
                'featured' => true,
            ],
            [
                'label' => 'Boîte de réception',
                'url' => url('boite-reception'),
                'description' => 'Messages, activité récente et canaux d’échange.',
                'icon' => 'messages',
                'accent' => 'sky',
            ],
        ];

        $canOpsBoardEdit = $gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
        if ($canOpsBoardEdit) {
            $priorityEntries[] = [
                'label' => 'Pilotage du mur opérationnel',
                'url' => url('back-office/tableau-operationnel'),
                'description' => 'Publier, valider et mettre à jour les informations affichées au mur.',
                'icon' => 'dashboard',
                'accent' => 'amber',
            ];
        } elseif ($gate->allows('operational.board.view')) {
            $priorityEntries[] = [
                'label' => 'Mur opérationnel',
                'url' => url('tableau-operationnel'),
                'description' => 'Permanences, consignes et informations publiées pour l’unité.',
                'icon' => 'dashboard',
                'accent' => 'amber',
            ];
        }

        $priorityEntries[] = [
            'label' => 'Mon activité',
            'url' => url('activite'),
            'description' => 'Suivi des échanges : forum, courrier et notifications récentes.',
            'icon' => 'activity',
            'accent' => 'sky',
        ];

        $sections[] = [
            'id' => 'priorites',
            'title' => 'Priorités du moment',
            'subtitle' => 'Commencez ici : synthèse du jour et éléments à traiter.',
            'entries' => $priorityEntries,
        ];

        $comm = [
            [
                'label' => 'Forum et briefings',
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
                'label' => 'Recherche dans le portail',
                'url' => url('search'),
                'description' => 'Retrouver un membre, un contenu ou une ressource.',
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
            'title' => 'Communication',
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
                'featured' => true,
            ],
            [
                'label' => 'Espace RH et formations',
                'url' => url('personnel/mon-espace-rh'),
                'description' => 'Charte, parcours, ancienneté et programmes de préqualification.',
                'icon' => 'rh_hub',
                'accent' => 'violet',
            ],
            [
                'label' => 'Pointage et présence',
                'url' => url('pointage'),
                'description' => 'Sessions, présence et activité du jour.',
                'icon' => 'pointage',
                'accent' => 'teal',
            ],
        ];
        if ($gate->allows('organization.orbat.view')) {
            $personnelEntries[] = [
                'label' => 'Organigramme des unités',
                'url' => url('orbat'),
                'description' => 'Structure des unités et rattachement des effectifs.',
                'icon' => 'orbat',
                'accent' => 'slate',
            ];
        }
        $sections[] = [
            'id' => 'personnel',
            'title' => 'Personnel et organisation',
            'subtitle' => 'Votre dossier, la présence et la structure des unités.',
            'entries' => $personnelEntries,
        ];

        $terrainEntries = [
            [
                'label' => 'Opérations',
                'url' => url('operations'),
                'description' => 'Dossier de mission : plan, renseignement, ordres et vue terrain.',
                'icon' => 'atak',
                'accent' => 'sky',
                'featured' => true,
            ],
            [
                'label' => 'Carte tactique',
                'url' => url('atak'),
                'description' => 'Carte, marqueurs et outils de coordination sur le terrain.',
                'icon' => 'atak',
                'accent' => 'orange',
            ],
            [
                'label' => 'Équipement',
                'url' => url('equipment'),
                'description' => 'Collections, tenues envoyées depuis l’arsenal, et fiches matériel.',
                'icon' => 'equipment',
                'accent' => 'stone',
            ],
        ];
        if ($gate->allows('intel.transmission.view')) {
            $terrainEntries[] = [
                'label' => 'Transmission de reconnaissance',
                'url' => url('transmission'),
                'description' => 'Comptes-rendus de reconnaissance en fil, synthétisés en Plan d’Exécution (PoE).',
                'icon' => 'atak',
                'accent' => 'emerald',
            ];
        }
        $sections[] = [
            'id' => 'terrain',
            'title' => 'Terrain et matériel',
            'subtitle' => 'Carte tactique et référentiels d’équipement.',
            'entries' => $terrainEntries,
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
                'description' => 'Dépôt, classement et administration des fichiers partagés.',
                'icon' => 'documents_admin',
                'accent' => 'cyan',
            ];
        }
        $sections[] = [
            'id' => 'ressources',
            'title' => 'Documents et formations',
            'subtitle' => 'Références, parcours pédagogiques et fichiers partagés.',
            'entries' => $docsTrain,
        ];

        $adminEntries = [];
        if ($gate->allows('admin.system')) {
            $adminEntries[] = [
                'label' => 'Administration plateforme',
                'url' => url('admin'),
                'description' => 'Paramètres globaux, rôles et maintenance du site.',
                'icon' => 'admin_platform',
                'accent' => 'rose',
                'badge' => 'Plateforme',
            ];
        }
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            $adminEntries[] = [
                'label' => 'Administration de la communauté',
                'url' => url('back-office'),
                'description' => 'Membres, invitations, structure et réglages de votre communauté.',
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
            'title' => 'Centre de commandement',
            'hubSections' => $sections,
            'hub_next_steps' => PortalNextStepsService::forHub($gate),
        ]);
    }
}
