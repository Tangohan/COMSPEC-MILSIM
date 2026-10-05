<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakDevicePresenter;
use PHPUnit\Framework\TestCase;

final class AtakDevicePresenterTest extends TestCase
{
    public function testGameTabletIsTheAndroidPhone(): void
    {
        self::assertSame('phone', AtakDevicePresenter::terminalType(['terminal_type' => 'tablet', 'platform_label' => 'Arma 3 · COMSPEC 2.0.60']));
        self::assertSame('tablet', AtakDevicePresenter::terminalType(['terminal_type' => 'tablet', 'platform_label' => '']));
        self::assertSame('desktop', AtakDevicePresenter::terminalType(['terminal_type' => 'desktop', 'platform_label' => 'Arma 3 / COMSPEC']));
    }

    public function testCertificateLifetime(): void
    {
        $now = 1_800_000_000;
        $expiring = AtakDevicePresenter::certificateLifetime([
            'status' => 'active',
            'valid_from' => gmdate('Y-m-d H:i:s', $now - 340 * 86400),
            'expires_at' => gmdate('Y-m-d H:i:s', $now + 10 * 86400),
        ], $now);
        self::assertSame('expiring', $expiring['state']);
        self::assertSame(10, $expiring['days_left']);
        self::assertSame(97, $expiring['elapsed_pct']);

        self::assertSame('revoked', AtakDevicePresenter::certificateLifetime(['status' => 'revoked'], $now)['state']);
        self::assertSame('expired', AtakDevicePresenter::certificateLifetime(['status' => 'active', 'expires_at' => gmdate('Y-m-d H:i:s', $now - 60)], $now)['state']);
        self::assertSame('none', AtakDevicePresenter::certificateLifetime([], $now)['state']);
    }

    public function testFormatting(): void
    {
        self::assertSame('AB:CD:01', AtakDevicePresenter::colonHex('abcd01'));
        self::assertSame('045 112', AtakDevicePresenter::gridRef(4520, 11230));
        self::assertSame(44.0, AtakDevicePresenter::wattsToDbm(25));
    }

    public function testLiaisonChecksFlagMissingCertificateAndPendingValidation(): void
    {
        $now = 1_800_000_000;
        $checks = AtakDevicePresenter::liaisonChecks([
            'user_id' => 4,
            'status' => 'pending',
            'last_seen_at' => gmdate('Y-m-d H:i:s', $now - 30),
            'server_signature' => 'a1b2c3',
        ], $now);
        $byKey = array_column($checks, 'state', 'key');

        self::assertSame('ok', $byKey['account']);
        self::assertSame('warn', $byKey['authorized']);
        self::assertSame('bad', $byKey['certificate']);
        self::assertSame('ok', $byKey['heartbeat']);
        self::assertSame('bad', AtakDevicePresenter::liaisonVerdict($checks));
    }

    public function testLinkEventsKeepOnlyLinkChannels(): void
    {
        $events = AtakDevicePresenter::linkEvents([
            ['channel' => 'liaison', 'level' => 'WARN', 'message' => 'Perte de liaison', 'logged_at' => '2026-10-05 08:00:00'],
            ['channel' => 'markers', 'level' => 'error', 'message' => 'Échec marqueurs', 'logged_at' => '2026-10-05 07:59:00'],
            ['channel' => 'boot', 'level' => 'info', 'message' => 'Démarré', 'logged_at' => '2026-10-05 07:58:00'],
        ]);

        self::assertCount(2, $events);
        self::assertSame('Liaison', $events[0]['module']);
        self::assertSame('warn', $events[0]['level']);
        self::assertSame('Démarré', $events[1]['message']);
    }
}
