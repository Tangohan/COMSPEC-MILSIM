<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Repositories\CommunityEventRepository;
use App\Repositories\CommunityEventSlotAssignmentRepository;
use App\Repositories\CommunityEventSlotRepository;

/**
 * Inscription nominative sur un poste (slot) de mission : un membre choisit un rôle précis
 * plutôt qu'un simple RSVP oui/non/peut-être. Bascule automatiquement en liste d'attente si
 * le slot est complet, et promeut le premier en attente quand une place confirmée se libère.
 */
final class CommunityEventSlotService
{
    public function __construct(
        private CommunityEventSlotRepository $slots,
        private CommunityEventSlotAssignmentRepository $assignments,
        private CommunityEventRepository $events,
        private CommunityEventAttendanceService $attendance,
    ) {}

    /** @return array{ok:bool, error?:string, status?:string} */
    public function signUp(int $tenantId, int $eventId, int $slotId, int $userId): array
    {
        $event = $this->events->findByIdForTenant($eventId, $tenantId);
        if (!$event || !empty($event['cancelled_at'])) {
            return ['ok' => false, 'error' => 'Événement introuvable ou annulé.'];
        }
        $slot = $this->slots->findByIdForEvent($slotId, $eventId);
        if (!$slot) {
            return ['ok' => false, 'error' => 'Poste introuvable.'];
        }
        $existing = $this->assignments->findForUserAndEvent($eventId, $userId);
        if ($existing) {
            return ['ok' => false, 'error' => 'Vous êtes déjà inscrit sur un poste pour cet événement. Désinscrivez-vous d’abord pour en changer.'];
        }

        $capacity = max(1, (int) $slot['capacity']);
        $confirmed = $this->slots->countConfirmed($slotId);
        if ($confirmed < $capacity) {
            $status = 'confirmed';
            $waitlistPosition = null;
        } else {
            $status = 'waitlisted';
            $waitlistPosition = $this->assignments->nextWaitlistPosition($slotId);
        }

        $this->assignments->create($tenantId, $slotId, $eventId, $userId, $status, $waitlistPosition);
        $this->attendance->setRsvpWithNotifications($eventId, $userId, $tenantId, 'yes');

        return ['ok' => true, 'status' => $status];
    }

    /** @return array{ok:bool, error?:string} */
    public function leave(int $tenantId, int $eventId, int $userId): array
    {
        $existing = $this->assignments->findForUserAndEvent($eventId, $userId);
        if (!$existing) {
            return ['ok' => false, 'error' => 'Vous n’êtes inscrit sur aucun poste pour cet événement.'];
        }
        $slotId = (int) $existing['slot_id'];
        $wasConfirmed = (string) $existing['status'] === 'confirmed';
        $this->assignments->delete((int) $existing['id']);

        if ($wasConfirmed) {
            $next = $this->assignments->firstWaitlisted($slotId);
            if ($next) {
                $this->assignments->promoteToConfirmed((int) $next['id']);
            }
        }

        return ['ok' => true];
    }
}
