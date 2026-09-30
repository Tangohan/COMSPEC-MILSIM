<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Advancement\SeniorityAdvancementService;

final class PersonnelAutoAdvancementCronJob implements CronJobInterface
{
    public function __construct(
        private SeniorityAdvancementService $advancement,
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
        return 'Attribue les grades à l’échéance statutaire depuis l’historique et les qualifications valides.';
    }

    public function run(): array
    {
        $stats = $this->advancement->run();
        $summary = sprintf(
            '%d dossier(s) évalué(s), %d avancement(s) appliqué(s), %d ignoré(s).',
            $stats['evaluated'],
            $stats['promoted'],
            $stats['skipped']
        );

        return [
            'ok' => true,
            'summary' => $summary,
            'details' => [
                'evaluated' => $stats['evaluated'],
                'promoted' => $stats['promoted'],
                'skipped' => $stats['skipped'],
            ],
        ];
    }
}
