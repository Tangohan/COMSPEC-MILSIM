<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\CronJobRunRepository;
use App\Repositories\EnlistmentRecruitmentEngagementRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\EmailService;

/**
 * Rappels e-mail (une fois) pour les bilans de recrutement dus après 30 jours.
 */
final class RecruitmentRetroRemindersCronJob implements CronJobInterface
{
    private const JOB = 'recruitment_retro_reminders';

    public function __construct(
        private EnlistmentRecruitmentEngagementRepository $engagement,
        private EnlistmentRepository $enlistments,
        private TenantRepository $tenants,
        private UserRepository $users,
        private EmailService $emailService,
        private TenantCommunityFeedRepository $feed,
        private CronJobRunRepository $cronLog,
    ) {}

    public function key(): string
    {
        return self::JOB;
    }

    public function label(): string
    {
        return 'Bilans recrutement (30 jours)';
    }

    public function description(): string
    {
        return 'Envoie un rappel unique à l’équipe et au candidat lorsque le bilan après 30 jours est attendu.';
    }

    public function run(): array
    {
        if (!$this->engagement->retroTableExists()) {
            return ['ok' => true, 'summary' => 'Rien à faire (bilans non activés).', 'details' => []];
        }

        $staffEmails = 0;
        $candidateEmails = 0;
        $feedItems = 0;
        $skipped = 0;

        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');

            $due = $this->engagement->listStaffRetrosDue($tenantId, 25);
            foreach ($due as $row) {
                $eid = (int) ($row['id'] ?? 0);
                if ($eid < 1) {
                    continue;
                }
                $subjectId = (string) $eid;
                if ($this->cronLog->wasNotified(self::JOB, 'enlistment_staff_retro', $subjectId, 'email')) {
                    $skipped++;
                    continue;
                }

                $recipients = $this->resolveStaffEmails($tenantId, $eid);
                if ($recipients === []) {
                    $this->cronLog->markNotified(self::JOB, 'enlistment_staff_retro', $subjectId, 'email', null);
                    $skipped++;
                    continue;
                }

                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if ($name === '') {
                    $name = 'Candidat';
                }
                $age = (int) ($row['age_days'] ?? 30);
                $url = url('back-office/recruitments/' . $eid . '?dossier=1#bilan-recrutement');
                $sentAny = false;
                foreach ($recipients as $to) {
                    if ($this->emailService->sendRecruitmentRetroStaffReminder(
                        $to,
                        $tenantName,
                        $name,
                        $age,
                        $eid,
                        $url,
                        $tenantId
                    )) {
                        $sentAny = true;
                        $staffEmails++;
                    }
                }
                if ($sentAny) {
                    $this->cronLog->markNotified(self::JOB, 'enlistment_staff_retro', $subjectId, 'email', $recipients[0] ?? null);
                    try {
                        $this->feed->insert(
                            $tenantId,
                            'recruitment_retro_due',
                            'Bilan recrutement à renseigner',
                            'Le dossier de ' . $name . ' a plus de ' . $age . ' jours. Laissez une courte note pour améliorer le processus.',
                            $url,
                            null
                        );
                        $feedItems++;
                    } catch (\Throwable) {
                    }
                }
            }

            $candDue = $this->engagement->listCandidateRetrosDueForTenant($tenantId, 25);
            foreach ($candDue as $crow) {
                $eid = (int) ($crow['id'] ?? 0);
                if ($eid < 1) {
                    continue;
                }
                $subjectId = (string) $eid;
                if ($this->cronLog->wasNotified(self::JOB, 'enlistment_candidate_retro', $subjectId, 'email')) {
                    $skipped++;
                    continue;
                }
                $email = strtolower(trim((string) ($crow['email'] ?? '')));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->cronLog->markNotified(self::JOB, 'enlistment_candidate_retro', $subjectId, 'email', null);
                    $skipped++;
                    continue;
                }
                $token = $this->enlistments->findValidCandidatePortalTokenForEnlistment($tenantId, $eid);
                if ($token === null) {
                    $token = $this->enlistments->ensureCandidatePortalToken($tenantId, $eid, 24 * 14);
                }
                if ($token === null) {
                    $skipped++;
                    continue;
                }
                $portalUrl = url('enlistment/suivi/' . rawurlencode($token) . '#bilan-processus');
                $age = (int) ($crow['age_days'] ?? 30);
                $first = trim((string) ($crow['first_name'] ?? ''));
                if ($this->emailService->sendRecruitmentRetroCandidateReminder(
                    $email,
                    $tenantName,
                    $first !== '' ? $first : 'bonjour',
                    $age,
                    $portalUrl,
                    $tenantId
                )) {
                    $candidateEmails++;
                    $this->cronLog->markNotified(self::JOB, 'enlistment_candidate_retro', $subjectId, 'email', $email);
                }
            }
        }

        return [
            'ok' => true,
            'summary' => "Rappels équipe : {$staffEmails} · Rappels candidats : {$candidateEmails} · Fil : {$feedItems} · Ignorés : {$skipped}",
            'details' => [
                'staff_emails' => $staffEmails,
                'candidate_emails' => $candidateEmails,
                'feed_items' => $feedItems,
                'skipped' => $skipped,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveStaffEmails(int $tenantId, int $enlistmentId): array
    {
        $out = [];
        $push = static function (array &$acc, string $e): void {
            $e = strtolower(trim($e));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $acc[] = $e;
            }
        };

        $enlistment = $this->enlistments->findForTenant($tenantId, $enlistmentId);
        if (is_array($enlistment)) {
            $handlerId = (int) ($enlistment['reviewed_by'] ?? 0);
            if ($handlerId > 0) {
                $handler = $this->users->findById($handlerId, $tenantId);
                if (is_array($handler)) {
                    $push($out, (string) ($handler['email'] ?? ''));
                }
            }
        }

        foreach ($this->users->listRecruitmentNotificationEmailsForTenant($tenantId) as $e) {
            $push($out, (string) $e);
        }
        foreach ($this->users->listEmailsForTenantAccessDelegation($tenantId) as $e) {
            $push($out, (string) $e);
        }
        foreach ($this->users->listAdministratorEmailsForTenant($tenantId) as $e) {
            $push($out, (string) $e);
        }

        return array_values(array_unique($out));
    }
}
