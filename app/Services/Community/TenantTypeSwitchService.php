<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Rbac\CommunityAccessCollapseService;
use App\Services\Rbac\CommunityAccessProfiles;
use App\Support\SqlText;
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

            try {
                (new CommunityAccessCollapseService(
                    $pdo,
                    new RoleRepository(),
                    new UserRepository()
                ))->collapseTenant($tenantId);
            } catch (\Throwable) {
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

        $permIds = [];
        $slugEq = SqlText::equals($pdo, 'slug');
        foreach ($permissions as $p) {
            $stmt = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1');
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

        TenantSeedHelper::ensureAccessProfilesForTenant($pdo, $tenantId);

        foreach (CommunityAccessProfiles::slugs() as $govSlug) {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1');
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
