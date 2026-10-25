<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Platform\PlanEndWarningService;

/**
 * Prévient par e-mail les gestionnaires d'une communauté dont l'essai fondateur, la formule offerte (si activé
 * dans Administration > Formules offertes) ou la période payée par PayPal (toujours, N jours avant et la veille)
 * se termine bientôt.
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
        return 'E-mail aux gestionnaires avant la fin d’une formule payée par PayPal (N jours avant et la veille), et d’un essai fondateur ou d’une formule offerte (si activé).';
    }

    public function run(): array
    {
        $r = ($this->service ??= new PlanEndWarningService())->sendWarnings();
        return [
            'ok' => true,
            'summary' => sprintf('%d communauté(s) prévenue(s), %d e-mail(s) envoyé(s)', $r['tenants'], $r['emails']),
            'details' => $r,
        ];
    }
}
