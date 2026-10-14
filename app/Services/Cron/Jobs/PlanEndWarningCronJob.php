<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Platform\PlanEndWarningService;

/**
 * Prévient par e-mail les gestionnaires d'une communauté dont l'essai fondateur ou la formule offerte
 * se termine bientôt (réglages dans Administration > Formules offertes). Une fois par fin.
 */
final class PlanEndWarningCronJob implements CronJobInterface
{
    public function __construct(private ?PlanEndWarningService $service = null)
    {
    }

    public function key(): string
    {
        return 'plan_end_warning';
    }

    public function label(): string
    {
        return 'Fins de formule à venir';
    }

    public function description(): string
    {
        return 'E-mail aux gestionnaires N jours avant la fin d’un essai fondateur ou d’une formule offerte (si activé).';
    }

    public function run(): array
    {
        $r = ($this->service ??= new PlanEndWarningService())->sendWarnings();
        if (!$r['enabled']) {
            return ['ok' => true, 'summary' => 'E-mail d’avertissement désactivé (Administration > Formules offertes).', 'details' => $r];
        }

        return [
            'ok' => true,
            'summary' => sprintf('%d communauté(s) prévenue(s), %d e-mail(s) envoyé(s)', $r['tenants'], $r['emails']),
            'details' => $r,
        ];
    }
}
