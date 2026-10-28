<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Support\CooperationDictionary;

/**
 * Règles métier : ce qui est permis selon la phase et les verrous d’échange.
 */
final class CooperationPhasePolicy
{
    /** @param array<string, mixed> $mission */
    public static function effectivePhase(array $mission): string
    {
        return CooperationDictionary::effectivePhase($mission);
    }

    /** @param array<string, mixed> $mission */
    public static function allowsMeetingLaunch(array $mission): bool
    {
        $st = (string) ($mission['status'] ?? '');

        return in_array($st, ['draft', 'pending', 'active'], true);
    }
}
