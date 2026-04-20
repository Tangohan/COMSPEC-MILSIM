<?php

declare(strict_types=1);

namespace App\Services\Security\Conditions;

final class StatusConditionEvaluator implements ConditionEvaluatorInterface
{
    public function supports(string $conditionType): bool
    {
        return strtoupper($conditionType) === 'STATUS';
    }

    public function evaluate(array $user, array $conditionValue): bool
    {
        $accepted = array_map('strval', (array) ($conditionValue['accepted'] ?? []));
        $status = (string) ($user['status'] ?? '');

        return in_array($status, $accepted, true);
    }
}
