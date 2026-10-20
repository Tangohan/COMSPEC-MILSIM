<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\AdminUserAtakService;
use PHPUnit\Framework\TestCase;

final class AdminUserAtakServiceTest extends TestCase
{
    public function testParseSteamAcceptsIdAndProfileUrl(): void
    {
        self::assertSame('76561198012345678', AdminUserAtakService::parseSteam('76561198012345678'));
        self::assertSame('76561198012345678', AdminUserAtakService::parseSteam(' https://steamcommunity.com/profiles/76561198012345678/ '));
        self::assertNull(AdminUserAtakService::parseSteam(''));
        self::assertNull(AdminUserAtakService::parseSteam('pas-un-steam'));
    }
}
