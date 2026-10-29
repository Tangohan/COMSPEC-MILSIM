<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Services\Billing\PlanPaymentService;
use App\Support\ManualBilling;
use App\Support\PlanGift;

final class AcademyAccess
{
    /** Use the same effective tier as ATHENA, including grants made by platform administrators. */
    public static function paid(array $tenant, ?int $now = null): bool
    {
        if ((int) ($tenant['id'] ?? 0) < 1) {
            return false;
        }
        $now ??= time();
        $effective = self::planSlug($tenant, $now);
        if (!in_array($effective, PlanPaymentService::PAID_PLANS, true)) {
            return false;
        }
        // An active administrative grant takes precedence over the billing status of the base plan.
        if (PlanGift::activeFromSettings($tenant['settings'] ?? null, $now) !== null) {
            return true;
        }
        $base = strtolower(trim((string) ($tenant['plan_slug'] ?? '')));
        $status = strtolower(trim((string) ($tenant['subscription_status'] ?? 'none')));
        if (!in_array($base, PlanPaymentService::PAID_PLANS, true)
            || !in_array($status, ['active', 'none'], true)) {
            return false;
        }
        // A paid tier may also be assigned directly in platform administration with status "none".
        // An old manual period must not override a current external subscription (native billing policy).
        $provider = $status === 'active' && (
            trim((string) ($tenant['stripe_subscription_id'] ?? '')) !== ''
            || trim((string) ($tenant['paypal_subscription_id'] ?? '')) !== ''
        );
        $manual = ManualBilling::current($tenant);
        if (!$provider && $manual !== null) {
            $end = strtotime($manual['ends_at']);
            if ($end === false || $end <= $now) {
                return false;
            }
        }
        return true;
    }

    public static function planSlug(array $tenant, ?int $now = null): string
    {
        return strtolower(trim(PlanGift::effectivePlanSlug($tenant, $now)));
    }
}
