<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\CronJobRunRepository;
use App\Repositories\ElevationRequestRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Résumé hebdomadaire au staff RH (bureau effectifs) : dossiers incomplets, membres sans
 * unité/rôle, élévations en attente. Le runner peut être déclenché plus souvent qu’une fois
 * par semaine (cron externe quotidien) — on se limite donc au lundi et on déduplique par
 * semaine ISO via cron_notification_log.
 */
final class HrWeeklyDigestCronJob implements CronJobInterface
{
    private const JOB = 'hr_weekly_digest';

    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private PersonnelProfileRepository $personnelProfiles,
        private ElevationRequestRepository $elevationRequests,
        private EffectifsStaffAlertService $staffAlerts,
        private UserNotificationPreferencesRepository $notificationPreferences,
        private EmailService $emailService,
        private CronJobRunRepository $cronLog,
    ) {}

    public function key(): string
    {
        return self::JOB;
    }

    public function label(): string
    {
        return 'Digest RH hebdomadaire';
    }

    public function description(): string
    {
        return 'Envoie un résumé hebdomadaire au staff du bureau effectifs : dossiers incomplets, membres sans unité/rôle, élévations en attente.';
    }

    public function run(): array
    {
        if ((int) date('N') !== 1) {
            return ['ok' => true, 'summary' => 'Hors fenêtre hebdomadaire (envoyé le lundi uniquement).', 'details' => []];
        }
        $isoWeek = date('o-\WW');

        $sent = 0;
        $skippedNoRecipient = 0;
        $skippedDedup = 0;
        $skippedNothingToReport = 0;

        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $subjectId = $tenantId . ':' . $isoWeek;
            if ($this->cronLog->wasNotified(self::JOB, 'tenant_digest', $subjectId, 'email')) {
                $skippedDedup++;
                continue;
            }

            $withoutUnit = $this->users->countListForTenant($tenantId, null, null, null, true, true, null);
            $withoutRole = $this->users->countListForTenant($tenantId, null, null, null, true, null, true);
            $pendingElevations = $this->elevationRequests->countOpenForTenant($tenantId);
            $incompleteProfiles = $this->personnelProfiles->countIncompleteForTenant($tenantId);

            if ($withoutUnit + $withoutRole + $pendingElevations + $incompleteProfiles === 0) {
                $this->cronLog->markNotified(self::JOB, 'tenant_digest', $subjectId, 'email', null);
                $skippedNothingToReport++;
                continue;
            }

            $recipients = $this->staffAlerts->listElevationRecipients($tenantId);
            if ($recipients === []) {
                $skippedNoRecipient++;
                continue;
            }

            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');
            $rosterUrl = effectifs_workspace_url();

            $anySent = false;
            foreach ($recipients as $r) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid < 1 || !$this->notificationPreferences->isEmailEventEnabled($uid, EmailEvents::EFFECTIFS_HR_WEEKLY_DIGEST)) {
                    continue;
                }
                if ($this->emailService->sendEffectifsHrWeeklyDigest(
                    $r['email'],
                    $r['name'],
                    $tenantName,
                    $incompleteProfiles,
                    $withoutUnit,
                    $withoutRole,
                    $pendingElevations,
                    $rosterUrl,
                    $tenantId
                )) {
                    $anySent = true;
                    $sent++;
                }
            }
            if ($anySent) {
                $this->cronLog->markNotified(self::JOB, 'tenant_digest', $subjectId, 'email', $recipients[0]['email'] ?? null);
            }
        }

        return [
            'ok' => true,
            'summary' => "Digests envoyés : {$sent} · Sans destinataire habilité : {$skippedNoRecipient} · "
                . "Rien à signaler : {$skippedNothingToReport} · Déjà envoyés cette semaine : {$skippedDedup}",
            'details' => [
                'sent' => $sent,
                'skipped_no_recipient' => $skippedNoRecipient,
                'skipped_nothing_to_report' => $skippedNothingToReport,
                'skipped_dedup' => $skippedDedup,
            ],
        ];
    }
}
