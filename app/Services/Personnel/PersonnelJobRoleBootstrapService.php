<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Rbac\MilitaryRoleCatalogSyncService;
use PDO;

/**
 * Initialise le référentiel rôles métier (fiche personnel) à partir du catalogue militaire unique.
 */
final class PersonnelJobRoleBootstrapService
{
    public function __construct(
        private PersonnelJobRoleRepository $jobRoleRepository
    ) {}

    public function ensureDefaultsForTenant(PDO $pdo, int $tenantId): void
    {
        if (!$this->jobRoleRepository->tablesExist()) {
            return;
        }
        MilitaryRoleCatalogSyncService::syncForTenant($pdo, $tenantId);
    }
}
