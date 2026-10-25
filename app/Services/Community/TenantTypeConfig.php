<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Configuration des types de tenant simplifiés.
 * Définit les modules accessibles et les rôles par défaut selon le type.
 */
final class TenantTypeConfig
{
    public const TYPE_FULL = 'full';
    public const TYPE_EFFECTIFS = 'effectifs';
    public const TYPE_ATAK = 'atak';

    /**
     * Liste des types disponibles avec leur description.
     *
     * @return array<string, array{label: string, description: string, consequences: string}>
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_FULL => [
                'label' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Complet') : 'Complet',
                'description' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Tout Athena : effectifs, recrutement, formations, opérations, documents, forum et carte ATAK.') : 'Tout Athena : effectifs, recrutement, formations, opérations, documents, forum et carte ATAK.',
                'consequences' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Tous les modules du portail deviennent disponibles. Les données déjà présentes restent en place.') : 'Tous les modules du portail deviennent disponibles. Les données déjà présentes restent en place.',
            ],
            self::TYPE_EFFECTIFS => [
                'label' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Bureau des effectifs') : 'Bureau des effectifs',
                'description' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Le registre de l’unité tenu comme un tableur : fiches, grades, unités et ORBAT, sans compte pour les membres. Recrutement uniquement sur Discord.') : 'Le registre de l’unité tenu comme un tableur : fiches, grades, unités et ORBAT, sans compte pour les membres. Recrutement uniquement sur Discord.',
                'consequences' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Formations, recrutement sur le site, documents et messagerie interne ne seront plus accessibles : on vous rejoint par Discord. Effectifs (avec fiches sans compte et import de tableur), forum, administration et carte ATAK restent disponibles.') : 'Formations, recrutement sur le site, documents et messagerie interne ne seront plus accessibles : on vous rejoint par Discord. Effectifs (avec fiches sans compte et import de tableur), forum, administration et carte ATAK restent disponibles.',
            ],
            self::TYPE_ATAK => [
                'label' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'Carte ATAK') : 'Carte ATAK',
                'description' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'La carte ATAK, les équipes de feu et le forum. Le plus simple pour jouer ensemble.') : 'La carte ATAK, les équipes de feu et le forum. Le plus simple pour jouer ensemble.',
                'consequences' => function_exists('i18n_phrase') ? i18n_phrase('nav', 'La carte tactique, Overwatch, le matériel, les relais, le forum public et la gestion des membres restent accessibles. Effectifs, formations et recrutement sur le site seront masqués : on vous rejoint par Discord.') : 'La carte tactique, Overwatch, le matériel, les relais, le forum public et la gestion des membres restent accessibles. Effectifs, formations et recrutement sur le site seront masqués : on vous rejoint par Discord.',
            ],
        ];
    }

    /**
     * Modules accessibles par type de tenant.
     *
     * @return array<string, list<string>>
     */
    public static function allowedModulesByType(): array
    {
        return [
            self::TYPE_FULL => [
                'admin',
                'forum',
                'documents',
                'personnel',
                'training',
                'recruitment',
                'operations',
                'atak',
                'cooperation',
                'messages',
                'analytics',
                'equipment',
            ],
            self::TYPE_EFFECTIFS => [
                'admin',
                'forum',
                'personnel',
                'analytics',
                'atak',
                'equipment',
            ],
            self::TYPE_ATAK => [
                'admin',
                'forum',
                'atak',
                'equipment',
            ],
        ];
    }

    /**
     * Permissions de base à créer selon le type de tenant, avec le niveau d’accès minimal
     * qui les reçoit (member < hr < manager, voir CommunityAccessProfiles).
     * Un membre ordinaire ne reçoit jamais l’administration, les invitations ni la gestion.
     *
     * @return array<string, list<array{slug: string, name: string, module: string, level: string}>>
     */
    public static function basePermissionsByType(): array
    {
        $forum = [
            ['slug' => 'forum.view', 'name' => 'Voir le forum', 'module' => 'forum', 'level' => 'member'],
            ['slug' => 'forum.create_topic', 'name' => 'Créer un sujet', 'module' => 'forum', 'level' => 'member'],
            ['slug' => 'forum.reply', 'name' => 'Répondre au forum', 'module' => 'forum', 'level' => 'member'],
            ['slug' => 'forum.edit_own', 'name' => 'Modifier ses messages forum', 'module' => 'forum', 'level' => 'member'],
        ];
        $admin = [
            ['slug' => 'admin.access', 'name' => 'Accès administration', 'module' => 'admin', 'level' => 'manager'],
            ['slug' => 'invitations.send', 'name' => 'Envoyer des invitations', 'module' => 'admin', 'level' => 'hr'],
        ];
        $atak = [
            ['slug' => 'atak.access', 'name' => 'Accès ATAK', 'module' => 'atak', 'level' => 'member'],
            ['slug' => 'atak.manage', 'name' => 'Gérer ATAK', 'module' => 'atak', 'level' => 'manager'],
        ];

        return [
            self::TYPE_FULL => [],
            self::TYPE_EFFECTIFS => array_merge($admin, $forum, [
                ['slug' => 'personnel.view', 'name' => 'Voir les effectifs', 'module' => 'personnel', 'level' => 'member'],
                ['slug' => 'personnel.profile.view', 'name' => 'Voir les fiches personnelles', 'module' => 'personnel', 'level' => 'member'],
                ['slug' => 'personnel.edit', 'name' => 'Modifier les effectifs', 'module' => 'personnel', 'level' => 'hr'],
                ['slug' => 'personnel.manage', 'name' => 'Gérer les effectifs', 'module' => 'personnel', 'level' => 'hr'],
            ], $atak),
            self::TYPE_ATAK => array_merge($admin, $forum, $atak),
        ];
    }

    /** Rang d’un niveau d’accès (member 1, hr 2, manager 3). */
    public static function levelRank(string $level): int
    {
        return match ($level) {
            'manager' => 3,
            'hr' => 2,
            default => 1,
        };
    }

    /**
     * Slugs de base qu’un niveau d’accès (member | hr | manager) reçoit pour ce type.
     *
     * @return list<string>
     */
    public static function basePermissionSlugsForLevel(string $tenantType, string $level): array
    {
        $rank = self::levelRank($level);
        $out = [];
        foreach (self::basePermissionsByType()[self::normalizeType($tenantType)] ?? [] as $p) {
            if (self::levelRank((string) $p['level']) <= $rank) {
                $out[] = (string) $p['slug'];
            }
        }

        return $out;
    }

    /**
     * Habilitations sensibles qu’un ancien paramétrage donnait à tous les niveaux d’accès
     * (bascule de profil, création d’une communauté simplifiée) : niveau minimal attendu.
     *
     * @return array<string, string> slug => level
     */
    public static function sensitivePermissionLevels(): array
    {
        return [
            'admin.access' => 'manager',
            'atak.manage' => 'manager',
            'forum.moderate' => 'manager',
            'forum.moderate_organization' => 'manager',
            'forum.manage_categories' => 'manager',
            'personnel.manage' => 'hr',
            'personnel.edit' => 'hr',
            'invitations.send' => 'hr',
        ];
    }

    /**
     * Rôles de base à créer selon le type de tenant.
     *
     * @return array<string, list<array{name: string, slug: string, description: string, is_system: int, is_locked: int, role_layer: string}>>
     */
    public static function baseRolesByType(): array
    {
        return [
            self::TYPE_FULL => [],
            self::TYPE_EFFECTIFS => [],
            self::TYPE_ATAK => [],
        ];
    }

    /**
     * Vérifie si un type de tenant est valide.
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, [self::TYPE_FULL, self::TYPE_EFFECTIFS, self::TYPE_ATAK], true);
    }

    /**
     * Normalise un type de tenant (défaut : full).
     */
    public static function normalizeType(?string $type): string
    {
        if ($type === null || trim($type) === '') {
            return self::TYPE_FULL;
        }

        $normalized = strtolower(trim($type));

        return self::isValidType($normalized) ? $normalized : self::TYPE_FULL;
    }

    public static function label(string $tenantType): string
    {
        $types = self::availableTypes();
        $key = self::normalizeType($tenantType);

        return (string) ($types[$key]['label'] ?? (function_exists('i18n_phrase') ? i18n_phrase('nav', 'Complet') : 'Complet'));
    }

    /**
     * Vérifie si un module est accessible pour un type de tenant donné.
     */
    public static function moduleAllowed(string $tenantType, string $module): bool
    {
        $module = strtolower(trim($module));
        if ($module === '') {
            return true;
        }
        $allowed = self::allowedModulesByType()[self::normalizeType($tenantType)] ?? [];

        return in_array($module, $allowed, true);
    }

    /**
     * Indique si une URI est autorisée pour le type de communauté.
     * Les chemins sans module associé (compte, accueil, etc.) restent ouverts.
     */
    public static function uriAllowed(string $tenantType, string $uri): bool
    {
        $module = self::moduleForUri($uri);
        if ($module === null) {
            return true;
        }

        return self::moduleAllowed($tenantType, $module);
    }

    /**
     * Associe une URI (sans slash initial) à un module métier, ou null si non restreint.
     */
    public static function moduleForUri(string $uri): ?string
    {
        $uri = strtolower(trim($uri, "/ \t"));
        if ($uri === '') {
            return null;
        }

        // Préfixes toujours ouverts (compte, auth, navigation transversale).
        foreach ([
            'account',
            'login',
            'logout',
            'register',
            'dashboard',
            'jnet',
            'communities',
            'community/switch',
            'join',
            'onboarding',
            'activite',
            'boite-reception',
            'search',
            'documentation',
            'assistant',
            'platform',
            'soutenir-atak',
            'verify-email',
            'forgot-password',
            'reset-password',
            'security',
            'api/auth',
            'api/session',
            'api/health',
            'assets',
            'storage',
        ] as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return null;
            }
        }

        // Pages publiques communauté : non restreintes ici (contrôle d’appartenance ailleurs).
        if ($uri === 'c' || str_starts_with($uri, 'c/')) {
            return null;
        }

        // Liste des membres, fiche, création de compte et niveaux d’accès : socle de gestion de tous les profils
        // (le profil « Carte ATAK » n’a pas les effectifs mais doit gérer ses joueurs). Racine seule, pas les sous-pages RH.
        if ($uri === 'back-office/ressources/effectifs' || $uri === 'back-office/ressources/effectifs/export') {
            return 'admin';
        }

        $prefixMap = [
            // ATAK (pages + API tactiques alignées sur detect_current_module)
            'atak' => 'atak',
            'tacmap' => 'atak',
            'overwatch' => 'atak',
            'c2' => 'atak',
            'admin/atak-config' => 'atak',
            'admin/atak-mod' => 'atak',
            'admin/atak-mod-blocks' => 'atak',
            'admin/atak-beta' => 'atak',
            'admin/atak' => 'atak',
            'back-office/atak' => 'atak',
            'back-office/ressources/atak-config' => 'atak',
            'back-office/ressources/atak-mod' => 'atak',
            'back-office/ressources/atak-mod-blocks' => 'atak',
            'back-office/ressources/atak-beta' => 'atak',
            'back-office/ressources/atak-diffusion-rapports' => 'atak',
            'back-office/ressources/serveurs-de-jeu' => 'atak',
            'back-office/ressources/atak-realism' => 'atak',
            'api/atak' => 'atak',
            'api/tacmap' => 'atak',
            'api/overwatch' => 'atak',
            'api/markers' => 'atak',
            'api/units' => 'atak',
            'api/chat' => 'atak',
            'api/pings' => 'atak',
            'api/nine-line' => 'atak',
            'api/cas' => 'atak',
            'api/recon' => 'atak',
            'api/map-shapes' => 'atak',
            'api/flight-manifest' => 'atak',
            'api/intel' => 'atak',
            'api/fire-support' => 'atak',
            'api/danger-zones' => 'atak',
            'api/logistics' => 'atak',
            'api/replay' => 'atak',
            'api/iff' => 'atak',
            'api/custom-maps' => 'atak',
            'map-data' => 'atak',
            'recon' => 'atak',
            'telecommande-drone' => 'atak',
            'operateur/terrain' => 'atak',
            'api/mission-cycle' => 'atak',
            'back-office/ma-situation/liaison-atak' => 'atak',
            'back-office/ma-situation/appareils' => 'atak',
            'back-office/ma-situation/premiere-liaison' => 'atak',
            // Forum
            'forum' => 'forum',
            'admin/forum-config' => 'forum',
            'back-office/forum' => 'forum',
            'back-office/forum-moderation' => 'forum',
            'admin/content-moderation' => 'forum',
            'back-office/ressources/forum-config' => 'forum',
            'back-office/content-moderation' => 'forum',
            // Documents / courrier
            'documents' => 'documents',
            'courrier' => 'documents',
            'back-office/courrier' => 'documents',
            'back-office/doctrine' => 'documents',
            'back-office/documents' => 'documents',
            // Personnel / effectifs
            'personnel' => 'personnel',
            'orbat' => 'personnel',
            'deploiement' => 'personnel',
            'distinctions' => 'personnel',
            'api/orbat' => 'personnel',
            'api/dossier-operateur' => 'personnel',
            'back-office/ressources/effectifs' => 'personnel',
            'back-office/ressources/effectifs/membres' => 'admin',
            'back-office/ressources/effectifs/nouveau' => 'admin',
            'back-office/ressources/effectifs/roles' => 'admin',
            'back-office/ressources/effectifs/droits' => 'admin',
            'back-office/ressources/effectifs/doublons' => 'admin',
            'back-office/ressources/effectifs/departs' => 'admin',
            'back-office/organisation-effectifs' => 'personnel',
            'back-office/organisation/structure' => 'personnel',
            'back-office/organisation/anciennete' => 'personnel',
            'back-office/organisation/catalogue' => 'personnel',
            'back-office/organisation/progression' => 'personnel',
            'back-office/organisation/indicatifs' => 'personnel',
            'back-office/personnel-job-roles' => 'personnel',
            'back-office/roles-functions' => 'personnel',
            'back-office/groups' => 'personnel',
            'back-office/teams' => 'personnel',
            'back-office/categories' => 'forum',
            'back-office/referentiels/grades' => 'personnel',
            'back-office/positions' => 'personnel',
            'back-office/roleplay-followup' => 'personnel',
            'back-office/roleplay' => 'personnel',
            'back-office/personnel' => 'personnel',
            'back-office/rh' => 'personnel',
            'back-office/units' => 'personnel',
            'admin/units' => 'personnel',
            'back-office/organisation/passes' => 'personnel',
            'back-office/organisation/matricules' => 'personnel',
            'back-office/organisation/unites' => 'personnel',
            'back-office/organisation/grades' => 'personnel',
            'back-office/organisation/qualite-donnees' => 'personnel',
            'back-office/organisation/disponibilite' => 'personnel',
            'back-office/referentiels/qualifications' => 'personnel',
            'back-office/referentiels/decorations' => 'personnel',
            'back-office/referentiels/dotation' => 'personnel',
            'back-office/referentiels/competences' => 'personnel',
            'back-office/integration-membres' => 'personnel',
            'back-office/onboarding-members' => 'personnel',
            'back-office/ma-situation/ma-fiche' => 'personnel',
            'back-office/ma-situation/unite' => 'personnel',
            'back-office/ma-situation/qualifications' => 'personnel',
            'back-office/ma-situation/avancement' => 'personnel',
            'back-office/ma-situation/carriere' => 'personnel',
            'back-office/ma-situation/decorations' => 'personnel',
            'back-office/ma-situation/dotation' => 'personnel',
            'back-office/ma-situation/mes-demarches' => 'personnel',
            'mon-integration' => 'personnel',
            'rh' => 'personnel',
            'api/avancement' => 'personnel',
            'templates-unite' => 'personnel',
            // Formation
            'formations' => 'training',
            'formation' => 'training',
            'courses' => 'training',
            'training' => 'training',
            'back-office/ressources/training' => 'training',
            'admin/training' => 'training',
            'api/training' => 'training',
            // Recrutement
            'enlistment' => 'recruitment',
            'recrutement' => 'recruitment',
            'back-office/ressources/recrutement' => 'recruitment',
            'back-office/recruitments' => 'recruitment',
            'back-office/recruitment' => 'recruitment',
            'admin/recruitments' => 'recruitment',
            // Opérations
            'operations' => 'operations',
            'missions' => 'operations',
            'manoeuvres' => 'operations',
            'evenements' => 'operations',
            'hub' => 'operations',
            'salle-de-guerre' => 'operations',
            'tableau-operationnel' => 'operations',
            'back-office/tableau-operationnel' => 'operations',
            'back-office/centre-operations' => 'operations',
            'back-office/operations-admin' => 'operations',
            'back-office/events' => 'operations',
            'back-office/planification' => 'operations',
            'back-office/evenements' => 'operations',
            'back-office/missions' => 'operations',
            'back-office/operations' => 'operations',
            'back-office/ma-situation/evenements' => 'operations',
            'api/events' => 'operations',
            'api/operations' => 'operations',
            'tactical' => 'operations',
            'pointage' => 'operations',
            'calendrier' => 'operations',
            // Arsenal / tenues : ouvert à tous les profils (le mod s’en sert pour les dotations en jeu).
            'equipment' => 'equipment',
            'equipement' => 'equipment',
            'modpacks' => 'operations',
            'admin/modpacks' => 'operations',
            'back-office/ressources/modpacks' => 'operations',
            // Coopérations
            'cooperation' => 'cooperation',
            'interteam' => 'cooperation',
            'back-office/cooperation' => 'cooperation',
            'admin/interteam-missions' => 'cooperation',
            'back-office/ressources/interteam-missions' => 'cooperation',
            // Messages
            'messages' => 'messages',
            'back-office/communications' => 'messages',
            // Analytics
            'analytics' => 'analytics',
            'back-office/analytics' => 'analytics',
            // Admin communauté (toujours lié au module admin)
            'back-office' => 'admin',
            'admin' => 'admin',
        ];

        // Plus long préfixe d’abord.
        $keys = array_keys($prefixMap);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($keys as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return $prefixMap[$prefix];
            }
        }

        return null;
    }

    /**
     * Pages du back-office retirées des menus selon le profil. Elles ne sont pas interdites (un lien
     * direct marche toujours) mais n’ont pas d’usage pour ce type de communauté : le menu reste court.
     * « Bureau des effectifs » : le registre est tenu par l’état-major, les membres n’ont pas besoin
     * de compte (pas d’invitation, de demande d’élévation, d’intégration ni de communication membres).
     *
     * @return list<string> chemins sans slash initial (la page et ses sous-pages)
     */
    public static function hiddenBackOfficePaths(string $tenantType): array
    {
        $recruitment = [
            'back-office/recruitments',
            'back-office/recruitment',
            'back-office/ressources/recrutement',
            'back-office/integration-membres',
            'back-office/onboarding-members',
        ];
        // Outils de diagnostic du mod : utiles au support, pas à une communauté simplifiée.
        $diagnostics = [
            'back-office/atak/data-inspector',
            'back-office/atak/files-envoi',
            'back-office/articles',
            'jnet',
        ];

        return match (self::normalizeType($tenantType)) {
            self::TYPE_EFFECTIFS => array_merge($recruitment, $diagnostics, [
                'back-office/invitations',
                'back-office/ressources/effectifs/elevations',
                'back-office/ressources/effectifs/integration',
                'back-office/ressources/effectifs/roleplay',
                'back-office/personnel/corrections',
                'back-office/organisation/disponibilite',
                'back-office/roleplay-followup',
                'back-office/roleplay',
                'back-office/moderation',
                'back-office/metriques-joueurs',
                'back-office/atak/temps-ecran',
                'back-office/atak/escouades',
                'back-office/alerts',
                'back-office/dashboard-pins',
                'back-office/ressources/musique',
            ]),
            self::TYPE_ATAK => array_merge($recruitment, $diagnostics),
            default => [],
        };
    }

    /** Vrai si la page (chemin sans slash initial) est retirée des menus pour ce profil. */
    public static function backOfficePathHidden(string $tenantType, string $path): bool
    {
        $path = strtolower(trim($path, "/ \t"));
        if ($path === '') {
            return false;
        }
        foreach (self::hiddenBackOfficePaths($tenantType) as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Profils sans module de recrutement : on ne candidate pas sur le site, on rejoint le Discord
     * de la communauté. Le formulaire public de candidature est remplacé par ce lien.
     */
    public static function recruitsOnDiscordOnly(string $tenantType): bool
    {
        return !self::moduleAllowed($tenantType, 'recruitment');
    }

    /**
     * « Bureau des effectifs » : l’état-major tient les fiches comme un tableur, sans que les membres
     * aient à créer de compte (fiches sans compte, import d’un tableur).
     */
    public static function rosterWithoutAccounts(string $tenantType): bool
    {
        return self::normalizeType($tenantType) === self::TYPE_EFFECTIFS;
    }

    /**
     * Modules du portail, dans l’ordre d’affichage des comparatifs (libellé + ce qu’ils apportent).
     * « admin » et « equipment » sont présents dans tous les profils.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function moduleCatalog(): array
    {
        return [
            'atak' => ['label' => 'Carte tactique ATAK', 'description' => 'Carte en temps réel, téléphones en jeu, équipes de feu, temps de jeu et rôles, relecture.'],
            'forum' => ['label' => 'Forum', 'description' => 'Annonces, discussions et sections publiques de la communauté.'],
            'admin' => ['label' => 'Administration', 'description' => 'Membres, invitations, niveaux d’accès, identité et vitrine de la communauté.'],
            'equipment' => ['label' => 'Arsenal et tenues', 'description' => 'Tenues et dotations partagées, reprises par le mod en jeu.'],
            'personnel' => ['label' => 'Effectifs', 'description' => 'Fiches, grades, unités et ORBAT, fonctions, avancement, décorations, intégration des nouveaux.'],
            'analytics' => ['label' => 'Statistiques', 'description' => 'Activité de la communauté et indicateurs de suivi.'],
            'recruitment' => ['label' => 'Recrutement', 'description' => 'Formulaire de candidature, bureau recrutement, entretiens et bilan 30 jours.'],
            'training' => ['label' => 'Formations', 'description' => 'Parcours de formation, quiz, certificats et suivi des stagiaires.'],
            'operations' => ['label' => 'Opérations et événements', 'description' => 'Calendrier, pointage, missions, ordres et comptes rendus d’opération.'],
            'documents' => ['label' => 'Documents et courrier', 'description' => 'Doctrine, procédures, courrier interne et accusés de lecture.'],
            'messages' => ['label' => 'Messagerie', 'description' => 'Messages internes et communications de l’état-major.'],
            'cooperation' => ['label' => 'Coopérations', 'description' => 'Missions communes et échanges avec d’autres communautés.'],
        ];
    }

    /**
     * Fiche explicative d’un profil : à qui il s’adresse, ce que chaque niveau d’accès peut faire
     * et les étapes pour bien démarrer (tutoriel du tableau de bord et de /documentation/profils).
     * audience : qui peut réaliser l’étape (member | hr | manager).
     *
     * @return array{
     *     key: string, label: string, tagline: string, ideal_for: list<string>, not_for: string,
     *     levels: array<string, string>,
     *     steps: list<array{title: string, text: string, path: string, audience: string}>
     * }
     */
    public static function profileGuide(string $tenantType): array
    {
        $type = self::normalizeType($tenantType);
        $label = self::label($type);

        $guide = match ($type) {
            self::TYPE_ATAK => [
                'tagline' => 'Le strict nécessaire pour jouer ensemble sur la carte : téléphones ATAK, équipes de feu et forum.',
                'ideal_for' => [
                    'Une équipe ou un serveur qui veut la carte ATAK sans gérer de dossiers RH.',
                    'Une communauté qui tient déjà ses effectifs ailleurs (Discord, tableur).',
                    'Un premier essai d’Athena avant de passer au profil Complet.',
                ],
                'not_for' => 'Pas de fiches, de grades ni de recrutement : si vous voulez suivre vos membres, choisissez « Bureau des effectifs » ou « Complet ».',
                'levels' => [
                    'member' => 'Ouvre la carte, relie son téléphone en jeu, lit et écrit sur le forum, gère ses tenues.',
                    'hr' => 'Comme un membre, et invite de nouveaux joueurs.',
                    'manager' => 'Tout : réglages de la carte et du mod, serveurs de jeu, parc ATAK, matériel et kits, réseau de relais, équipes de feu, temps de jeu, membres et niveaux d’accès.',
                ],
                'steps' => [
                    ['title' => 'Inviter les joueurs', 'text' => 'Envoyez un lien d’invitation : chaque joueur crée son compte et rejoint la communauté.', 'path' => 'back-office/invitations', 'audience' => 'hr'],
                    ['title' => 'Installer le mod et relier le serveur', 'text' => 'Téléchargez le mod COMSPEC ATAK, déclarez votre serveur de jeu et sa clé.', 'path' => 'back-office/ressources/atak-mod', 'audience' => 'manager'],
                    ['title' => 'Régler la carte', 'text' => 'Cartes disponibles, fonctions activées sur les téléphones, mode roleplay.', 'path' => 'back-office/ressources/atak-config', 'audience' => 'manager'],
                    ['title' => 'Relier son téléphone en jeu', 'text' => 'Chaque joueur renseigne son identifiant Steam et suit la première liaison.', 'path' => 'atak/premiere-liaison', 'audience' => 'member'],
                    ['title' => 'Constituer les équipes de feu', 'text' => 'Une couleur, un chef, des membres : les équipes apparaissent sur la carte.', 'path' => 'back-office/atak/fire-teams', 'audience' => 'manager'],
                    ['title' => 'Suivre le matériel et les relais', 'text' => 'Parc ATAK des membres, équipement remonté du jeu, kits par rôle et réseau de relais, depuis la gestion.', 'path' => 'back-office/atak/parc-atak', 'audience' => 'manager'],
                    ['title' => 'Jouer et suivre', 'text' => 'Ouvrez la carte pendant la session, puis consultez les heures de jeu par joueur et par rôle.', 'path' => 'atak', 'audience' => 'member'],
                ],
            ],
            self::TYPE_EFFECTIFS => [
                'tagline' => 'Le registre de votre unité, tenu par l’état-major comme un tableur : qui est qui, à quel grade, dans quelle équipe. Vos membres n’ont pas besoin de compte.',
                'ideal_for' => [
                    'Une unité qui tient ses effectifs dans Excel et veut des fiches propres (indicatif, grade, fonction, affectation) sans tout le portail.',
                    'Un état-major qui recrute sur Discord et veut un ORBAT à jour, sans demander à chacun de créer un compte.',
                ],
                'not_for' => 'Pas de candidature sur le site (on vous rejoint par Discord), pas de formations, d’opérations ni de documents : passez au profil « Complet » quand vous en aurez besoin, rien n’est perdu.',
                'levels' => [
                    'member' => 'N’a pas besoin de compte : sa fiche est tenue par l’état-major. S’il en a un, il consulte sa fiche, l’ORBAT, la carte ATAK et le forum.',
                    'hr' => 'Tient les fiches : création sans compte, import de tableur, grades, affectations, fonctions, indicatifs, matricules.',
                    'manager' => 'Tout : structure des unités, référentiels, niveaux d’accès, réglages ATAK et de la communauté.',
                ],
                'steps' => [
                    ['title' => 'Dessiner la structure', 'text' => 'Créez vos unités et sous-unités : c’est l’ORBAT dans lequel les membres sont affectés.', 'path' => 'back-office/organisation/structure', 'audience' => 'manager'],
                    ['title' => 'Choisir grades et fonctions', 'text' => 'Échelle de grades et fonctions (chef de groupe, infirmier…) affichées sur les fiches.', 'path' => 'back-office/referentiels/grades', 'audience' => 'manager'],
                    ['title' => 'Importer votre tableur', 'text' => 'Une ligne par membre (nom, indicatif, grade, affectation) : chaque ligne devient une fiche sans compte. Ou ajoutez les fiches une à une.', 'path' => 'back-office/ressources/effectifs', 'audience' => 'hr'],
                    ['title' => 'Mettre le lien Discord', 'text' => 'On vous rejoint par Discord, jamais par un formulaire du site : renseignez le lien d’invitation de votre serveur.', 'path' => 'back-office/community', 'audience' => 'manager'],
                    ['title' => 'Donner les bons accès', 'text' => 'Seuls ceux qui tiennent les fiches ont un compte : niveau RH pour eux, Gestionnaire pour l’état-major.', 'path' => 'back-office/access-management', 'audience' => 'manager'],
                    ['title' => 'Relier la carte ATAK', 'text' => 'Renseignez l’identifiant Steam sur la fiche : c’est lui qui relie le joueur en jeu à sa fiche, même sans compte.', 'path' => 'back-office/ressources/effectifs', 'audience' => 'hr'],
                ],
            ],
            default => [
                'tagline' => 'Tout Athena pour piloter une unité MilSim : du recrutement à l’opération, en passant par la formation.',
                'ideal_for' => [
                    'Une unité structurée qui recrute, forme et planifie ses opérations.',
                    'Une communauté qui veut un seul outil pour ses membres et son état-major.',
                ],
                'not_for' => 'Si vous ne voulez que la carte, le profil « Carte ATAK » garde une interface plus simple.',
                'levels' => [
                    'member' => 'Forum, documents, formations, sa fiche et l’annuaire, événements et pointage, carte ATAK.',
                    'hr' => 'Fiches, grades, affectations, recrutement et intégration des nouveaux.',
                    'manager' => 'Tout le portail : paramètres, niveaux d’accès, vitrine, opérations, formations, ATAK.',
                ],
                'steps' => [
                    ['title' => 'Identité et vitrine', 'text' => 'Nom, logo, description publique et page d’accueil de la communauté.', 'path' => 'back-office/organisation/parametres', 'audience' => 'manager'],
                    ['title' => 'Structure, grades et fonctions', 'text' => 'Unités de l’ORBAT, échelle de grades et fonctions affichées sur les fiches.', 'path' => 'back-office/organisation/structure', 'audience' => 'manager'],
                    ['title' => 'Niveaux d’accès', 'text' => 'Membre, RH, Gestionnaire : vérifiez qui peut faire quoi avant d’inviter.', 'path' => 'back-office/access-management', 'audience' => 'manager'],
                    ['title' => 'Ouvrir le recrutement', 'text' => 'Formulaire, délai d’alerte, entretiens ; le bilan 30 jours suit chaque recrue acceptée.', 'path' => 'back-office/recruitments/settings', 'audience' => 'hr'],
                    ['title' => 'Formations', 'text' => 'Publiez les parcours d’accueil et de qualification.', 'path' => 'formation', 'audience' => 'manager'],
                    ['title' => 'Premier événement', 'text' => 'Planifiez une session : les membres répondent et pointent leur présence.', 'path' => 'back-office/events', 'audience' => 'manager'],
                    ['title' => 'Carte ATAK', 'text' => 'Installez le mod, reliez le serveur ; chaque membre relie son téléphone en jeu.', 'path' => 'atak/premiere-liaison', 'audience' => 'member'],
                ],
            ],
        };

        return ['key' => $type, 'label' => $label] + $guide;
    }

    /**
     * Profils qui incluent un module (pour expliquer un accès refusé).
     *
     * @return list<string> libellés des profils
     */
    public static function profilesIncludingModule(string $module): array
    {
        $out = [];
        foreach (self::allowedModulesByType() as $type => $modules) {
            if (in_array($module, $modules, true)) {
                $out[] = self::label($type);
            }
        }

        return $out;
    }

    /**
     * Vue tableau de bord dédiée selon le type.
     */
    public static function dashboardView(string $tenantType): string
    {
        return match (self::normalizeType($tenantType)) {
            self::TYPE_ATAK => 'dashboard_atak',
            self::TYPE_EFFECTIFS => 'dashboard_effectifs',
            default => 'dashboard',
        };
    }

    /**
     * Retourne la configuration de seed minimale selon le type.
     *
     * @return array{seed_forum: bool, seed_documents: bool, seed_training: bool, seed_recruitment: bool, seed_personnel: bool}
     */
    public static function getSeedConfig(string $tenantType): array
    {
        return match (self::normalizeType($tenantType)) {
            self::TYPE_EFFECTIFS => [
                'seed_forum' => true,
                'seed_documents' => false,
                'seed_training' => false,
                'seed_recruitment' => false,
                'seed_personnel' => true,
            ],
            self::TYPE_ATAK => [
                'seed_forum' => true,
                'seed_documents' => false,
                'seed_training' => false,
                'seed_recruitment' => false,
                'seed_personnel' => false,
            ],
            default => [
                'seed_forum' => true,
                'seed_documents' => true,
                'seed_training' => true,
                'seed_recruitment' => true,
                'seed_personnel' => true,
            ],
        };
    }
}
