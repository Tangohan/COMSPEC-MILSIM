<?php

declare(strict_types=1);

namespace App\Services\Integrations;

final class DiscordWebhookChannelService
{
    public function __construct(private ?DiscordEventClient $client = null)
    {
        $this->client ??= new DiscordEventClient();
    }

    /** Le nom d’un webhook n’est pas le nom du salon. @return array{channel:string, channel_id:string} */
    public function resolve(string $url, string $botToken): array
    {
        if ($botToken === '') {
            throw new \RuntimeException('events.channel_needs_bot');
        }
        $webhook = $this->client->webhookInfo($url);
        $id = (string) ($webhook['channel_id'] ?? '');
        if (preg_match('/^\d{1,24}$/D', $id) !== 1) {
            throw new \RuntimeException('events.channel_unavailable');
        }
        $channel = $this->client->bot('GET', '/channels/' . $id, $botToken);
        $name = DiscordWebhookDirectory::cleanChannel((string) ($channel['name'] ?? ''));
        if ($name === '' || (string) ($channel['id'] ?? '') !== $id) {
            throw new \RuntimeException('events.channel_unavailable');
        }

        return ['channel' => $name, 'channel_id' => $id];
    }
}
