<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SteamId;
use PHPUnit\Framework\TestCase;

final class SteamIdNormalizeTest extends TestCase
{
    public function testEmptyAndPlaceholdersYieldNull(): void
    {
        self::assertNull(SteamId::normalize(null));
        self::assertNull(SteamId::normalize(''));
        self::assertNull(SteamId::normalize('   '));
        self::assertNull(SteamId::normalize('_SP_player'));
        self::assertNull(SteamId::normalize('LOCAL'));
        self::assertNull(SteamId::normalize('AI'));
        self::assertNull(SteamId::normalize('not-a-steam-id'));
    }

    public function testSteam64IsKept(): void
    {
        self::assertSame('76561198000000000', SteamId::normalize('76561198000000000'));
    }
}
