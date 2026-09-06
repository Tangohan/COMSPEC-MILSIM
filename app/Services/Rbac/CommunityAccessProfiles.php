<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Authorization\SystemReservedPermissions;
use App\Authorization\TenantPermissionCatalog;
use App\Services\Admin\TenantRolePermissionPresetService;

/**
 * Accès communauté : trois profils exclusifs.
 *
 * Les habilitations techniques (catalogue) restent en base : le portail en a besoin.
 * L’administrateur ne choisit plus que Membre, Ressources humaines ou Gestionnaire.
 */
final class CommunityAccessProfiles
{
    public const MANAGER = 'manager';
    public const HR = 'hr';
    public const MEMBER = 'member';

    public const SLUG_MANAGER = 'community_owner';
    public const SLUG_HR = 'hr';
    public const SLUG_MEMBER = 'member';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::MEMBER, self::HR, self::MANAGER];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return [self::SLUG_MEMBER, self::SLUG_HR, self::SLUG_MANAGER];
    }

    public static function isAccessSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), self::slugs(), true);
    }

    public static function keyToSlug(string $key): string
    {
        return match ($key) {
            self::MANAGER => self::SLUG_MANAGER,
            self::HR => self::SLUG_HR,
            default => self::SLUG_MEMBER,
        };
    }

    public static function slugToKey(string $slug): string
    {
        return match (strtolower(trim($slug))) {
            self::SLUG_MANAGER => self::MANAGER,
            self::SLUG_HR => self::HR,
            default => self::MEMBER,
        };
    }

    /**
     * @return list<array{
     *     key: string,
     *     slug: string,
     *     name: string,
     *     description: string,
     *     role_layer: string,
     *     is_system: int,
     *     is_locked: int
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => self::MEMBER,
                'slug' => self::SLUG_MEMBER,
                'name' => 'Membre',
                'description' => 'Accès courant : forum, documents standards, formations en consultation, annuaire et fiche.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 1,
            ],
            [
                'key' => self::HR,
                'slug' => self::SLUG_HR,
                'name' => 'Ressources humaines',
                'description' => 'Pilotage des effectifs : dossiers, grades, affectations, recrutement et intégration.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 1,
            ],
            [
                'key' => self::MANAGER,
                'slug' => self::SLUG_MANAGER,
                'name' => 'Gestionnaire',
                'description' => 'Responsable de la communauté : paramètres, accès, et l’ensemble des outils d’organisation.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 1,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definitionByKey(string $key): ?array
    {
        foreach (self::definitions() as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    public static function label(string $key): string
    {
        return (string) (self::definitionByKey($key)['name'] ?? 'Membre');
    }

    /**
     * Anciens slugs → profil d’accès. Priorité Gestionnaire > RH > Membre.
     *
     * @param list<string> $slugs
     */
    public static function resolveFromSlugs(array $slugs): string
    {
        $set = [];
        foreach ($slugs as $slug) {
            $s = strtolower(trim((string) $slug));
            if ($s !== '') {
                $set[$s] = true;
            }
        }
        foreach (self::managerLegacySlugs() as $slug) {
            if (isset($set[$slug])) {
                return self::MANAGER;
            }
        }
        foreach (self::hrLegacySlugs() as $slug) {
            if (isset($set[$slug])) {
                return self::HR;
            }
        }

        return self::MEMBER;
    }

    /**
     * @return list<string>
     */
    public static function managerLegacySlugs(): array
    {
        return [
            self::SLUG_MANAGER,
            'tenant_admin',
            'deputy_commander',
            'technical_admin',
            'unit_manager',
            'rbac_manager',
        ];
    }

    /**
     * @return list<string>
     */
    public static function hrLegacySlugs(): array
    {
        return [
            self::SLUG_HR,
            'recruiter',
            'recruitment_officer',
            'personnel_manager',
            'admin_hr_officer',
            'admin_hr_technician',
            'integration_lead',
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionSlugsFor(string $key): array
    {
        $slugs = match ($key) {
            self::MANAGER => self::managerPermissionSlugs(),
            self::HR => self::hrPermissionSlugs(),
            default => self::memberPermissionSlugs(),
        };

        return SystemReservedPermissions::filter($slugs);
    }

    /**
     * @return list<string>
     */
    public static function memberPermissionSlugs(): array
    {
        return [
            'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
            'documents.view', 'documents.download.standard',
            'training.view',
            'personnel.profile.view',
            'operational.board.view',
            'organization.orbat.view',
            'operations.tactical.view',
            'operations.missions.view',
        ];
    }

    /**
     * @return list<string>
     */
    public static function hrPermissionSlugs(): array
    {
        return array_values(array_unique(array_merge(self::memberPermissionSlugs(), [
            'admin.backoffice.view',
            'admin.members.view',
            'admin.members.manage',
            'admin.members.invite',
            'admin.members.moderate',
            'invitations.send',
            'dashboard.pins.manage',
            'personnel.profile.update',
            'personnel.sensitive.view',
            'personnel.grades.manage',
            'personnel.assignments.manage',
            'personnel.status.manage',
            'personnel.badges.manage',
            'personnel.directory.export',
            'personnel.member_number.manage',
            'personnel.callsign.manage',
            'personnel.progression.view',
            'organization.recruitment.manage',
            'organization.recruitment.openings.manage',
            'organization.effectifs.hub.view',
            'organization.job_roles.referential.manage',
            'member_integration.view',
            'member_integration.manage',
            'member_integration.assign',
            'member_integration.note',
            'member_integration.template_manage',
        ])));
    }

    /**
     * @return list<string>
     */
    public static function managerPermissionSlugs(): array
    {
        return array_values(array_diff(
            TenantPermissionCatalog::allSlugs(),
            TenantRolePermissionPresetService::EXCLUDED_SLUGS
        ));
    }
}
