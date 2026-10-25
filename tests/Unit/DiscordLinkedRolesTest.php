<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\DiscordLinkedRoles;
use App\Support\SecretCipher;
use PHPUnit\Framework\TestCase;

final class DiscordLinkedRolesTest extends TestCase
{
    public function testConnectionShowsCommunitiesAndConditions(): void
    {
        $c = DiscordLinkedRoles::connectionFor(
            ['display_name' => 'Ghost', 'created_at' => '2026-01-02 03:04:05'],
            [['name' => 'Athena', 'slug' => 'default'], ['name' => '1st Recon', 'slug' => 'recon'], ['name' => 'Task Force 7', 'slug' => 'tf7']]
        );

        self::assertSame('Athena', $c['platform_name']);
        // Le tenant système n'est pas une communauté : seules les vraies apparaissent sur le profil Discord.
        self::assertSame('1st Recon · Task Force 7', $c['platform_username']);
        self::assertSame('1', $c['metadata']['athena_account']);
        self::assertSame('1', $c['metadata']['community_member']);
        self::assertSame('2', $c['metadata']['communities']);
        self::assertMatchesRegularExpression('/^2026-01-0\dT\d\d:\d\d:\d\dZ$/', $c['metadata']['member_since']);

        $solo = DiscordLinkedRoles::connectionFor(['display_name' => 'Ghost'], []);
        self::assertSame('Ghost', $solo['platform_username']);
        self::assertSame('0', $solo['metadata']['community_member']);
        self::assertSame('0', $solo['metadata']['communities']);
    }

    public function testMetadataKeysAreTheOnesSentAndDeclaredByTheScript(): void
    {
        $keys = array_column(DiscordLinkedRoles::METADATA, 'key');
        self::assertLessThanOrEqual(5, count($keys));
        self::assertSame($keys, array_keys(DiscordLinkedRoles::connectionFor([], [])['metadata']));

        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/tools/discord/comspec_discord_contributors.py');
        foreach (DiscordLinkedRoles::METADATA as $m) {
            self::assertStringContainsString('"key": "' . $m['key'] . '", "type": ' . $m['type'], $script);
        }
    }

    public function testSettingsAreValidatedAndTheSecretIsEncrypted(): void
    {
        $saved = [];
        $svc = new DiscordLinkedRoles(null, static fn (string $k): string => $saved[$k] ?? '', static function (array $pairs) use (&$saved): void {
            $saved = $pairs + $saved;
        });

        self::assertFalse($svc->saveConfig('abc', '', false)['ok']);
        self::assertFalse($svc->saveConfig('123456789012345678', 'not a secret!', false)['ok']);
        self::assertTrue($svc->saveConfig('123456789012345678', 'AbCdEf0123456789_xyzXYZ-0', false)['ok']);
        self::assertStringStartsWith('sc1:', $saved[DiscordLinkedRoles::SETTING_SECRET]);
        self::assertSame('AbCdEf0123456789_xyzXYZ-0', SecretCipher::decrypt($saved[DiscordLinkedRoles::SETTING_SECRET]));

        $reader = new DiscordLinkedRoles(null, static fn (string $k): string => $saved[$k] ?? '');
        self::assertTrue($reader->configured());
        $url = $reader->authorizeUrl('st4te');
        self::assertStringStartsWith('https://discord.com/oauth2/authorize?', $url);
        self::assertStringContainsString('client_id=123456789012345678', $url);
        self::assertStringContainsString('scope=identify%20role_connections.write', $url);
        self::assertStringContainsString('state=st4te', $url);

        // Champ vide : le secret enregistré est gardé ; case « effacer » : il part.
        self::assertTrue($svc->saveConfig('123456789012345678', '', false)['ok']);
        self::assertNotSame('', $saved[DiscordLinkedRoles::SETTING_SECRET]);
        self::assertTrue($svc->saveConfig('123456789012345678', '', true)['ok']);
        self::assertSame('', $saved[DiscordLinkedRoles::SETTING_SECRET]);
    }

    public function testRoutesAccountPanelAndAdminCardAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/discord/roles-lies', [\\App\\Controllers\\Web\\DiscordLinkedRolesController::class, 'start'], [AuthMiddleware::class]", $routes);
        self::assertStringContainsString("'/discord/roles-lies/retour'", $routes);
        self::assertStringContainsString("'/back-office/integrations/plateforme/discord-roles-lies'", $routes);
        self::assertStringContainsString('id="section-discord"', (string) file_get_contents($root . '/views/account/preferences.php'));
        self::assertStringContainsString('id="plateforme-roles-lies"', (string) file_get_contents($root . '/views/admin/organization/integrations.php'));
        self::assertStringContainsString('user_discord_links', (string) file_get_contents($root . '/migrations/20261025000002_user_discord_links.sql'));
        self::assertStringContainsString("'discord_linked_roles_sync' => 60", (string) file_get_contents($root . '/app/Services/Cron/CronSchedule.php'));
    }
}
