<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RoleRepository
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
            'SELECT id, tenant_id, name, slug, description, is_system, is_locked, role_layer FROM roles WHERE tenant_id = ? ORDER BY name ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Rôles communauté + intra (exclut toute logique site). */
    /** @return list<array<string, mixed>> */
    public function forTenantOrganization(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, tenant_id, name, slug, description, is_system, is_locked, role_layer FROM roles WHERE tenant_id = ? AND role_layer IN ('community','intra') ORDER BY role_layer DESC, name ASC"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function forTenantByLayer(int $tenantId, string $layer): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, name, slug, description, is_system, is_locked, role_layer FROM roles WHERE tenant_id = ? AND role_layer = ? ORDER BY name ASC'
        );
        $stmt->execute([$tenantId, $layer]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Rôles site globaux (tenant_id NULL). */
    /** @return list<array<string, mixed>> */
    public function allSiteRoles(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, tenant_id, name, slug, description, is_system, is_locked, role_layer FROM roles WHERE tenant_id IS NULL AND role_layer = 'site' ORDER BY name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM roles WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return int|null ID du rôle par slug dans le tenant. */
    public function getIdBySlug(int $tenantId, string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_COLUMN);

        return $row !== false ? (int) $row : null;
    }

    /** Attribution depuis l’admin communauté : pas de rôle site / global. */
    public function canAssignInTenantAdminContext(int $roleId, int $tenantId): bool
    {
        $r = $this->findById($roleId, $tenantId);
        if (!$r) {
            return false;
        }
        $layer = (string) ($r['role_layer'] ?? 'community');

        return $layer === 'community' || $layer === 'intra';
    }
}
