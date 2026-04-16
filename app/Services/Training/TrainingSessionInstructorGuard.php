<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\PedagogyRepository;

/**
 * Vérifie qu’un créneau peut désigner un encadrant pédagogique (éligibilité ou rôle reconnu).
 */
final class TrainingSessionInstructorGuard
{
    private ?string $lastUserMessage = null;

    public function __construct(
        private PedagogyRepository $pedagogyRepository,
    ) {}

    public function lastUserMessage(): ?string
    {
        return $this->lastUserMessage;
    }

    public function canAssignInstructor(int $tenantId, int $instructorUserId, int $courseId): bool
    {
        $this->lastUserMessage = null;
        if ($instructorUserId < 1) {
            return true;
        }
        if ($this->pedagogyRepository->hasActiveInstructorEligibility($tenantId, $instructorUserId, $courseId)) {
            return true;
        }
        if ($this->pedagogyRepository->tenantHasAnyInstructorEligibility($tenantId)) {
            $this->lastUserMessage = 'Cet encadrant n’est pas habilité pour ce parcours ou son habilitation a expiré. Mettez à jour les habilitations ou choisissez un autre membre.';

            return false;
        }
        if ($this->pedagogyRepository->userHasInstructorLikeDefinitions($tenantId, $instructorUserId)) {
            return true;
        }
        $this->lastUserMessage = 'Seuls les membres disposant d’un rôle d’animation ou d’une habilitation valide peuvent être désignés sur ce créneau.';

        return false;
    }
}
