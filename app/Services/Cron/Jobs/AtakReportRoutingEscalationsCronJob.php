<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\AtakReportRoutingRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Tactical\AtakBridgeModulesService;

final class AtakReportRoutingEscalationsCronJob implements CronJobInterface
{
    public function __construct(
        private AtakReportRoutingRepository $routing,
        private AtakBridgeModulesService $modules,
    ) {}

    public function key(): string
    {
        return 'atak_report_routing_escalations';
    }

    public function label(): string
    {
        return 'Escalade des rapports tactiques';
    }

    public function description(): string
    {
        return 'Route les rapports non acquittés vers les rôles d’escalade configurés.';
    }

    public function run(): array
    {
        $reportIds = [];
        $contexts = 0;
        foreach ($this->routing->listEscalationContexts() as $row) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $contextId = (int) ($row['context_id'] ?? 0);
            if ($tenantId < 1 || $contextId < 1) {
                continue;
            }
            $state = $this->modules->get($tenantId);
            if (($state['modules']['report_routing'] ?? true) !== true) {
                continue;
            }
            $contexts++;
            foreach ($this->routing->processEscalations($tenantId, $contextId) as $reportId) {
                $reportIds[(int) $reportId] = true;
            }
        }

        $count = count($reportIds);

        return [
            'ok' => true,
            'summary' => sprintf('Contextes traités : %d · Rapports escaladés : %d', $contexts, $count),
            'details' => [
                'contexts_processed' => $contexts,
                'reports_escalated' => $count,
                'report_ids' => array_keys($reportIds),
            ],
        ];
    }
}
