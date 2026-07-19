<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Fenêtre de pointage : 30 min avant le début jusqu’à fin d’événement + 2 h (ou début + 4 h si pas de fin).
 */
final class CommunityEventAttendanceService
{
    /** @var list<string> */
    public const ABSENCE_REASONS = ['service', 'sante', 'indisponibilite_planifiee', 'absence_non_justifiee', 'autre'];

    public const CHECK_IN_OPEN_BEFORE_MIN = 30;

    public const CHECK_IN_AFTER_END_GRACE_HOURS = 2;

    public const DEFAULT_DURATION_IF_NO_END_HOURS = 4;

    public function __construct(
        private CommunityEventRepository $events,
        private EmailService $emailService,
        private TenantRepository $tenants,
        private UserRepository $users,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private ForumNotificationRepository $forumNotificationRepository,
    ) {}

    /**
     * @return array{ok: bool, error?: string}
     */
    public function checkIn(int $eventId, int $userId, int $tenantId): array
    {
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'error' => 'Événement introuvable ou annulé.'];
        }
        $rsvp = $this->events->getRsvp($eventId, $userId);
        if (!$rsvp || !in_array((string) ($rsvp['status'] ?? ''), ['yes', 'maybe'], true)) {
            return ['ok' => false, 'error' => 'Pointage réservé aux participants (présent ou peut-être).'];
        }
        if (!empty($rsvp['checked_in_at'])) {
            return ['ok' => false, 'error' => 'Présence déjà enregistrée.'];
        }
        $now = new \DateTimeImmutable('now');
        if (!$this->isWithinCheckInWindow($event, $now)) {
            return ['ok' => false, 'error' => 'La fenêtre de pointage n’est pas ouverte.'];
        }
        $this->events->setCheckIn($eventId, $userId, $now->format('Y-m-d H:i:s'), $this->currentActorId($userId));

        $tenant = $this->tenants->findById($tenantId);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
        $u = $this->users->findById($userId, $tenantId);
        $email = (string) ($u['email'] ?? '');
        $displayName = (string) ($u['display_name'] ?? 'Membre');
        if ($email !== '' && $this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::ATTENDANCE_CHECKIN_CONFIRM)) {
            $this->emailService->sendAttendanceCheckInConfirm(
                $email,
                $displayName,
                $tenantName,
                (string) ($event['title'] ?? ''),
                (string) ($event['starts_at'] ?? ''),
                $eventId,
                $tenantId
            );
        }

        return ['ok' => true];
    }

    /**
     * Pointage autorisé : RSVP oui/maybe, pas encore pointé, fenêtre ouverte.
     *
     * @param array<string, mixed> $event Ligne community_events (+ champs joint optionnels)
     */
    public function canUserCheckInNow(array $event, int $userId): bool
    {
        if (!empty($event['cancelled_at'])) {
            return false;
        }
        $eventId = (int) ($event['id'] ?? 0);
        if ($eventId < 1) {
            return false;
        }
        $rsvp = $this->events->getRsvp($eventId, $userId);
        if (!$rsvp || !in_array((string) ($rsvp['status'] ?? ''), ['yes', 'maybe'], true)) {
            return false;
        }
        if (!empty($rsvp['checked_in_at'])) {
            return false;
        }

        return $this->isWithinCheckInWindow($event, new \DateTimeImmutable('now'));
    }

    /**
     * @param array<string, mixed> $event
     */
    public function isWithinCheckInWindow(array $event, \DateTimeImmutable $now): bool
    {
        $starts = $this->parseDateTime((string) ($event['starts_at'] ?? ''));
        if ($starts === null) {
            return false;
        }
        $open = $starts->modify('-' . self::CHECK_IN_OPEN_BEFORE_MIN . ' minutes');
        $end = $this->resolveEventEnd($event);
        $close = $end->modify('+' . self::CHECK_IN_AFTER_END_GRACE_HOURS . ' hours');

        return $now >= $open && $now <= $close;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function resolveEventEnd(array $event): \DateTimeImmutable
    {
        $endsRaw = $event['ends_at'] ?? null;
        if ($endsRaw !== null && trim((string) $endsRaw) !== '') {
            $e = $this->parseDateTime((string) $endsRaw);
            if ($e !== null) {
                return $e;
            }
        }
        $starts = $this->parseDateTime((string) ($event['starts_at'] ?? ''));

        return $starts?->modify('+' . self::DEFAULT_DURATION_IF_NO_END_HOURS . ' hours')
            ?? new \DateTimeImmutable();
    }

    private function parseDateTime(string $iso): ?\DateTimeImmutable
    {
        $iso = trim($iso);
        if ($iso === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{ok: bool, previous: ?string, error?: string}
     */
    public function setRsvpWithNotifications(
        int $eventId,
        int $userId,
        int $tenantId,
        string $status,
        ?string $absenceReason = null,
        ?string $absenceNote = null
    ): array {
        if (!in_array($status, ['yes', 'no', 'maybe'], true)) {
            $status = 'yes';
        }
        $normalizedReason = $this->normalizeAbsenceReason($absenceReason);
        $normalizedNote = trim((string) ($absenceNote ?? ''));
        if ($status === 'no' && $normalizedReason === null) {
            $normalizedReason = 'autre';
        }
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'previous' => null, 'error' => 'Événement introuvable ou annulé.'];
        }
        $prev = $this->events->getRsvp($eventId, $userId);
        $previousStatus = $prev ? (string) ($prev['status'] ?? '') : null;

        $this->events->setRsvp($eventId, $userId, $status, $normalizedReason, $normalizedNote, $this->currentActorId($userId));

        if ($previousStatus === $status) {
            return ['ok' => true, 'previous' => $previousStatus];
        }

        $tenant = $this->tenants->findById($tenantId);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
        $u = $this->users->findById($userId, $tenantId);
        $email = $u ? (string) ($u['email'] ?? '') : '';
        $displayName = $u ? (string) ($u['display_name'] ?? 'Membre') : 'Membre';
        if ($email !== null && $email !== '' && $this->notificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::ATTENDANCE_RSVP_CONFIRM)) {
            $this->emailService->sendAttendanceRsvpConfirmation(
                $email,
                $displayName,
                $tenantName,
                (string) ($event['title'] ?? ''),
                (string) ($event['starts_at'] ?? ''),
                $status,
                $eventId,
                $tenantId
            );
        }

        $labels = ['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent'];
        $statusLabel = $labels[$status] ?? $status;
        $organizerId = (int) ($event['created_by_user_id'] ?? 0);
        if ($organizerId > 0 && $organizerId !== $userId) {
            $organizer = $this->users->findById($organizerId, $tenantId);
            $orgEmail = $organizer ? trim((string) ($organizer['email'] ?? '')) : '';
            $organizerName = $organizer ? (string) ($organizer['display_name'] ?? 'Membre') : 'Membre';
            if ($orgEmail !== '' && filter_var($orgEmail, FILTER_VALIDATE_EMAIL)
                && $this->notificationPreferencesRepository->isEmailEventEnabled($organizerId, EmailEvents::ATTENDANCE_RSVP_ORGANIZER)) {
                try {
                    $this->emailService->sendAttendanceRsvpOrganizer(
                        $orgEmail,
                        $organizerName,
                        $tenantName,
                        (string) ($event['title'] ?? ''),
                        (string) ($event['starts_at'] ?? ''),
                        $displayName,
                        $statusLabel,
                        $eventId,
                        $tenantId
                    );
                } catch (\Throwable) {
                }
            }
            if ($this->forumNotificationRepository->tableExists()) {
                try {
                    $this->forumNotificationRepository->create($tenantId, $organizerId, 'event_rsvp_change', [
                        'event_id' => $eventId,
                        'title' => (string) ($event['title'] ?? ''),
                        'participant' => $displayName,
                        'status_label' => $statusLabel,
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        return ['ok' => true, 'previous' => $previousStatus];
    }

    /**
     * @return array{ok: bool, notified: int, error?: string}
     */
    public function cancelEventByOrg(int $eventId, int $tenantId, ?string $reason): array
    {
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'notified' => 0, 'error' => 'Événement introuvable ou déjà annulé.'];
        }
        $recipients = $this->events->listUsersForEventByStatuses($eventId, ['yes', 'maybe']);
        $ok = $this->events->cancelEvent($eventId, $tenantId, $reason);
        if (!$ok) {
            return ['ok' => false, 'notified' => 0, 'error' => 'Annulation impossible.'];
        }
        $tenant = $this->tenants->findById($tenantId);
        $tenantName = (string) ($tenant['name'] ?? 'Communauté');
        $notified = 0;
        foreach ($recipients as $row) {
            $recipientUserId = (int) ($row['user_id'] ?? 0);
            $to = (string) ($row['email'] ?? '');
            if ($to === '') {
                continue;
            }
            if ($recipientUserId > 0 && !$this->notificationPreferencesRepository->isEmailEventEnabled($recipientUserId, EmailEvents::ATTENDANCE_EVENT_CANCELLED)) {
                continue;
            }
            if ($this->emailService->sendAttendanceEventCancelled(
                $to,
                (string) ($row['display_name'] ?? 'Membre'),
                $tenantName,
                (string) ($event['title'] ?? ''),
                (string) ($event['starts_at'] ?? ''),
                $reason ?? '',
                $eventId,
                $tenantId
            )) {
                $notified++;
            }
        }

        return ['ok' => true, 'notified' => $notified];
    }

    /**
     * @param 'yes'|'no'|'maybe'|'remove' $status
     *
     * @return array{ok: bool, error?: string}
     */
    public function adminSetParticipantRsvp(int $eventId, int $tenantId, int $targetUserId, string $status): array
    {
        if ($targetUserId < 1) {
            return ['ok' => false, 'error' => 'Membre invalide.'];
        }
        if (!in_array($status, ['yes', 'no', 'maybe', 'remove'], true)) {
            return ['ok' => false, 'error' => 'Choix de participation invalide.'];
        }
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'error' => 'Ce créneau est introuvable ou annulé.'];
        }
        $member = $this->users->findById($targetUserId, $tenantId);
        if (!$member) {
            return ['ok' => false, 'error' => 'Membre introuvable dans cette communauté.'];
        }
        if ($status === 'remove') {
            $this->events->deleteRsvp($eventId, $targetUserId, $this->currentActorId($targetUserId));

            return ['ok' => true];
        }
        $this->events->setRsvp($eventId, $targetUserId, $status, null, null, $this->currentActorId($targetUserId));

        return ['ok' => true];
    }

    /**
     * @param 'yes'|'no'|'maybe'|'remove' $status
     *
     * @return array{ok: bool, error?: string}
     */
    public function adminSetParticipantRsvpWithReason(
        int $eventId,
        int $tenantId,
        int $targetUserId,
        string $status,
        ?string $absenceReason,
        ?string $absenceNote
    ): array {
        if ($targetUserId < 1) {
            return ['ok' => false, 'error' => 'Membre invalide.'];
        }
        if (!in_array($status, ['yes', 'no', 'maybe', 'remove'], true)) {
            return ['ok' => false, 'error' => 'Choix de participation invalide.'];
        }
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'error' => 'Ce créneau est introuvable ou annulé.'];
        }
        $member = $this->users->findById($targetUserId, $tenantId);
        if (!$member) {
            return ['ok' => false, 'error' => 'Membre introuvable dans cette communauté.'];
        }
        if ($status === 'remove') {
            $this->events->deleteRsvp($eventId, $targetUserId, $this->currentActorId($targetUserId));

            return ['ok' => true];
        }

        $normalizedReason = $this->normalizeAbsenceReason($absenceReason);
        if ($status === 'no' && $normalizedReason === null) {
            $normalizedReason = 'autre';
        }
        $normalizedNote = trim((string) ($absenceNote ?? ''));
        $this->events->setRsvp($eventId, $targetUserId, $status, $normalizedReason, $normalizedNote, $this->currentActorId($targetUserId));

        return ['ok' => true];
    }

    /**
     * Pointage manuel par le staff (hors fenêtre horaire).
     *
     * @return array{ok: bool, error?: string}
     */
    public function adminForceCheckIn(int $eventId, int $tenantId, int $targetUserId): array
    {
        if ($targetUserId < 1) {
            return ['ok' => false, 'error' => 'Membre invalide.'];
        }
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'error' => 'Ce créneau est introuvable ou annulé.'];
        }
        if (!$this->users->findById($targetUserId, $tenantId)) {
            return ['ok' => false, 'error' => 'Membre introuvable dans cette communauté.'];
        }
        $rsvp = $this->events->getRsvp($eventId, $targetUserId);
        if (!$rsvp || !in_array((string) ($rsvp['status'] ?? ''), ['yes', 'maybe'], true)) {
            return ['ok' => false, 'error' => 'Le pointage n’est possible que pour un membre indiqué comme présent ou « peut-être ».'];
        }
        if (!empty($rsvp['checked_in_at'])) {
            return ['ok' => false, 'error' => 'La présence est déjà enregistrée pour ce membre.'];
        }
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $this->events->setCheckIn($eventId, $targetUserId, $now, $this->currentActorId($targetUserId));

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function adminClearCheckIn(int $eventId, int $tenantId, int $targetUserId): array
    {
        if ($targetUserId < 1) {
            return ['ok' => false, 'error' => 'Membre invalide.'];
        }
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'error' => 'Ce créneau est introuvable ou annulé.'];
        }
        if (!$this->users->findById($targetUserId, $tenantId)) {
            return ['ok' => false, 'error' => 'Membre introuvable dans cette communauté.'];
        }
        $rsvp = $this->events->getRsvp($eventId, $targetUserId);
        if (!$rsvp) {
            return ['ok' => false, 'error' => 'Aucune inscription à modifier pour ce membre.'];
        }
        if (empty($rsvp['checked_in_at'])) {
            return ['ok' => false, 'error' => 'Aucun pointage enregistré à effacer.'];
        }
        $this->events->clearCheckIn($eventId, $targetUserId, $this->currentActorId($targetUserId));

        return ['ok' => true];
    }

    private function currentActorId(int $fallbackUserId): int
    {
        $sid = (int) (Session::get('user_id') ?? 0);

        return $sid > 0 ? $sid : max(0, $fallbackUserId);
    }

    private function normalizeAbsenceReason(?string $absenceReason): ?string
    {
        $reason = strtolower(trim((string) ($absenceReason ?? '')));
        if ($reason === '') {
            return null;
        }
        if (!in_array($reason, self::ABSENCE_REASONS, true)) {
            return null;
        }

        return $reason;
    }
}
