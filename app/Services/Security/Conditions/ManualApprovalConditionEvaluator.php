<?php

declare(strict_types=1);

namespace App\Services\Security\Conditions;

final class ManualApprovalConditionEvaluator implements ConditionEvaluatorInterface
{
    public function supports(string $conditionType): bool
    {
        return strtoupper($conditionType) === 'MANUAL_APPROVAL';
    }

    public function evaluate(array $user, array $conditionValue): bool
    {
        $field = (string) ($conditionValue['field'] ?? 'access_manually_approved');

        return !empty($user[$field]);
    }
}
