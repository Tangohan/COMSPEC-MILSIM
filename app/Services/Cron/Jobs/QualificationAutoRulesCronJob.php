<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\QualificationAwardRuleRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\QualificationAwardRuleService;

/**
 * Qualifications automatiques : délivre, propose ou renouvelle les qualifications dont les seuils mesurés (heures de
 * jeu, heures de vol par appareil, missions, présences…) sont atteints, et rattache les attestations Academy.
 * Même principe que le cursus pilote ; relancer ne change rien.
 */
final class QualificationAutoRulesCronJob implements CronJobInterface
{
    public function __construct(
        private QualificationAwardRuleRepository $rules,
        private QualificationAwardRuleService $service,
    ) {
    }

    public function key(): string
    {
        return 'qualification_auto_rules';
    }

    public function label(): string
    {
        return 'Qualifications automatiques';
    }

    public function description(): string
    {
        return 'Délivre ou propose les qualifications dont les seuils mesurés en jeu sont atteints, renouvelle celles qui expirent et rattache les attestations Academy.';
    }

    public function run(): array
    {
        $totals = ['awarded' => 0, 'proposed' => 0, 'renewed' => 0, 'linked' => 0];
        $tenants = 0;
        foreach ($this->rules->tenantsWithAutoRules() as $tenantId) {
            try {
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
                '%d délivrée(s), %d proposée(s), %d renouvelée(s), %d attestation(s) Academy rattachée(s) (%d communauté(s))',
                $totals['awarded'],
                $totals['proposed'],
                $totals['renewed'],
                $totals['linked'],
                $tenants
            ),
            'details' => $totals + ['tenants' => $tenants],
        ];
    }
}
