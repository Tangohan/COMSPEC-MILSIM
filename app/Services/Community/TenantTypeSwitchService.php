<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Rbac\CommunityAccessCollapseService;
use App\Services\Rbac\CommunityAccessProfiles;
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
     * @return array{from: string, to: string, changed: bool, reapplied: bool, access_granted?: int, access_revoked?: int}
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
            $access = $this->ensureTypePermissionsAndRoles($pdo, $tenantId, $newType);

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
            'access_granted' => $access['granted'],
            'access_revoked' => $access['revoked'],
        ];
    }

    /**
     * @return array{granted: int, revoked: int}
     */
    private function ensureTypePermissionsAndRoles(PDO $pdo, int $tenantId, string $tenantType): array
    {
        return TenantSeedHelper::alignAccessProfilesForType($pdo, $tenantId, $tenantType);
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
