<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\EmailDeliveryRepository;
use App\Services\Email\EmailEvents;
use App\Services\Email\MailBudget;
use App\Services\Email\OpsMailDigestService;
use App\Services\EmailService;
use App\Services\EmailTemplateEngine;
use App\Services\EmailTransportResolver;
use App\Services\Monitoring\ErrorAlertThrottle;
use App\Services\Monitoring\ErrorReportMailer;
use PHPUnit\Framework\TestCase;

/** Quota Hostinger : aucun e-mail SSE, plafond par destinataire, résumé quotidien, garde-fou du jour, essentiels toujours envoyés. */
final class MailVolumeReductionTest extends TestCase
{
    /** @return array{limit:int, reserve:int, digest_minutes:int, muted:list<string>} */
    private static function cfg(int $limit = 90, array $muted = []): array
    {
        return ['limit' => $limit, 'reserve' => 15, 'digest_minutes' => 120, 'muted' => $muted];
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testSseEmailIsNeverHandedToTheTransport(): void
    {
        $transport = $this->createMock(EmailTransportResolver::class);
        $transport->expects(self::never())->method('sendMessage');
        $deliveries = $this->createMock(EmailDeliveryRepository::class);
        $deliveries->expects(self::once())->method('insert')
            ->with(7, EmailEvents::SSE_ANALYST_DIGEST, 'analyste@example.com', self::anything(), 'none', 'muted');
        $svc = new EmailService($transport, $this->createMock(EmailTemplateEngine::class), $deliveries);

        self::assertTrue($svc->send(EmailEvents::SSE_ANALYST_DIGEST, 'analyste@example.com', 'Point SSE', '<p>x</p>', 'x', 7));
        self::assertSame(MailBudget::MUTE, MailBudget::decide(EmailEvents::SSE_ANALYST_DIGEST, MailBudget::PRIORITY_NORMAL, 0, self::cfg(limit: 0)));
        self::assertFalse(MailBudget::canMute(EmailEvents::SSE_ANALYST_DIGEST), 'pas réactivable depuis l’administration');
    }

    public function testNoSseCodeSendsEmailAndSseErrorsStayLocal(): void
    {
        $files = array_merge(
            glob(self::root() . '/app/Services/Sse/*.php') ?: [],
            glob(self::root() . '/app/Controllers/Api/Sse*.php') ?: [],
            glob(self::root() . '/app/Controllers/Web/Sse*.php') ?: [],
            glob(self::root() . '/app/Services/Cron/Jobs/Sse*.php') ?: [],
        );
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/EmailService|->send(Templated)?\(|\bmail\(/', $src, basename($file));
        }
        self::assertStringNotContainsString('sendSseAnalystDigest', (string) file_get_contents(self::root() . '/app/Services/EmailService.php'));

        foreach (['/api/sse/persons', 'api/sse/notes', '/atak/sse/dossiers/4', '/api/atak/sse-tracks'] as $path) {
            self::assertTrue(ErrorReportMailer::isSsePath($path), $path);
        }
        foreach (['/api/atak/reports', '/account/security', '/'] as $path) {
            self::assertFalse(ErrorReportMailer::isSsePath($path), $path);
        }
    }

    public function testSameTypeToSameRecipientAtMostOncePerPeriod(): void
    {
        $code = EmailEvents::TENANT_INTERNAL_MESSAGE_THREAD;
        self::assertTrue(MailBudget::capsRepeats($code));
        self::assertSame(MailBudget::SEND, MailBudget::decide($code, MailBudget::PRIORITY_NORMAL, 0, self::cfg(), false));
        self::assertSame(MailBudget::GROUP, MailBudget::decide($code, MailBudget::PRIORITY_NORMAL, 0, self::cfg(), true));
        // Rappels d'activité et annulations : un par activité, même plusieurs le même jour.
        self::assertFalse(MailBudget::capsRepeats(EmailEvents::ATTENDANCE_REMINDER));
        // Informations d'encadrement : toujours dans le résumé quotidien.
        self::assertSame(MailBudget::GROUP, MailBudget::decide(EmailEvents::NEW_COMMUNITY_MEMBER, MailBudget::PRIORITY_NORMAL, 0, self::cfg()));
        self::assertSame(MailBudget::PRIORITY_DIGEST, MailBudget::priority(EmailEvents::MEMBER_DAILY_DIGEST));
    }

    public function testDailyGuardDefersNonCriticalButCriticalAlwaysLeaves(): void
    {
        $limit = 90;
        self::assertSame(MailBudget::DEFER, MailBudget::decide(EmailEvents::ATTENDANCE_REMINDER, MailBudget::PRIORITY_NORMAL, 75, self::cfg($limit)));
        self::assertSame(MailBudget::DEFER, MailBudget::decide(EmailEvents::MEMBER_DAILY_DIGEST, MailBudget::PRIORITY_DIGEST, 90, self::cfg($limit)));
        self::assertSame('00:05', MailBudget::nextWindow(strtotime('2026-11-01 18:00:00'))->format('H:i'));
        foreach ([EmailEvents::PASSWORD_RESET, EmailEvents::USER_REGISTER_CONFIRMATION, EmailEvents::EMAIL_VERIFICATION, EmailEvents::LOGIN_SECURITY_OTP] as $code) {
            $p = MailBudget::priority($code);
            self::assertSame(MailBudget::PRIORITY_CRITICAL, $p, $code);
            self::assertSame(MailBudget::SEND, MailBudget::decide($code, $p, 10_000, self::cfg($limit, [$code]), true), $code);
        }
    }

    public function testNonEssentialTypesAreMutedUntilTheAdminReviewsThem(): void
    {
        $c = MailBudget::configFrom([]);
        self::assertContains(EmailEvents::ATTENDANCE_RSVP_CONFIRM, $c['muted']);
        self::assertContains(EmailEvents::REGISTER_SECURITY_COMPANION, $c['muted']);
        $kept = MailBudget::configFrom([MailBudget::SETTING_MUTED => 'NEW_DEVICE_LOGIN']);
        self::assertContains(EmailEvents::NEW_DEVICE_LOGIN, $kept['muted']);
        self::assertContains(EmailEvents::ATTENDANCE_CHECKIN_CONFIRM, $kept['muted']);
        $reviewed = MailBudget::configFrom([MailBudget::SETTING_MUTED => '', MailBudget::SETTING_MUTED_REVIEWED => '1']);
        self::assertSame([], $reviewed['muted']);
        foreach (MailBudget::DEFAULT_MUTED as $code) {
            self::assertTrue(MailBudget::canMute($code), $code);
        }
    }

    public function testMemberDailySummaryIsReadableInBothLanguages(): void
    {
        $rows = [
            ['id' => 1, 'event_code' => EmailEvents::NEW_COMMUNITY_MEMBER, 'subject' => 'Nouveau membre : Bravo', 'body_text' => 'Bravo a rejoint <la> communauté.', 'created_at' => '2026-11-01 09:00:00'],
            ['id' => 2, 'event_code' => EmailEvents::TENANT_INTERNAL_MESSAGE_THREAD, 'subject' => 'Nouveau message', 'body_text' => 'Salut', 'created_at' => '2026-11-01 10:00:00'],
        ];
        [$subject, $html, $text] = OpsMailDigestService::composeMember($rows, 'https://example.test/account/preferences#notifications-email');
        self::assertStringContainsString('2 notifications', $subject);
        self::assertStringContainsString('Nouveau membre : Bravo', $text);
        self::assertStringContainsString('&lt;la&gt;', $html);
        self::assertStringContainsString('account/preferences#notifications-email', $html);
        self::assertStringContainsString('Your daily summary', OpsMailDigestService::composeMember($rows, 'x', 'en')[0]);

        self::assertFalse(OpsMailDigestService::isOpsRecipient('membre@example.com', $rows, ['ops@example.com' => 0]));
        self::assertTrue(OpsMailDigestService::isOpsRecipient('OPS@example.com', $rows, ['ops@example.com' => 0]));
        self::assertTrue(OpsMailDigestService::isOpsRecipient('x@example.com', [['event_code' => 'error_alert']], []));
    }

    public function testErrorAlertsAtMostOncePerSignaturePerHour(): void
    {
        $throttle = ErrorAlertThrottle::fromEnv();
        $key = 'test|' . bin2hex(random_bytes(8));
        self::assertFalse($throttle->isThrottled($key));
        self::assertTrue($throttle->isThrottled($key), 'même signature dans l’heure');
        @unlink(self::root() . '/storage/cache/error-alerts/' . hash('sha256', $key) . '.json');
        self::assertGreaterThanOrEqual(3600, ErrorAlertThrottle::MIN_COOLDOWN_SECONDS);
        $src = (string) file_get_contents(self::root() . '/app/Services/Monitoring/ErrorReportMailer.php');
        self::assertStringNotContainsString("\$fingerprint . '|' . \$ip", $src, 'la signature ne dépend plus de l’IP');
    }
}
