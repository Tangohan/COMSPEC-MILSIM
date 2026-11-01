<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ProfileInviteDiscordMessage;
use PHPUnit\Framework\TestCase;

/** Message Discord d'un lien « compléter son profil » : variables, mentions, carte. */
final class ProfileInviteDiscordMessageTest extends TestCase
{
    private const LINK = 'https://athena.test/invitation/profil/abc';

    /** @return array<string, mixed> */
    private function ctx(array $over = []): array
    {
        return $over + [
            'template' => 'Salut {indicatif} ({grade}, {unite}) : rejoins {communaute} ici {lien} avant le {expire}.',
            'callsign' => 'VIPER',
            'name' => 'Jean Dupont',
            'link' => self::LINK,
            'expires_unix' => 1793635200,
            'expires_label' => '03/11/2026 à 12:00',
            'community' => 'SOAR',
            'unit' => '1re section · SOAR-B-1',
            'grade' => 'Caporal',
            'discord_user_id' => null,
            'discord_handle' => '',
        ];
    }

    public function testVariablesAreReplaced(): void
    {
        $text = ProfileInviteDiscordMessage::render('{indicatif}|{lien}|{expire}|{communaute}|{unite}|{grade}|{inconnue}', [
            'indicatif' => 'VIPER', 'lien' => self::LINK, 'expire' => 'demain', 'communaute' => 'SOAR', 'unite' => 'B-1', 'grade' => 'Cpl',
        ]);
        self::assertSame('VIPER|' . self::LINK . '|demain|SOAR|B-1|Cpl|{inconnue}', $text);
        self::assertSame(ProfileInviteDiscordMessage::DEFAULT_TEMPLATE, ProfileInviteDiscordMessage::normalizeTemplate('   '));
        self::assertStringContainsString('{lien}', ProfileInviteDiscordMessage::DEFAULT_TEMPLATE);
        self::assertSame(['indicatif', 'lien', 'expire', 'communaute', 'unite', 'grade'], array_keys(ProfileInviteDiscordMessage::variables()));
    }

    public function testMassAndHandwrittenMentionsAreNeutralized(): void
    {
        $text = ProfileInviteDiscordMessage::render('@everyone @here <@123456789012345678> <@&42> <#99> {indicatif}', ['indicatif' => '@everyone']);
        self::assertStringNotContainsString('@everyone', $text);
        self::assertStringNotContainsString('@here', $text);
        self::assertStringNotContainsString('<@123456789012345678>', $text);
        self::assertStringNotContainsString('<@&42>', $text);
    }

    public function testKnownDiscordIdIsMentionedAndOnlyThatUserIsAllowed(): void
    {
        $p = ProfileInviteDiscordMessage::payload($this->ctx(['discord_user_id' => '123456789012345678']));
        self::assertStringStartsWith('<@123456789012345678> Salut VIPER (Caporal, 1re section · SOAR-B-1)', $p['content']);
        self::assertSame(['parse' => [], 'users' => ['123456789012345678']], $p['allowed_mentions']);
        self::assertSame(self::LINK, $p['embeds'][0]['url']);
        self::assertStringContainsString('SOAR', $p['embeds'][0]['title']);
        $fields = array_column($p['embeds'][0]['fields'], 'value', 'name');
        self::assertSame('Caporal', $fields['Grade']);
        self::assertSame('1re section · SOAR-B-1', $fields['Unité']);
        self::assertSame('<t:1793635200:f> (<t:1793635200:R>)', $fields['Expire']);
        self::assertStringContainsString(self::LINK, $fields['Votre lien']);
    }

    public function testWithoutIdTheHandleIsPlainTextAndNobodyIsPinged(): void
    {
        $p = ProfileInviteDiscordMessage::payload($this->ctx(['discord_handle' => '@jeannot']));
        self::assertStringStartsWith('**@jeannot** Salut', $p['content']);
        self::assertSame(['parse' => []], $p['allowed_mentions']);

        $bare = ProfileInviteDiscordMessage::payload($this->ctx(['callsign' => '', 'grade' => '', 'unit' => '']));
        self::assertStringStartsWith('Salut Jean Dupont', $bare['content']);
        self::assertSame(['Expire', 'Votre lien'], array_column($bare['embeds'][0]['fields'], 'name'));
    }

    public function testDiscordIdDetection(): void
    {
        self::assertSame('123456789012345678', ProfileInviteDiscordMessage::discordUserId(null, '<@!123456789012345678>'));
        self::assertSame('123456789012345678', ProfileInviteDiscordMessage::discordUserId('', '123456789012345678'));
        self::assertNull(ProfileInviteDiscordMessage::discordUserId('jeannot', '12345'));
    }
}
