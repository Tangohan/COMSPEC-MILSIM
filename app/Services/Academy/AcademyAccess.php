<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Support\ManualBilling;

final class AcademyAccess
{
    /** Paid membership only: no free plans, trials, gifts or expired manual periods. */
    public static function paid(array $tenant, ?int $now = null): bool
    {
        $manual = ManualBilling::current($tenant);
        if ($manual !== null && (($end = strtotime($manual['ends_at'])) === false || $end <= ($now ?? time()))) {
            return false;
        }
        return (int) ($tenant['id'] ?? 0) > 0
            && in_array(strtolower((string) ($tenant['plan_slug'] ?? '')), ['standard', 'pro'], true)
            && ($tenant['subscription_status'] ?? '') === 'active'
            && ManualBilling::paidActive($tenant, $now);
    }
}
