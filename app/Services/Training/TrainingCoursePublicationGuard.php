<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\PedagogyRepository;

/**
 * Vérifie qu’une formation peut être publiée (responsable pédagogique, chaîne minimale).
 */
final class TrainingCoursePublicationGuard
{
    private ?string $lastUserMessage = null;

    public function __construct(
        private PedagogyRepository $pedagogyRepository,
        private TenantPedagogyChainGuard $chainGuard,
    ) {}

    public function lastUserMessage(): ?string
    {
        return $this->lastUserMessage;
    }

    /**
     * @param array<string, mixed> $courseRow ligne training_courses (après fusion patch)
     */
    public function canPublish(int $tenantId, array $courseRow, ?int $actorUserId = null): bool
    {
        $this->lastUserMessage = null;
        if (($courseRow['visibility'] ?? '') !== 'published') {
            return true;
        }
        if (!$this->pedagogyRepository->trainingCoursesHavePedagogyColumns()) {
            if (!$this->chainGuard->hasActiveDesignerCapacity($tenantId)) {
                $this->lastUserMessage = 'La publication nécessite au moins un membre habilité à concevoir des parcours dans votre organisation. Contactez votre administration.';

                return false;
            }

            return true;
        }
        $owner = (int) ($courseRow['pedagogical_owner_user_id'] ?? 0);
        if ($owner < 1) {
            $this->lastUserMessage = 'Indiquez un responsable pédagogique avant de publier cette formation.';

            return false;
        }
        if (!$this->chainGuard->hasActiveDesignerCapacity($tenantId)) {
            $this->lastUserMessage = 'Votre organisation doit désigner au moins un concepteur de parcours actif avant la publication.';

            return false;
        }

        return true;
    }
}
