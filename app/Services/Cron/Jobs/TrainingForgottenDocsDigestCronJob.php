<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\CronJobRunRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingFormationCustomPageRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Digest hebdomadaire au staff LMS (Studio Documentations) : brouillons sans modification
 * depuis 30 jours, documents publiés jamais consultés. Même logique de fenêtre/dédup que
 * HrWeeklyDigestCronJob : envoi le lundi, une fois par semaine ISO via cron_notification_log.
 */
final class TrainingForgottenDocsDigestCronJob implements CronJobInterface
{
    private const JOB = 'training_forgotten_docs_digest';

    /** @var list<string> */
    private const RECIPIENT_PERMISSION_SLUGS = ['admin.access', 'training.manage', 'training.create', 'training.publish'];

    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private TrainingFormationCustomPageRepository $customPages,
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
        return 'Digest Documentations LMS oubliées';
    }

    public function description(): string
    {
        return 'Envoie un résumé hebdomadaire au staff LMS : brouillons sans modification depuis 30 jours, documents publiés jamais consultés.';
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

            $forgottenDrafts = $this->customPages->listForgottenDrafts($tenantId, 30, 10);
            $neverViewed = $this->customPages->listNeverViewed($tenantId, 10);

            if ($forgottenDrafts === [] && $neverViewed === []) {
                $this->cronLog->markNotified(self::JOB, 'tenant_digest', $subjectId, 'email', null);
                $skippedNothingToReport++;
                continue;
            }

            $ids = $this->users->listActiveUserIdsWithAnyPermissionSlug($tenantId, self::RECIPIENT_PERMISSION_SLUGS);
            $recipients = $ids === [] ? [] : $this->users->findByIdsForTenant($tenantId, $ids);
            if ($recipients === []) {
                $skippedNoRecipient++;
                continue;
            }

            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');
            $docsUrl = url('formation/pages-html');

            $anySent = false;
            $seenEmails = [];
            foreach ($recipients as $r) {
                $uid = (int) ($r['id'] ?? 0);
                $email = strtolower(trim((string) ($r['email'] ?? '')));
                if ($uid < 1 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                    continue;
                }
                if (!$this->notificationPreferences->isEmailEventEnabled($uid, EmailEvents::TRAINING_FORGOTTEN_DOCS_DIGEST)) {
                    continue;
                }
                $seenEmails[$email] = true;
                if ($this->emailService->sendTrainingForgottenDocsDigest(
                    $email,
                    (string) ($r['display_name'] ?? ''),
                    $tenantName,
                    $forgottenDrafts,
                    $neverViewed,
                    $docsUrl,
                    $tenantId
                )) {
                    $anySent = true;
                    $sent++;
                }
            }
            if ($anySent) {
                $this->cronLog->markNotified(self::JOB, 'tenant_digest', $subjectId, 'email', array_key_first($seenEmails));
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
