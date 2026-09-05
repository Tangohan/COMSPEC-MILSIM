<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SystemReservedPermissions;
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

    /** @return list<array<string, mixed>> Droits communautaires pouvant faire l’objet d’une demande. */
    public function allRequestableForTenant(int $tenantId): array
    {
        return array_values(array_filter(
            $this->allForTenant($tenantId),
            static fn (array $permission): bool => !SystemReservedPermissions::isReserved((string) ($permission['slug'] ?? ''))
        ));
    }

    /** @param list<int> $permissionIds */
    public function grantToUser(int $tenantId, int $userId, array $permissionIds, ?int $actorUserId = null): void
    {
        if ($permissionIds === []) {
            return;
        }
        $allowed = [];
        foreach ($this->allRequestableForTenant($tenantId) as $permission) {
            $allowed[(int) ($permission['id'] ?? 0)] = true;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_permission_overrides
                (tenant_id, user_id, permission_id, grant_flag, org_unit_id, reason, created_by_user_id, created_at)
             VALUES (?, ?, ?, 1, NULL, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE grant_flag = 1, reason = VALUES(reason), created_by_user_id = VALUES(created_by_user_id)'
        );
        foreach (array_values(array_unique(array_map('intval', $permissionIds))) as $permissionId) {
            if ($permissionId > 0 && isset($allowed[$permissionId])) {
                $stmt->execute([$tenantId, $userId, $permissionId, 'Demande d’élévation approuvée', $actorUserId]);
            }
        }
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
