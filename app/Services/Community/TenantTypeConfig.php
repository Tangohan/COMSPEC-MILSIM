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
                'label' => 'Complet',
                'description' => 'Forum, formations, recrutement, effectifs et carte tactique : l’ensemble des outils Athena pour piloter une unité.',
                'consequences' => 'Tous les modules du portail deviennent disponibles. Les données déjà présentes restent en place.',
            ],
            self::TYPE_EFFECTIFS => [
                'label' => 'Bureau des effectifs',
                'description' => 'Registre RH (pseudo, indicatif, fonctions, affectations), forum public et carte tactique ATAK.',
                'consequences' => 'Formations, recrutement, documents et messagerie interne ne seront plus accessibles. Effectifs, forum, administration et carte ATAK restent disponibles.',
            ],
            self::TYPE_ATAK => [
                'label' => 'Carte ATAK',
                'description' => 'Carte et coordination terrain, avec le forum public pour les échanges de la communauté.',
                'consequences' => 'La carte tactique, le forum public et l’administration restent accessibles. Effectifs, formations et recrutement seront masqués.',
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
            ],
            self::TYPE_EFFECTIFS => [
                'admin',
                'forum',
                'personnel',
                'analytics',
                'atak',
            ],
            self::TYPE_ATAK => [
                'admin',
                'forum',
                'atak',
            ],
        ];
    }

    /**
     * Permissions de base à créer selon le type de tenant.
     *
     * @return array<string, list<array{slug: string, name: string, module: string}>>
     */
    public static function basePermissionsByType(): array
    {
        return [
            self::TYPE_FULL => [],
            self::TYPE_EFFECTIFS => [
                ['slug' => 'admin.access', 'name' => 'Accès administration', 'module' => 'admin'],
                ['slug' => 'invitations.send', 'name' => 'Envoyer des invitations', 'module' => 'admin'],
                ['slug' => 'forum.view', 'name' => 'Voir le forum', 'module' => 'forum'],
                ['slug' => 'forum.create_topic', 'name' => 'Créer un sujet', 'module' => 'forum'],
                ['slug' => 'forum.reply', 'name' => 'Répondre au forum', 'module' => 'forum'],
                ['slug' => 'forum.edit_own', 'name' => 'Modifier ses messages forum', 'module' => 'forum'],
                ['slug' => 'personnel.view', 'name' => 'Voir les effectifs', 'module' => 'personnel'],
                ['slug' => 'personnel.edit', 'name' => 'Modifier les effectifs', 'module' => 'personnel'],
                ['slug' => 'personnel.manage', 'name' => 'Gérer les effectifs', 'module' => 'personnel'],
                ['slug' => 'personnel.profile.view', 'name' => 'Voir les fiches personnelles', 'module' => 'personnel'],
                ['slug' => 'atak.access', 'name' => 'Accès ATAK', 'module' => 'atak'],
                ['slug' => 'atak.manage', 'name' => 'Gérer ATAK', 'module' => 'atak'],
            ],
            self::TYPE_ATAK => [
                ['slug' => 'admin.access', 'name' => 'Accès administration', 'module' => 'admin'],
                ['slug' => 'invitations.send', 'name' => 'Envoyer des invitations', 'module' => 'admin'],
                ['slug' => 'forum.view', 'name' => 'Voir le forum', 'module' => 'forum'],
                ['slug' => 'forum.create_topic', 'name' => 'Créer un sujet', 'module' => 'forum'],
                ['slug' => 'forum.reply', 'name' => 'Répondre au forum', 'module' => 'forum'],
                ['slug' => 'forum.edit_own', 'name' => 'Modifier ses messages forum', 'module' => 'forum'],
                ['slug' => 'atak.access', 'name' => 'Accès ATAK', 'module' => 'atak'],
                ['slug' => 'atak.manage', 'name' => 'Gérer ATAK', 'module' => 'atak'],
            ],
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
            self::TYPE_EFFECTIFS => [
                [
                    'name' => 'Gestionnaire effectifs',
                    'slug' => 'personnel_manager',
                    'description' => 'Gère les effectifs de la communauté',
                    'is_system' => 1,
                    'is_locked' => 0,
                    'role_layer' => 'community',
                ],
                [
                    'name' => 'Membre',
                    'slug' => 'member',
                    'description' => 'Membre de la communauté',
                    'is_system' => 1,
                    'is_locked' => 0,
                    'role_layer' => 'community',
                ],
            ],
            self::TYPE_ATAK => [
                [
                    'name' => 'Opérateur ATAK',
                    'slug' => 'atak_operator',
                    'description' => 'Opérateur du système ATAK',
                    'is_system' => 1,
                    'is_locked' => 0,
                    'role_layer' => 'community',
                ],
                [
                    'name' => 'Administrateur ATAK',
                    'slug' => 'atak_admin',
                    'description' => 'Administrateur du système ATAK',
                    'is_system' => 1,
                    'is_locked' => 0,
                    'role_layer' => 'community',
                ],
            ],
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

        return (string) ($types[$key]['label'] ?? 'Complet');
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
            // Forum
            'forum' => 'forum',
            'admin/forum-config' => 'forum',
            'back-office/forum' => 'forum',
            'back-office/forum-moderation' => 'forum',
            'admin/content-moderation' => 'forum',
            // Documents / courrier
            'documents' => 'documents',
            'courrier' => 'documents',
            'back-office/courrier' => 'documents',
            'back-office/doctrine' => 'documents',
            // Personnel / effectifs
            'personnel' => 'personnel',
            'orbat' => 'personnel',
            'deploiement' => 'personnel',
            'distinctions' => 'personnel',
            'api/orbat' => 'personnel',
            'api/dossier-operateur' => 'personnel',
            'back-office/ressources/effectifs' => 'personnel',
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
            // Formation
            'formations' => 'training',
            'formation' => 'training',
            'courses' => 'training',
            'training' => 'training',
            'back-office/ressources/training' => 'training',
            // Recrutement
            'enlistment' => 'recruitment',
            'recrutement' => 'recruitment',
            'back-office/ressources/recrutement' => 'recruitment',
            'back-office/recruitments' => 'recruitment',
            'back-office/recruitment' => 'recruitment',
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
            'equipment' => 'operations',
            'equipement' => 'operations',
            'modpacks' => 'operations',
            'admin/modpacks' => 'operations',
            // Coopérations
            'cooperation' => 'cooperation',
            'interteam' => 'cooperation',
            'back-office/cooperation' => 'cooperation',
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
