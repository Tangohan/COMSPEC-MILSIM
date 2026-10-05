<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\AccountActivityService;
use PHPUnit\Framework\TestCase;

final class AccountActivityServiceTest extends TestCase
{
    public function testDeviceLabelReadsBrowserAndSystem(): void
    {
        self::assertSame('Firefox sur Windows', AccountActivityService::deviceLabel('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0'));
        self::assertSame('Chrome sur Android', AccountActivityService::deviceLabel('Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36'));
        self::assertSame('Edge sur Windows', AccountActivityService::deviceLabel('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/129.0 Safari/537.36 Edg/129.0'));
        self::assertSame('Appareil inconnu', AccountActivityService::deviceLabel(''));
    }

    public function testIpIsTruncated(): void
    {
        self::assertSame('92.184.•.•', AccountActivityService::maskIp('92.184.12.7'));
        self::assertSame('2a01:cb00:…', AccountActivityService::maskIp('2a01:cb00:1234::1'));
        self::assertSame('—', AccountActivityService::maskIp('not-an-ip'));
    }
}
