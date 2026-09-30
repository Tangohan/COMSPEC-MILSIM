<?php

declare(strict_types=1);

namespace App\Services\Personnel\Pass;

/**
 * Faits chargés pour évaluer un PASS. Les tests peuvent les fournir sans base.
 */
final class PassEvaluationContext
{
    /**
     * @param array<string, bool> $favorableAvisByKind avis_kind => favorable
     */
    public function __construct(
        public int $tenantId,
        public int $userId,
        public ?int $qualificationHeldId = null,
        public bool $qualificationValid = false,
        public bool $lmsCompleted = false,
        public float $rawHours = 0.0,
        public float $validatedHours = 0.0,
        public float $categoryHours = 0.0,
        public int $currentGradeId = 0,
        public int $currentGradeOrder = 0,
        public int $requiredGradeOrder = 0,
        public bool $gradeMet = false,
        public float $bilanAverage = 0.0,
        public int $bilanCount = 0,
        public array $favorableAvisByKind = [],
        public string $qualificationLabel = 'la qualification demandée',
        public string $lmsLabel = 'le module demandé',
        public string $categoryLabel = 'cette catégorie',
        public string $gradeLabel = 'le grade demandé',
        public string $bilanLabel = 'notation',
    ) {}
}
