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
        $hasAction = $this->tableHasColumn('permissions', 'action');
        $hasRbacScope = $this->tableHasColumn('permissions', 'rbac_scope');
        $extra = $hasRbacScope ? ', rbac_scope' : '';
        $sql = $hasAction
            ? 'SELECT id, name, slug, module, action, scope' . $extra . ' FROM permissions WHERE tenant_id = ? ORDER BY module ASC, COALESCE(action, \'\') ASC, slug ASC'
            : 'SELECT id, name, slug, module, scope' . $extra . ' FROM permissions WHERE tenant_id = ? ORDER BY module ASC, slug ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Permissions globales (rôles site). */
    /** @return list<array<string, mixed>> */
    public function allGlobalSite(): array
    {
        $hasAction = $this->tableHasColumn('permissions', 'action');
        $hasRbacScope = $this->tableHasColumn('permissions', 'rbac_scope');
        $extra = $hasRbacScope ? ', rbac_scope' : '';
        $sql = $hasAction
            ? 'SELECT id, name, slug, module, action, scope' . $extra . ' FROM permissions WHERE tenant_id IS NULL ORDER BY module ASC, COALESCE(action, \'\') ASC, slug ASC'
            : 'SELECT id, name, slug, module, scope' . $extra . ' FROM permissions WHERE tenant_id IS NULL ORDER BY module ASC, slug ASC';
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool) $stmt->fetchColumn();

        return $cache[$key];
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
