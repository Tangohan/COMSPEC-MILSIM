<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\TenantRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\PhaseRules\PhaseTransitionService;

final class PersonnelPhaseAutoCronJob implements CronJobInterface
{
    public function __construct(
        private TenantRepository $tenants,
        private PhaseTransitionService $transitions,
    ) {}

    public function key(): string
    {
        return 'personnel_phase_auto';
    }

    public function label(): string
    {
        return 'Passages d’étape automatiques';
    }

    public function description(): string
    {
        return 'Pour chaque membre éligible dont le passage est automatique, applique l’étape suivante. Un échec est signalé au staff.';
    }

    public function run(): array
    {
        $applied = 0;
        $skipped = 0;
        $errors = 0;
        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $stats = $this->transitions->runAutomaticForTenant($tenantId);
            $applied += (int) ($stats['applied'] ?? 0);
            $skipped += (int) ($stats['skipped'] ?? 0);
            $errors += (int) ($stats['errors'] ?? 0);
        }

        return [
            'ok' => true,
            'summary' => "Passages : {$applied} · Ignorés : {$skipped} · Erreurs : {$errors}",
            'details' => [
                'applied' => $applied,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ];
    }
}
