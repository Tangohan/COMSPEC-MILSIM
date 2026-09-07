<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Effectifs\PersonnelAutoAdvancementService;

final class PersonnelAutoAdvancementCronJob implements CronJobInterface
{
    public function __construct(
        private PersonnelAutoAdvancementService $advancement,
    ) {}

    public function key(): string
    {
        return 'personnel_auto_advancement';
    }

    public function label(): string
    {
        return 'Avancements d’effectifs';
    }

    public function description(): string
    {
        return 'Propose ou applique les avancements de grade selon l’ancienneté, uniquement si la communauté l’a activé.';
    }

    public function run(): array
    {
        $stats = $this->advancement->evaluateAllActiveTenants(true);
        $summary = sprintf(
            '%d communauté(s), %d dossier(s) éligible(s), %d proposition(s), %d avancement(s) appliqué(s).',
            $stats['tenants'],
            $stats['eligible'],
            $stats['proposed'],
            $stats['applied']
        );

        return [
            'ok' => true,
            'summary' => $summary,
            'details' => [
                'tenants' => $stats['tenants'],
                'eligible' => $stats['eligible'],
                'proposed' => $stats['proposed'],
                'applied' => $stats['applied'],
                'skipped' => $stats['skipped'],
            ],
        ];
    }
}
