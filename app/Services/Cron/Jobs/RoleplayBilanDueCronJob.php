<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Repositories\CronJobRunRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Cron\CronJobInterface;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\RoleplayBilanPolicy;

/**
 * Rappel hebdomadaire — bilans roleplay dus ou en retard (cadence App\Support\RoleplayBilanPolicy) :
 * un e-mail par tuteur (uniquement ses tutorés), et un e-mail groupé au staff RH habilité.
 * Envoyé le lundi uniquement, déduplication par semaine ISO et par destinataire.
 */
final class RoleplayBilanDueCronJob implements CronJobInterface
{
    private const JOB = 'roleplay_bilan_due';

    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private PersonnelProfileRepository $personnelProfiles,
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
        return 'Bilans roleplay dus';
    }

    public function description(): string
    {
        return 'Alerte hebdomadaire (tuteur + staff RH) pour les membres dont le bilan roleplay est dû ou en retard, selon la cadence 6/8/12 mois.';
    }

    public function run(): array
    {
        if ((int) date('N') !== 1) {
            return ['ok' => true, 'summary' => 'Hors fenêtre hebdomadaire (envoyé le lundi uniquement).', 'details' => []];
        }
        $isoWeek = date('o-\WW');

        $sent = 0;
        $skippedDedup = 0;
        $skippedNothingToReport = 0;
        $roleplayFollowupUrl = url('back-office/roleplay-followup');

        foreach ($this->tenants->listBasicAll() as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $due = $this->personnelProfiles->listRoleplayBilanDueForTenant($tenantId);
            if ($due === []) {
                continue;
            }
            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');

            $membersByTutor = [];
            $allMembers = [];
            foreach ($due as $row) {
                $displayName = trim((string) ($row['display_name'] ?? '')) ?: (trim((string) ($row['callsign'] ?? '')) ?: (string) ($row['email'] ?? 'Membre'));
                $nextDueAt = (string) ($row['next_due_at'] ?? '');
                $nextDueLabel = $nextDueAt !== '' ? date('d/m/Y', strtotime($nextDueAt) ?: time()) : '—';
                $entry = [
                    'name' => $displayName,
                    'next_due_label' => $nextDueLabel,
                    'overdue' => (int) ($row['is_overdue'] ?? 0) === 1,
                ];
                $allMembers[] = $entry;
                $tutorId = (int) ($row['rp_tutor_user_id'] ?? 0);
                if ($tutorId > 0) {
                    $membersByTutor[$tutorId][] = $entry;
                }
            }

            // Tuteurs : un e-mail par tuteur, uniquement ses tutorés.
            foreach ($membersByTutor as $tutorId => $members) {
                $subjectId = $tenantId . ':tutor:' . $tutorId . ':' . $isoWeek;
                if ($this->cronLog->wasNotified(self::JOB, 'tutor_reminder', $subjectId, 'email')) {
                    $skippedDedup++;
                    continue;
                }
                $tutor = $this->users->findById($tutorId, $tenantId);
                if (!$tutor) {
                    continue;
                }
                $tutorEmail = strtolower(trim((string) ($tutor['email'] ?? '')));
                if ($tutorEmail === '' || !filter_var($tutorEmail, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                if (!$this->notificationPreferences->isEmailEventEnabled($tutorId, EmailEvents::ROLEPLAY_BILAN_DUE)) {
                    continue;
                }
                $tutorName = trim((string) ($tutor['display_name'] ?? '')) ?: (trim((string) ($tutor['callsign'] ?? '')) ?: $tutorEmail);
                if ($this->emailService->sendRoleplayBilanDueAlert($tutorEmail, $tutorName, $tenantName, $members, $roleplayFollowupUrl, $tenantId)) {
                    $sent++;
                }
                $this->cronLog->markNotified(self::JOB, 'tutor_reminder', $subjectId, 'email', $tutorEmail);
            }

            // Staff RH : un e-mail groupé pour tout le tenant.
            $subjectId = $tenantId . ':staff:' . $isoWeek;
            if ($this->cronLog->wasNotified(self::JOB, 'staff_digest', $subjectId, 'email')) {
                $skippedDedup++;
                continue;
            }
            $recipients = $this->staffAlerts->listElevationRecipients($tenantId);
            if ($recipients === []) {
                $skippedNothingToReport++;
                continue;
            }
            $anySent = false;
            foreach ($recipients as $r) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid < 1 || !$this->notificationPreferences->isEmailEventEnabled($uid, EmailEvents::ROLEPLAY_BILAN_DUE)) {
                    continue;
                }
                if ($this->emailService->sendRoleplayBilanDueAlert((string) $r['email'], (string) $r['name'], $tenantName, $allMembers, $roleplayFollowupUrl, $tenantId)) {
                    $anySent = true;
                    $sent++;
                }
            }
            if ($anySent) {
                $this->cronLog->markNotified(self::JOB, 'staff_digest', $subjectId, 'email', $recipients[0]['email'] ?? null);
            }
        }

        return [
            'ok' => true,
            'summary' => "E-mails envoyés : {$sent} · Déjà envoyés cette semaine : {$skippedDedup} · Sans destinataire : {$skippedNothingToReport}",
            'details' => [
                'sent' => $sent,
                'skipped_dedup' => $skippedDedup,
                'skipped_no_recipient' => $skippedNothingToReport,
                'cadence' => [
                    'first_year_days' => RoleplayBilanPolicy::FIRST_YEAR_INTERVAL_DAYS,
                    'second_year_days' => RoleplayBilanPolicy::SECOND_YEAR_INTERVAL_DAYS,
                    'ongoing_days' => RoleplayBilanPolicy::ONGOING_INTERVAL_DAYS,
                ],
            ],
        ];
    }
}
