<?php

declare(strict_types=1);

/**
 * Menu latéral du back-office : pastilles, pages nouvelles et favoris. Un seul fichier à toucher.
 *
 * badges : une clé = un compteur affiché sur la page `path` du menu (et additionné sur l'entrée qui la contient).
 *   - provider        classe qui implémente App\Services\BackOffice\Menu\BadgeProvider (constructeur sans argument)
 *   - any_permissions la personne doit avoir l'un de ces droits ; la page doit aussi être visible dans son menu
 *   - tone            notif (compteur 1, 2, 3, 9+) ou important (rouge : urgent ou bloquant)
 *   - title           info-bulle, %d = le nombre
 *   - task            explication de la tâche dans le bloc « Tâches en attente » du tableau de bord, écrite pour
 *                     quelqu'un qui ne connaît pas le vocabulaire interne (voir PendingTaskExplainer) :
 *                     explain (chaîne ou {one, other}, %d = le nombre), consequence, consequence_urgent (facultatif),
 *                     action, button, who et help (facultatifs), path (facultatif : page du bouton si ce n'est pas
 *                     celle de la pastille). Sans « task », la tâche reste un titre et un lien. Un fournisseur qui
 *                     implémente aussi PendingTaskDetailProvider compose lui-même ses textes (formule).
 *   Plusieurs clés peuvent viser la même page (Demandes RH : corrections, élévations, mobilité) : elles s'additionnent.
 *   Le calcul est mis en cache `cache_seconds` par personne et par communauté, puis relu par l'endpoint JSON
 *   toutes les `poll_seconds` secondes tant que l'onglet est visible (0 : pas de mise à jour sans rechargement).
 *
 * new : pages récentes marquées « Nouveau » pendant `new_days` jours après `since` (AAAA-MM-JJ), jusqu'à la
 *   première visite de la page par la personne.
 *
 * @return array{
 *   cache_seconds: int,
 *   poll_seconds: int,
 *   favorites_max: int,
 *   new_days: int,
 *   badges: array<string, array{path: string, provider: class-string, any_permissions: list<string>, tone: string, title: string, task?: array<string, string|array{one: string, other: string}>}>,
 *   new: list<array{path: string, since: string}>
 * }
 */
return [
    'cache_seconds' => 45,
    'poll_seconds' => 90,
    'favorites_max' => 12,
    'new_days' => 30,

    'badges' => [
        'candidatures' => [
            'path' => 'back-office/recruitments',
            'provider' => \App\Services\BackOffice\Menu\Badges\RecruitmentsBadge::class,
            'any_permissions' => ['organization.recruitment.manage', 'admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d candidature(s) à instruire',
            'task' => [
                'explain' => [
                    'one' => '1 personne a déposé une candidature pour rejoindre la communauté et attend votre réponse.',
                    'other' => '%d personnes ont déposé une candidature pour rejoindre la communauté et attendent votre réponse.',
                ],
                'consequence' => 'Tant que vous ne répondez pas, les candidats restent en attente et peuvent finir par renoncer.',
                'action' => 'Ouvrez chaque candidature pour l’accepter, la refuser ou demander un complément.',
                'button' => 'Répondre aux candidatures',
                'who' => 'Les personnes chargées du recrutement et les administrateurs de la communauté. Si ce n’est pas votre rôle, prévenez-les.',
            ],
        ],
        'corrections' => [
            'path' => 'back-office/demandes-rh',
            'provider' => \App\Services\BackOffice\Menu\Badges\CorrectionsBadge::class,
            'any_permissions' => \App\Services\Personnel\PersonnelCorrectionRequestService::STAFF_PERMISSIONS,
            'tone' => 'notif',
            'title' => '%d correction(s) de fiche en attente',
            'task' => [
                'explain' => [
                    'one' => '1 membre demande de corriger une information de sa fiche personnelle (nom, indicatif radio, devise…).',
                    'other' => '%d membres demandent de corriger une information de leur fiche personnelle (nom, indicatif radio, devise…).',
                ],
                'consequence' => 'La fiche ne change pas tant que vous n’avez pas décidé : le membre attend votre réponse.',
                'action' => 'Ouvrez chaque demande, puis validez-la pour mettre la fiche à jour, ou refusez-la.',
                'button' => 'Examiner les corrections de fiche',
                'who' => 'Les personnes qui gèrent les dossiers du personnel et les administrateurs de la communauté.',
                'help' => 'Chaque membre a une fiche personnelle (identité, indicatif radio, présentation…). Il ne la modifie pas lui-même : il demande une correction, et un responsable la valide ou la refuse.',
            ],
        ],
        'elevations' => [
            'path' => 'back-office/demandes-rh',
            'provider' => \App\Services\BackOffice\Menu\Badges\ElevationsBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d demande(s) d’accès élevé à décider',
            'task' => [
                'explain' => [
                    'one' => '1 demande attend votre décision : un membre voudrait un autre grade, un autre rôle ou des droits supplémentaires.',
                    'other' => '%d demandes attendent votre décision : des membres voudraient un autre grade, un autre rôle ou des droits supplémentaires.',
                ],
                'consequence' => 'Tant que vous ne décidez pas, le grade, le rôle et les droits du membre restent inchangés.',
                'action' => 'Ouvrez chaque demande, lisez le motif, puis acceptez-la ou refusez-la.',
                'button' => 'Décider des demandes d’accès élevé',
                'who' => 'Seuls les administrateurs de la communauté peuvent décider.',
                'help' => 'Un « accès élevé » est une montée en responsabilités : un nouveau grade, un nouveau rôle ou des droits plus étendus dans le back-office. Rien n’est accordé tant que la demande n’est pas acceptée.',
            ],
        ],
        'mobilite' => [
            'path' => 'back-office/demandes-rh',
            'provider' => \App\Services\BackOffice\Menu\Badges\MobilityBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access', 'personnel.assignments.manage'],
            'tone' => 'notif',
            'title' => '%d demande(s) de mobilité en attente',
            'task' => [
                'explain' => [
                    'one' => '1 membre demande à changer d’unité, de spécialité ou de poste.',
                    'other' => '%d membres demandent à changer d’unité, de spécialité ou de poste.',
                ],
                'consequence' => 'Tant que vous ne répondez pas, l’affectation du membre ne change pas et il ne sait pas où en est sa demande.',
                'action' => 'Ouvrez chaque demande, puis acceptez-la ou refusez-la.',
                'button' => 'Répondre aux demandes de mobilité',
                'who' => 'Les personnes qui gèrent les affectations et les administrateurs de la communauté.',
            ],
        ],
        'signalements' => [
            'path' => 'back-office/signalements',
            'provider' => \App\Services\BackOffice\Menu\Badges\ModerationBadge::class,
            'any_permissions' => ['moderation.reports.manage', 'admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d signalement(s) à traiter',
            'task' => [
                'explain' => [
                    'one' => '1 élément est à vérifier : un contenu signalé par un membre (fiche, photo, message…) ou un fichier mis de côté pour contrôle.',
                    'other' => '%d éléments sont à vérifier : des contenus signalés par des membres (fiches, photos, messages…) ou des fichiers mis de côté pour contrôle.',
                ],
                'consequence' => 'Tant que ce n’est pas traité, un contenu litigieux peut rester visible et un fichier mis de côté reste bloqué.',
                'action' => 'Ouvrez chaque signalement, vérifiez le contenu, puis prenez la mesure qui convient et clôturez-le.',
                'button' => 'Traiter les signalements',
                'who' => 'Les modérateurs et les administrateurs de la communauté.',
                'help' => 'Un membre peut signaler un contenu qu’il juge inapproprié. Les fichiers douteux sont aussi mis en quarantaine, c’est-à-dire retenus, jusqu’à ce qu’une personne les ait contrôlés.',
            ],
        ],
        'mise_a_niveau' => [
            'path' => 'back-office/mise-a-niveau',
            'provider' => \App\Services\BackOffice\Menu\Badges\ConfigurationUpdatesBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access', 'tenant.configuration.manage', 'admin.settings.manage'],
            'tone' => 'notif',
            'title' => '%d étape(s) de mise à niveau à faire',
            'task' => [
                'explain' => [
                    'one' => 'La plateforme a évolué : 1 réglage de votre communauté est à faire pour profiter des nouveautés.',
                    'other' => 'La plateforme a évolué : %d réglages de votre communauté sont à faire pour profiter des nouveautés.',
                ],
                'consequence' => 'Rien ne s’arrête, mais les nouveautés concernées ne sont pas pleinement utilisables tant que l’étape n’est pas faite.',
                'consequence_urgent' => 'Au moins une étape est obligatoire : tant qu’elle n’est pas faite, la fonction concernée peut rester bloquée.',
                'action' => 'Ouvrez chaque étape, réglez la page indiquée, puis cliquez sur « C’est fait » (ou remettez-la à plus tard).',
                'button' => 'Traiter les étapes de mise à niveau',
                'who' => 'Les administrateurs de la communauté et les personnes autorisées à modifier la configuration.',
                'help' => 'Après une nouvelle version d’Athena, certaines fonctions demandent un réglage de votre part (par exemple rédiger des consignes pour vos joueurs). Chaque étape indique la page à régler et le temps estimé.',
            ],
        ],
        'messages_plateforme' => [
            'path' => 'back-office/messages-plateforme',
            'provider' => \App\Services\BackOffice\Menu\Badges\PlatformMessagesBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d message(s) de la plateforme à lire',
            'task' => [
                'explain' => [
                    'one' => 'L’équipe de la plateforme Athena vous a écrit : 1 message n’a pas encore été ouvert (annonce, invitation ou consigne).',
                    'other' => 'L’équipe de la plateforme Athena vous a écrit : %d messages n’ont pas encore été ouverts (annonces, invitations ou consignes).',
                ],
                'consequence' => 'Vous risquez de manquer une information ou une consigne de la plateforme qui concerne votre communauté.',
                'consequence_urgent' => 'Au moins un message est marqué urgent : lisez-le en premier, il peut demander une action rapide de votre part.',
                'action' => 'Ouvrez la liste, lisez chaque message, puis fermez-le ou confirmez sa lecture quand on vous le demande.',
                'button' => 'Lire les messages de la plateforme',
                'who' => 'Seuls les administrateurs de la communauté reçoivent ces messages.',
            ],
        ],
        'formule' => [
            'path' => 'back-office/configuration',
            'provider' => \App\Services\BackOffice\Menu\Badges\PlanEndBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access'],
            'tone' => 'important',
            'title' => 'La formule de la communauté se termine ou est terminée',
        ],
    ],

    'new' => [
        ['path' => 'back-office/messages-plateforme', 'since' => '2026-11-09'],
        ['path' => 'back-office/ressources/atak-consignes', 'since' => '2026-11-08'],
        ['path' => 'back-office/organisation/types-entite', 'since' => '2026-11-08'],
        ['path' => 'back-office/organisation/noms', 'since' => '2026-11-02'],
        ['path' => 'back-office/roleplay/sessions', 'since' => '2026-11-02'],
        ['path' => 'back-office/atak/controle-serveur', 'since' => '2026-11-01'],
    ],
];
