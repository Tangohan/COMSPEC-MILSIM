<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\CronJobRunRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Core\Database;

/**
 * Digest e-mail SSE (analystes) : rapprochements, signaux, fiches terrain, dossiers d’intérêt.
 * Dédupliqué une fois par jour et par communauté via cron_notification_log.
 */
final class SseAnalystDigestService
{
    public const JOB_KEY = 'sse_analyst_digest';

    /** @var list<string> */
    private const RECIPIENT_PERMISSION_SLUGS = [
        'admin.access',
        'atak.sse.access',
        'atak.sse.case.manage',
        'atak.sse.grant',
    ];

    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private SseSuggestionQueueRepository $queue,
        private UserNotificationPreferencesRepository $notificationPreferences,
        private EmailService $emailService,
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
        $skippedNoRecipient = 0;
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
            if (!empty($result['skipped_no_recipient'])) {
                $skippedNoRecipient++;
            }
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                'Communautés %d · e-mails %d · déjà envoyé %d · rien à signaler %d · sans destinataire %d',
                $tenantsTouched,
                $sent,
                $skippedDedup,
                $skippedEmpty,
                $skippedNoRecipient
            ),
            'details' => [
                'tenants' => $tenantsTouched,
                'sent' => $sent,
                'skipped_dedup' => $skippedDedup,
                'skipped_empty' => $skippedEmpty,
                'skipped_no_recipient' => $skippedNoRecipient,
                'day' => $dayKey,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $tenant
     * @return array{sent:int,skipped_dedup?:bool,skipped_empty?:bool,skipped_no_recipient?:bool}
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

        $ids = $this->users->listActiveUserIdsWithAnyPermissionSlug($tenantId, self::RECIPIENT_PERMISSION_SLUGS);
        $recipients = $ids === [] ? [] : $this->users->findByIdsForTenant($tenantId, $ids);
        if ($recipients === []) {
            return ['sent' => 0, 'skipped_no_recipient' => true];
        }

        $tenantName = function_exists('community_display_name')
            ? community_display_name($tenant)
            : (string) ($tenant['name'] ?? 'Communauté');
        $workspaceUrl = url('atak/sse');
        $suggestionsUrl = url('atak/sse/rapprochements');

        $sent = 0;
        $seenEmails = [];
        foreach ($recipients as $r) {
            $uid = (int) ($r['id'] ?? 0);
            $email = strtolower(trim((string) ($r['email'] ?? '')));
            if ($uid < 1 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                continue;
            }
            if (!$this->notificationPreferences->isEmailEventEnabled($uid, EmailEvents::SSE_ANALYST_DIGEST)) {
                continue;
            }
            $seenEmails[$email] = true;
            $name = trim((string) (($r['callsign'] ?? '') !== '' ? $r['callsign'] : ($r['display_name'] ?? $r['username'] ?? 'Analyste')));
            if ($this->emailService->sendSseAnalystDigest(
                $email,
                $name !== '' ? $name : 'Analyste',
                $tenantName,
                $pendingSuggestions,
                $openSignals,
                $newPersons,
                $interestOpen,
                $workspaceUrl,
                $suggestionsUrl,
                $tenantId
            )) {
                $sent++;
            }
        }

        if ($sent > 0) {
            $this->cronLog->markNotified(self::JOB_KEY, 'tenant_digest', $subjectId, 'email', 'batch:' . $sent);
        }

        return ['sent' => $sent, 'skipped_no_recipient' => $sent < 1];
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
