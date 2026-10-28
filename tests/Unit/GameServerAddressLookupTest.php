<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\GameServerAddressLookup;
use PHPUnit\Framework\TestCase;

final class GameServerAddressLookupTest extends TestCase
{
    private const NAME = '[S.O.A.R] Task Force Green Training Camp';

    public function testSteamExactNameGivesAddressAndGamePort(): void
    {
        $urls = [];
        $lookup = new GameServerAddressLookup(null, static function (string $url) use (&$urls): ?string {
            $urls[] = $url;

            return json_encode(['response' => ['servers' => [
                ['addr' => '198.51.100.20:2303', 'gameport' => 2302, 'name' => '[S.O.A.R] Task Force Green Training Camp | Altis'],
                ['addr' => '198.51.100.9:2303', 'gameport' => 2302, 'name' => self::NAME],
            ]]]);
        }, 'steam-test-key-123');
        self::assertSame(['ip' => '198.51.100.9', 'port' => 2302, 'source' => 'steam'], $lookup->lookupByName(self::NAME));
        self::assertCount(1, $urls);
        self::assertStringContainsString('IGameServersService', $urls[0]);
    }

    public function testBattleMetricsWhenNoSteamKey(): void
    {
        $lookup = new GameServerAddressLookup(null, static fn (string $url): ?string => str_contains($url, 'battlemetrics')
            ? json_encode(['data' => [['attributes' => ['name' => self::NAME, 'ip' => '198.51.100.9', 'port' => 2302]]]])
            : null, '');
        self::assertSame(['ip' => '198.51.100.9', 'port' => 2302, 'source' => 'battlemetrics'], $lookup->lookupByName(self::NAME));
    }

    public function testOnlyIdenticalNameCounts(): void
    {
        $lookup = new GameServerAddressLookup(null, static fn (string $url): ?string => json_encode(
            ['data' => [['attributes' => ['name' => 'Task Force Green', 'ip' => '198.51.100.9', 'port' => 2302]]]]
        ), '');
        self::assertNull($lookup->lookupByName(self::NAME));
        self::assertNull($lookup->lookupByName('abc'));
    }
}
