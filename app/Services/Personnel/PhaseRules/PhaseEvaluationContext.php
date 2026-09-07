<?php

declare(strict_types=1);

namespace App\Services\Personnel\PhaseRules;

/**
 * Faits déjà chargés pour un membre. Les tests peuvent les fournir sans base.
 */
final class PhaseEvaluationContext
{
    public function __construct(
        public int $tenantId,
        public int $userId,
        public bool $archived = false,
        public bool $tenantActive = true,
        public ?int $currentPhaseId = null,
        public ?int $qualificationHeldId = null,
        public bool $qualificationValid = false,
        public bool $lmsCompleted = false,
        public float $rawHours = 0.0,
        public float $validatedHours = 0.0,
        public float $categoryHours = 0.0,
        public int $validatedSessions = 0,
        public int $kindSessions = 0,
        public int $checkedInCount = 0,
        public float $attendanceRate = 0.0,
        public int $seniorityDays = 0,
        public int $completenessPercent = 0,
        public bool $tutorAssigned = false,
        public bool $hasActiveSanction = false,
        public string $qualificationLabel = 'la qualification demandée',
        public string $lmsLabel = 'le module demandé',
        public string $categoryLabel = 'cette catégorie',
        public string $kindLabel = 'ce type de session',
    ) {}
}
