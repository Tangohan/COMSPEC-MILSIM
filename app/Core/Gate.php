<?php

declare(strict_types=1);

namespace App\Core;

use App\Authorization\PermissionImplication;

class Gate
{
    private static ?self $instance = null;

    /** @var list<string> */
    private array $permissions = [];

    private bool $platformAdmin = false;

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

    /** À appeler au début de chaque requête HTTP (les workers PHP réutilisent sinon l’état précédent). */
    public static function reset(): void
    {
        if (self::$instance === null) {
            return;
        }
        self::$instance->setPermissions([]);
        self::$instance->setPlatformAdmin(false);
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
        $this->unitPermissionMap = [];
        $this->platformAdmin = false;
    }

    public function setPlatformAdmin(bool $enabled): void
    {
        $this->platformAdmin = $enabled;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platformAdmin;
    }

    /**
     * @param list<string> $flatPermissions
     * @param array<string, list<int>> $unitSlugToUnitIds
     */
    public function setFullRbacState(array $flatPermissions, array $unitSlugToUnitIds): void
    {
        $this->permissions = $flatPermissions;
        $this->unitPermissionMap = [];
        $this->platformAdmin = false;
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
        // No identity or request context bypasses the function catalogue.
        if ($this->platformAdmin) {
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
