<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\CommunityModReportRelay;
use App\Services\Platform\PlatformIntegrationSettings;
use App\Support\DiscordWebhookCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Salons Discord de la plateforme réglés en base (Intégrations > Plateforme) au lieu du .env,
 * et copie des signalements du mod dans le salon de la communauté du joueur.
 */
final class PlatformIntegrationSettingsTest extends TestCase
{
    private const HOOK = 'https://discord.com/api/webhooks/1290000000000000001/AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';

    protected function tearDown(): void
    {
        PlatformIntegrationSettings::flush();
    }

    /** @param array<string, string> $store */
    private function settings(array &$store): PlatformIntegrationSettings
    {
        return new PlatformIntegrationSettings(
            static function (string $key) use (&$store): string {
                return $store[$key] ?? '';
            },
            static function (array $pairs) use (&$store): void {
                $store = array_merge($store, $pairs);
            }
        );
    }

    public function testSavesWebhooksAndRejectsChannelLinks(): void
    {
        $store = [];
        $settings = $this->settings($store);

        self::assertTrue($settings->save([PlatformIntegrationSettings::MOD_REPORTS => self::HOOK])['ok']);
        self::assertSame(self::HOOK, $store['discord.mod_reports_webhook']);
        self::assertSame('', $store['discord.startup_check_webhook']);
        self::assertSame(self::HOOK, $settings->state()[PlatformIntegrationSettings::MOD_REPORTS]['url']);

        $bad = $settings->save([PlatformIntegrationSettings::STARTUP_CHECK => 'https://discord.com/channels/123456789012345678/123456789012345678']);
        self::assertFalse($bad['ok']);
        self::assertStringContainsString('lien du salon', $bad['message']);
    }

    public function testOldEnvValueIsOnlyAFallback(): void
    {
        $store = [];
        $settings = $this->settings($store);
        $_ENV['DISCORD_DEBUG_WEBHOOK'] = self::HOOK;
        try {
            self::assertTrue($settings->state()[PlatformIntegrationSettings::STARTUP_CHECK]['from_env']);
            $settings->save([PlatformIntegrationSettings::STARTUP_CHECK => self::HOOK]);
            self::assertFalse($settings->state()[PlatformIntegrationSettings::STARTUP_CHECK]['from_env']);
        } finally {
            unset($_ENV['DISCORD_DEBUG_WEBHOOK']);
        }
    }

    public function testServicesReadTheSavedChannel(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['app/Services/Atak/AtakStartupCheckService.php', 'app/Services/Monitoring/AtakModReportDiscordTicketService.php'] as $file) {
            self::assertStringContainsString('PlatformIntegrationSettings::webhook(', (string) file_get_contents($root . '/' . $file));
        }
        self::assertStringContainsString('platformOverride()', (string) file_get_contents($root . '/app/Support/helpers.php'));
    }

    public function testCommunityModReportMessageIsCleanAndBilingual(): void
    {
        self::assertContains(DiscordWebhookCatalog::KEY_ATAK_MOD_REPORTS, DiscordWebhookCatalog::keys());
        self::assertSame('off', DiscordWebhookCatalog::defaultMode(DiscordWebhookCatalog::KEY_ATAK_MOD_REPORTS));

        $text = CommunityModReportRelay::message([
            'gravity' => 'high',
            'source' => 'player',
            'channel' => 'Map',
            'message' => 'Carte vide @everyone voir https://exemple.test depuis 192.168.1.20',
            'callsign' => 'Viper 1-1',
            'mod_version' => '2.1.0',
            'extension_version' => '2.0.88',
        ]);
        self::assertStringContainsString('Viper 1-1', $text);
        self::assertStringContainsString('ATAK 2.1.0', $text);
        self::assertStringContainsString('Link 2.0.88', $text);
        self::assertStringNotContainsString('https://', $text);
        self::assertStringNotContainsString('192.168.1.20', $text);
        self::assertStringNotContainsString('@everyone', $text);
        self::assertSame('skipped_low', (new CommunityModReportRelay())->relay(5, ['gravity' => 'low']));
    }

    public function testIntegrationsPageHasPlatformSectionForAdminsOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/organization/integrations.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationIntegrationsController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        self::assertStringContainsString('<?php if ($platformAdmin): ?>', $view);
        self::assertStringContainsString('isPlatformAdmin()', $controller);
        self::assertStringContainsString("'/back-office/integrations/plateforme/discord'", $routes);
        self::assertStringContainsString("'/back-office/integrations/plateforme/emails'", $routes);
        self::assertStringContainsString("'back-office-integrations.css'", $pages);
        self::assertFileExists($root . '/public/assets/js/back-office-integrations.js');
        self::assertFileExists($root . '/views/admin/organization/partials/integrations_mail_form.php');
    }
}
