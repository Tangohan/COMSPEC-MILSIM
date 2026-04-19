<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmailDeliveryRepository;
use App\Services\Email\EmailEvents;

final class EmailService
{
    private ?string $lastSendError = null;

    public function __construct(
        private EmailTransportResolver $transportResolver,
        private EmailTemplateEngine $templateEngine,
        private EmailDeliveryRepository $deliveryRepository
    ) {}

    /** Dernier message d’erreur après un envoi échoué (même requête HTTP). */
    public function getLastSendError(): ?string
    {
        return $this->lastSendError;
    }

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
        ?array $payloadSummary = null,
        ?int $campaignId = null
    ): bool {
        $this->lastSendError = null;
        if (filter_var((string) env('MAIL_QUEUE', ''), FILTER_VALIDATE_BOOLEAN)) {
            try {
                $repo = \App\Core\Container::get(\App\Repositories\AsyncJobRepository::class);
                if ($repo->tableExists()) {
                    $repo->enqueue('email_send', json_encode([
                        'eventCode' => $eventCode,
                        'to' => $to,
                        'subject' => $subject,
                        'htmlBody' => $htmlBody,
                        'textBody' => $textBody,
                        'tenantId' => $tenantId,
                        'replyTo' => $replyTo,
                        'payloadSummary' => $payloadSummary,
                        'campaignId' => $campaignId,
                    ], JSON_UNESCAPED_UNICODE));

                    return true;
                }
            } catch (\Throwable) {
            }
        }
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastSendError = 'Adresse e-mail du destinataire invalide.';

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
            $payloadSummary,
            $campaignId
        );

        if (!$result['ok']) {
            $this->lastSendError = $result['error'] ?? 'Échec du transport e-mail.';
        }

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
        $this->lastSendError = null;
        $parts = $this->templateEngine->render($templateName, $templateVars);
        if ($parts['html'] === '' && $parts['text'] === '') {
            $this->lastSendError = 'Modèle e-mail introuvable ou vide.';

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

    /**
     * Compte créé par l’administrateur d’une communauté : le membre définit son mot de passe via la même page que la réinitialisation.
     *
     * @param 'admin_created'|'recruitment_accepted' $inviteSource admin_created : libellé générique ; recruitment_accepted : premier accès suite candidature (évite « finaliser » ambigu).
     */
    public function sendTenantUserSetupInvite(
        string $to,
        string $setupUrl,
        int $hoursValid,
        string $tenantName,
        int $tenantId,
        string $inviteSource = 'admin_created'
    ): bool {
        $isRecruitment = $inviteSource === 'recruitment_accepted';
        $subject = $isRecruitment
            ? 'Premier accès — choisissez votre mot de passe — ' . $tenantName
            : 'Finalisez votre compte — ' . $tenantName;

        return $this->sendTemplated(
            EmailEvents::TENANT_USER_SETUP,
            'tenant_user_setup',
            $to,
            $subject,
            [
                'setupUrl' => $setupUrl,
                'hoursValid' => $hoursValid,
                'tenantName' => $tenantName,
                'inviteSource' => $isRecruitment ? 'recruitment_accepted' : 'admin_created',
            ],
            $tenantId,
            null,
            ['purpose' => 'tenant_user_setup', 'invite_source' => $inviteSource]
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

    public function sendRegisterSecurityCompanion(
        string $to,
        string $displayName,
        string $tenantName,
        string $accountPreferencesUrl,
        string $communityCreateUrl,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::REGISTER_SECURITY_COMPANION,
            'register_security_companion',
            $to,
            'Sécurité & démarrage rapide — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'accountPreferencesUrl' => $accountPreferencesUrl,
                'communityCreateUrl' => $communityCreateUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'register_security_companion']
        );
    }

    public function sendCommunityCreationChecklist(
        string $to,
        string $displayName,
        string $tenantName,
        string $dashboardUrl,
        string $communitySettingsUrl,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_CREATION_CHECKLIST,
            'community_creation_checklist',
            $to,
            'Communauté créée : checklist opérationnelle — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'dashboardUrl' => $dashboardUrl,
                'communitySettingsUrl' => $communitySettingsUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'community_creation_checklist']
        );
    }

    public function sendInterteamCooperationOtp(
        string $to,
        string $displayName,
        string $tenantName,
        string $code,
        int $ttlMinutes,
        string $missionTitle,
        int $tenantId,
        ?string $sharingSummary = null
    ): bool {
        return $this->sendTemplated(
            EmailEvents::INTERTEAM_COOPERATION_OTP,
            'interteam_consent_otp',
            $to,
            'Code de confirmation — coopération inter-unités — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'code' => $code,
                'ttlMinutes' => $ttlMinutes,
                'missionTitle' => $missionTitle,
                'sharingSummary' => $sharingSummary ?? '',
            ],
            $tenantId,
            null,
            ['purpose' => 'interteam_consent_otp']
        );
    }

    public function sendLoginSecurityOtp(
        string $to,
        string $displayName,
        string $tenantName,
        string $code,
        int $ttlMinutes,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::LOGIN_SECURITY_OTP,
            'login_security_otp',
            $to,
            'Code OTP de connexion — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'code' => $code,
                'ttlMinutes' => $ttlMinutes,
            ],
            $tenantId,
            null,
            ['purpose' => 'login_security_otp']
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

    /**
     * Notification recrutement / fondateur / RH : nouvelle candidature enregistrée.
     */
    public function sendEnlistmentSubmittedStaffNotify(
        string $to,
        string $tenantName,
        string $candidateFullName,
        string $candidateEmail,
        ?string $availability,
        ?string $motivation,
        int $enlistmentId,
        string $reviewUrl,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ENLISTMENT_SUBMITTED_STAFF,
            'enlistment_submitted_staff',
            $to,
            'Nouvelle candidature — ' . $tenantName,
            [
                'tenantName' => $tenantName,
                'candidateFullName' => $candidateFullName,
                'candidateEmail' => $candidateEmail,
                'availability' => $availability,
                'motivation' => $motivation,
                'enlistmentId' => $enlistmentId,
                'reviewUrl' => $reviewUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'enlistment_submitted', 'enlistment_id' => $enlistmentId]
        );
    }

    /**
     * Offre de poste publiée — notification recruteur / fondateur / RH.
     */
    public function sendRecruitmentOpeningPublishedStaffNotify(
        string $to,
        string $tenantName,
        string $openingTitle,
        string $referencePublic,
        string $publicAvisUrl,
        string $candidaterUrl,
        int $openingId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::RECRUITMENT_OPENING_PUBLISHED_STAFF,
            'recruitment_opening_published_staff',
            $to,
            'Nouvelle offre publiée — ' . $tenantName,
            [
                'tenantName' => $tenantName,
                'openingTitle' => $openingTitle,
                'referencePublic' => $referencePublic,
                'publicAvisUrl' => $publicAvisUrl,
                'candidaterUrl' => $candidaterUrl,
                'openingId' => $openingId,
            ],
            $tenantId,
            null,
            ['purpose' => 'recruitment_opening_published', 'opening_id' => $openingId]
        );
    }

    /**
     * Candidature acceptée — message au candidat (message du recruteur + lien espace).
     *
     * @param 'existing'|'new_password_pending' $accountScenario existing : compte déjà présent sur la communauté ; new_password_pending : compte tout juste créé, autre mail pour le mot de passe.
     */
    public function sendEnlistmentAcceptedCandidate(
        string $to,
        string $tenantName,
        ?string $reviewerComment,
        string $dashboardUrl,
        string $accountScenario,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ENLISTMENT_ACCEPTED_CANDIDATE,
            'enlistment_accepted_candidate',
            $to,
            'Candidature acceptée — ' . $tenantName,
            [
                'tenantName' => $tenantName,
                'reviewerComment' => $reviewerComment,
                'dashboardUrl' => $dashboardUrl,
                'accountScenario' => $accountScenario,
            ],
            $tenantId,
            null,
            ['purpose' => 'enlistment_accepted_candidate', 'account_scenario' => $accountScenario]
        );
    }

    /**
     * Candidature acceptée — notification recrutement / RH.
     */
    public function sendEnlistmentAcceptedStaff(
        string $to,
        string $tenantName,
        int $enlistmentId,
        string $candidateFullName,
        string $candidateEmail,
        string $summaryLine,
        string $reviewUrl,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ENLISTMENT_ACCEPTED_STAFF,
            'enlistment_accepted_staff',
            $to,
            'Candidature acceptée — ' . $tenantName . ' — #' . $enlistmentId,
            [
                'tenantName' => $tenantName,
                'enlistmentId' => $enlistmentId,
                'candidateFullName' => $candidateFullName,
                'candidateEmail' => $candidateEmail,
                'summaryLine' => $summaryLine,
                'reviewUrl' => $reviewUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'enlistment_accepted', 'enlistment_id' => $enlistmentId]
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

    public function sendTrainingEnrollmentAssigned(
        string $to,
        string $displayName,
        string $tenantName,
        string $courseTitle,
        string $courseUrl,
        int $tenantId
    ): bool {
        $myTrainingUrl = \url('formations/mes-formations');

        return $this->sendTemplated(
            EmailEvents::TRAINING_ENROLLMENT_ASSIGNED,
            'training_enrollment_assigned',
            $to,
            'Formation assignée — ' . $courseTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'courseUrl' => $courseUrl,
                'myTrainingUrl' => $myTrainingUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_assigned', 'course_title' => $courseTitle]
        );
    }

    public function sendTrainingCourseCompleted(
        string $to,
        string $displayName,
        string $tenantName,
        string $courseTitle,
        string $courseUrl,
        bool $isCertifying,
        int $tenantId
    ): bool {
        $myTrainingUrl = \url('formations/mes-formations');

        return $this->sendTemplated(
            EmailEvents::TRAINING_COURSE_COMPLETED,
            'training_course_completed',
            $to,
            'Félicitations — parcours terminé — ' . $courseTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'courseUrl' => $courseUrl,
                'myTrainingUrl' => $myTrainingUrl,
                'isCertifying' => $isCertifying,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_completed', 'course_title' => $courseTitle]
        );
    }

    public function sendTrainingEnrollmentPendingApproval(
        string $to,
        string $staffDisplayName,
        string $learnerDisplayName,
        string $learnerEmail,
        string $tenantName,
        string $courseTitle,
        string $reviewUrl,
        int $enrollmentId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::TRAINING_ENROLLMENT_PENDING_APPROVAL,
            'training_enrollment_pending_approval',
            $to,
            'À valider : inscription — ' . $courseTitle,
            [
                'staffDisplayName' => $staffDisplayName,
                'learnerDisplayName' => $learnerDisplayName,
                'learnerEmail' => $learnerEmail,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'reviewUrl' => $reviewUrl,
                'enrollmentId' => $enrollmentId,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_pending_approval', 'enrollment_id' => $enrollmentId]
        );
    }

    public function sendTrainingSelfEnrollApproved(
        string $to,
        string $displayName,
        string $tenantName,
        string $courseTitle,
        string $courseUrl,
        int $tenantId
    ): bool {
        $myTrainingUrl = \url('formations/mes-formations');

        return $this->sendTemplated(
            EmailEvents::TRAINING_SELF_ENROLL_APPROVED,
            'training_self_enroll_approved',
            $to,
            'Inscription acceptée — ' . $courseTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'courseUrl' => $courseUrl,
                'myTrainingUrl' => $myTrainingUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_self_enroll_approved', 'course_title' => $courseTitle]
        );
    }

    public function sendTrainingSelfEnrollDeclined(
        string $to,
        string $displayName,
        string $tenantName,
        string $courseTitle,
        int $tenantId
    ): bool {
        $catalogUrl = \url('formations');

        return $this->sendTemplated(
            EmailEvents::TRAINING_SELF_ENROLL_DECLINED,
            'training_self_enroll_declined',
            $to,
            'Inscription non retenue — ' . $courseTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'catalogUrl' => $catalogUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_self_enroll_declined', 'course_title' => $courseTitle]
        );
    }

    public function sendTrainingModuleBlockedStaff(
        string $to,
        string $staffDisplayName,
        string $learnerDisplayName,
        string $learnerEmail,
        string $tenantName,
        string $courseTitle,
        string $moduleTitle,
        string $summaryText,
        string $enrollmentsAdminUrl,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::TRAINING_MODULE_BLOCKED_STAFF,
            'training_module_blocked_staff',
            $to,
            'Aide formation — module « ' . $moduleTitle . ' »',
            [
                'staffDisplayName' => $staffDisplayName,
                'learnerDisplayName' => $learnerDisplayName,
                'learnerEmail' => $learnerEmail,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'moduleTitle' => $moduleTitle,
                'summaryText' => $summaryText,
                'enrollmentsAdminUrl' => $enrollmentsAdminUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_module_blocked', 'course_title' => $courseTitle]
        );
    }

    public function sendTrainingCourseSessionScheduledLearner(
        string $to,
        string $displayName,
        string $tenantName,
        string $courseTitle,
        string $courseUrl,
        string $periodLine,
        ?string $sessionLabel,
        ?string $sessionLocation,
        int $tenantId
    ): bool {
        $myTrainingUrl = \url('formations/mes-formations');

        return $this->sendTemplated(
            EmailEvents::TRAINING_COURSE_SESSION_SCHEDULED_LEARNER,
            'training_course_session_scheduled_learner',
            $to,
            'Nouveau créneau — ' . $courseTitle,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'courseTitle' => $courseTitle,
                'courseUrl' => $courseUrl,
                'periodLine' => $periodLine,
                'sessionLabel' => $sessionLabel,
                'sessionLocation' => $sessionLocation,
                'myTrainingUrl' => $myTrainingUrl,
            ],
            $tenantId,
            null,
            ['purpose' => 'training_session_scheduled', 'course_title' => $courseTitle]
        );
    }

    public function sendCommunityReportReceipt(
        string $to,
        string $displayName,
        string $tenantName,
        int $reportId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_REPORT_RECEIPT,
            'community_report_receipt',
            $to,
            'Votre demande a bien été transmise — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'activityUrl' => \url('activite'),
            ],
            $tenantId,
            null,
            ['purpose' => 'community_report_receipt', 'report_id' => $reportId]
        );
    }

    public function sendCommunityReportHandled(
        string $to,
        string $displayName,
        string $tenantName,
        int $reportId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_REPORT_HANDLED,
            'community_report_handled',
            $to,
            'Votre signalement a été traité — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'activityUrl' => \url('activite'),
            ],
            $tenantId,
            null,
            ['purpose' => 'community_report_handled', 'report_id' => $reportId]
        );
    }

    public function sendCommunityReportNewStaff(
        string $to,
        string $displayName,
        string $tenantName,
        string $summaryLine,
        int $reportId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_REPORT_NEW_STAFF,
            'community_report_new_staff',
            $to,
            'Nouveau signalement — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'summaryLine' => $summaryLine,
                'moderationUrl' => \url('back-office/forum-moderation'),
            ],
            $tenantId,
            null,
            ['purpose' => 'community_report_staff', 'report_id' => $reportId]
        );
    }

    public function sendCommunityReportReopenedStaff(
        string $to,
        string $displayName,
        string $tenantName,
        string $summaryLine,
        int $reportId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_REPORT_REOPENED_STAFF,
            'community_report_reopened_staff',
            $to,
            'Signalement rouvert — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'summaryLine' => $summaryLine,
                'moderationUrl' => \url('back-office/forum-moderation'),
            ],
            $tenantId,
            null,
            ['purpose' => 'community_report_reopened_staff', 'report_id' => $reportId]
        );
    }

    public function sendCommunityReportReopenedReporter(
        string $to,
        string $displayName,
        string $tenantName,
        int $reportId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::COMMUNITY_REPORT_REOPENED_REPORTER,
            'community_report_reopened_reporter',
            $to,
            'Votre signalement est à nouveau examiné — ' . $tenantName,
            [
                'displayName' => $displayName,
                'tenantName' => $tenantName,
                'activityUrl' => \url('activite'),
            ],
            $tenantId,
            null,
            ['purpose' => 'community_report_reopened_reporter', 'report_id' => $reportId]
        );
    }

    public function sendAttendanceRsvpOrganizer(
        string $to,
        string $organizerName,
        string $tenantName,
        string $eventTitle,
        string $startsAt,
        string $participantName,
        string $statusLabel,
        int $eventId,
        int $tenantId
    ): bool {
        return $this->sendTemplated(
            EmailEvents::ATTENDANCE_RSVP_ORGANIZER,
            'attendance_rsvp_organizer',
            $to,
            'Participation mise à jour — ' . $eventTitle,
            [
                'organizerName' => $organizerName,
                'tenantName' => $tenantName,
                'eventTitle' => $eventTitle,
                'startsAt' => $startsAt,
                'participantName' => $participantName,
                'statusLabel' => $statusLabel,
                'eventsUrl' => \url('evenements'),
            ],
            $tenantId,
            null,
            ['purpose' => 'attendance_rsvp_organizer', 'event_id' => $eventId]
        );
    }

    public function sendTenantInternalMessageThread(
        string $to,
        string $recipientDisplayName,
        string $tenantName,
        string $senderLabel,
        string $previewLine,
        int $threadId,
        int $tenantId
    ): bool {
        $subject = 'Nouveau message — ' . $tenantName;

        return $this->sendTemplated(
            EmailEvents::TENANT_INTERNAL_MESSAGE_THREAD,
            'tenant_internal_message_thread',
            $to,
            $subject,
            [
                'displayName' => $recipientDisplayName,
                'tenantName' => $tenantName,
                'senderLabel' => $senderLabel,
                'previewLine' => $previewLine,
                'conversationUrl' => \url('messages/' . $threadId),
            ],
            $tenantId,
            null,
            ['purpose' => 'tenant_internal_message', 'thread_id' => $threadId]
        );
    }
}
