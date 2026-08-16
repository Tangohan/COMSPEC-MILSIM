<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Core\Database;
use App\Services\Cron\CronJobInterface;
use App\Services\Sse\SseAnalyticalEngineService;
use App\Services\Sse\SseAnalystDigestService;

/**
 * Pipeline nocturne SSE : propose, ne décide jamais.
 *
 * INGESTION → NORMALISATION → DÉDOUBLONNAGE → CORRÉLATION → CONTRADICTIONS
 * → SCORING → ALERTES → SYNTHÈSE → digest e-mail (si du nouveau)
 */
final class SseAnalyticalNightlyCronJob implements CronJobInterface
{
    public function __construct(
        private SseAnalyticalEngineService $engine,
        private ?SseAnalystDigestService $digest = null,
        private ?Database $db = null,
    ) {
        $this->db ??= Database::getInstance();
    }

    public function key(): string
    {
        return 'sse_analytical_nightly';
    }

    public function label(): string
    {
        return 'Moteur analytique SSE (nuit)';
    }

    public function description(): string
    {
        return 'Produit des rapprochements possibles/probables, signaux et scores de complétude, puis envoie le digest e-mail du jour. Aucune fusion automatique.';
    }

    public function run(): array
    {
        $tenantIds = [];
        try {
            $rows = $this->db->fetchAll('SELECT DISTINCT tenant_id AS id FROM sse_cases WHERE tenant_id > 0');
            foreach ($rows as $row) {
                $tenantIds[] = (int) $row['id'];
            }
        } catch (\Throwable) {
            try {
                $rows = $this->db->fetchAll('SELECT id FROM tenants WHERE id > 1 ORDER BY id ASC LIMIT 200');
                foreach ($rows as $row) {
                    $tenantIds[] = (int) $row['id'];
                }
            } catch (\Throwable) {
                return [
                    'ok' => false,
                    'summary' => 'Impossible de lister les unités.',
                    'details' => [],
                ];
            }
        }

        $tenantIds = array_values(array_unique(array_filter($tenantIds)));
        $summaries = [];
        $totalSuggestions = 0;
        $totalSignals = 0;
        $ok = true;

        foreach ($tenantIds as $tenantId) {
            try {
                $result = $this->engine->runForTenant($tenantId);
                $summaries[] = 'U' . $tenantId . ': ' . ($result['summary'] ?? '');
                $totalSuggestions += (int) ($result['details']['suggestions'] ?? 0);
                $totalSignals += (int) ($result['details']['signals'] ?? 0);
                if (empty($result['ok'])) {
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $ok = false;
                $summaries[] = 'U' . $tenantId . ': erreur ' . $e->getMessage();
            }
        }

        $mailSummary = '';
        if ($this->digest !== null) {
            try {
                $mail = $this->digest->runAllTenants();
                $mailSummary = ' · mail ' . (string) ($mail['summary'] ?? '');
            } catch (\Throwable $e) {
                $mailSummary = ' · mail erreur ' . $e->getMessage();
            }
        }

        return [
            'ok' => $ok,
            'summary' => sprintf(
                'Unités %d · suggestions cumulées %d · signaux %d%s',
                count($tenantIds),
                $totalSuggestions,
                $totalSignals,
                $mailSummary
            ),
            'details' => [
                'tenants' => count($tenantIds),
                'suggestions' => $totalSuggestions,
                'signals' => $totalSignals,
                'per_tenant' => $summaries,
            ],
        ];
    }
}
