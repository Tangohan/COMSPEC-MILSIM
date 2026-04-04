<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PermissionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, module, scope FROM permissions WHERE tenant_id = ? ORDER BY module ASC, slug ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Permissions globales (rôles site). */
    /** @return list<array<string, mixed>> */
    public function allGlobalSite(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, name, slug, module, scope FROM permissions WHERE tenant_id IS NULL ORDER BY module ASC, slug ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<int> Permission IDs pour un rôle. */
    public function getPermissionIdsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function setPermissionsForRole(int $roleId, array $permissionIds): void
    {
        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
        $stmt = $this->pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($permissionIds as $pid) {
            $stmt->execute([$roleId, (int) $pid]);
        }
    }
}
