<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyAccess;
use App\Services\Billing\PlanPaymentService;
use App\Support\PlanGift;
use PHPUnit\Framework\TestCase;

/** Entitlement fixtures use the same server-owned tenant rows as the native plan resolver. */
final class AcademyEffectiveAccessTest extends TestCase
{
    private function now(): int
    {
        return (int) strtotime('2026-10-29 12:00:00 UTC');
    }

    private function tenant(array $overrides = []): array
    {
        return array_replace(['id' => 42, 'plan_slug' => 'pro_plus', 'subscription_status' => 'active'], $overrides);
    }

    private function gift(string $slug = 'pro_plus', ?string $endsAt = '2026-11-29 12:00:00 UTC'): array
    {
        return ['gift_id' => 17, 'plan_slug' => $slug, 'ends_at' => $endsAt];
    }

    public function testServerAssignedPaidTierDoesNotRequireAPaymentProviderSubscription(): void
    {
        foreach (PlanPaymentService::PAID_PLANS as $slug) {
            $tenant = $this->tenant(['plan_slug' => $slug, 'subscription_status' => 'none']);
            self::assertSame($slug, PlanGift::effectivePlanSlug($tenant, $this->now()));
            self::assertTrue(AcademyAccess::paid($tenant, $this->now()), $slug);
        }
    }

    public function testActivePaidPlansAndCanonicalSlugVariantsAreAccepted(): void
    {
        foreach (array_merge(PlanPaymentService::PAID_PLANS, ['PRO_PLUS', ' pro_plus ']) as $slug) {
            self::assertTrue(AcademyAccess::paid($this->tenant(['plan_slug' => $slug]), $this->now()), $slug);
        }
    }

    public function testCurrentGiftGrantsTheEffectivePaidTierToAFreeTenant(): void
    {
        foreach (['none', 'past_due', 'canceled'] as $status) {
            $tenant = $this->tenant([
                'plan_slug' => 'free',
                'subscription_status' => $status,
                'settings' => ['plan_gift' => $this->gift('PRO_PLUS')],
            ]);
            self::assertSame('pro_plus', PlanGift::effectivePlanSlug($tenant, $this->now()));
            self::assertTrue(AcademyAccess::paid($tenant, $this->now()), $status);
        }
    }

    public function testIndefiniteGiftAndJsonSettingsAreRecognised(): void
    {
        $tenant = $this->tenant([
            'plan_slug' => 'free',
            'subscription_status' => 'none',
            'settings' => json_encode(['plan_gift' => $this->gift('pro_plus', null)], JSON_THROW_ON_ERROR),
        ]);
        self::assertTrue(AcademyAccess::paid($tenant, $this->now()));
    }

    public function testCurrentPaidGiftTakesPrecedenceOverExpiredManualBilling(): void
    {
        $tenant = $this->tenant([
            'plan_slug' => 'free',
            'subscription_status' => 'none',
            'settings' => [
                'plan_gift' => $this->gift(),
                'manual_billing' => ['ends_at' => '2026-10-01 12:00:00 UTC'],
            ],
        ]);
        self::assertSame('pro_plus', PlanGift::effectivePlanSlug($tenant, $this->now()));
        self::assertTrue(AcademyAccess::paid($tenant, $this->now()));
    }

    public function testFreeEffectiveGiftCannotUnlockAStoredPaidBasePlan(): void
    {
        $tenant = $this->tenant(['settings' => ['plan_gift' => $this->gift('free')]]);
        self::assertSame('free', PlanGift::effectivePlanSlug($tenant, $this->now()));
        self::assertFalse(AcademyAccess::paid($tenant, $this->now()));
    }

    public function testExpiredRevokedAndMalformedGiftsDoNotUnlockAFreeTenant(): void
    {
        $gifts = [
            $this->gift('pro_plus', '2026-10-01 12:00:00 UTC'),
            $this->gift('pro_plus', '2026-10-29 12:00:00 UTC'),
            $this->gift('pro_plus', 'not-a-date'),
            null,
        ];
        foreach ($gifts as $gift) {
            $tenant = $this->tenant([
                'plan_slug' => 'free',
                'subscription_status' => 'none',
                'settings' => ['plan_gift' => $gift],
            ]);
            self::assertSame('free', PlanGift::effectivePlanSlug($tenant, $this->now()));
            self::assertFalse(AcademyAccess::paid($tenant, $this->now()));
        }
    }

    public function testExpiredGiftFallsBackToAnOtherwiseValidAssignedPaidTier(): void
    {
        $tenant = $this->tenant([
            'subscription_status' => 'none',
            'settings' => ['plan_gift' => $this->gift('pro', '2026-10-01 12:00:00 UTC')],
        ]);
        self::assertSame('pro_plus', PlanGift::effectivePlanSlug($tenant, $this->now()));
        self::assertTrue(AcademyAccess::paid($tenant, $this->now()));
    }

    public function testTrialsDoNotBecomePaidEntitlementsThroughTheEffectivePlanResolver(): void
    {
        $tenant = $this->tenant(['subscription_status' => 'trialing']);
        self::assertFalse(AcademyAccess::paid($tenant, $this->now()));

        $founder = $this->tenant([
            'plan_slug' => 'free',
            'subscription_status' => 'none',
            'settings' => ['founder_trial_ends_at' => '2026-11-29 12:00:00 UTC'],
        ]);
        self::assertSame('pro', PlanGift::effectivePlanSlug($founder, $this->now()));
        self::assertFalse(AcademyAccess::paid($founder, $this->now()));
    }

    public function testInactiveAndUnknownSubscriptionStatesAreDeniedWithoutACurrentGift(): void
    {
        foreach (['past_due', 'canceled', 'cancelled', 'unpaid', 'incomplete', 'incomplete_expired', 'paused', 'unknown'] as $status) {
            self::assertFalse(AcademyAccess::paid($this->tenant(['subscription_status' => $status]), $this->now()), $status);
        }
    }

    public function testExpiredOrInvalidManualPeriodCannotUnlockWithoutAProvider(): void
    {
        foreach (['active', 'none'] as $status) {
            foreach (['2026-10-01 12:00:00 UTC', '2026-10-29 12:00:00 UTC', 'not-a-date'] as $end) {
                $tenant = $this->tenant([
                    'subscription_status' => $status,
                    'settings' => ['manual_billing' => ['ends_at' => $end]],
                ]);
                self::assertFalse(AcademyAccess::paid($tenant, $this->now()), $status . ': ' . $end);
            }
        }
    }

    public function testActiveProviderSupersedesAnOldManualPeriod(): void
    {
        foreach (['stripe_subscription_id', 'paypal_subscription_id'] as $providerField) {
            $tenant = $this->tenant([
                $providerField => 'fixture-active-subscription',
                'settings' => ['manual_billing' => ['ends_at' => '2000-01-01 00:00:00 UTC']],
            ]);
            self::assertSame('pro_plus', PlanGift::effectivePlanSlug($tenant, $this->now()));
            self::assertTrue(AcademyAccess::paid($tenant, $this->now()), $providerField);
        }
    }

    public function testMissingTenantFreeAndUnknownPlansRemainDenied(): void
    {
        self::assertFalse(AcademyAccess::paid($this->tenant(['id' => 0]), $this->now()));
        foreach (['free', '', 'unknown'] as $slug) {
            self::assertFalse(AcademyAccess::paid($this->tenant(['plan_slug' => $slug]), $this->now()), $slug);
        }
    }
}
