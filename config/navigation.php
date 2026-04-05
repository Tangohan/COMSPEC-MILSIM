<?php

declare(strict_types=1);

/**
 *  portail Athena — chemins relatifs passés à url() (sans domaine ni préfixe /public).
 *
 * Permissions : voir Gate / header actuel (documents.view, courrier.view, admin.*, etc.).
 *
 * Méga-menus : accent (sky|amber|emerald|violet|rose|slate), variant (layout), sections.slot (primary|center|secondary),
 * live (placeholders ou futurs flux), featured.cta_intent (open|monitor|resume|administer|view_activity|access).
 */
return [
    'brand' => [
        'name' => 'Athena',
        'subtitle' => 'Système de conduite communautaire',
        'tagline' => '',
        'path' => '',
    ],

    'search' => [
        'enabled' => true,
        'placeholder' => 'Rechercher un module, un document, un personnel…',
        'path' => 'search',
        'method' => 'get',
        'param' => 'q',
    ],

    'menu' => [
        [
            'label' => 'Accueil',
            'type' => 'link',
            'path' => '',
            'guest_only' => false,
            'auth_only' => false,
        ],
        [
            'label' => 'Dashboard',
            'type' => 'link',
            'path' => 'dashboard',
            'auth_only' => true,
        ],
        [
            'label' => 'Pointage',
            'type' => 'link',
            'path' => 'pointage',
            'auth_only' => true,
        ],
        [
            'label' => 'Opérations',
            'type' => 'mega',
            'auth_only' => true,
            'icon' => 'crosshair',
            'accent' => 'sky',
            'variant' => 'operations',
            'featured' => [
                'eyebrow' => 'Mission',
                'title' => 'Centre opérationnel',
                'description' => 'Pilotage des modules tactiques, coordination et accès mission.',
                'image' => 'assets/img/nav/operations.jpg',
                'image_enabled' => true,
                'image_position' => 'center',
                'overlay' => 'dark',
                'cta_label' => 'Ouvrir le centre',
                'cta_path' => 'hub',
                'cta_intent' => 'open',
            ],
            'sections' => [
                [
                    'title' => 'Accès immédiats',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Hub', 'path' => 'hub'],
                        ['label' => 'Pointage', 'path' => 'pointage'],
                        ['label' => 'Briefing', 'path' => 'forum'],
                        ['label' => 'ORBAT', 'path' => 'orbat'],
                        ['label' => 'ATAK', 'path' => 'atak'],
                    ],
                ],
                [
                    'title' => 'Situation & veille',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Derniers briefings', 'path' => 'forum'],
                        ['label' => 'Situation tactique', 'path' => 'atak'],
                    ],
                ],
            ],
            'live' => [
                [
                    'id' => 'ops_briefing',
                    'type' => 'forum_recent',
                    'enabled' => true,
                    'title' => 'Dernier briefing publié',
                    'empty_message' => 'Aucun briefing récent.',
                ],
                [
                    'id' => 'ops_mission',
                    'type' => 'mission_active',
                    'enabled' => true,
                    'title' => 'Mission active',
                    'empty_message' => 'Aucune mission en cours signalée.',
                ],
            ],
        ],
        [
            'label' => 'Ressources',
            'type' => 'mega',
            'auth_only' => true,
            'any_permissions' => ['documents.view', 'documents.upload', 'courrier.view', 'admin.access'],
            'icon' => 'folder',
            'accent' => 'amber',
            'variant' => 'resources',
            'featured' => [
                'eyebrow' => 'Documentaire',
                'title' => 'Pôle documentaire',
                'description' => 'Centralisation des documents, flux et traitements administratifs.',
                'image' => 'assets/img/nav/resources.jpg',
                'image_enabled' => true,
                'image_position' => 'center',
                'overlay' => 'dark',
                'cta_label' => 'Consulter les documents',
                'cta_path' => 'documents',
                'cta_permission' => 'documents.view',
                'cta_intent' => 'access',
            ],
            'sections' => [
                [
                    'title' => 'Documents',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Documents', 'path' => 'documents', 'permission' => 'documents.view'],
                        ['label' => 'Gestion documents', 'path' => 'documents/gestion', 'permission' => 'documents.upload'],
                    ],
                ],
                [
                    'title' => 'Flux & procédures',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Bureau courrier', 'path' => 'courrier', 'any_permissions' => ['courrier.view', 'admin.access']],
                    ],
                ],
            ],
            'live' => [
                [
                    'id' => 'res_stats',
                    'type' => 'document_stats',
                    'enabled' => true,
                    'title' => 'Synthèse documentaire',
                    'empty_message' => 'Statistiques disponibles prochainement.',
                ],
            ],
        ],
        [
            'label' => 'Personnel',
            'type' => 'mega',
            'auth_only' => true,
            'icon' => 'users',
            'accent' => 'emerald',
            'variant' => 'personnel',
            'featured' => [
                'eyebrow' => 'Effectifs',
                'title' => 'Espace personnel',
                'description' => 'Fiches, organisation, grades et structure des effectifs.',
                'image' => 'assets/img/nav/personnel.jpg',
                'image_enabled' => true,
                'image_position' => 'center',
                'overlay' => 'dark',
                'cta_label' => 'Accéder à ma fiche',
                'cta_path' => 'personnel/me',
                'cta_intent' => 'access',
            ],
            'sections' => [
                [
                    'title' => 'Ma situation',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Ma fiche', 'path' => 'personnel/me'],
                        ['label' => 'Annuaire (ORBAT)', 'path' => 'orbat', 'description' => 'Vue d’organisation et des effectifs'],
                    ],
                ],
                [
                    'title' => 'Structure',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Grades', 'path' => 'back-office/referentiels/grades', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Unités', 'path' => 'back-office/groups', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Équipes', 'path' => 'back-office/teams', 'any_permissions' => ['admin.organization', 'admin.access']],
                    ],
                ],
            ],
            'live' => [
                [
                    'id' => 'pers_roster',
                    'type' => 'recent_members',
                    'enabled' => true,
                    'title' => 'Mouvements récents',
                    'empty_message' => 'Aucune mutation récente.',
                ],
            ],
        ],
        [
            'label' => 'Formation',
            'type' => 'mega',
            'auth_only' => true,
            'icon' => 'academic',
            'accent' => 'violet',
            'variant' => 'training',
            'featured' => [
                'eyebrow' => 'Parcours',
                'title' => 'Académie Athena',
                'description' => 'Catalogue, parcours, suivi et administration de la formation.',
                'image' => 'assets/img/nav/training.jpg',
                'image_enabled' => true,
                'image_position' => 'center',
                'overlay' => 'dark',
                'cta_label' => 'Reprendre la formation',
                'cta_path' => 'formations',
                'cta_intent' => 'resume',
            ],
            'sections' => [
                [
                    'title' => 'Catalogue & parcours',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Catalogue', 'path' => 'formations'],
                        ['label' => 'Mes parcours', 'path' => 'formations/mes-formations'],
                    ],
                ],
                [
                    'title' => 'Pilotage',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Administration formation', 'path' => 'admin/training', 'any_permissions' => ['training.manage', 'training.assign', 'training.create', 'training.update', 'training.publish']],
                    ],
                ],
            ],
            'live' => [
                [
                    'id' => 'train_progress',
                    'type' => 'user_progress',
                    'enabled' => true,
                    'title' => 'Progression',
                    'empty_message' => 'Aucune progression enregistrée pour l’instant.',
                ],
            ],
        ],
        [
            'label' => 'Administration',
            'type' => 'mega',
            'auth_only' => true,
            'any_permissions' => ['admin.system', 'admin.organization', 'admin.access', 'forum.moderate', 'forum.moderate_organization'],
            'icon' => 'shield',
            'accent' => 'rose',
            'variant' => 'admin',
            'featured' => [
                'eyebrow' => 'Supervision',
                'title' => 'Centre de contrôle',
                'description' => 'Supervision, paramétrage, audit et administration des modules.',
                'image' => 'assets/img/nav/admin.jpg',
                'image_enabled' => true,
                'image_position' => 'center',
                'overlay' => 'dark',
                'cta_label' => 'Administrer la plateforme',
                'cta_path' => 'admin',
                'cta_permission' => 'admin.system',
                'cta_intent' => 'administer',
            ],
            'sections' => [
                [
                    'title' => 'Plateforme',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Dashboard admin', 'path' => 'admin', 'permission' => 'admin.system'],
                        ['label' => 'Paramètres système', 'path' => 'admin/settings', 'permission' => 'admin.system'],
                        ['label' => 'Alertes plateforme', 'path' => 'admin/system/alerts', 'permission' => 'admin.system'],
                        ['label' => 'Maintenance BDD', 'path' => 'admin/maintenance', 'permission' => 'admin.system'],
                        ['label' => 'Journaux / audit', 'path' => 'admin/audit', 'permission' => 'admin.system'],
                    ],
                ],
                [
                    'title' => 'Modules techniques (communauté courante)',
                    'slot' => 'secondary',
                    'links' => [
                        ['label' => 'Modpacks', 'path' => 'admin/modpacks', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Configuration ATAK', 'path' => 'admin/atak-config', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Mod ATAK', 'path' => 'admin/atak-mod', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Configuration forum', 'path' => 'admin/forum-config', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Formations (LMS)', 'path' => 'admin/training', 'any_permissions' => ['admin.system', 'training.manage', 'training.assign', 'training.create', 'training.update', 'training.publish', 'admin.access']],
                        ['label' => 'Gestion documentaire', 'path' => 'documents/gestion', 'any_permissions' => ['admin.system', 'documents.upload', 'admin.access']],
                    ],
                ],
                [
                    'title' => 'Organisation & sécurité',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Back-office', 'path' => 'back-office', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Modération forum', 'path' => 'back-office/forum-moderation', 'any_permissions' => ['forum.moderate', 'forum.moderate_organization', 'forum.topic.pin', 'forum.reports.manage', 'forum.post.edit_any', 'admin.organization', 'admin.access']],
                        ['label' => 'Modération fichiers', 'path' => 'back-office/content-moderation', 'any_permissions' => ['forum.moderate', 'forum.moderate_organization', 'forum.topic.pin', 'forum.reports.manage', 'admin.organization', 'admin.access']],
                        ['label' => 'Audit organisation', 'path' => 'back-office/audit', 'any_permissions' => ['admin.organization', 'admin.access']],
                    ],
                ],
            ],
            'live' => [
                [
                    'id' => 'adm_alerts',
                    'type' => 'security_digest',
                    'enabled' => true,
                    'title' => 'Alertes & état',
                    'empty_message' => 'Aucun incident critique signalé.',
                ],
            ],
        ],
    ],
];
