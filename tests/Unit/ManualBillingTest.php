<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ManualBilling;
use App\Support\PlanAccessWindow;
use App\Support\PlanGift;
use PHPUnit\Framework\TestCase;

final class ManualBillingTest extends TestCase
{
    public function testReferenceFormat(): void
    {
        for ($i = 0; $i < 20; $i++) {
            self::assertMatchesRegularExpression('/^ATH-[A-HJ-KMNP-Z2-9]{6}$/', ManualBilling::generateReference());
        }
    }

    public function testPaypalUrlCarriesAmount(): void
    {
        self::assertSame('https://www.paypal.me/TTRDProIndividual/9.99EUR', ManualBilling::paypalUrl('https://www.paypal.me/TTRDProIndividual/', 999));
        self::assertSame('https://www.paypal.me/TTRDProIndividual/2.00EUR', ManualBilling::paypalUrl('', 200));
    }

    public function testFormatAmount(): void
    {
        self::assertSame('10 €', ManualBilling::formatAmount(1000));
        self::assertSame('9,90 €', ManualBilling::formatAmount(990));
    }

    public function testPeriodEndExtendsRunningPeriod(): void
    {
        $now = strtotime('2026-10-25 10:00:00');
        self::assertSame('2026-11-25 10:00:00', ManualBilling::periodEnd(null, 'monthly', $now));
        self::assertSame('2027-10-25 10:00:00', ManualBilling::periodEnd('2026-01-01 00:00:00', 'yearly', $now));
        // Période encore en cours : la nouvelle s’ajoute à la fin.
        self::assertSame('2026-12-30 00:00:00', ManualBilling::periodEnd('2026-11-30 00:00:00', 'monthly', $now));
    }

    public function testExpiredManualPeriodFallsBackToFree(): void
    {
        $now = strtotime('2026-12-01 00:00:00');
        $tenant = [
            'plan_slug' => 'pro',
            'subscription_status' => 'active',
            'settings' => json_encode([ManualBilling::SETTINGS_KEY => ['reference' => 'ATH-ABCDEF', 'plan_slug' => 'pro', 'interval' => 'monthly', 'ends_at' => '2026-11-25 10:00:00']]),
        ];

        self::assertTrue(ManualBilling::isExpired($tenant, $now));
        self::assertFalse(ManualBilling::paidActive($tenant, $now));
        self::assertSame('free', PlanGift::effectivePlanSlug($tenant, $now));
        self::assertSame('base', PlanAccessWindow::source($tenant, $now)['kind']);

        $running = strtotime('2026-11-10 00:00:00');
        self::assertSame('pro', PlanGift::effectivePlanSlug($tenant, $running));
        self::assertSame(['kind' => 'paid', 'plan_slug' => 'pro', 'ends_at' => '2026-11-25 10:00:00'], PlanAccessWindow::source($tenant, $running));
    }

    public function testProviderSubscriptionNeverExpiresFromManualSettings(): void
    {
        $tenant = [
            'plan_slug' => 'standard',
            'subscription_status' => 'active',
            'paypal_subscription_id' => 'I-123',
            'settings' => json_encode([ManualBilling::SETTINGS_KEY => ['plan_slug' => 'standard', 'ends_at' => '2020-01-01 00:00:00']]),
        ];

        self::assertFalse(ManualBilling::isExpired($tenant));
        self::assertSame('standard', PlanGift::effectivePlanSlug($tenant));
    }
}
