<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Repositories\MemberIntegrationAppointmentRepository;
use App\Repositories\MemberIntegrationRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Calendar\IcalendarService;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\MemberIntegrationCatalog;
use DateTimeImmutable;
use Throwable;

final class MemberIntegrationInvitationService
{
    public function __construct(
        private MemberIntegrationAppointmentRepository $appointments,
        private MemberIntegrationRepository $integrations,
        private UserRepository $users,
        private IcalendarService $ics,
        private EmailService $email,
        private UserNotificationPreferencesRepository $prefs,
    ) {}

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param list<int> $participantUserIds
     * @return array{ok: bool, appointment_id?: int, tokens?: array<int, string>, message?: string}
     */
    public function createAppointmentWithInvites(
        int $tenantId,
        array $payload,
        array $participantUserIds,
        int $actorUserId
    ): array {
        $title = trim((string) ($payload['title'] ?? ''));
        $starts = trim((string) ($payload['starts_at'] ?? ''));
        $ends = trim((string) ($payload['ends_at'] ?? ''));
        if ($title === '' || $starts === '' || $ends === '') {
            return ['ok' => false, 'message' => 'Indiquez un titre, une date de début et une date de fin.'];
        }
        $max = isset($payload['max_attendees']) ? (int) $payload['max_attendees'] : 0;
        $ids = array_values(array_unique(array_filter(array_map('intval', $participantUserIds), static fn (int $v): bool => $v > 0)));
        if ($max > 0 && count($ids) > $max) {
            return ['ok' => false, 'message' => 'Trop de participants pour le nombre de places prévu.'];
        }
        $appointmentId = $this->appointments->create($tenantId, [
            'integration_id' => $payload['integration_id'] ?? null,
            'step_id' => $payload['step_id'] ?? null,
            'title' => $title,
            'description' => $payload['description'] ?? null,
            'event_type' => $payload['event_type'] ?? 'custom',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'timezone' => $payload['timezone'] ?? 'UTC',
            'location' => $payload['location'] ?? null,
            'meeting_url' => $payload['meeting_url'] ?? null,
            'organizer_user_id' => $payload['organizer_user_id'] ?? $actorUserId,
            'max_attendees' => $max > 0 ? $max : null,
            'linked_course_id' => $payload['linked_course_id'] ?? null,
            'status' => MemberIntegrationCatalog::APPT_SCHEDULED,
        ]);
        if ($appointmentId < 1) {
            return ['ok' => false, 'message' => 'Le rendez-vous n’a pas pu être créé.'];
        }
        $tokens = [];
        $personal = trim((string) ($payload['personal_message'] ?? ''));
        foreach ($ids as $uid) {
            $plain = bin2hex(random_bytes(32));
            $this->appointments->createInvitation($tenantId, [
                'appointment_id' => $appointmentId,
                'user_id' => $uid,
                'response_token_hash' => self::hashToken($plain),
                'token_expires_at' => (new DateTimeImmutable('+21 days'))->format('Y-m-d H:i:s'),
                'personal_message' => $personal !== '' ? $personal : null,
                'invited_by' => $actorUserId,
            ]);
            $tokens[$uid] = $plain;
            $this->appointments->addInvitationHistory(
                $tenantId,
                (int) ($this->appointments->findInvitationForAppointmentUser($tenantId, $appointmentId, $uid)['id'] ?? 0),
                $actorUserId,
                null,
                MemberIntegrationCatalog::RSVP_PENDING,
                null
            );
            $this->notifyInvite($tenantId, $appointmentId, $uid, $plain, EmailEvents::MEMBER_INTEGRATION_INVITE);
        }
        $integrationId = (int) ($payload['integration_id'] ?? 0);
        if ($integrationId > 0) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'invitation_sent',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'Une invitation a été envoyée : ' . $title,
                $actorUserId,
                isset($payload['step_id']) ? (int) $payload['step_id'] : null
            );
        }

        return ['ok' => true, 'appointment_id' => $appointmentId, 'tokens' => $tokens];
    }

    /**
     * @return array{ok: bool, generic: bool, message: string, invitation?: array<string, mixed>}
     */
    public function respondWithToken(string $plainToken, string $response, ?string $comment = null, ?int $actorUserId = null): array
    {
        $generic = 'Ce lien n’est plus valable. Demandez une nouvelle invitation à votre encadrement.';
        $plainToken = trim($plainToken);
        if ($plainToken === '' || !in_array($response, [
            MemberIntegrationCatalog::RSVP_ACCEPTED,
            MemberIntegrationCatalog::RSVP_TENTATIVE,
            MemberIntegrationCatalog::RSVP_DECLINED,
        ], true)) {
            return ['ok' => false, 'generic' => true, 'message' => $generic];
        }
        $hash = self::hashToken($plainToken);
        $row = $this->appointments->findInvitationByTokenHash($hash);
        if (!$row) {
            return ['ok' => false, 'generic' => true, 'message' => $generic];
        }
        $tenantId = (int) $row['tenant_id'];
        $id = (int) $row['id'];
        if (!empty($row['revoked_at'])) {
            return ['ok' => false, 'generic' => true, 'message' => $generic];
        }
        $expires = strtotime((string) ($row['token_expires_at'] ?? ''));
        if ($expires !== false && $expires < time()) {
            return ['ok' => false, 'generic' => true, 'message' => $generic];
        }
        if ((string) ($row['appointment_status'] ?? '') === MemberIntegrationCatalog::APPT_CANCELLED) {
            return ['ok' => false, 'generic' => true, 'message' => $generic];
        }
        $current = (string) ($row['status'] ?? '');
        if ($current === $response) {
            return ['ok' => true, 'generic' => false, 'message' => 'Votre réponse est déjà enregistrée.', 'invitation' => $row];
        }
        if ($response === MemberIntegrationCatalog::RSVP_ACCEPTED) {
            $max = (int) ($row['max_attendees'] ?? 0);
            if ($max > 0) {
                $accepted = $this->appointments->countAccepted($tenantId, (int) $row['appointment_id']);
                if ($current !== MemberIntegrationCatalog::RSVP_ACCEPTED && $accepted >= $max) {
                    return ['ok' => false, 'generic' => false, 'message' => 'Il n’y a plus de place pour ce rendez-vous.'];
                }
            }
        }
        $this->appointments->updateInvitation($tenantId, $id, [
            'status' => $response,
            'responded_at' => date('Y-m-d H:i:s'),
            'response_comment' => $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 500) : null,
        ]);
        $this->appointments->addInvitationHistory(
            $tenantId,
            $id,
            $actorUserId,
            $current,
            $response,
            $comment
        );
        $integrationId = (int) ($row['integration_id'] ?? 0);
        if ($integrationId > 0) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'invitation_response',
                MemberIntegrationCatalog::VISIBILITY_STAFF,
                'Réponse à l’invitation : ' . (MemberIntegrationCatalog::rsvpLabels()[$response] ?? $response),
                $actorUserId ?? (int) $row['user_id']
            );
        }

        return ['ok' => true, 'generic' => false, 'message' => 'Votre réponse a été enregistrée.', 'invitation' => $row];
    }

    public function cancelAppointment(int $tenantId, int $appointmentId, int $actorUserId): bool
    {
        $appt = $this->appointments->findForTenant($tenantId, $appointmentId);
        if (!$appt) {
            return false;
        }
        $this->appointments->update($tenantId, $appointmentId, ['status' => MemberIntegrationCatalog::APPT_CANCELLED]);
        foreach ($this->appointments->listInvitations($tenantId, $appointmentId) as $inv) {
            $this->appointments->updateInvitation($tenantId, (int) $inv['id'], [
                'status' => MemberIntegrationCatalog::RSVP_CANCELLED,
            ]);
            $this->notifyInvite($tenantId, $appointmentId, (int) $inv['user_id'], null, EmailEvents::MEMBER_INTEGRATION_APPOINTMENT_CANCELLED);
        }
        $integrationId = (int) ($appt['integration_id'] ?? 0);
        if ($integrationId > 0) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'appointment_cancelled',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'Le rendez-vous « ' . (string) $appt['title'] . ' » a été annulé.',
                $actorUserId
            );
        }

        return true;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function reschedule(int $tenantId, int $appointmentId, array $fields, int $actorUserId): bool
    {
        $appt = $this->appointments->findForTenant($tenantId, $appointmentId);
        if (!$appt) {
            return false;
        }
        $this->appointments->update($tenantId, $appointmentId, $fields);
        foreach ($this->appointments->listInvitations($tenantId, $appointmentId) as $inv) {
            $this->notifyInvite($tenantId, $appointmentId, (int) $inv['user_id'], null, EmailEvents::MEMBER_INTEGRATION_APPOINTMENT_CHANGED);
        }
        $integrationId = (int) ($appt['integration_id'] ?? 0);
        if ($integrationId > 0) {
            $this->integrations->addEvent(
                $tenantId,
                $integrationId,
                'appointment_rescheduled',
                MemberIntegrationCatalog::VISIBILITY_MEMBER,
                'Le rendez-vous « ' . (string) $appt['title'] . ' » a été reprogrammé.',
                $actorUserId
            );
        }

        return true;
    }

    public function buildIcs(int $tenantId, int $appointmentId, bool $cancelled = false): ?string
    {
        $appt = $this->appointments->findForTenant($tenantId, $appointmentId);
        if (!$appt) {
            return null;
        }
        $organizer = null;
        $oid = (int) ($appt['organizer_user_id'] ?? 0);
        if ($oid > 0) {
            $organizer = $this->users->findById($oid, $tenantId);
        }

        return $this->ics->buildEventCalendar([
            'uid' => (string) $appt['ics_uid'],
            'summary' => (string) $appt['title'],
            'description' => (string) ($appt['description'] ?? ''),
            'location' => (string) ($appt['location'] ?? ''),
            'url' => (string) ($appt['meeting_url'] ?? url('mon-integration')),
            'starts_at' => (string) $appt['starts_at'],
            'ends_at' => (string) $appt['ends_at'],
            'organizer_email' => is_array($organizer) ? (string) ($organizer['email'] ?? '') : '',
            'organizer_name' => is_array($organizer) ? (string) ($organizer['display_name'] ?? '') : '',
            'status' => $cancelled || (string) $appt['status'] === MemberIntegrationCatalog::APPT_CANCELLED
                ? 'CANCELLED'
                : 'CONFIRMED',
        ], 'Intégration');
    }

    private function notifyInvite(int $tenantId, int $appointmentId, int $userId, ?string $plainToken, string $event): void
    {
        $user = $this->users->findById($userId, $tenantId);
        $appt = $this->appointments->findForTenant($tenantId, $appointmentId);
        if (!$user || !$appt) {
            return;
        }
        $to = trim((string) ($user['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            if (!$this->prefs->isEmailEventEnabled($userId, $event)) {
                return;
            }
            $respondUrl = $plainToken !== null
                ? url('integration/invitation/repondre') . '?token=' . rawurlencode($plainToken)
                : url('mon-integration');
            $icsUrl = $plainToken !== null
                ? url('integration/invitation/calendrier') . '?token=' . rawurlencode($plainToken)
                : url('mon-integration');
            $ok = $this->email->sendMemberIntegrationNotice(
                $event,
                $to,
                (string) ($user['display_name'] ?? 'Membre'),
                (string) ($appt['title'] ?? 'Rendez-vous'),
                (string) ($appt['starts_at'] ?? ''),
                $respondUrl,
                $icsUrl,
                $tenantId
            );
            if ($ok && $plainToken !== null) {
                $inv = $this->appointments->findInvitationForAppointmentUser($tenantId, $appointmentId, $userId);
                if ($inv) {
                    $this->appointments->updateInvitation($tenantId, (int) $inv['id'], [
                        'email_sent_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        } catch (Throwable) {
            // L’échec d’e-mail ne doit pas annuler la création métier.
        }
    }
}
