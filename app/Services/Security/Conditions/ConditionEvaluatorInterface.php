<?php

declare(strict_types=1);

namespace App\Services\Security\Conditions;

interface ConditionEvaluatorInterface
{
    /** @param array<string,mixed> $user @param array<string,mixed> $conditionValue */
    public function evaluate(array $user, array $conditionValue): bool;

    public function supports(string $conditionType): bool;
}
