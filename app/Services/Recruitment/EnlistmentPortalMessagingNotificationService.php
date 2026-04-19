<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Notifications liées au fil de messages du portail candidat (suivi /suivi/{token}).
 */
final class EnlistmentPortalMessagingNotificationService
{
    public function __construct(
        private UserRepository $userRepository,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
    ) {}

    /**
     * @param array<string, mixed> $enlistment
     */
    public function notifyStaffOfCandidatePortalMessage(int $tenantId, string $tenantName, array $enlistment, string $messageBody): void
    {
        $recipients = $this->userRepository->listRecruitmentNotificationEmailsForTenant($tenantId);
        if ($recipients === []) {
            $recipients = $this->userRepository->listGovernanceEmailsForTenant($tenantId);
        }
        if ($recipients === []) {
            $recipients = $this->userRepository->listAdministratorEmailsForTenant($tenantId);
        }
        if ($recipients === []) {
            return;
        }

        $tenantName = trim($tenantName) !== '' ? trim($tenantName) : 'Communauté';

        $eid = (int) ($enlistment['id'] ?? 0);
        if ($eid < 1) {
            return;
        }

        $first = trim((string) ($enlistment['first_name'] ?? ''));
        $last = trim((string) ($enlistment['last_name'] ?? ''));
        $callsign = trim((string) ($enlistment['callsign'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full === '') {
            $full = $callsign !== '' ? $callsign : 'Candidat';
        } elseif ($callsign !== '') {
            $full .= ' (« ' . $callsign . ' »)';
        }

        $candidateEmail = strtolower(trim((string) ($enlistment['email'] ?? '')));
        $reviewUrl = url('back-office/recruitments/' . $eid);

        foreach ($recipients as $to) {
            try {
                $em = strtolower(trim((string) $to));
                $u = $em !== '' ? $this->userRepository->findByEmail($tenantId, $em) : null;
                if ($u && !$this->notificationPreferencesRepository->isEmailEventEnabled((int) ($u['id'] ?? 0), EmailEvents::ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF)) {
                    continue;
                }
                $this->emailService->sendEnlistmentPortalCandidateReplyStaffNotify(
                    $to,
                    $tenantName,
                    $eid,
                    $full,
                    $candidateEmail,
                    $messageBody,
                    $reviewUrl,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param array<string, mixed> $enlistment
     */
    public function notifyStaffOfCandidatePortalUpload(int $tenantId, string $tenantName, array $enlistment, string $kind, string $originalName): void
    {
        $isAudio = $kind === 'audio';
        $label = $isAudio ? 'Enregistrement audio transmis' : 'Document transmis';
        $body = $label . ' : ' . $originalName;
        $this->notifyStaffOfCandidatePortalMessage($tenantId, $tenantName, $enlistment, $body);
    }
}
