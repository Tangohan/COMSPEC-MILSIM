<?php

declare(strict_types=1);

namespace App\Services\Admin;

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
