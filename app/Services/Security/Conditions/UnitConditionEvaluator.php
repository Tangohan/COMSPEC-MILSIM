<?php

declare(strict_types=1);

namespace App\Services\Security\Conditions;

final class UnitConditionEvaluator implements ConditionEvaluatorInterface
{
    public function supports(string $conditionType): bool
    {
        return strtoupper($conditionType) === 'UNIT';
    }

    public function evaluate(array $user, array $conditionValue): bool
    {
        $requiredUnit = (int) ($conditionValue['unit_id'] ?? 0);
        $userUnit = (int) ($user['unit_id'] ?? ($user['org_unit_id'] ?? 0));

        return $requiredUnit > 0 && $requiredUnit === $userUnit;
    }
}
