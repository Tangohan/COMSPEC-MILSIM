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
}
