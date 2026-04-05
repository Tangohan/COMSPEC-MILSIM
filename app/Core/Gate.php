<?php

declare(strict_types=1);

namespace App\Core;

use App\Authorization\PermissionImplication;

class Gate
{
    private static ?self $instance = null;
    private array $permissions = [];

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
    }

    public function allows(string $permission): bool
    {
        return PermissionImplication::isGranted($this->permissions, $permission);
    }

    public function deny(string $permission): bool
    {
        return !$this->allows($permission);
    }
}
