<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Repositories\PermissionRepository;
use App\Repositories\RolePermissionMatrixRepository;
use App\Repositories\RoleRepository;
use App\Services\Admin\RolePermissionService;

/**
 * Synchronise la matrice métier avec les habilitations granulaires (role_permissions).
 */
final class RolePermissionMatrixService
{
    public function __construct(
        private ?RolePermissionMatrixRepository $matrix = null,
        private ?RoleRepository $roles = null,
        private ?PermissionRepository $permissions = null,
        private ?RolePermissionService $rolePermissions = null,
    ) {
        $this->matrix ??= new RolePermissionMatrixRepository();
        $this->roles ??= new RoleRepository();
        $this->permissions ??= new PermissionRepository();
        $this->rolePermissions ??= new RolePermissionService($this->roles, $this->permissions);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string}
     */
    public function saveRoleMatrix(int $tenantId, int $roleId, array $payload, bool $syncPermissions = true): array
    {
        if (!$this->roles->canAssignInTenantAdminContext($roleId, $tenantId)) {
            return ['ok' => false, 'error' => 'Ce rôle ne peut pas être modifié depuis votre espace.'];
        }
        if ($this->rolePermissions->isRoleLocked($roleId) && empty($payload['force'])) {
            return ['ok' => false, 'error' => 'Ce rôle est verrouillé et ne peut pas être modifié.'];
        }

        $saved = $this->matrix->saveRoleRow($tenantId, $roleId, $payload);
        if (!$saved) {
            return ['ok' => false, 'error' => 'Enregistrement impossible.'];
        }
        if ($syncPermissions) {
            $this->syncGranularPermissionsFromMatrix($tenantId, $roleId);
        }

        return ['ok' => true];
    }

    public function syncGranularPermissionsFromMatrix(int $tenantId, int $roleId): void
    {
        $data = $this->matrix->listMatrix($tenantId);
        $row = null;
        foreach ($data['rows'] as $candidate) {
            if ((int) ($candidate['id'] ?? 0) === $roleId) {
                $row = $candidate;
                break;
            }
        }
        if ($row === null) {
            return;
        }

        $slugs = [];
        foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
            $level = (string) ($row['modules'][$moduleKey]['access_level'] ?? RolePermissionMatrixCatalog::LEVEL_NONE);
            $slugs = array_merge($slugs, RolePermissionMatrixCatalog::permissionSlugsForModuleLevel($moduleKey, $level));
        }
        $slugs = array_merge(
            $slugs,
            RolePermissionMatrixCatalog::transversalPermissionSlugs(
                !empty($row['can_delete']),
                !empty($row['can_export'])
            )
        );
        $slugs = array_values(array_unique(array_filter($slugs)));

        $tenantRows = $this->permissions->allForTenant($tenantId);
        $slugToId = [];
        foreach ($tenantRows as $perm) {
            $slug = (string) ($perm['slug'] ?? '');
            if ($slug !== '') {
                $slugToId[$slug] = (int) ($perm['id'] ?? 0);
            }
        }

        $ids = [];
        foreach ($slugs as $slug) {
            if (isset($slugToId[$slug]) && $slugToId[$slug] > 0) {
                $ids[] = $slugToId[$slug];
            }
        }

        if ($ids !== []) {
            $this->rolePermissions->setPermissionsForOrganizationTenantRole($tenantId, $roleId, $ids);
        }
    }

    /**
     * Vérifie si un utilisateur dispose d'un niveau minimal sur un module métier.
     *
     * @param list<string> $userPermissionSlugs
     */
    public function userMeetsModuleLevel(array $userPermissionSlugs, string $moduleKey, string $requiredLevel): bool
    {
        $requiredLevel = RolePermissionMatrixCatalog::normalizeAccessLevel($requiredLevel);
        if ($requiredLevel === RolePermissionMatrixCatalog::LEVEL_NONE) {
            return true;
        }

        $rank = [
            RolePermissionMatrixCatalog::LEVEL_NONE => 0,
            RolePermissionMatrixCatalog::LEVEL_SA_FICHE => 1,
            RolePermissionMatrixCatalog::LEVEL_LECTURE => 2,
            RolePermissionMatrixCatalog::LEVEL_INSTRUCTION => 2,
            RolePermissionMatrixCatalog::LEVEL_PARTIEL => 3,
            RolePermissionMatrixCatalog::LEVEL_SON_GROUPE => 4,
            RolePermissionMatrixCatalog::LEVEL_SA_SECTION => 5,
            RolePermissionMatrixCatalog::LEVEL_COMPLET => 6,
        ];

        $requiredRank = $rank[$requiredLevel] ?? 0;
        foreach (RolePermissionMatrixCatalog::accessLevelKeys() as $levelKey) {
            if (($rank[$levelKey] ?? 0) < $requiredRank) {
                continue;
            }
            $needed = RolePermissionMatrixCatalog::permissionSlugsForModuleLevel($moduleKey, $levelKey);
            foreach ($needed as $slug) {
                if (in_array($slug, $userPermissionSlugs, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Contrôle d'accès unifié pour les nouveaux modules (ATAK, AAR, RSVP, réglages).
     */
    public function canAccessModuleFeature(array $userPermissionSlugs, string $moduleKey, string $action = 'view'): bool
    {
        $action = strtolower(trim($action));
        $required = match ($action) {
            'manage', 'write', 'edit' => RolePermissionMatrixCatalog::LEVEL_COMPLET,
            'section' => RolePermissionMatrixCatalog::LEVEL_SA_SECTION,
            'export' => RolePermissionMatrixCatalog::LEVEL_LECTURE,
            default => RolePermissionMatrixCatalog::LEVEL_LECTURE,
        };

        if ($this->userMeetsModuleLevel($userPermissionSlugs, $moduleKey, $required)) {
            return true;
        }

        $fallback = match ($moduleKey) {
            RolePermissionMatrixCatalog::MODULE_ATAK => ['admin.system', 'admin.organization', 'admin.access', 'atak.terminals.manage'],
            RolePermissionMatrixCatalog::MODULE_OPERATIONS => ['operations.missions.manage', 'admin.organization', 'admin.access'],
            RolePermissionMatrixCatalog::MODULE_MEMBERS => ['admin.members.manage', 'admin.organization', 'admin.access'],
            RolePermissionMatrixCatalog::MODULE_FINANCES => ['finances.manage', 'admin.organization', 'admin.access'],
            RolePermissionMatrixCatalog::MODULE_SYSTEMS => ['admin.settings.manage', 'admin.organization', 'admin.access'],
            default => ['admin.access'],
        };

        foreach ($fallback as $slug) {
            if (in_array($slug, $userPermissionSlugs, true)) {
                return true;
            }
        }

        return false;
    }
}
