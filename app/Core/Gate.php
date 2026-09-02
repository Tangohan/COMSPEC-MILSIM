<?php

declare(strict_types=1);

namespace App\Core;

use App\Authorization\PermissionImplication;

class Gate
{
    private static ?self $instance = null;

    /** @var list<string> */
    private array $permissions = [];

    /**
     * Permissions à périmètre unitaire : slug => liste d’IDs d’unités où le droit s’applique.
     *
     * @var array<string, list<int>>
     */
    private array $unitPermissionMap = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
        $this->unitPermissionMap = [];
    }

    /**
     * @param list<string> $flatPermissions
     * @param array<string, list<int>> $unitSlugToUnitIds
     */
    public function setFullRbacState(array $flatPermissions, array $unitSlugToUnitIds): void
    {
        $this->permissions = $flatPermissions;
        $this->unitPermissionMap = [];
        foreach ($unitSlugToUnitIds as $slug => $ids) {
            $slug = (string) $slug;
            if ($slug === '') {
                continue;
            }
            $clean = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn (int $x): bool => $x > 0)));
            if ($clean !== []) {
                $this->unitPermissionMap[$slug] = $clean;
            }
        }
    }

    /** @return array<string, list<int>> */
    public function getUnitPermissionMap(): array
    {
        return $this->unitPermissionMap;
    }

    public function allows(string $permission): bool
    {
        // This bypass is request-local and is enabled only after AuthMiddleware has
        // reloaded the real platform administrator and explicitly verified admin.system.
        if (\App\Services\Tenant\TenantContext::isIntervention()) {
            return true;
        }
        return PermissionImplication::isGranted($this->permissions, $permission);
    }

    /**
     * Vérifie un droit tenant/global (union plate) ou, à défaut, un droit réservé au périmètre d’une unité.
     */
    public function allowsWithUnitContext(string $permission, ?int $unitId): bool
    {
        if ($this->allows($permission)) {
            return true;
        }
        if ($unitId === null || $unitId <= 0) {
            return false;
        }
        $uids = $this->unitPermissionMap[$permission] ?? [];

        return in_array($unitId, $uids, true);
    }

    public function deny(string $permission): bool
    {
        return !$this->allows($permission);
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        return $this->permissions;
    }
}
