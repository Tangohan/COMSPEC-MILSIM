<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Repositories\DiscordBotRepository;
use App\Services\Integrations\DiscordBotInvite;
use App\Services\Integrations\DiscordCompanionSettings;
use App\Services\Integrations\DiscordLinkedRoles;
use App\Support\Migrations\SchemaInspector;
use App\Support\SecretCipher;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/** Invitation du bot avec l'application Discord du bot (et non celle des rôles liés), avertissements et réglage affiché. */
final class DiscordBotApplicationTest extends TestCase
{
    private const LINKED_ID = '123456789012345678';
    private const BOT_ID = '987654321098765432';
    private const LINKED_SECRET = 'LinkedSecret0123456789';
    private const BOT_SECRET = 'BotSecret0123456789_ab';

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    /** @param array<string, string> $extra */
    private function store(array $extra = []): \Closure
    {
        $data = [
            DiscordLinkedRoles::SETTING_CLIENT_ID => self::LINKED_ID,
            DiscordLinkedRoles::SETTING_SECRET => SecretCipher::encrypt(self::LINKED_SECRET),
        ] + $extra;

        return static fn (string $k): string => $data[$k] ?? '';
    }

    /**
     * @param array<string, string> $extra
     * @return array{0: DiscordBotInvite, 1: \ArrayObject<int, array<string, string>>}
     */
    private function invite(array $extra = []): array
    {
        $sent = new \ArrayObject();
        $http = static function (string $method, string $url, array $headers, ?string $body) use ($sent): array {
            if (str_ends_with($url, '/oauth2/token')) {
                parse_str((string) $body, $form);
                $sent[] = $form;

                return ['status' => 200, 'body' => json_encode(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 600])];
            }
            if (str_ends_with($url, '/users/@me/guilds')) {
                return ['status' => 200, 'body' => json_encode([['id' => '111111111111111111', 'name' => 'Serveur', 'owner' => true, 'permissions' => 8]])];
            }

            return ['status' => 200, 'body' => '{}'];
        };
        $store = $this->store($extra);
        $linked = new DiscordLinkedRoles($http, $store);
        $pdo = new MysqlDdlSqlitePdo();
        (new SchemaInspector($pdo))->runSqlFile(dirname(__DIR__, 2) . '/migrations/2026_11_17_100_discord_bot.sql');
        $pdo->exec('CREATE TABLE discord_companion_guilds (guild_id TEXT PRIMARY KEY, tenant_id INTEGER UNIQUE, guild_name TEXT, channel_id TEXT, channels_json TEXT, synced_at TEXT)');
        $repo = new DiscordBotRepository($pdo);

        return [new DiscordBotInvite($linked, $repo, 'test-key', new DiscordCompanionSettings($store, $linked)), $sent];
    }

    /** @param array<string, string> $extra */
    private function settings(array $extra = []): DiscordCompanionSettings
    {
        $store = $this->store($extra);

        return new DiscordCompanionSettings($store, new DiscordLinkedRoles(null, $store));
    }

    /** @return array<string, string> */
    private function botSettings(): array
    {
        return [
            DiscordCompanionSettings::CLIENT_ID => self::BOT_ID,
            DiscordCompanionSettings::CLIENT_SECRET => SecretCipher::encrypt(self::BOT_SECRET),
        ];
    }

    /** @return array<string, string> */
    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return $q;
    }

    public function testInviteUsesTheBotApplicationAndKeepsTheRedirectUri(): void
    {
        [$invite] = $this->invite($this->botSettings());
        $q = $this->query($invite->inviteUrl('STATE'));
        self::assertSame(self::BOT_ID, $q['client_id']);
        self::assertSame(DiscordBotInvite::redirectUri(), $q['redirect_uri']);
        self::assertSame('code', $q['response_type']);
        self::assertTrue($invite->configured());
        $own = $this->settings($this->botSettings());
        self::assertSame(self::BOT_ID, $this->query($own->inviteUrl())['client_id']);
    }

    public function testInviteFallsBackToTheLinkedRolesApplicationWhenTheBotOneIsNotSet(): void
    {
        [$invite] = $this->invite();
        self::assertSame(self::LINKED_ID, $this->query($invite->inviteUrl('S'))['client_id']);
        self::assertTrue($invite->configured());
        $settings = $this->settings();
        self::assertSame(self::LINKED_ID, $this->query($settings->inviteUrl())['client_id']);
        $none = new DiscordCompanionSettings(static fn (): string => '', new DiscordLinkedRoles(null, static fn (): string => ''));
        self::assertSame('', $none->inviteUrl());
    }

    public function testCodeExchangeUsesTheBotIdAndSecret(): void
    {
        [$invite, $sent] = $this->invite($this->botSettings());
        $result = $invite->complete('code', $invite->state(5, 1, 'n', time()), '111111111111111111', 'n', 5, 1);
        self::assertTrue($result['ok'], json_encode($result));
        self::assertSame(self::BOT_ID, $sent[0]['client_id']);
        self::assertSame(self::BOT_SECRET, $sent[0]['client_secret']);
    }

    public function testBotIdWithoutItsSecretIsNotConfiguredInsteadOfUsingTheOtherSecret(): void
    {
        [$invite] = $this->invite([DiscordCompanionSettings::CLIENT_ID => self::BOT_ID]);
        self::assertFalse($invite->configured());
    }

    public function testClientIdValidation(): void
    {
        self::assertTrue(DiscordCompanionSettings::validClientId('1571803014707023912'));
        self::assertTrue(DiscordCompanionSettings::validClientId(str_repeat('1', 17)));
        self::assertTrue(DiscordCompanionSettings::validClientId(str_repeat('1', 20)));
        foreach (['', '1234567890123456', str_repeat('1', 21), '15718030147070239a2', ' 1571803014707023912', "1571803014707023912\n", 'https://discord.com'] as $bad) {
            self::assertFalse(DiscordCompanionSettings::validClientId($bad), var_export($bad, true));
        }
    }

    public function testWarningNamesTheMistake(): void
    {
        $warn = fn (array $extra): string => $this->settings($extra)->inviteWarning();
        self::assertSame('missing', $warn([]));
        self::assertSame('same', $warn([DiscordCompanionSettings::CLIENT_ID => self::LINKED_ID]));
        self::assertSame('no_secret', $warn([DiscordCompanionSettings::CLIENT_ID => self::BOT_ID]));
        self::assertSame('', $warn($this->botSettings()));
    }

    public function testSecretIsStoredEncryptedAndNeverRenderedBack(): void
    {
        $stored = SecretCipher::encrypt(self::BOT_SECRET);
        self::assertStringNotContainsString(self::BOT_SECRET, $stored);
        $settings = $this->settings([DiscordCompanionSettings::CLIENT_SECRET => $stored]);
        self::assertTrue($settings->hasClientSecret());
        self::assertSame(self::BOT_SECRET, $settings->clientSecret());

        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/platform_integrations.php');
        self::assertSame(1, preg_match('/<input[^>]*name="companion_client_secret"[^>]*>/', $view, $m));
        self::assertStringNotContainsString('value=', $m[0]);
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/System/SystemPlatformIntegrationsController.php');
        self::assertStringNotContainsString('clientSecret()', $controller, 'le contrôleur ne lit jamais le secret en clair');
    }

    /** @param array<string, mixed> $bot */
    private function renderPage(array $bot): string
    {
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(dirname(__DIR__, 2) . '/views/admin/organization/discord_bot.php', ['bot' => $bot + [
            'ready' => true, 'status' => 'none', 'guild' => null, 'settings' => [], 'channels' => [], 'roles' => [], 'invite_url' => '',
            'with_roles' => false, 'service_ready' => true, 'oauth_ready' => true, 'app_warning' => '',
            'redirect_uri' => DiscordBotInvite::redirectUri(), 'settings_url' => url('admin/system/integrations') . '#companion',
        ]]);
    }

    public function testPageShowsTheWarningWithTheSettingsLinkAndTheRedirectUriToRegister(): void
    {
        foreach (['missing', 'same', 'no_secret'] as $code) {
            $html = $this->renderPage(['app_warning' => $code]);
            self::assertStringContainsString('dbot-note--warn', $html, $code);
            self::assertStringContainsString('admin/system/integrations#companion', $html, $code);
        }
        $html = $this->renderPage(['app_warning' => 'missing']);
        self::assertStringContainsString('value="' . htmlspecialchars(DiscordBotInvite::redirectUri(), ENT_QUOTES) . '"', $html);
        self::assertStringContainsString('data-dbot-copy="dbot-redirect-uri"', $html);
        self::assertStringContainsString('back-office/discord-bot/retour', $html);

        $ok = $this->renderPage(['app_warning' => '']);
        self::assertStringNotContainsString('admin/system/integrations#companion', $ok);
        self::assertStringContainsString('dbot-redirect-uri', $ok, 'l’URI à enregistrer reste affichée sans avertissement');
    }
}
