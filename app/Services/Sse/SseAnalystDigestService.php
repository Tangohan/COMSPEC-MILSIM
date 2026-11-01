<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\CronJobRunRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\TenantRepository;
use App\Services\Email\EmailEvents;
use App\Services\Integrations\DiscordEventRelayService;
use App\Core\Database;

/**
 * Point quotidien SSE : rapprochements, signaux, fiches terrain, dossiers d’intérêt.
 * Plus aucun e-mail (quota d'envoi) : le point part seulement sur le salon Discord de la communauté
 * si elle l'a activé (Intégrations > Discord) ; dans le site, la boîte du portail SSE montre les mêmes éléments.
 * Dédupliqué une fois par jour et par communauté via cron_notification_log.
 */
final class SseAnalystDigestService
{
    public const JOB_KEY = 'sse_analyst_digest';

    public function __construct(
        private TenantRepository $tenants,
        private SseSuggestionQueueRepository $queue,
        private CronJobRunRepository $cronLog,
        private ?Database $db = null,
    ) {
        $this->db ??= Database::getInstance();
    }

    /**
     * @return array{
     *   ok:bool,
     *   summary:string,
     *   details:array<string,mixed>
     * }
     */
    public function runAllTenants(?string $dayKey = null): array
    {
        $dayKey ??= date('Y-m-d');
        $sent = 0;
        $skippedDedup = 0;
        $skippedEmpty = 0;
        $tenantsTouched = 0;

        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $tenantsTouched++;
            $result = $this->sendForTenant($tenantId, $tenant, $dayKey);
            $sent += (int) ($result['sent'] ?? 0);
            if (!empty($result['skipped_dedup'])) {
                $skippedDedup++;
            }
            if (!empty($result['skipped_empty'])) {
                $skippedEmpty++;
            }
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                'Communautés %d · points Discord %d · déjà fait %d · rien à signaler %d',
                $tenantsTouched,
                $sent,
                $skippedDedup,
                $skippedEmpty
            ),
            'details' => [
                'tenants' => $tenantsTouched,
                'sent' => $sent,
                'skipped_dedup' => $skippedDedup,
                'skipped_empty' => $skippedEmpty,
                'day' => $dayKey,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $tenant
     * @return array{sent:int,skipped_dedup?:bool,skipped_empty?:bool}
     */
    public function sendForTenant(int $tenantId, array $tenant = [], ?string $dayKey = null): array
    {
        $dayKey ??= date('Y-m-d');
        $subjectId = $tenantId . ':' . $dayKey;
        if ($this->cronLog->wasNotified(self::JOB_KEY, 'tenant_digest', $subjectId, 'email')) {
            return ['sent' => 0, 'skipped_dedup' => true];
        }

        $pendingSuggestions = $this->queue->countPending($tenantId);
        $openSignals = $this->queue->countOpenSignals($tenantId);
        $newPersons = $this->countNewPersonsLastDay($tenantId);
        $interestOpen = $this->countOpenInterestCases($tenantId);

        if ($pendingSuggestions + $openSignals + $newPersons + $interestOpen < 1) {
            $this->cronLog->markNotified(self::JOB_KEY, 'tenant_digest', $subjectId, 'email', null);

            return ['sent' => 0, 'skipped_empty' => true];
        }

        if ($tenant === []) {
            $tenant = $this->tenants->findById($tenantId) ?? ['id' => $tenantId, 'name' => 'Communauté'];
        }
        $tenantName = function_exists('community_display_name')
            ? community_display_name($tenant)
            : (string) ($tenant['name'] ?? 'Communauté');
        $text = sprintf(
            "%d rapprochement(s) à trancher · %d signal(aux) ouvert(s) · %d nouvelle(s) fiche(s) terrain · %d dossier(s) d’intérêt ouvert(s)\n%s",
            $pendingSuggestions,
            $openSignals,
            $newPersons,
            $interestOpen,
            url('atak/sse/rapprochements')
        );
        // Aucun e-mail : relais Discord seulement (rien ne part si la communauté ne l'a pas activé).
        DiscordEventRelayService::relayFromEmail($tenantId, EmailEvents::SSE_ANALYST_DIGEST, 'Point renseignement SSE — ' . $tenantName, $text);
        $this->cronLog->markNotified(self::JOB_KEY, 'tenant_digest', $subjectId, 'email', 'discord');

        return ['sent' => 1];
    }

    private function countNewPersonsLastDay(int $tenantId): int
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_persons
                  WHERE tenant_id = :t AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)',
                ['t' => $tenantId]
            );

            return (int) ($row['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countOpenInterestCases(int $tenantId): int
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_interest_cases
                  WHERE tenant_id = :t
                    AND status IN (
                        \'signalement_recu\', \'a_qualifier\', \'en_collecte\', \'en_analyse\',
                        \'rapprochements_detectes\', \'en_validation\', \'correspondance_probable\'
                    )',
                ['t' => $tenantId]
            );

            return (int) ($row['n'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
