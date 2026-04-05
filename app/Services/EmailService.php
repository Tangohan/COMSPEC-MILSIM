<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailDeliveryRepository;
use App\Services\Email\EmailEvents;

final class EmailService
{
    public function __construct(
        private EmailTransportResolver $transportResolver,
        private EmailTemplateEngine $templateEngine,
        private EmailDeliveryRepository $deliveryRepository
    ) {}

    /**
     * @param array<string, mixed> $payloadSummary
     */
    public function send(
        string $eventCode,
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?int $tenantId = null,
        ?string $replyTo = null,
        ?array $payloadSummary = null
    ): bool {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cfg = \email_config();
        $mailerType = (string) ($cfg['default_mailer'] ?? 'file');
        $reply = $replyTo !== null && trim((string) $replyTo) !== '' ? trim((string) $replyTo) : null;
        $result = $this->transportResolver->sendMessage(
            $to,
            $subject,
            $htmlBody,
            $textBody,
            $reply
        );

        $status = $result['ok'] ? 'sent' : 'failed';
        $this->deliveryRepository->insert(
            $tenantId,
            $eventCode,
            $to,
            $subject,
            $result['transport'] ?? $mailerType,
            $status,
            $result['provider_id'] ?? null,
            $result['error'] ?? null,
            $payloadSummary
        );

        return $result['ok'];
    }

    /**
     * @param array<string, mixed> $templateVars
     * @param array<string, mixed> $payloadSummary
     */
    public function sendTemplated(
        string $eventCode,
        string $templateName,
        string $to,
        string $subject,
        array $templateVars,
        ?int $tenantId = null,
        ?string $replyTo = null,
        ?array $payloadSummary = null
    ): bool {
        $parts = $this->templateEngine->render($templateName, $templateVars);
        if ($parts['html'] === '' && $parts['text'] === '') {
            return false;
        }

        return $this->send(
            $eventCode,
            $to,
            $subject,
            $parts['html'],
            $parts['text'] !== '' ? $parts['text'] : strip_tags($parts['html']),
            $tenantId,
            $replyTo,
            $payloadSummary
        );
    }

    /**
     * Rappel membre : compléter la fiche personnelle (personnage / dossier opérationnel).
     *
     * @param array<string, mixed>|null $payloadSummary
     */
    public function sendProfileIncompleteReminder(
        string $to,
        string $displayName,
        string $tenantName,
        string $personnelEditUrl,
        int $tenantId,
        ?array $payloadSummary = null
    ): bool {
        return $this->sendTemplated(
            EmailEvents::PROFILE_INCOMPLETE_REMINDER,
            'profile_incomplete_reminder',
            $to,
            'Complétez votre fiche personnelle — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'editUrl' => $personnelEditUrl,
            ],
            $tenantId,
            null,
            $payloadSummary ?? ['purpose' => 'profile_incomplete_reminder']
        );
    }

    public function sendPasswordReset(string $to, string $resetUrl, int $hoursValid, ?int $tenantId = null): bool
    {
        return $this->sendTemplated(
            EmailEvents::PASSWORD_RESET,
            'password_reset',
            $to,
            'Réinitialisation de votre mot de passe — ' . (string) \env('APP_NAME', 'Athena'),
            ['resetUrl' => $resetUrl, 'hoursValid' => $hoursValid],
            $tenantId,
            null,
            ['purpose' => 'password_reset']
        );
    }

    public function sendUserRegisterConfirmation(
        string $to,
        string $displayName,
        string $tenantName,
        string $verifyUrl,
        int $ttlMinutes,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::USER_REGISTER_CONFIRMATION,
            'user_register_confirmation',
            $to,
            'Confirmez votre adresse e-mail — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'verifyUrl' => $verifyUrl,
                'ttlMinutes' => $ttlMinutes,
            ],
            $tenantId,
            null,
            ['purpose' => 'register']
        );
    }

    public function sendCommunityInvitation(
        string $to,
        string $tenantName,
        string $acceptUrl,
        string $roleLabel,
        string $inviterLabel,
        int $tenantId,
        ?string $replyToEmail = null
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_INVITATION,
            'community_invitation',
            $to,
            'Invitation — ' . $tenantName,
            [
                'tenantName' => $tenantName,
                'acceptUrl' => $acceptUrl,
                'roleLabel' => $roleLabel,
                'inviterLabel' => $inviterLabel,
            ],
            $tenantId,
            $replyToEmail,
            ['purpose' => 'invitation']
        );
    }

    public function sendNewCommunityMemberStaff(
        string $to,
        string $tenantName,
        string $memberEmail,
        string $ip,
        string $context,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::NEW_COMMUNITY_MEMBER,
            'new_community_member',
            $to,
            'Nouveau membre — ' . $tenantName,
            [
                'tenantName' => $tenantName,
                'memberEmail' => $memberEmail,
                'ip' => $ip,
                'context' => $context,
            ],
            $tenantId,
            null,
            ['purpose' => 'staff_notify']
        );
    }

    public function sendSecurityAlert(string $to, string $level, string $title, string $body, ?int $tenantId = null): bool
    {
        return $this->sendTemplated(
            EmailEvents::SECURITY_ALERT,
            'security_alert',
            $to,
            '[' . $level . '] ' . $title,
            ['level' => $level, 'title' => $title, 'body' => $body],
            $tenantId,
            null,
            ['level' => $level]
        );
    }

    public function sendNewDeviceLogin(
        string $to,
        string $displayName,
        string $ip,
        string $userAgent,
        string $geo,
        string $denyUrl,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::NEW_DEVICE_LOGIN,
            'new_device_login',
            $to,
            'Nouvelle connexion sur votre compte',
            [
                'displayName' => $displayName,
                'ip' => $ip,
                'userAgent' => $userAgent,
                'geo' => $geo,
                'denyUrl' => $denyUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'new_device']
        );
    }

    public function sendMultipleLoginAttempts(
        string $to,
        string $email,
        string $ip,
        string $when,
        string $forgotUrl,
        ?int $tenantId = null
    ): bool {
        return $this->sendTemplated(
            EmailEvents::MULTIPLE_LOGIN_ATTEMPTS,
            'multiple_login_attempts',
            $to,
            'Alerte : tentatives de connexion',
            [
                'email' => $email,
                'ip' => $ip,
                'when' => $when,
                'forgotUrl' => $forgotUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'brute']
        );
    }

    public function sendCommunityContact(string $to, string $tenantName, string $fromEmail, string $messageBody, int $tenantId): bool
    {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_CONTACT,
            'community_contact',
            $to,
            'Contact — ' . $tenantName,
            [
                'tenantName' => $tenantName,
                'fromEmail' => $fromEmail,
                'messageBody' => $messageBody,
            ],
            $tenantId,
            $fromEmail,
            ['purpose' => 'public_contact']
        );
    }

    public function sendAttendanceRsvpConfirmation(
        string $to,
        string $displayName,
        string $tenantName,
        string $eventTitle,
        string $startsAt,
        string $status,
        int $eventId,
        int $tenantId
    ): bool {
        $labels = ['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'];
        $label = $labels[$status] ?? $status;

        return $this->sendTemplated(
            EmailEvents::ATTENDANCE_RSVP_CONFIRM,
            'attendance_rsvp_confirmation',
            $to,
            'Participation — ' . $eventTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'eventTitle' => $eventTitle,
                'startsAt' => $startsAt,
                'status' => $status,
                'statusLabel' => $label,
                'pointageUrl' => \url('pointage'),
            ],
            $tenantId,
            null,
            ['purpose' => 'attendance_rsvp', 'event_id' => $eventId]
        );
    }

    public function sendAttendanceEventCancelled(
        string $to,
        string $displayName,
        string $tenantName,
        string $eventTitle,
        string $startsAt,
        string $reason,
        int $eventId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ATTENDANCE_EVENT_CANCELLED,
            'attendance_event_cancelled',
            $to,
            'Événement annulé — ' . $eventTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'eventTitle' => $eventTitle,
                'startsAt' => $startsAt,
                'reason' => $reason,
                'pointageUrl' => \url('pointage'),
            ],
            $tenantId,
            null,
            ['purpose' => 'attendance_cancel', 'event_id' => $eventId]
        );
    }

    public function sendAttendanceReminder(
        string $to,
        string $displayName,
        string $tenantName,
        string $eventTitle,
        string $startsAt,
        int $eventId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ATTENDANCE_REMINDER,
            'attendance_reminder',
            $to,
            'Rappel : ' . $eventTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'eventTitle' => $eventTitle,
                'startsAt' => $startsAt,
                'pointageUrl' => \url('pointage'),
            ],
            $tenantId,
            null,
            ['purpose' => 'attendance_reminder', 'event_id' => $eventId]
        );
    }

    public function sendAttendanceCheckInConfirm(
        string $to,
        string $displayName,
        string $tenantName,
        string $eventTitle,
        string $startsAt,
        int $eventId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ATTENDANCE_CHECKIN_CONFIRM,
            'attendance_checkin_confirm',
            $to,
            'Présence enregistrée — ' . $eventTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'eventTitle' => $eventTitle,
                'startsAt' => $startsAt,
                'pointageUrl' => \url('pointage'),
            ],
            $tenantId,
            null,
            ['purpose' => 'attendance_checkin', 'event_id' => $eventId]
        );
    }
}
