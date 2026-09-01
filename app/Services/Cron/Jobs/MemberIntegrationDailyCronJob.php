<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\TenantRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\MemberIntegration\MemberIntegrationAutomationService;
use Throwable;

final class MemberIntegrationDailyCronJob implements CronJobInterface
{
    public function __construct(
        private TenantRepository $tenants,
        private MemberIntegrationAutomationService $automation,
    ) {}

    public function key(): string
    {
        return 'member_integration_daily';
    }

    public function label(): string
    {
        return 'Intégration des nouveaux membres';
    }

    public function description(): string
    {
        return 'Met à jour les parcours d’intégration et relance les étapes en retard.';
    }

    public function run(): array
    {
        $tenants = $this->tenants->listBasicAll();
        $refreshed = 0;
        $reminded = 0;
        $errors = 0;
        foreach ($tenants as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            try {
                $out = $this->automation->runDaily($tenantId);
                $refreshed += (int) ($out['refreshed'] ?? 0);
                $reminded += (int) ($out['reminded'] ?? 0);
            } catch (Throwable) {
                $errors++;
            }
        }

        return [
            'ok' => $errors === 0,
            'summary' => $refreshed . ' parcours mis à jour, ' . $reminded . ' relances.',
            'details' => ['refreshed' => $refreshed, 'reminded' => $reminded, 'errors' => $errors],
        ];
    }
}
