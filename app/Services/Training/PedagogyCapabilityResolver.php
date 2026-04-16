<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Core\Gate;
use App\Repositories\PedagogyRepository;

/**
 * Habilitations pédagogiques : combine permissions Gate et profils role_definitions.
 *
 * @phpstan-type ResolveResult array{allowed: bool, reason_code: string, user_message: string|null}
 */
final class PedagogyCapabilityResolver
{
    public function __construct(
        private PedagogyRepository $pedagogyRepository,
        private TenantPedagogyChainGuard $chainGuard,
    ) {}

    /**
     * @param 'view'|'create'|'update'|'publish'|'instruct'|'validate'|'certify'|'suspend' $capability
     */
    public function can(
        int $tenantId,
        int $userId,
        ?int $orgUnitId,
        string $capability,
        string $resourceType,
        array $resourceIds = []
    ): array {
        unset($orgUnitId, $resourceIds);
        $gate = Gate::getInstance();

        $perm = match ($capability) {
            'view' => 'training.view',
            'create' => 'training.create',
            'update' => 'training.update',
            'publish' => 'training.publish',
            'instruct' => 'training.submissions.grade',
            'validate', 'certify' => 'training.certifications.manage',
            'suspend' => 'training.manage',
            default => 'training.view',
        };

        if (!$gate->allows($perm)) {
            return [
                'allowed' => false,
                'reason_code' => 'permission_denied',
                'user_message' => 'Vous n’avez pas les droits nécessaires pour cette action.',
            ];
        }

        $defs = match ($capability) {
            'publish', 'create', 'update' => ['trainer', 'instructor_trainer', 'trainer_of_trainers', 'training_officer', 'pedagogy_coordinator'],
            'instruct' => ['instructor', 'senior_instructor', 'trainer', 'instructor_trainer', 'trainer_of_trainers'],
            'validate', 'certify' => ['evaluator', 'certification_lead', 'instructor_trainer', 'trainer_of_trainers'],
            'suspend' => ['training_officer', 'instructor_trainer', 'trainer_of_trainers'],
            default => [],
        };

        if ($defs !== [] && !$this->pedagogyRepository->userHasOneOfDefinitions($tenantId, $userId, $defs)) {
            return [
                'allowed' => false,
                'reason_code' => 'pedagogy_role_required',
                'user_message' => 'Cette action est réservée à un profil pédagogique adapté dans votre organisation.',
            ];
        }

        if ($resourceType === 'training_course' && $capability === 'publish') {
            $chain = $this->chainGuard->hasActiveDesignerCapacity($tenantId);
            if (!$chain) {
                return [
                    'allowed' => false,
                    'reason_code' => 'no_designer_in_tenant',
                    'user_message' => 'Aucun concepteur de parcours actif n’est enregistré pour votre organisation.',
                ];
            }
        }

        return ['allowed' => true, 'reason_code' => 'ok', 'user_message' => null];
    }
}
