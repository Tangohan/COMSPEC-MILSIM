<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OverwatchMinimumVersionOverride;
use PHPUnit\Framework\TestCase;

final class OverwatchMinimumVersionOverrideTest extends TestCase
{
    public function testItOnlyLowersAMinimumAboveThePublishedVersion(): void
    {
        $config = [
            'min_mod_version' => '1.6.0',
            'channel' => 'BETA',
            'chat_enabled' => false,
        ];

        self::assertSame([
            'min_mod_version' => '1.5.0',
            'channel' => 'BETA',
            'chat_enabled' => false,
        ], OverwatchMinimumVersionOverride::lowerIfAbove($config, '1.5.0'));
    }

    /**
     * @param array<string, mixed> $config
     * @dataProvider unchangedMinimumProvider
     */
    public function testItLeavesEqualLowerAndMissingMinimumsUntouched(array $config): void
    {
        self::assertSame($config, OverwatchMinimumVersionOverride::lowerIfAbove($config, '1.5.0'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function unchangedMinimumProvider(): iterable
    {
        yield 'equal' => [['min_mod_version' => '1.5.0', 'channel' => 'PROD']];
        yield 'lower' => [['min_mod_version' => '1.4.9', 'channel' => 'DEV']];
        yield 'missing' => [['channel' => 'PROD']];
    }
}
