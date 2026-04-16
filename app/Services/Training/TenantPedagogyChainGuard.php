<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\PedagogyRepository;

/**
 * Garde-fous « chaîne pédagogique » au niveau tenant (présence de profils clés).
 */
final class TenantPedagogyChainGuard
{
    private const DESIGN_DEFINITIONS = ['trainer', 'instructor_trainer', 'trainer_of_trainers'];

    private const INSTRUCTOR_DEFINITIONS = ['instructor', 'senior_instructor', 'trainer', 'instructor_trainer', 'trainer_of_trainers'];

    public function __construct(
        private PedagogyRepository $pedagogyRepository,
    ) {}

    public function hasActiveDesignerCapacity(int $tenantId): bool
    {
        if ($this->pedagogyRepository->countUsersWithDesignTrainerRoleSet($tenantId) > 0) {
            return true;
        }

        return $this->pedagogyRepository->countUsersWithActiveRoleDefinitions($tenantId, self::DESIGN_DEFINITIONS) > 0;
    }

    public function hasCertifiedOrRoleInstructor(int $tenantId): bool
    {
        if ($this->pedagogyRepository->tenantHasAnyInstructorEligibility($tenantId)) {
            return true;
        }

        return $this->pedagogyRepository->countUsersWithActiveRoleDefinitions($tenantId, self::INSTRUCTOR_DEFINITIONS) > 0;
    }

    public function hasInstructorTrainer(int $tenantId): bool
    {
        if ($this->pedagogyRepository->countUsersWithActiveRoleDefinitions($tenantId, ['instructor_trainer', 'trainer_of_trainers']) > 0) {
            return true;
        }

        return $this->pedagogyRepository->countUsersWithPedagogyKindRoles($tenantId, 'instructor_certifier') > 0;
    }

    public function hasTrainerOfTrainers(int $tenantId): bool
    {
        if ($this->pedagogyRepository->countUsersWithActiveRoleDefinitions($tenantId, ['trainer_of_trainers']) > 0) {
            return true;
        }

        return $this->pedagogyRepository->countUsersWithPedagogyKindRoles($tenantId, 'trainer_certifier') > 0;
    }

    /**
     * @return array{ok: bool, gaps: list<string>}
     */
    public function assessTenantChain(int $tenantId): array
    {
        $gaps = [];
        if (!$this->hasActiveDesignerCapacity($tenantId)) {
            $gaps[] = 'Aucun membre identifié comme responsable de conception pédagogique pour cette organisation.';
        }
        if (!$this->hasCertifiedOrRoleInstructor($tenantId)) {
            $gaps[] = 'Aucun encadrant pédagogique actif ni habilitation d’animation enregistrée.';
        }
        if (!$this->hasInstructorTrainer($tenantId)) {
            $gaps[] = 'Aucun référent pour la validation des encadrants pédagogiques.';
        }
        if (!$this->hasTrainerOfTrainers($tenantId)) {
            $gaps[] = 'Aucun référent pour la gouvernance des concepteurs.';
        }

        return ['ok' => $gaps === [], 'gaps' => $gaps];
    }
}
