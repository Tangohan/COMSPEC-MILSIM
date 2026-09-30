<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\AdvancementSeniorityService;

final class AdvancementSeniorityCronJob implements CronJobInterface
{
    public function __construct(
        private AdvancementSeniorityService $seniority,
    ) {
    }

    public function key(): string
    {
        return 'advancement_seniority';
    }

    public function label(): string
    {
        return 'Avancement à l’ancienneté';
    }

    public function description(): string
    {
        return 'Attribue automatiquement le grade suivant lorsque le temps de grade et les qualifications requises sont réunis, sans commission.';
    }

    public function run(): array
    {
        $stats = $this->seniority->promoteEligible(null, true);

        return [
            'ok' => true,
            'summary' => sprintf(
                '%d communauté(s), %d avancement(s) à l’ancienneté, %d dossier(s) non éligible(s).',
                $stats['tenants'],
                $stats['promoted'],
                $stats['skipped']
            ),
            'details' => $stats,
        ];
    }
}
