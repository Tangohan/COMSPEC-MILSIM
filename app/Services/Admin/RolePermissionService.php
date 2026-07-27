<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Authorization\SystemReservedPermissions;
use App\Repositories\RoleRepository;
use App\Repositories\PermissionRepository;

class RolePermissionService
{
    public function __construct(
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository
    ) {}

    /** @return list<array<string, mixed>> Rôles du tenant (système et métier). */
    public function listRoles(int $tenantId): array
    {
        return $this->roleRepository->allForTenant($tenantId);
    }

    /** Communauté + intra uniquement (admin organisation). */
    /** @return list<array<string, mixed>> */
    public function listOrganizationRoles(int $tenantId): array
    {
        return $this->roleRepository->forTenantOrganization($tenantId);
    }

    /** @return list<array<string, mixed>> */
    public function listSiteRoles(): array
    {
        return $this->roleRepository->allSiteRoles();
    }

    /** @return list<array<string, mixed>> */
    public function listOrganizationRolesByLayer(int $tenantId, string $layer): array
    {
        return $this->roleRepository->forTenantByLayer($tenantId, $layer);
    }

    /** @return list<int> IDs des permissions du rôle. */
    public function getPermissionIdsForRole(int $roleId): array
    {
        return $this->permissionRepository->getPermissionIdsForRole($roleId);
    }

    /** Met à jour les permissions d'un rôle (vérifier is_locked côté contrôleur). */
    public function setPermissionsForRole(int $roleId, array $permissionIds): void
    {
        $this->permissionRepository->setPermissionsForRole($roleId, $permissionIds);
    }

    /**
     * Remplace les permissions d’un rôle **strictement** rattaché à la communauté (couches communauté / intra).
     * Refuse les rôles site ou sans lien avec le tenant.
     *
     * Les identifiants sont en outre restreints aux permissions **de ce tenant**, puis débarrassés
     * des habilitations réservées à la plateforme ({@see SystemReservedPermissions}). Cette barrière
     * est portée par le service et non par ses appelants : un nouveau formulaire, une API ou un
     * script ne peut pas la contourner par omission.
     *
     * @param list<int> $permissionIds
     * @throws \InvalidArgumentException si le rôle est hors périmètre
     */
    public function setPermissionsForOrganizationTenantRole(int $tenantId, int $roleId, array $permissionIds): void
    {
        if (!$this->roleRepository->canAssignInTenantAdminContext($roleId, $tenantId)) {
            throw new \InvalidArgumentException('Ce rôle ne relève pas de votre communauté ou est réservé à la plateforme. Il ne peut pas être modifié depuis cet espace.');
        }
        $this->permissionRepository->setPermissionsForRole(
            $roleId,
            $this->assignablePermissionIdsForTenant($tenantId, $permissionIds)
        );
    }

    /**
     * Restreint une liste d’identifiants de permission à ce qui est attribuable dans une communauté :
     * la permission doit appartenir au tenant et ne pas être réservée à la plateforme.
     *
     * @param list<int> $permissionIds
     * @return list<int>
     */
    public function assignablePermissionIdsForTenant(int $tenantId, array $permissionIds): array
    {
        $requested = array_values(array_unique(array_filter(
            array_map('intval', $permissionIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($requested === []) {
            return [];
        }

        $allowed = [];
        foreach ($this->permissionRepository->allForTenant($tenantId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            $slug = (string) ($row['slug'] ?? '');
            if ($id > 0 && !SystemReservedPermissions::isReserved($slug)) {
                $allowed[$id] = true;
            }
        }

        return array_values(array_filter($requested, static fn (int $id): bool => isset($allowed[$id])));
    }

    public function isRoleLocked(int $roleId): bool
    {
        $role = $this->roleRepository->findById($roleId);
        return $role && !empty($role['is_locked']);
    }

    public function isSystemRole(int $roleId): bool
    {
        $role = $this->roleRepository->findById($roleId);
        return $role && !empty($role['is_system']);
    }
}
