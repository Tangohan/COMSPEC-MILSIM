<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\TenantRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\SeniorityDossierInferenceSyncService;
use App\Services\Personnel\SeniorityEnrollmentBootstrapService;
use App\Services\Personnel\SeniorityTenantDefaultsService;

/**
 * Recalcule les périodes d’ancienneté (communauté + inférence dossier) pour tous les membres actifs.
 */
final class SenioritySyncCronJob implements CronJobInterface
{
    public function __construct(
        private TenantRepository $tenants,
        private SeniorityTenantDefaultsService $tenantDefaults,
        private SeniorityEnrollmentBootstrapService $enrollmentBootstrap,
        private SeniorityDossierInferenceSyncService $dossierInference,
    ) {}

    public function key(): string
    {
        return 'seniority_sync_all';
    }

    public function label(): string
    {
        return 'Ancienneté (synchronisation)';
    }

    public function description(): string
    {
        return 'Aligne l’ancienneté dans la communauté et les périodes dérivées du dossier pour tous les membres actifs de chaque organisation.';
    }

    public function run(): array
    {
        $tenantsDone = 0;
        $members = 0;
        $inserted = 0;
        $updated = 0;
        $inferenceInserted = 0;
        $inferenceUpdated = 0;
        $errors = 0;
        $details = [];

        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            try {
                $this->tenantDefaults->ensureStandardPack($tenantId);
                $boot = $this->enrollmentBootstrap->syncTenureCommunityForAllActiveMembers($tenantId);
                $inf = $this->dossierInference->syncForAllActiveMembers($tenantId);
                ++$tenantsDone;
                $members += (int) ($boot['members'] ?? 0);
                $inserted += (int) ($boot['inserted'] ?? 0);
                $updated += (int) ($boot['updated'] ?? 0);
                $inferenceInserted += (int) ($inf['inserted'] ?? 0);
                $inferenceUpdated += (int) ($inf['updated'] ?? 0);
            } catch (\Throwable $e) {
                ++$errors;
                $details[] = 'tenant ' . $tenantId . ': ' . $e->getMessage();
            }
        }

        return [
            'ok' => $errors === 0,
            'summary' => sprintf(
                '%d communauté(s), %d membre(s) : communauté +%d/~%d, dossier +%d/~%d, %d erreur(s).',
                $tenantsDone,
                $members,
                $inserted,
                $updated,
                $inferenceInserted,
                $inferenceUpdated,
                $errors
            ),
            'details' => $details,
        ];
    }
}
