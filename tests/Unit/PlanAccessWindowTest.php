<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PlanAccessWindow;
use PHPUnit\Framework\TestCase;

final class PlanAccessWindowTest extends TestCase
{
    private const NOW = 1_790_000_000;

    private static function at(int $deltaDays): string
    {
        return date('c', self::NOW + $deltaDays * 86400);
    }

    public function testTrialEndingSoonIsReportedWithDaysLeft(): void
    {
        $tenant = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['founder_trial_ends_at' => self::at(3)])];

        $soon = PlanAccessWindow::endingSoon($tenant, 7, self::NOW);
        self::assertNotNull($soon);
        self::assertSame('trial', $soon['kind']);
        self::assertSame('pro', $soon['plan_slug']);
        self::assertSame(3, $soon['days_left']);
        self::assertNull(PlanAccessWindow::endingSoon($tenant, 2, self::NOW));
    }

    public function testPaidSubscriptionIsNeverEnding(): void
    {
        $tenant = ['plan_slug' => 'standard', 'subscription_status' => 'active', 'settings' => ['founder_trial_ends_at' => self::at(2)]];

        self::assertSame('paid', PlanAccessWindow::source($tenant, self::NOW)['kind']);
        self::assertNull(PlanAccessWindow::endingSoon($tenant, 7, self::NOW));
        self::assertNull(PlanAccessWindow::grace($tenant, 7, self::NOW));
    }

    public function testGiftEndingSoonWinsOverTrial(): void
    {
        $tenant = ['plan_slug' => 'free', 'settings' => [
            'founder_trial_ends_at' => self::at(20),
            'plan_gift' => ['gift_id' => 4, 'plan_slug' => 'standard', 'ends_at' => date('Y-m-d H:i:s', self::NOW + 2 * 86400)],
        ]];

        $soon = PlanAccessWindow::endingSoon($tenant, 7, self::NOW);
        self::assertSame('gift', $soon['kind'] ?? null);
        self::assertSame('standard', $soon['plan_slug'] ?? null);
    }

    public function testGraceOnlyWhenEnabledAndWithinDays(): void
    {
        $tenant = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => ['founder_trial_ends_at' => self::at(-3)]];

        self::assertNull(PlanAccessWindow::grace($tenant, 0, self::NOW), 'grâce désactivée');
        $g = PlanAccessWindow::grace($tenant, 7, self::NOW);
        self::assertNotNull($g);
        self::assertSame('pro', $g['plan_slug']);
        self::assertSame(4, $g['days_left']);
        self::assertNull(PlanAccessWindow::grace($tenant, 2, self::NOW), 'grâce déjà écoulée');
    }

    public function testGraceUsesLatestEndedSourceAndSkipsPastDue(): void
    {
        $tenant = ['plan_slug' => 'free', 'settings' => [
            'founder_trial_ends_at' => self::at(-30),
            'plan_gift' => ['gift_id' => 2, 'plan_slug' => 'pro_plus', 'ends_at' => self::at(-1)],
        ]];

        $g = PlanAccessWindow::grace($tenant, 7, self::NOW);
        self::assertSame('gift', $g['kind'] ?? null);
        self::assertSame('pro_plus', $g['plan_slug'] ?? null);

        $tenant['subscription_status'] = 'past_due';
        self::assertNull(PlanAccessWindow::grace($tenant, 7, self::NOW));
    }

    public function testWarningFingerprintIsPerEnd(): void
    {
        $end = self::at(3);
        $tenant = ['settings' => [PlanAccessWindow::WARNED_KEY => PlanAccessWindow::warningFingerprint('trial', $end)]];

        self::assertTrue(PlanAccessWindow::alreadyWarned($tenant, 'trial', $end));
        self::assertFalse(PlanAccessWindow::alreadyWarned($tenant, 'trial', self::at(40)));
        self::assertFalse(PlanAccessWindow::alreadyWarned($tenant, 'gift', $end));
    }

    public function testClampDays(): void
    {
        self::assertSame(7, PlanAccessWindow::clampDays('', 7));
        self::assertSame(7, PlanAccessWindow::clampDays('abc', 7));
        self::assertSame(0, PlanAccessWindow::clampDays('-4', 7));
        self::assertSame(PlanAccessWindow::MAX_DAYS, PlanAccessWindow::clampDays('999', 7));
        self::assertSame(10, PlanAccessWindow::clampDays('10', 7));
    }

    public function testCopyIsWrittenInBothLanguages(): void
    {
        $fr = PlanAccessWindow::copy('fr', 'Bet Leader', 'Pro', 'trial', '2026-10-20T10:00:00+00:00', true, 7);
        $en = PlanAccessWindow::copy('en', 'Bet Leader', 'Pro', 'trial', '2026-10-20T10:00:00+00:00', true, 0);

        self::assertStringContainsString('20/10/2026', $fr['lead']);
        self::assertStringContainsString('vidéo drone', $fr['lost']);
        self::assertStringContainsString('positions, comptes rendus et briefing', $fr['lost']);
        self::assertStringContainsString('7 jours', $fr['grace']);
        self::assertStringContainsString('October 20, 2026', $en['lead']);
        self::assertSame('', $en['grace']);
    }

    public function testManualPaypalPeriodEndingSoonIsReported(): void
    {
        $tenant = ['plan_slug' => 'pro', 'subscription_status' => 'active', 'settings' => [
            'manual_billing' => ['reference' => 'ATH-7KQ2XM', 'plan_slug' => 'pro', 'interval' => 'monthly', 'ends_at' => date('Y-m-d H:i:s', self::NOW + 5 * 86400)],
        ]];

        $soon = PlanAccessWindow::endingSoon($tenant, 7, self::NOW);
        self::assertSame('paid', $soon['kind'] ?? null);
        self::assertSame('pro', $soon['plan_slug'] ?? null);
        self::assertSame(5, $soon['days_left'] ?? null);
        self::assertSame('first', PlanAccessWindow::reminderStage('paid', 5));
        self::assertSame('last', PlanAccessWindow::reminderStage('paid', 1));
        self::assertSame('', PlanAccessWindow::reminderStage('gift', 1));
        self::assertSame(7, PlanAccessWindow::paidReminderDays(0));
    }

    public function testManualPaypalPeriodExpiredIsLapsedAndNoticed(): void
    {
        $tenant = ['plan_slug' => 'pro', 'subscription_status' => 'active', 'settings' => [
            'manual_billing' => ['reference' => 'ATH-7KQ2XM', 'plan_slug' => 'pro', 'interval' => 'monthly', 'ends_at' => date('Y-m-d H:i:s', self::NOW - 2 * 86400)],
        ]];

        self::assertNull(PlanAccessWindow::endingSoon($tenant, 7, self::NOW));
        self::assertSame('paid', PlanAccessWindow::lapsed($tenant, self::NOW)['kind'] ?? null);
        self::assertSame('pro', PlanAccessWindow::paidExpired($tenant, self::NOW)['plan_slug'] ?? null);
        self::assertNull(PlanAccessWindow::paidExpired($tenant, self::NOW + 40 * 86400));
        $copy = PlanAccessWindow::paidExpiredCopy('fr', 'Pro', date('Y-m-d H:i:s', self::NOW - 2 * 86400));
        self::assertStringContainsString('payée par PayPal', $copy['text']);
    }

    public function testPaidReminderFingerprintsAreDistinctPerStage(): void
    {
        $end = '2026-11-25 10:00:00';
        $tenant = ['settings' => [PlanAccessWindow::WARNED_KEY => PlanAccessWindow::warningFingerprint('paid', $end, 'first')]];

        self::assertTrue(PlanAccessWindow::alreadyWarned($tenant, 'paid', $end, 'first'));
        self::assertFalse(PlanAccessWindow::alreadyWarned($tenant, 'paid', $end, 'last'));
        self::assertSame('trial|' . $end, PlanAccessWindow::warningFingerprint('trial', $end));
    }
}
