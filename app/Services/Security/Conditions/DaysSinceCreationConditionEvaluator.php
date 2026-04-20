<?php

declare(strict_types=1);

namespace App\Services\Security\Conditions;

final class DaysSinceCreationConditionEvaluator implements ConditionEvaluatorInterface
{
    public function supports(string $conditionType): bool
    {
        return strtoupper($conditionType) === 'DAYS_SINCE_CREATION';
    }

    public function evaluate(array $user, array $conditionValue): bool
    {
        $days = max(0, (int) ($conditionValue['days'] ?? 0));
        $createdAt = isset($user['created_at']) ? strtotime((string) $user['created_at']) : false;
        if ($createdAt === false) {
            return false;
        }

        return (time() - $createdAt) >= ($days * 86400);
    }
}
