<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\TenantRepository;
use PDO;

/**
 * Change le profil d’une communauté (Complet / Effectifs / ATAK)
 * et aligne permissions / rôles / seeds minimaux.
 */
final class TenantTypeSwitchService
{
    public function __construct(
        private TenantRepository $tenantRepository
    ) {}

    /**
     * @return array{from: string, to: string, changed: bool, reapplied: bool}
     */
    public function switchType(int $tenantId, string $newType, bool $forceReapply = false): array
    {
        $newType = TenantTypeConfig::normalizeType($newType);
        $tenant = $this->tenantRepository->findById($tenantId);
        if ($tenant === null) {
            throw new \InvalidArgumentException('Communauté introuvable.');
        }

        $from = TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full'));
        $typeUnchanged = $from === $newType;
        if ($typeUnchanged && !$forceReapply) {
            return ['from' => $from, 'to' => $newType, 'changed' => false, 'reapplied' => false];
        }

        $pdo = Database::getPdo();
        $pdo->beginTransaction();
        try {
            if (!$typeUnchanged) {
                $this->tenantRepository->updateTenantType($tenantId, $newType);
            } else {
                // Réaffirmer la valeur en base (réparation après migration / backfill « full »).
                $this->tenantRepository->updateTenantType($tenantId, $newType);
            }
            $this->ensureTypePermissionsAndRoles($pdo, $tenantId, $newType);

            if ($newType === TenantTypeConfig::TYPE_FULL) {
                $this->ensureFullSeeds($pdo, $tenantId);
            } elseif ($newType === TenantTypeConfig::TYPE_EFFECTIFS) {
                TenantSeedHelper::seedForumAndRoles($pdo, $tenantId);
                TenantSeedHelper::ensureOrganizationForumSection($pdo, $tenantId);
                TenantSeedHelper::ensurePersonnelPanelsAndMatricule($pdo, $tenantId);
            } elseif ($newType === TenantTypeConfig::TYPE_ATAK) {
                TenantSeedHelper::seedForumAndRoles($pdo, $tenantId);
                TenantSeedHelper::ensureOrganizationForumSection($pdo, $tenantId);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'from' => $from,
            'to' => $newType,
            'changed' => !$typeUnchanged,
            'reapplied' => true,
        ];
    }

    private function ensureTypePermissionsAndRoles(PDO $pdo, int $tenantId, string $tenantType): void
    {
        $permissions = TenantTypeConfig::basePermissionsByType()[$tenantType] ?? [];
        $roles = TenantTypeConfig::baseRolesByType()[$tenantType] ?? [];

        $permIds = [];
        foreach ($permissions as $p) {
            $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$tenantId, $p['slug']]);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                $permIds[$p['slug']] = (int) $existing;
                continue;
            }
            $ins = $pdo->prepare(
                'INSERT INTO permissions (tenant_id, name, slug, module, scope, rbac_scope, created_at) VALUES (?, ?, ?, ?, \'community\', \'tenant\', NOW())'
            );
            $ins->execute([$tenantId, $p['name'], $p['slug'], $p['module']]);
            $permIds[$p['slug']] = (int) $pdo->lastInsertId();
        }

        foreach ($roles as $r) {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$tenantId, $r['slug']]);
            $roleId = (int) ($stmt->fetchColumn() ?: 0);
            if ($roleId < 1) {
                $pdo->prepare(
                    'INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $tenantId,
                    $r['name'],
                    $r['slug'],
                    $r['description'],
                    $r['is_system'],
                    $r['is_locked'],
                    $r['role_layer'],
                ]);
                $roleId = (int) $pdo->lastInsertId();
            }
            foreach ($permIds as $pid) {
                $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                $link->execute([$roleId, $pid]);
            }
        }

        // Propriétaire / admin communauté : s’assurer qu’ils ont les permissions du profil.
        foreach (['community_owner', 'tenant_admin'] as $govSlug) {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$tenantId, $govSlug]);
            $govRoleId = (int) ($stmt->fetchColumn() ?: 0);
            if ($govRoleId < 1) {
                continue;
            }
            foreach ($permIds as $pid) {
                $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                $link->execute([$govRoleId, $pid]);
            }
        }

        if ($tenantType === TenantTypeConfig::TYPE_FULL) {
            TenantSeedHelper::ensureSystemAdminPermissions($pdo, $tenantId);
            TenantSeedHelper::ensureTenantPermissionCatalog($pdo, $tenantId);
        }
    }

    private function ensureFullSeeds(PDO $pdo, int $tenantId): void
    {
        TenantSeedHelper::seedForumAndRoles($pdo, $tenantId);
        TenantSeedHelper::ensureOrganizationForumSection($pdo, $tenantId);
        TenantSeedHelper::seedDocumentsEquipment($pdo, $tenantId);
        TenantSeedHelper::ensureSystemAdminPermissions($pdo, $tenantId);
        TenantSeedHelper::ensureTenantPermissionCatalog($pdo, $tenantId);
        TenantSeedHelper::ensurePersonnelPanelsAndMatricule($pdo, $tenantId);
        (new \App\Services\Personnel\PersonnelJobRoleBootstrapService(
            new \App\Repositories\PersonnelJobRoleRepository()
        ))->ensureDefaultsForTenant($pdo, $tenantId);
    }
}
