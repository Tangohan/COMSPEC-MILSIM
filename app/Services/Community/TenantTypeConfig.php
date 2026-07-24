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
     * @return array<string, array{label: string, description: string}>
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_FULL => [
                'label' => 'Complet',
                'description' => 'Accès à tous les modules (forum, formation, recrutement, ATAK, etc.)',
            ],
            self::TYPE_EFFECTIFS => [
                'label' => 'Effectifs',
                'description' => 'Gestion simplifiée : Pseudo, Indicatif, Fonction, Affectations, Position administrative',
            ],
            self::TYPE_ATAK => [
                'label' => 'ATAK',
                'description' => 'Uniquement le module ATAK pour la coordination tactique',
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
                'personnel',
                'analytics',
            ],
            self::TYPE_ATAK => [
                'admin',
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
                ['slug' => 'personnel.view', 'name' => 'Voir les effectifs', 'module' => 'personnel'],
                ['slug' => 'personnel.edit', 'name' => 'Modifier les effectifs', 'module' => 'personnel'],
                ['slug' => 'personnel.manage', 'name' => 'Gérer les effectifs', 'module' => 'personnel'],
            ],
            self::TYPE_ATAK => [
                ['slug' => 'admin.access', 'name' => 'Accès administration', 'module' => 'admin'],
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

    /**
     * Vérifie si un module est accessible pour un type de tenant donné.
     */
    public static function moduleAllowed(string $tenantType, string $module): bool
    {
        $allowed = self::allowedModulesByType()[$tenantType] ?? [];

        return in_array($module, $allowed, true);
    }

    /**
     * Retourne la configuration de seed minimale selon le type.
     *
     * @return array{seed_forum: bool, seed_documents: bool, seed_training: bool, seed_recruitment: bool, seed_personnel: bool}
     */
    public static function getSeedConfig(string $tenantType): array
    {
        return match ($tenantType) {
            self::TYPE_EFFECTIFS => [
                'seed_forum' => false,
                'seed_documents' => false,
                'seed_training' => false,
                'seed_recruitment' => false,
                'seed_personnel' => true,
            ],
            self::TYPE_ATAK => [
                'seed_forum' => false,
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
