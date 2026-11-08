<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\DiscordEventClient;
use App\Services\Integrations\DiscordWebhookChannelService;
use PHPUnit\Framework\TestCase;

final class DiscordWebhookChannelServiceTest extends TestCase
{
    public function testReadsActualChannelNameRatherThanWebhookName(): void
    {
        $client = $this->createMock(DiscordEventClient::class);
        $client->expects(self::once())->method('webhookInfo')->with('https://discord.com/api/webhooks/123/token')->willReturn(['channel_id' => '456', 'name' => 'Athena webhook']);
        $client->expects(self::once())->method('bot')->with('GET', '/channels/456', 'bot-secret')->willReturn(['id' => '456', 'name' => 'operations']);
        $client->expects(self::never())->method('webhook');
        self::assertSame(['channel' => '#operations', 'channel_id' => '456'], (new DiscordWebhookChannelService($client))->resolve('https://discord.com/api/webhooks/123/token', 'bot-secret'));
    }

    public function testMissingBotMakesNoNetworkRequest(): void
    {
        $client = $this->createMock(DiscordEventClient::class);
        $client->expects(self::never())->method('webhookInfo');
        $this->expectExceptionMessage('events.channel_needs_bot');
        (new DiscordWebhookChannelService($client))->resolve('https://discord.com/api/webhooks/123/token', '');
    }

    public function testInvalidChannelIdNeverReachesBotEndpoint(): void
    {
        $client = $this->createMock(DiscordEventClient::class);
        $client->method('webhookInfo')->willReturn(['channel_id' => '../users/@me']);
        $client->expects(self::never())->method('bot');
        $this->expectExceptionMessage('events.channel_unavailable');
        (new DiscordWebhookChannelService($client))->resolve('https://discord.com/api/webhooks/123/token', 'secret');
    }

    public function testMissingChannelNameDoesNotFallBackToWebhookName(): void
    {
        $client = $this->createMock(DiscordEventClient::class);
        $client->method('webhookInfo')->willReturn(['channel_id' => '456', 'name' => 'Webhook name']);
        $client->method('bot')->willReturn(['id' => '456']);
        $this->expectExceptionMessage('events.channel_unavailable');
        (new DiscordWebhookChannelService($client))->resolve('https://discord.com/api/webhooks/123/token', 'secret');
    }
}
