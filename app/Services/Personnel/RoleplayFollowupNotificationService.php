<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\ForumNotificationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * E-mails + notifications « Mon activité » lors de mises à jour du suivi roleplay (hors notes seules).
 */
final class RoleplayFollowupNotificationService
{
    /** @var list<string> */
    private const TRACKED_KEYS = [
        'rp_followup_stage',
        'rp_followup_status',
        'rp_followup_progress',
        'rp_tutor_user_id',
        'rp_recruitment_stream',
        'rp_next_interview_date',
        'rp_medical_due_date',
        'rp_service_rotation_date',
    ];

    public function __construct(
        private EmailService $emailService,
        private UserRepository $userRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private ForumNotificationRepository $forumNotificationRepository,
        private TenantRepository $tenantRepository,
    ) {}

    /**
     * @param array<string, mixed> $beforeProfile personnel_profiles row (or subset) before update
     * @param array<string, mixed> $afterProfile  merged state after update (same keys as DB)
     * @param array<string, mixed> $targetUserRow users row for the dossier owner
     */
    public function notifyAfterSave(
        int $tenantId,
        int $targetUserId,
        int $actorUserId,
        array $beforeProfile,
        array $afterProfile,
        array $targetUserRow,
        string $personnelEditUrl,
        bool $manualTimelineAdded,
    ): void {
        $lines = $this->buildChangeLines($tenantId, $beforeProfile, $afterProfile, $manualTimelineAdded);
        if ($lines === []) {
            return;
        }

        $summary = implode("\n", $lines);
        $subjectLabel = $this->subjectDisplayLabel($targetUserRow);

        $newTutorId = (int) ($afterProfile['rp_tutor_user_id'] ?? 0);

        if ($actorUserId !== $targetUserId) {
            $this->notifyUser(
                $tenantId,
                $targetUserId,
                'member',
                $subjectLabel,
                $summary,
                $personnelEditUrl,
                $lines
            );
        }

        if ($newTutorId > 0 && $actorUserId !== $newTutorId) {
            $this->notifyUser(
                $tenantId,
                $newTutorId,
                'tutor',
                $subjectLabel,
                $summary,
                $personnelEditUrl,
                $lines
            );
        }
    }

    /**
     * @param list<string> $lines
     */
    private function notifyUser(
        int $tenantId,
        int $recipientUserId,
        string $recipientRole,
        string $subjectMemberLabel,
        string $summaryText,
        string $personnelEditUrl,
        array $lines,
    ): void {
        $user = $this->userRepository->findById($recipientUserId, $tenantId);
        if (!$user) {
            return;
        }

        if ($this->notificationPreferencesRepository->isEmailEventEnabled($recipientUserId, EmailEvents::ROLEPLAY_FOLLOWUP_UPDATED)) {
            $email = trim((string) ($user['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $tenant = $this->tenantRepository->findById($tenantId);
                $tenantName = trim((string) ($tenant['name'] ?? 'Votre communauté'));
                $displayName = trim((string) ($user['display_name'] ?? '')) ?: trim((string) ($user['callsign'] ?? 'Membre'));
                $this->emailService->sendTemplated(
                    EmailEvents::ROLEPLAY_FOLLOWUP_UPDATED,
                    'roleplay_followup_updated',
                    $email,
                    $recipientRole === 'tutor'
                        ? ('Suivi roleplay — dossier tutoré (' . $subjectMemberLabel . ') — ' . $tenantName)
                        : ('Mise à jour de votre suivi roleplay — ' . $tenantName),
                    [
                        'displayName' => $displayName,
                        'tenantName' => $tenantName,
                        'subjectMemberLabel' => $subjectMemberLabel,
                        'recipientRole' => $recipientRole,
                        'summaryText' => $summaryText,
                        'dossierUrl' => $personnelEditUrl,
                        'lines' => $lines,
                    ],
                    $tenantId,
                    null,
                    [
                        'purpose' => 'roleplay_followup_updated',
                        'recipient_user_id' => $recipientUserId,
                        'recipient_role' => $recipientRole,
                    ]
                );
            }
        }

        $this->createInAppNotification(
            $tenantId,
            $recipientUserId,
            $recipientRole,
            $subjectMemberLabel,
            $summaryText,
            $personnelEditUrl
        );
    }

    private function createInAppNotification(
        int $tenantId,
        int $recipientUserId,
        string $recipientRole,
        string $subjectMemberLabel,
        string $summary,
        string $href,
    ): void {
        if (!$this->forumNotificationRepository->tableExists()) {
            return;
        }
        $this->forumNotificationRepository->create($tenantId, $recipientUserId, 'roleplay_followup', [
            'recipient_role' => $recipientRole,
            'subject_label' => $subjectMemberLabel,
            'summary' => $summary,
            'href' => $href,
        ]);
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return list<string>
     */
    private function buildChangeLines(int $tenantId, array $before, array $after, bool $manualTimelineAdded): array
    {
        $lines = [];
        foreach (self::TRACKED_KEYS as $key) {
            $a = $this->normalizeCompareValue($key, $after[$key] ?? null);
            $b = $this->normalizeCompareValue($key, $before[$key] ?? null);
            if ($a === $b) {
                continue;
            }
            $lines[] = $this->humanLabelForKey($tenantId, $key, $before[$key] ?? null, $after[$key] ?? null);
        }

        $eligBefore = $this->decodeEligible($before['rp_eligibility_snapshot_json'] ?? null);
        $eligAfter = $this->decodeEligible($after['rp_eligibility_snapshot_json'] ?? null);
        if ($eligBefore !== $eligAfter) {
            $lines[] = $eligAfter
                ? 'Éligibilité roleplay : désormais éligible.'
                : 'Éligibilité roleplay : à compléter ou critères non remplis.';
        }

        if ($manualTimelineAdded) {
            $lines[] = 'Un nouvel événement a été ajouté à la timeline du dossier.';
        }

        return $lines;
    }

    private function normalizeCompareValue(string $key, mixed $val): string
    {
        if ($key === 'rp_followup_progress') {
            if ($val === null || $val === '') {
                return '';
            }

            return (string) max(0, min(100, (int) $val));
        }
        if ($key === 'rp_tutor_user_id') {
            $n = (int) $val;

            return $n > 0 ? (string) $n : '';
        }

        return trim((string) ($val ?? ''));
    }

    private function humanLabelForKey(int $tenantId, string $key, mixed $oldVal, mixed $newVal): string
    {
        $nv = $this->formatScalarForDisplay($newVal);
        $ov = $this->formatScalarForDisplay($oldVal);

        return match ($key) {
            'rp_followup_stage' => 'Étape : ' . ($nv !== '' ? $nv : '—') . ($ov !== '' && $ov !== $nv ? ' (avant : ' . $ov . ')' : ''),
            'rp_followup_status' => 'Statut de suivi : ' . ($nv !== '' ? $nv : '—'),
            'rp_followup_progress' => 'Progression : ' . ($nv !== '' ? $nv . ' %' : '—'),
            'rp_tutor_user_id' => $this->tutorChangeLine($tenantId, (int) $oldVal, (int) $newVal),
            'rp_recruitment_stream' => 'Filière : ' . ($nv !== '' ? $nv : '—'),
            'rp_next_interview_date' => 'Date entretien : ' . ($nv !== '' ? $nv : '—'),
            'rp_medical_due_date' => 'Date visite médicale : ' . ($nv !== '' ? $nv : '—'),
            'rp_service_rotation_date' => 'Date rotation de service : ' . ($nv !== '' ? $nv : '—'),
            default => $key,
        };
    }

    private function tutorChangeLine(int $tenantId, int $_oldId, int $newId): string
    {
        if ($newId < 1) {
            return 'Tutorat : affectation retirée.';
        }
        $u = $this->userRepository->findById($newId, $tenantId);
        $label = $u ? (trim((string) ($u['display_name'] ?? '')) ?: trim((string) ($u['callsign'] ?? ''))) : '';

        return 'Tuteur : ' . ($label !== '' ? $label : ('compte #' . $newId));
    }

    private function formatScalarForDisplay(mixed $val): string
    {
        if ($val === null) {
            return '';
        }
        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }

        return trim((string) $val);
    }

    private function decodeEligible(mixed $json): ?bool
    {
        if (!is_string($json) || trim($json) === '') {
            return null;
        }
        $d = json_decode($json, true);
        if (!is_array($d)) {
            return null;
        }

        return isset($d['eligible']) ? (bool) $d['eligible'] : null;
    }

    /** @param array<string, mixed> $targetUserRow */
    private function subjectDisplayLabel(array $targetUserRow): string
    {
        $n = trim((string) ($targetUserRow['display_name'] ?? ''));

        return $n !== '' ? $n : trim((string) ($targetUserRow['callsign'] ?? 'Membre'));
    }
}
