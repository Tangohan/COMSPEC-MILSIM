<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Advancement\AdvancementWorkflowService;
use App\Services\Cron\CronJobInterface;

/**
 * Avancement à l'ancienneté : pas de dossier, la ligne d'historique est créée à l'échéance.
 */
final class AdvancementSeniorityCronJob implements CronJobInterface
{
    public function __construct(
        private AdvancementWorkflowService $workflow,
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
        return 'Attribue le grade suivant lorsque le temps minimum est atteint et que la voie ancienneté est ouverte, puis notifie le personnel.';
    }

    public function run(): array
    {
        $out = $this->workflow->applySeniorityAll();
        $promoted = (int) ($out['promoted'] ?? 0);

        return [
            'ok' => true,
            'summary' => $promoted . ' avancement' . ($promoted > 1 ? 's' : '') . ' à l’ancienneté.',
            'details' => $out,
        ];
    }
}
