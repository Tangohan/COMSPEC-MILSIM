<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\PersonnelProgressionEvaluator;

final class PersonnelProgressionCronJob implements CronJobInterface
{
    public function __construct(
        private PersonnelProgressionEvaluator $evaluator,
    ) {}

    public function key(): string
    {
        return 'personnel_progression_evaluate';
    }

    public function label(): string
    {
        return 'Progression des personnels';
    }

    public function description(): string
    {
        return 'Évalue l’éligibilité des parcours de carrière (idempotent) et prépare les demandes de validation.';
    }

    public function run(): array
    {
        $stats = $this->evaluator->evaluateAllActiveTenants();
        $summary = sprintf(
            '%d communauté(s), %d membership(s), %d demande(s) créée(s), %d ignoré(s).',
            $stats['tenants'],
            $stats['memberships'],
            $stats['requests_created'],
            $stats['skipped']
        );

        return [
            'ok' => true,
            'summary' => $summary,
            'details' => $stats['details'],
        ];
    }
}
