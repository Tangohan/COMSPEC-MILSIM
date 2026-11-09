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
 *   badges: array<string, array{path: string, provider: class-string, any_permissions: list<string>, tone: string, title: string}>,
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
        ],
        'corrections' => [
            'path' => 'back-office/personnel/corrections',
            'provider' => \App\Services\BackOffice\Menu\Badges\CorrectionsBadge::class,
            'any_permissions' => \App\Services\Personnel\PersonnelCorrectionRequestService::STAFF_PERMISSIONS,
            'tone' => 'notif',
            'title' => '%d correction(s) de fiche en attente',
        ],
        'elevations' => [
            'path' => 'back-office/ressources/effectifs/elevations',
            'provider' => \App\Services\BackOffice\Menu\Badges\ElevationsBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d demande(s) d’accès élevé à décider',
        ],
        'mobilite' => [
            'path' => 'back-office/ressources/effectifs/mobilite',
            'provider' => \App\Services\BackOffice\Menu\Badges\MobilityBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access', 'personnel.assignments.manage'],
            'tone' => 'notif',
            'title' => '%d demande(s) de mobilité en attente',
        ],
        'signalements' => [
            'path' => 'back-office/signalements',
            'provider' => \App\Services\BackOffice\Menu\Badges\ModerationBadge::class,
            'any_permissions' => ['moderation.reports.manage', 'admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d signalement(s) à traiter',
        ],
        'mise_a_niveau' => [
            'path' => 'back-office/mise-a-niveau',
            'provider' => \App\Services\BackOffice\Menu\Badges\ConfigurationUpdatesBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access', 'tenant.configuration.manage', 'admin.settings.manage'],
            'tone' => 'notif',
            'title' => '%d étape(s) de mise à niveau à faire',
        ],
        'messages_plateforme' => [
            'path' => 'back-office/messages-plateforme',
            'provider' => \App\Services\BackOffice\Menu\Badges\PlatformMessagesBadge::class,
            'any_permissions' => ['admin.organization', 'admin.access'],
            'tone' => 'notif',
            'title' => '%d message(s) de la plateforme à lire',
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
