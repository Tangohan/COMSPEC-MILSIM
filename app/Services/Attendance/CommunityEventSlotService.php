<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Repositories\CommunityEventRepository;
use App\Repositories\CommunityEventSlotAssignmentRepository;
use App\Repositories\CommunityEventSlotRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\TrainingCourseRepository;

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
        private PersonnelQualificationRepository $qualifications,
        private TrainingCourseRepository $courses,
    ) {}

    /**
     * Le membre satisfait-il le prérequis de qualification du poste ?
     *
     * Retourne null si le poste n'exige rien, si le déploiement n'est pas migré, ou si le
     * membre est qualifié. Sinon un couple {strict, message} : en mode « advisory » l'appelant
     * laisse passer et se contente d'informer, en mode « strict » il refuse.
     *
     * @param array<string, mixed> $slot
     *
     * @return array{strict: bool, message: string}|null
     */
    public function qualificationGapForSlot(int $tenantId, int $userId, array $slot): ?array
    {
        $courseId = (int) ($slot['required_training_course_id'] ?? 0);
        if ($courseId < 1 || !$this->qualifications->trainingLinkReady()) {
            return null;
        }
        if ($this->qualifications->userHasValidQualificationForCourse($tenantId, $userId, $courseId)) {
            return null;
        }

        $label = 'la qualification requise';
        try {
            $course = $this->courses->findByIdForViewer($courseId, $tenantId);
            $title = trim((string) ($course['title'] ?? ''));
            if ($title !== '') {
                $label = '« ' . $title . ' »';
            }
        } catch (\Throwable) {
            // Libellé générique : ne jamais bloquer une inscription sur un défaut d'affichage.
        }

        $strict = (string) ($slot['qualification_enforcement'] ?? 'advisory') === 'strict';

        return [
            'strict' => $strict,
            'message' => $strict
                ? 'Ce poste exige ' . $label . '. Votre qualification est absente ou expirée : contactez l’encadrement ou suivez la formation avant de vous inscrire.'
                : 'Attention : ce poste recommande ' . $label . ', que vous ne détenez pas (ou plus). Votre inscription est enregistrée, signalez-le à l’encadrement.',
        ];
    }

    /** @return array{ok:bool, error?:string, status?:string, warning?:string} */
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

        // Prérequis de qualification : refuse en mode strict, avertit sinon.
        $gap = $this->qualificationGapForSlot($tenantId, $userId, $slot);
        if ($gap !== null && $gap['strict']) {
            return ['ok' => false, 'error' => $gap['message']];
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

        $result = ['ok' => true, 'status' => $status];
        if ($gap !== null) {
            $result['warning'] = $gap['message'];
        }

        return $result;
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
