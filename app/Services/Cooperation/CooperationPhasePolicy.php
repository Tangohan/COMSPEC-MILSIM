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

    /**
     * Écriture sur le fil principal coop (hors verrous forum).
     *
     * @param array<string, mixed> $mission
     */
    public static function allowsCrossTenantExchangeWrite(array $mission, int $consumerTenantId, bool $isForumHostTenant): bool
    {
        if (($mission['status'] ?? '') !== 'active') {
            return false;
        }
        $phase = self::effectivePhase($mission);
        if (in_array($phase, ['closed', 'archived', 'cancelled'], true)) {
            return false;
        }
        if (!in_array($phase, ['active', 'preparing', 'validated_pending'], true)) {
            return false;
        }
        $lock = (string) ($mission['exchange_lock_mode'] ?? 'none');
        if ($lock === 'full') {
            return false;
        }
        if ($lock === 'main_only' && !$isForumHostTenant) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $mission */
    public static function allowsMeetingLaunch(array $mission): bool
    {
        $st = (string) ($mission['status'] ?? '');

        return in_array($st, ['draft', 'pending', 'active'], true);
    }
}
