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

    /**
     * Permissions issues des rôles site globaux affectés à l’email (site_role_assignments).
     *
     * @return list<string>
     */
    public function loadSitePermissionsForEmail(?string $email): array
    {
        if ($email === null || trim($email) === '') {
            return [];
        }
        $email = strtolower(trim($email));
        $roleIds = $this->fetchSiteRoleIdsForEmail($email);
        if ($roleIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id IN ($placeholders)"
        );
        $stmt->execute($roleIds);
        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($slugs)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $slugs)));
    }

    /** @return list<int> */
    private function fetchSiteRoleIdsForEmail(string $emailNormalized): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sra.role_id FROM site_role_assignments sra
             INNER JOIN roles r ON r.id = sra.role_id AND r.tenant_id IS NULL AND r.role_layer = \'site\'
             WHERE sra.email_normalized = ? AND sra.revoked_at IS NULL'
        );
        $stmt->execute([$emailNormalized]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Fusionne permissions du rôle tenant courant et des rôles site pour l’email.
     */
    public function setPermissionsForGate(?int $roleId, ?string $userEmail = null): void
    {
        $tenantPerms = $this->loadPermissionsForRole($roleId);
        $sitePerms = $this->loadSitePermissionsForEmail($userEmail);
        $merged = array_values(array_unique([...$tenantPerms, ...$sitePerms]));
        Gate::getInstance()->setPermissions($merged);
    }
}
