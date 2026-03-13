<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Core\Database;
use App\Core\Gate;
use PDO;

class RbacService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function loadPermissionsForRole(?int $roleId): array
    {
        if (!$roleId) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function setPermissionsForGate(?int $roleId): void
    {
        $permissions = $this->loadPermissionsForRole($roleId);
        Gate::getInstance()->setPermissions($permissions);
    }
}
