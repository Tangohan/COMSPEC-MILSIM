<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Notifications liées au fil de messages du portail candidat (suivi /suivi/{token}).
 * Les destinataires suivent la même logique élargie que {@see EnlistmentController::notifyStaffNewEnlistment}
 * (rôles recrutement, accès back-office recrutement, gouvernance, administrateurs, e-mail de contact communauté).
 */
final class EnlistmentPortalMessagingNotificationService
{
    public function __construct(
        private UserRepository $userRepository,
        private EmailService $emailService,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private TenantRepository $tenantRepository,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
    ) {}

    /**
     * @param array<string, mixed>|null $tenantRow Ligne `tenants` (optionnelle ; chargée si besoin pour le contact public).
     *
     * @return list<string>
     */
    private function resolveStaffRecipientEmails(int $tenantId, array $enlistment, ?array $tenantRow = null): array
    {
        $out = [];
        $push = static function (array &$acc, string $e): void {
            $e = strtolower(trim($e));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $acc[] = $e;
            }
        };

        $handlerId = (int) ($enlistment['reviewed_by'] ?? 0);
        if ($handlerId > 0) {
            $handler = $this->userRepository->findById($handlerId, $tenantId);
            if (is_array($handler)) {
                $push($out, (string) ($handler['email'] ?? ''));
            }
        }

        foreach ($this->userRepository->listRecruitmentNotificationEmailsForTenant($tenantId) as $e) {
            $push($out, (string) $e);
        }
        foreach ($this->userRepository->listEmailsForTenantAccessDelegation($tenantId) as $e) {
            $push($out, (string) $e);
        }
        foreach ($this->userRepository->listAdministratorEmailsForTenant($tenantId) as $e) {
            $push($out, (string) $e);
        }

        $out = array_values(array_unique($out));
        if ($out !== []) {
            return $out;
        }

        $row = $tenantRow ?? $this->tenantRepository->findById($tenantId);
        $contact = $this->communityContactEmailFromTenantRow($row);
        if ($contact !== null) {
            return [$contact];
        }

        return [];
    }

    /** @param array<string, mixed>|null $tenantRow */
    private function communityContactEmailFromTenantRow(?array $tenantRow): ?string
    {
        if ($tenantRow === null) {
            return null;
        }
        $raw = $tenantRow['settings'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $community = $decoded['community'] ?? null;
        if (!is_array($community)) {
            return null;
        }
        $contact = strtolower(trim((string) ($community['contact_email'] ?? '')));

        return $contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : null;
    }

    /**
     * @param array<string, mixed> $enlistment
     * @param array<string, mixed>|null $tenantRow
     */
    public function notifyStaffOfCandidatePortalMessage(int $tenantId, string $tenantName, array $enlistment, string $messageBody, ?array $tenantRow = null, bool $fromStaffViewer = false): void
    {
        if ($fromStaffViewer) {
            return;
        }
        $recipients = $this->resolveStaffRecipientEmails($tenantId, $enlistment, $tenantRow);
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
        $reviewUrl = url('back-office/recruitments/' . $eid . '?dossier=1');

        $sent = 0;
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
                $sent++;
            } catch (\Throwable) {
            }
        }
        if ($sent > 0 && $this->enlistmentTimelineRepository->tableExists()) {
            $this->enlistmentTimelineRepository->append(
                $tenantId,
                $eid,
                'system',
                'communication',
                'Courriel d’alerte à l’équipe recrutement',
                $sent === 1
                    ? 'Un courriel de notification a été envoyé aux recruteurs habilités suite à une activité sur le portail candidat.'
                    : $sent . ' courriels de notification ont été envoyés aux recruteurs habilités suite à une activité sur le portail candidat.',
                null,
                ['timeline_family' => 'email_notify', 'notify_kind' => 'staff_portal_activity', 'sent' => $sent],
                null
            );
        }
    }

    /**
     * @param array<string, mixed> $enlistment
     * @param array<string, mixed>|null $tenantRow
     */
    public function notifyStaffOfCandidatePortalUpload(int $tenantId, string $tenantName, array $enlistment, string $kind, string $originalName, ?array $tenantRow = null, bool $fromStaffViewer = false): void
    {
        $isAudio = $kind === 'audio';
        $label = $isAudio ? 'Enregistrement audio transmis' : 'Document transmis';
        $body = $label . ' : ' . $originalName;
        $this->notifyStaffOfCandidatePortalMessage($tenantId, $tenantName, $enlistment, $body, $tenantRow, $fromStaffViewer);
    }

    /**
     * Signalement (contenu ou message) depuis le portail de suivi — courriel aux personnes habilitées au recrutement.
     * Aucune entrée timeline ici : le contrôleur enregistre déjà le détail côté dossier.
     *
     * @param array<string, mixed> $enlistment
     * @param array<string, mixed>|null $tenantRow
     */
    public function notifyStaffOfCandidatePortalContentReport(
        int $tenantId,
        string $tenantName,
        array $enlistment,
        string $reportSummaryForEmail,
        ?array $tenantRow = null
    ): void {
        $recipients = $this->resolveStaffRecipientEmails($tenantId, $enlistment, $tenantRow);
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
        $reviewUrl = url('back-office/recruitments/' . $eid . '?dossier=1');
        $excerpt = mb_strlen($reportSummaryForEmail) > 1800 ? mb_substr($reportSummaryForEmail, 0, 1797) . '…' : $reportSummaryForEmail;

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
                    $excerpt,
                    $reviewUrl,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }
    }
}
