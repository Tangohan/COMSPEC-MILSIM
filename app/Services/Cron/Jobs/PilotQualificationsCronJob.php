<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\PilotQualificationRuleRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\PilotQualificationService;
use App\Services\Platform\FeatureGateService;

/**
 * Cursus pilote : attribue ou propose les qualifications de pilote atteintes d'après les heures de vol du mod, et
 * marque « à jour » les qualifications du type sur lequel le membre a revolé.
 */
final class PilotQualificationsCronJob implements CronJobInterface
{
    public function __construct(
        private PilotQualificationRuleRepository $rules,
        private PilotQualificationService $service,
        private FeatureGateService $features,
    ) {
    }

    public function key(): string
    {
        return 'pilot_qualifications';
    }

    public function label(): string
    {
        return 'Cursus pilote';
    }

    public function description(): string
    {
        return 'Attribue ou propose aux instructeurs les qualifications de pilote atteintes d’après les heures de vol relevées en jeu.';
    }

    public function run(): array
    {
        $totals = ['awarded' => 0, 'proposed' => 0, 'practiced' => 0];
        $tenants = 0;
        foreach ($this->rules->tenantsWithRules() as $tenantId) {
            try {
                if (!$this->features->allowsWithCatalogDefault($tenantId, PilotQualificationService::FEATURE)) {
                    continue;
                }
                $r = $this->service->runAuto($tenantId);
            } catch (\Throwable) {
                continue;
            }
            $tenants++;
            foreach ($totals as $k => $_) {
                $totals[$k] += $r[$k];
            }
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                '%d attribuée(s), %d proposée(s) aux instructeurs, %d remise(s) à jour par un vol (%d communauté(s))',
                $totals['awarded'],
                $totals['proposed'],
                $totals['practiced'],
                $tenants
            ),
            'details' => $totals + ['tenants' => $tenants],
        ];
    }
}
