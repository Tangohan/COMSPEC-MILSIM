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
        'subtitle' => '',
        'tagline' => '',
        'path' => '',
    ],

    'search' => [
        'enabled' => true,
        'shortcut' => true,
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
            'guest_only' => true,
        ],
        [
            'label' => 'Tableau de bord',
            'type' => 'link',
            'path' => 'dashboard',
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
                    'title' => 'Mission & coordination',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Hub', 'path' => 'hub', 'description' => 'Modules, raccourcis et synthèse'],
                        ['label' => 'Pointage', 'path' => 'pointage', 'description' => 'Présences et confirmations aux manœuvres'],
                        ['label' => 'Communautés', 'path' => 'communities', 'description' => 'Registre et accès aux espaces'],
                        ['label' => 'Événements', 'path' => 'evenements', 'description' => 'Calendrier et inscriptions'],
                        ['label' => 'Messagerie interne', 'path' => 'messages', 'description' => 'Échanges avec l’encadrement de votre communauté'],
                    ],
                ],
                [
                    'title' => 'Forum & briefings',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Accueil du forum', 'path' => 'forum', 'permission' => 'forum.view', 'description' => 'Rubriques, sujets et échanges'],
                        ['label' => 'Publier un sujet', 'path' => 'forum/new-topic', 'permission' => 'forum.create_topic', 'description' => 'Démarrer une discussion'],
                    ],
                ],
                [
                    'title' => 'Organisation & situation',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Mur opérationnel', 'path' => 'tableau-operationnel', 'permission' => 'operational.board.view', 'description' => 'Permanences et consignes publiées'],
                        ['label' => 'Pilotage du mur opérationnel', 'path' => 'back-office/tableau-operationnel', 'any_permissions' => ['operational.board.edit', 'admin.organization', 'admin.access', 'admin.system'], 'description' => 'Publication et mise à jour des entrées'],
                        ['label' => 'ORBAT', 'path' => 'orbat', 'description' => 'Structure et effectifs'],
                        ['label' => 'Situation tactique (ATAK)', 'path' => 'atak', 'description' => 'Vue opérationnelle'],
                        ['label' => 'TACMAP', 'path' => 'tacmap', 'description' => 'Cartographie tactique'],
                        ['label' => 'Overwatch', 'path' => 'overwatch', 'description' => 'Supervision'],
                        ['label' => 'Aide terrain', 'path' => 'operateur/terrain', 'description' => 'Mode terrain et accès rapides'],
                    ],
                ],
                [
                    'title' => 'ATAK — prise en main',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Installation et réglages', 'path' => 'atak/setup', 'description' => 'Premiers pas et configuration'],
                        ['label' => 'Tutoriel', 'path' => 'atak/tuto', 'description' => 'Guide pas à pas'],
                        ['label' => 'Télécharger le module', 'path' => 'atak/mod/download', 'description' => 'Fichier pour l’application'],
                    ],
                ],
                [
                    'title' => 'Dossier & logistique',
                    'slot' => 'secondary',
                    'links' => [
                        ['label' => 'Dossier opérateur', 'path' => 'dossier-operateur/accreditation', 'description' => 'Accréditation et pièces'],
                        ['label' => 'Équipement', 'path' => 'equipment', 'description' => 'Manuels et fiches matériel'],
                        ['label' => 'Modpacks', 'path' => 'modpacks', 'description' => 'Mods et paquets de la communauté'],
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
                    'title' => 'Portail',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Accueil public', 'path' => '', 'description' => 'Page d’entrée du site'],
                    ],
                ],
                [
                    'title' => 'Documents',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Bibliothèque', 'path' => 'documents', 'permission' => 'documents.view', 'description' => 'Consulter les documents publiés'],
                        ['label' => 'Gestion documentaire', 'path' => 'documents/gestion', 'permission' => 'documents.upload', 'description' => 'Ajouter, classer et suivre les versions'],
                    ],
                ],
                [
                    'title' => 'Correspondance officielle',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Tableau du courrier', 'path' => 'courrier', 'permission' => 'courrier.view', 'description' => 'Brouillons, validations et suivi'],
                        ['label' => 'Rédiger un courrier', 'path' => 'courrier/editor', 'permission' => 'courrier.create', 'description' => 'Création et édition'],
                        ['label' => 'Modèles', 'path' => 'courrier/templates', 'any_permissions' => ['courrier.create', 'courrier.validate'], 'description' => 'Gabarits réutilisables'],
                        ['label' => 'Historique', 'path' => 'courrier/history', 'permission' => 'courrier.view', 'description' => 'Activité récente'],
                        ['label' => 'Archives', 'path' => 'courrier/archives', 'any_permissions' => ['courrier.view', 'courrier.archive'], 'description' => 'Courriers classés'],
                    ],
                ],
                [
                    'title' => 'Aide & recherche',
                    'slot' => 'secondary',
                    'links' => [
                        ['label' => 'Guide du portail', 'path' => 'documentation', 'description' => 'Mode d’emploi intégré au site'],
                        ['label' => 'Coopérations inter-unités (guide)', 'path' => 'documentation#cooperations-inter-unites', 'description' => 'Cycle de vie et espace commun entre communautés'],
                        ['label' => 'Références équipe', 'path' => 'documentation/references', 'description' => 'Notes et sources projet'],
                        ['label' => 'Recherche', 'path' => 'search', 'description' => 'Parcourir le portail (raccourci clavier selon configuration)'],
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
                        ['label' => 'Mon activité', 'path' => 'activite', 'description' => 'Fil personnel, rappels et raccourcis'],
                        ['label' => 'Ma fiche personnelle', 'path' => 'personnel/me'],
                        ['label' => 'Guides dossier & préréglages', 'path' => 'personnel/tutorials', 'description' => 'ORBAT, forum, complétude, préréglages métier'],
                    ],
                ],
                [
                    'title' => 'Effectifs & annuaire',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Annuaire des profils', 'path' => 'personnel', 'description' => 'Rechercher un membre'],
                        ['label' => 'Organisation (ORBAT)', 'path' => 'orbat', 'description' => 'Vue hiérarchique des effectifs'],
                    ],
                ],
                [
                    'title' => 'Compte',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Paramètres du compte', 'path' => 'account', 'description' => 'Identité, sécurité et médias'],
                        ['label' => 'Préférences', 'path' => 'account/preferences', 'description' => 'Langue, fuseau et affichage'],
                    ],
                ],
                [
                    'title' => 'Structure (administrateurs)',
                    'slot' => 'secondary',
                    'links' => [
                        ['label' => 'Organisation des effectifs', 'path' => 'back-office/organisation-effectifs', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Vue d’ensemble : rôles communauté, grades, structure, rôles métier.'],
                        ['label' => 'Rôles et droits (communauté)', 'path' => 'back-office/roles', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Grades', 'path' => 'back-office/referentiels/grades', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Rôles métier (référentiel)', 'path' => 'back-office/personnel-job-roles', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Attributions rôles métier', 'path' => 'back-office/personnel-job-roles/assignments', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Unités et regroupements', 'path' => 'back-office/groups', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Équipes transverses', 'path' => 'back-office/teams', 'any_permissions' => ['admin.organization', 'admin.access']],
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
            'badge' => 'Nouveau',
            'type' => 'mega',
            'auth_only' => true,
            'icon' => 'academic',
            'accent' => 'violet',
            'variant' => 'training',
            'featured' => [
                'eyebrow' => 'Parcours',
                'title' => 'Académie Athena',
                'description' => 'Catalogue, parcours, suivi et pilotage de la formation au sein de la communauté.',
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
                        ['label' => 'Compétences', 'path' => 'formations/competences', 'permission' => 'training.view', 'description' => 'Progression transversale et jalons'],
                        ['label' => 'Code d’accès', 'path' => 'formations/code-acces', 'permission' => 'training.view', 'description' => 'Débloquer un parcours sur invitation'],
                    ],
                ],
                [
                    'title' => 'Pilotage',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Pilotage des formations', 'path' => 'back-office/ressources/training', 'any_permissions' => ['admin.access', 'training.manage', 'training.assign', 'training.create', 'training.update', 'training.publish', 'training.delete'], 'description' => 'Tableau de bord LMS de la communauté'],
                        ['label' => 'Studio LMS', 'path' => 'back-office/ressources/training/studio', 'any_permissions' => ['admin.access', 'training.manage', 'training.assign', 'training.create', 'training.update', 'training.publish', 'training.delete'], 'description' => 'Édition des parcours'],
                        ['label' => 'Pilotage des compétences', 'path' => 'back-office/ressources/training/competences/commandement', 'any_permissions' => ['training.manage', 'training.assign'], 'description' => 'Vue encadrement'],
                        ['label' => 'Validation instructeur', 'path' => 'back-office/ressources/training/competences/instructeur', 'any_permissions' => ['training.manage', 'training.assign', 'training.submissions.grade', 'training.results.view'], 'description' => 'Évaluations à traiter'],
                    ],
                ],
                [
                    'title' => 'Documentation',
                    'slot' => 'secondary',
                    'links' => [
                        ['label' => 'Guide du portail', 'path' => 'documentation', 'description' => 'Documentation utilisateur intégrée au site'],
                        ['label' => 'Références projet (équipe)', 'path' => 'documentation/references', 'description' => 'Inventaires techniques et fiches sources'],
                        ['label' => 'Rubrique Formations (guide)', 'path' => 'documentation#formations', 'description' => 'Section du guide utilisateur'],
                        ['label' => 'Navigation & dashboard (guide)', 'path' => 'documentation#navigation-et-recherche', 'description' => 'Sommaire du guide intégré'],
                        ['label' => 'Coopérations inter-unités (guide)', 'path' => 'documentation#cooperations-inter-unites', 'description' => 'Cycle de vie, espace commun et autorisations entre communautés'],
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
            'any_permissions' => ['admin.system', 'admin.organization', 'admin.access', 'forum.moderate', 'forum.moderate_organization', 'invitations.send', 'interteam.missions.manage', 'interteam.missions.respond', 'cooperation.missions.view', 'cooperation.missions.manage', 'cooperation.missions.create', 'cooperation.missions.respond'],
            'icon' => 'shield',
            'accent' => 'rose',
            'variant' => 'admin',
            'featured' => [
                'eyebrow' => 'Deux périmètres',
                'title' => 'Plateforme et communauté',
                'description' => 'Certains réglages concernent toute la plateforme (équipe d’infrastructure), d’autres votre organisation active. Forum, formations et courrier restent toujours cadrés par la communauté sélectionnée.',
                'image' => 'assets/img/nav/admin.jpg',
                'image_enabled' => true,
                'image_position' => 'center',
                'overlay' => 'dark',
                'cta_label' => 'Administration plateforme',
                'cta_path' => 'admin',
                'cta_permission' => 'admin.system',
                'cta_intent' => 'administer',
            ],
            'sections' => [
                [
                    'title' => 'Plateforme (site entier)',
                    'slot' => 'primary',
                    'links' => [
                        ['label' => 'Tableau de bord plateforme', 'path' => 'admin', 'permission' => 'admin.system'],
                        ['label' => 'Synthèse opérationnelle', 'path' => 'admin/ops-center', 'permission' => 'admin.system', 'description' => 'Signaux critiques et vue transversale'],
                        ['label' => 'Rôles système', 'path' => 'admin/roles', 'permission' => 'admin.system'],
                        ['label' => 'Rôles site (affectations)', 'path' => 'admin/site-roles', 'permission' => 'admin.system'],
                        ['label' => 'Paramètres système', 'path' => 'admin/settings', 'permission' => 'admin.system'],
                        ['label' => 'Alertes plateforme', 'path' => 'admin/system/alerts', 'permission' => 'admin.system'],
                        ['label' => 'Maintenance BDD', 'path' => 'admin/maintenance', 'permission' => 'admin.system'],
                        ['label' => 'Journaux / audit', 'path' => 'admin/audit', 'permission' => 'admin.system'],
                    ],
                ],
                [
                    'title' => 'Modules métier (communauté active)',
                    'slot' => 'secondary',
                    'links' => [
                        ['label' => 'Modpacks', 'path' => 'back-office/ressources/modpacks', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Configuration ATAK', 'path' => 'back-office/ressources/atak-config', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Mod ATAK', 'path' => 'back-office/ressources/atak-mod', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Configuration forum', 'path' => 'back-office/ressources/forum-config', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access']],
                        ['label' => 'Formations (LMS)', 'path' => 'back-office/ressources/training', 'any_permissions' => ['admin.access', 'training.manage', 'training.assign', 'training.create', 'training.update', 'training.publish', 'training.delete']],
                        ['label' => 'Gestion documentaire', 'path' => 'documents/gestion', 'any_permissions' => ['admin.system', 'documents.upload', 'admin.access']],
                        ['label' => 'Coopérations inter-unités', 'path' => 'back-office/cooperation/missions', 'any_permissions' => ['admin.system', 'admin.organization', 'admin.access', 'interteam.missions.manage', 'interteam.missions.respond', 'cooperation.missions.view', 'cooperation.missions.manage', 'cooperation.missions.respond']],
                    ],
                ],
                [
                    'title' => 'Organisation (back-office)',
                    'slot' => 'center',
                    'links' => [
                        ['label' => 'Back-office communauté', 'path' => 'back-office', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Centre opérationnel', 'path' => 'back-office/centre-operations', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Synthèse des leviers d’administration'],
                        ['label' => 'Mur opérationnel', 'path' => 'tableau-operationnel', 'permission' => 'operational.board.view', 'description' => 'Permanences et consignes publiées'],
                        ['label' => 'Pilotage du mur opérationnel', 'path' => 'back-office/tableau-operationnel', 'any_permissions' => ['operational.board.edit', 'admin.organization', 'admin.access', 'admin.system'], 'description' => 'Publication et mise à jour des entrées'],
                        ['label' => 'E-mails aux membres', 'path' => 'back-office/communications', 'any_permissions' => ['comms.email.send.orbat', 'comms.email.send.mission', 'comms.email.send.activity', 'comms.email.send.custom', 'comms.email.broadcast', 'comms.email_templates.manage', 'comms.notifications.history.view'], 'description' => 'Diffusions, modèles et groupes de destinataires'],
                        ['label' => 'Paramètres de la communauté', 'path' => 'back-office/community', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Identité, modules et options'],
                        ['label' => 'Vitrine publique', 'path' => 'back-office/community/presentation', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Page publique de présentation'],
                        ['label' => 'Événements (gestion)', 'path' => 'back-office/events', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Dossiers de recrutement', 'path' => 'back-office/recruitments', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Rubriques du forum', 'path' => 'back-office/categories', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Arborescence des catégories et sous-rubriques'],
                        ['label' => 'Invitations', 'path' => 'back-office/invitations', 'any_permissions' => ['admin.organization', 'admin.access', 'invitations.send']],
                        ['label' => 'Utilisateurs', 'path' => 'back-office/users', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Organisation des effectifs', 'path' => 'back-office/organisation-effectifs', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Rôles et droits (communauté)', 'path' => 'back-office/roles', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Raccourcis dashboard', 'path' => 'back-office/dashboard-pins', 'any_permissions' => ['dashboard.pins.manage']],
                        ['label' => 'Profils permissions (rôles)', 'path' => 'back-office/roles/presets', 'any_permissions' => ['admin.organization', 'admin.roles.manage', 'admin.permissions.manage']],
                        ['label' => 'Rôles & fonctions (toile)', 'path' => 'back-office/roles-functions', 'any_permissions' => ['admin.organization', 'admin.roles.manage', 'admin.permissions.manage']],
                        ['label' => 'Modération forum', 'path' => 'back-office/forum-moderation', 'any_permissions' => ['forum.moderate', 'forum.moderate_organization', 'forum.topic.pin', 'forum.reports.manage', 'forum.post.edit_any', 'admin.organization', 'admin.access']],
                        ['label' => 'Modération fichiers', 'path' => 'admin/content-moderation', 'any_permissions' => ['forum.moderate', 'forum.moderate_organization', 'forum.topic.pin', 'forum.reports.manage', 'admin.organization', 'admin.access']],
                        ['label' => 'Restrictions membres (organisation)', 'path' => 'back-office/moderation', 'any_permissions' => ['admin.members.moderate'], 'description' => 'Limitations d’activité dans la communauté (formations, documents, etc.)'],
                        ['label' => 'Export conformité', 'path' => 'back-office/conformite/export-dossier', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Dossier réglementaire pour audit'],
                        ['label' => 'Intégrations externes', 'path' => 'back-office/integrations', 'any_permissions' => ['admin.organization', 'admin.access'], 'description' => 'Clés d’accès et services connectés'],
                        ['label' => 'Indicateurs d’activité', 'path' => 'back-office/analytics', 'any_permissions' => ['admin.organization', 'admin.access']],
                        ['label' => 'Configuration générale', 'path' => 'back-office/configuration', 'any_permissions' => ['admin.organization', 'admin.access']],
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
