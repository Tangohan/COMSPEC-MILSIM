<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelJobRoleRepository;
use PDO;

/**
 * Initialise le référentiel d’emplois à partir des unités déjà présentes — pas d’un catalogue semé.
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
        $sync = new UnitJobRoleSyncService($pdo, $this->jobRoleRepository);
        $sync->ensureOrganisationCategory($tenantId);
        $sync->backfillFromUnits($tenantId);
    }
}
