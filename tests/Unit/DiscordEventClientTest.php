<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\DiscordEventClient;
use PHPUnit\Framework\TestCase;

final class DiscordEventClientTest extends TestCase
{
    public function testReactionPaginationSkipsBotsAndFetchesAllPages(): void
    {
        $client = $this->getMockBuilder(DiscordEventClient::class)->onlyMethods(['bot'])->getMock();
        $page = [];
        for ($i = 1; $i <= 100; $i++) {
            $page[] = ['id' => (string) $i, 'bot' => $i === 1];
        }
        $client->expects(self::exactly(2))->method('bot')->willReturnCallback(
            static function (string $method, string $path, string $token) use ($page): array {
                self::assertSame('GET', $method);
                self::assertSame('secret', $token);
                if (str_ends_with($path, 'after=0')) {
                    return $page;
                }
                self::assertStringEndsWith('after=100', $path);
                return [['id' => '101']];
            }
        );
        $ids = $client->reactionUsers('100', '200', '✅', 'secret');
        self::assertCount(100, $ids);
        self::assertSame('2', $ids[0]);
        self::assertSame('101', $ids[99]);
    }

    public function testWebhookRejectsNonDiscordDestinations(): void
    {
        $this->expectException(\RuntimeException::class);
        (new DiscordEventClient())->webhook('https://discord.com.evil.example/api/webhooks/123/token', []);
    }
}
