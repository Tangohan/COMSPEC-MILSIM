<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DiscordChannelInput;
use App\Support\QuickPictureDiscordEmbed;
use PHPUnit\Framework\TestCase;

final class DiscordChannelInputTest extends TestCase
{
    private const TOKEN = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-AbCdEfGhIjKlMnOpQrStUvWxYz01';

    public function testWebhookUrlIsNormalized(): void
    {
        $a = DiscordChannelInput::analyze('  https://canary.discordapp.com/api/v10/webhooks/123456789012345678/' . self::TOKEN . '  ');
        self::assertSame(DiscordChannelInput::KIND_WEBHOOK, $a['kind']);
        self::assertSame('https://discord.com/api/webhooks/123456789012345678/' . self::TOKEN, $a['url']);
        self::assertSame(
            'https://discord.com/api/webhooks/1/' . self::TOKEN . '?thread_id=123456789012345678',
            DiscordChannelInput::webhookUrl('<https://discord.com/api/webhooks/1/' . self::TOKEN . '?thread_id=123456789012345678&wait=true>')
        );
        self::assertSame('https://discord.com/api/webhooks/1/' . self::TOKEN, DiscordChannelInput::webhookUrl('discord.com/api/webhooks/1/' . self::TOKEN));
        self::assertNull(DiscordChannelInput::problem('https://discord.com/api/webhooks/1/' . self::TOKEN, 'X'));
        self::assertNull(DiscordChannelInput::problem('', 'X'));
    }

    public function testChannelIdAndChannelLinkAreRecognizedWithClearMessage(): void
    {
        $id = DiscordChannelInput::analyze('1234567890123456789');
        self::assertSame(DiscordChannelInput::KIND_CHANNEL_ID, $id['kind']);
        self::assertSame('1234567890123456789', $id['channel_id']);
        self::assertSame('1234567890123456789', DiscordChannelInput::analyze('<#1234567890123456789>')['channel_id']);

        $link = DiscordChannelInput::analyze('https://discord.com/channels/111111111111111111/222222222222222222');
        self::assertSame(DiscordChannelInput::KIND_CHANNEL_LINK, $link['kind']);
        self::assertSame('111111111111111111', $link['guild_id']);
        self::assertSame('222222222222222222', $link['channel_id']);

        $msg = (string) DiscordChannelInput::problem('1234567890123456789', 'Photos');
        self::assertStringContainsString('identifiant du salon', $msg);
        self::assertStringContainsString('Webhooks', $msg);
        self::assertStringContainsString('lien du salon', (string) DiscordChannelInput::problem('https://discord.com/channels/111111111111111111/222222222222222222', 'P'));
        self::assertSame('', DiscordChannelInput::webhookUrl('1234567890123456789'));
    }

    public function testInviteIncompleteAndForeignAreRejected(): void
    {
        self::assertSame(DiscordChannelInput::KIND_INVITE, DiscordChannelInput::analyze('https://discord.gg/abcd')['kind']);
        self::assertSame(DiscordChannelInput::KIND_WEBHOOK_INCOMPLETE, DiscordChannelInput::analyze('https://discord.com/api/webhooks/123456789012345678')['kind']);
        self::assertSame(DiscordChannelInput::KIND_OTHER, DiscordChannelInput::analyze('https://evil.example/api/webhooks/1/' . self::TOKEN)['kind']);
        self::assertSame(DiscordChannelInput::KIND_OTHER, DiscordChannelInput::analyze('salon-photos')['kind']);
        self::assertStringContainsString('incomplet', (string) DiscordChannelInput::problem('https://discord.com/api/webhooks/123456789012345678', 'P'));
    }

    public function testQuickPictureEmbedHasPhotoDateTimeAndZone(): void
    {
        $meta = [
            'author_callsign' => 'TA1',
            'unit_name' => 'TA1',
            'grid_ref' => '1519 1730',
            'caption' => 'TA1 · 1519 1730 · 146°',
        ];
        $embed = QuickPictureDiscordEmbed::build($meta, [
            'attachment' => 'qp.jpg',
            'map_label' => 'Altis',
            'world_name' => 'Altis',
            'location' => 'Kavala',
            'game_date' => '2035,6,24',
            'game_time' => '14.5',
            'real_unix' => 1_790_000_000,
            'timezone' => 'Europe/Paris',
        ]);
        self::assertSame('attachment://qp.jpg', $embed['image']['url']);
        self::assertArrayNotHasKey('description', $embed, 'légende automatique redondante retirée');
        $byName = [];
        foreach ($embed['fields'] as $f) {
            $byName[$f['name']] = $f['value'];
        }
        self::assertSame('TA1', $byName['Opérateur']);
        self::assertArrayNotHasKey('Unité', $byName);
        self::assertSame('Grille 1519 1730 · cap 146°', $byName['Position']);
        self::assertSame('Kavala — Altis', $byName['Zone']);
        self::assertSame('24/06/2035 à 14:30', $byName['Date / heure en jeu']);
        self::assertStringContainsString('<t:1790000000:f>', $byName['Date / heure réelle']);
        self::assertSame(gmdate('Y-m-d\TH:i:s\Z', 1_790_000_000), $embed['timestamp']);
    }

    public function testQuickPictureEmbedFallbacks(): void
    {
        $embed = QuickPictureDiscordEmbed::build(
            ['author_callsign' => 'Unknown', 'caption' => 'Pont détruit · 090°'],
            ['image_url' => 'http://insecure.example/x.jpg', 'map_label' => 'Tanoa', 'world_name' => 'Tanoa']
        );
        self::assertSame('Pont détruit', $embed['description']);
        self::assertArrayNotHasKey('image', $embed);
        self::assertSame('Quick Picture — Opérateur', $embed['title']);
        self::assertSame('14:30', QuickPictureDiscordEmbed::formatClock('14h30'));
        self::assertSame('23:59', QuickPictureDiscordEmbed::clockFromDaytime(23.99));
        self::assertSame('', QuickPictureDiscordEmbed::formatGameDate('2035-02-30'));
        self::assertSame(146.0, QuickPictureDiscordEmbed::headingFromCaption('TA1 · 1519 1730 · 146°'));
        $https = QuickPictureDiscordEmbed::build(['author_callsign' => 'X'], ['image_url' => 'https://athena.example/uploads/recon/a.jpg']);
        self::assertSame('https://athena.example/uploads/recon/a.jpg', $https['image']['url']);
    }
}
