<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Email\EmailEvents;
use App\Services\Email\MailBudget;
use App\Services\Email\OpsMailDigestService;
use PHPUnit\Framework\TestCase;

final class MailBudgetTest extends TestCase
{
    /** @return array{limit:int, reserve:int, digest_minutes:int, muted:list<string>} */
    private static function cfg(int $limit = 90, int $reserve = 15, int $digest = 120, array $muted = []): array
    {
        return ['limit' => $limit, 'reserve' => $reserve, 'digest_minutes' => $digest, 'muted' => $muted];
    }

    public function testEssentialMailsAlwaysLeaveEvenOverQuota(): void
    {
        foreach ([EmailEvents::LOGIN_SECURITY_OTP, EmailEvents::PASSWORD_RESET, EmailEvents::USER_REGISTER_CONFIRMATION] as $code) {
            $p = MailBudget::priority($code, 'ops@example.com', ['ops@example.com']);
            self::assertSame(MailBudget::PRIORITY_CRITICAL, $p, $code);
            self::assertSame(MailBudget::SEND, MailBudget::decide($code, $p, 500, self::cfg(muted: [$code])));
        }
    }

    public function testOpsAlertsAreGroupedByEventOrByOpsInbox(): void
    {
        self::assertSame(MailBudget::PRIORITY_OPS, MailBudget::priority('error_alert'));
        self::assertSame(MailBudget::PRIORITY_OPS, MailBudget::priority(EmailEvents::ATAK_MOD_PLAYER_REPORT));
        self::assertSame(MailBudget::PRIORITY_OPS, MailBudget::priority(EmailEvents::NEW_COMMUNITY_MEMBER, 'Ops@Example.com', ['ops@example.com']));
        self::assertSame(MailBudget::PRIORITY_NORMAL, MailBudget::priority(EmailEvents::NEW_COMMUNITY_MEMBER, 'member@example.com', ['ops@example.com']));

        self::assertSame(MailBudget::GROUP, MailBudget::decide('error_alert', MailBudget::PRIORITY_OPS, 0, self::cfg()));
        // Récapitulatif désactivé : l'alerte suit le quota comme un e-mail ordinaire.
        self::assertSame(MailBudget::SEND, MailBudget::decide('error_alert', MailBudget::PRIORITY_OPS, 10, self::cfg(digest: 0)));
        self::assertSame(MailBudget::DEFER, MailBudget::decide('error_alert', MailBudget::PRIORITY_OPS, 80, self::cfg(digest: 0)));
    }

    public function testMemberMailsStopAtQuotaMinusReserveAndDigestMayUseTheReserve(): void
    {
        $code = EmailEvents::ATTENDANCE_REMINDER;
        self::assertSame(MailBudget::SEND, MailBudget::decide($code, MailBudget::PRIORITY_NORMAL, 74, self::cfg()));
        self::assertSame(MailBudget::DEFER, MailBudget::decide($code, MailBudget::PRIORITY_NORMAL, 75, self::cfg()));
        self::assertSame(MailBudget::SEND, MailBudget::decide($code, MailBudget::PRIORITY_NORMAL, 5000, self::cfg(limit: 0)));

        self::assertSame(MailBudget::PRIORITY_DIGEST, MailBudget::priority(EmailEvents::OPS_DIGEST, 'ops@example.com', ['ops@example.com']));
        self::assertSame(MailBudget::SEND, MailBudget::decide(EmailEvents::OPS_DIGEST, MailBudget::PRIORITY_DIGEST, 85, self::cfg()));
        self::assertSame(MailBudget::DEFER, MailBudget::decide(EmailEvents::OPS_DIGEST, MailBudget::PRIORITY_DIGEST, 90, self::cfg()));
    }

    public function testMutedTypesAreSkippedButEssentialsCannotBeMuted(): void
    {
        $muted = MailBudget::parseMuted('NEW_DEVICE_LOGIN, PASSWORD_RESET;OPS_DIGEST bad-code!');
        self::assertSame(['NEW_DEVICE_LOGIN'], $muted);
        self::assertSame(MailBudget::MUTE, MailBudget::decide('NEW_DEVICE_LOGIN', MailBudget::PRIORITY_NORMAL, 0, self::cfg(muted: $muted)));
        self::assertFalse(MailBudget::canMute(EmailEvents::LOGIN_SECURITY_OTP));
    }

    public function testConfigFallsBackToEnvThenDefaultsAndClampsReserve(): void
    {
        $c = MailBudget::configFrom([]);
        self::assertSame(MailBudget::DEFAULT_DAILY_LIMIT, $c['limit']);
        self::assertSame(MailBudget::DEFAULT_DIGEST_MINUTES, $c['digest_minutes']);
        self::assertSame(280, MailBudget::configFrom([], 280)['limit']);
        self::assertSame(50, MailBudget::configFrom([MailBudget::SETTING_DAILY_LIMIT => '50'], 280)['limit']);
        self::assertSame(10, MailBudget::configFrom([MailBudget::SETTING_DAILY_LIMIT => '10', MailBudget::SETTING_RESERVE => '40'])['reserve']);
    }

    public function testNextWindowIsJustAfterMidnight(): void
    {
        $w = MailBudget::nextWindow(strtotime('2026-10-18 14:30:00'));
        self::assertSame('2026-10-19 00:05', $w->format('Y-m-d H:i'));
    }

    public function testDigestListsEachAlertAndCountsByType(): void
    {
        $rows = [
            ['id' => 1, 'event_code' => 'error_alert', 'subject' => '[Athena] Incident technique — /api/x', 'body_text' => 'Trace…', 'created_at' => '2026-10-18 10:00:00'],
            ['id' => 2, 'event_code' => 'error_alert', 'subject' => '[Athena] Incident technique — /api/y', 'body_text' => '<b>x</b>', 'created_at' => '2026-10-18 10:05:00'],
            ['id' => 3, 'event_code' => EmailEvents::PLATFORM_ACCOUNT_CREATED, 'subject' => 'Nouveau compte créé — Bob', 'body_text' => '', 'created_at' => '2026-10-18 10:10:00'],
        ];
        [$subject, $html, $text] = OpsMailDigestService::compose($rows, 'https://example.test/admin/system/e-mails');
        self::assertStringContainsString('3 alertes', $subject);
        self::assertStringContainsString('2 × Incident technique', $text);
        self::assertStringContainsString('Nouveau compte créé — Bob', $text);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        self::assertStringContainsString('https://example.test/admin/system/e-mails', $html);
    }
}
