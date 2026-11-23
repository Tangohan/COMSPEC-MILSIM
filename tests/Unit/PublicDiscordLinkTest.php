<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Support\PublicLinks;
use PHPUnit\Framework\TestCase;

/**
 * Lien Discord officiel : une seule URL, présente dans le pied de page public et dans une
 * section de l'accueil, sans widget tiers.
 */
final class PublicDiscordLinkTest extends TestCase
{
    private const INVITE = 'https://discord.gg/KFB2TsJVXn';

    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_URI'] = '/';
        unset($_ENV['PUBLIC_DISCORD_INVITE_URL']);
        $GLOBALS['__app_locale'] = 'fr';
        unset($GLOBALS['__app_translator']);
    }

    protected function tearDown(): void
    {
        unset($_ENV['PUBLIC_DISCORD_INVITE_URL'], $GLOBALS['__app_locale'], $GLOBALS['__app_translator']);
    }

    public function testInviteUrlIsCentralisedWithSafeOverride(): void
    {
        self::assertSame(self::INVITE, PublicLinks::discordInviteUrl());

        $_ENV['PUBLIC_DISCORD_INVITE_URL'] = 'https://discord.gg/AutreCode';
        self::assertSame('https://discord.gg/AutreCode', PublicLinks::discordInviteUrl());

        $_ENV['PUBLIC_DISCORD_INVITE_URL'] = 'https://evil.example/x';
        self::assertSame(self::INVITE, PublicLinks::discordInviteUrl());
    }

    public function testHomeHasFooterLinkAndSectionOpeningInNewTab(): void
    {
        $body = $this->home();

        self::assertSame(2, substr_count($body, 'href="' . self::INVITE . '"'));
        self::assertSame(1, substr_count($body, self::INVITE . '" target="_blank" rel="noopener noreferrer">Discord</a>'));
        self::assertSame(1, substr_count($body, self::INVITE . '" class="hd__cta" target="_blank" rel="noopener noreferrer">'));
        self::assertStringContainsString('id="discord-heading"', $body);
        self::assertStringContainsString('Retrouvez-nous sur Discord', $body);
        self::assertStringContainsString('Rejoindre le Discord', $body);
        self::assertStringContainsString('Annonces et nouveautés', $body);
        self::assertStringNotContainsString('<iframe', $body);
        self::assertStringNotContainsString('discord.com/widget', $body);
        self::assertStringNotContainsString('SUPPORT', $body);
    }

    public function testHomeIsTranslatedInEnglish(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        $body = $this->home();

        self::assertStringContainsString('Find us on Discord', $body);
        self::assertStringContainsString('Join the Discord', $body);
        self::assertStringNotContainsString('Rejoindre le Discord', $body);
    }

    public function testFooterPartialAloneCarriesTheLink(): void
    {
        ob_start();
        require dirname(__DIR__, 2) . '/views/partials/public_site_footer.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('<a href="' . self::INVITE . '" target="_blank" rel="noopener noreferrer">Discord</a>', $html);
    }

    private function home(): string
    {
        $response = Response::view('home.index', [
            'title' => 'Athena Comspec — Portail MILSIM',
            'platformStats' => [],
            'featuredUnits' => [],
        ]);
        self::assertSame(200, $response->statusCode());

        return $response->body();
    }
}
