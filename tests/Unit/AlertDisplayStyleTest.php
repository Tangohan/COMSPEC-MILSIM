<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AlertDisplayStyle;
use PHPUnit\Framework\TestCase;

final class AlertDisplayStyleTest extends TestCase
{
    public function testParsePlatformListKeepsSingleHistoricValue(): void
    {
        self::assertSame(['classic'], AlertDisplayStyle::parsePlatformList('classic'));
        self::assertSame(['mini_info'], AlertDisplayStyle::parsePlatformList('mini_info'));
    }

    public function testParsePlatformListCombinesAndDedupes(): void
    {
        self::assertSame(
            ['classic', 'mini_info', 'popup'],
            AlertDisplayStyle::parsePlatformList('classic,mini_info,popup,classic')
        );
        self::assertSame(
            ['classic', 'breaking'],
            AlertDisplayStyle::parsePlatformList(['classic', 'nope', 'breaking'])
        );
    }

    public function testEncodePlatformListFallsBackToClassic(): void
    {
        self::assertSame('classic', AlertDisplayStyle::encodePlatformList([]));
        self::assertSame('classic,popup', AlertDisplayStyle::encodePlatformList(['classic', 'popup']));
    }

    public function testSanitizePlatformTakesFirstOfList(): void
    {
        self::assertSame('classic', AlertDisplayStyle::sanitizePlatform('classic,mini_info'));
        self::assertSame('popup', AlertDisplayStyle::sanitizePlatform('popup'));
    }
}
