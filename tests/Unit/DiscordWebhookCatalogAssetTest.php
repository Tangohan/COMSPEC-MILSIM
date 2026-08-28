<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Email\EmailEvents;
use App\Services\Integrations\DiscordWebhookService;
use App\Support\DiscordWebhookCatalog;
use PHPUnit\Framework\TestCase;

final class DiscordWebhookCatalogAssetTest extends TestCase
{
    public function testCatalogCoversPortalStaffAndSseEvents(): void
    {
        $keys = DiscordWebhookCatalog::keys();
        self::assertContains(DiscordWebhookCatalog::KEY_ANNOUNCEMENTS, $keys);
        self::assertContains(DiscordWebhookCatalog::KEY_OVERWATCH_PACK, $keys);
        self::assertContains(EmailEvents::NEW_COMMUNITY_MEMBER, $keys);
        self::assertContains(EmailEvents::ENLISTMENT_SUBMITTED_STAFF, $keys);
        self::assertContains(EmailEvents::ENLISTMENT_ACCEPTED_STAFF, $keys);
        self::assertContains(EmailEvents::ATTENDANCE_RSVP_ORGANIZER, $keys);
        self::assertContains(EmailEvents::TRAINING_COURSE_COMPLETED, $keys);
        self::assertContains(EmailEvents::COMMUNITY_REPORT_NEW_STAFF, $keys);
        self::assertContains(EmailEvents::SSE_ANALYST_DIGEST, $keys);
        self::assertSame('default', DiscordWebhookCatalog::defaultMode(DiscordWebhookCatalog::KEY_ANNOUNCEMENTS));
        self::assertSame('off', DiscordWebhookCatalog::defaultMode(EmailEvents::NEW_COMMUNITY_MEMBER));
        self::assertGreaterThanOrEqual(20, count($keys));
    }

    public function testIntegrationsPageListsEveryCatalogEventAndSavesRelays(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/organization/integrations.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationIntegrationsController.php');
        $email = (string) file_get_contents($root . '/app/Services/EmailService.php');

        self::assertStringContainsString('relais-discord', $view);
        self::assertStringContainsString('discord_event[', $view);
        self::assertStringContainsString('Salon par défaut', $view);
        self::assertStringContainsString('Transmissions terrain', $view);
        self::assertStringContainsString('discord_events', $view);
        self::assertStringContainsString('DiscordWebhookCatalog::events()', $controller);
        self::assertStringContainsString("'/back-office/integrations/discord'", $routes);
        self::assertStringContainsString('saveDiscord', $controller);
        self::assertStringContainsString('DiscordEventRelayService::relayFromEmail', $email);
    }

    public function testDiscordUrlAcceptsWwwAndRejectsForeignHosts(): void
    {
        $svc = new DiscordWebhookService();
        self::assertTrue($svc->isValidWebhookUrl('https://www.discord.com/api/webhooks/1/abc'));
        self::assertTrue($svc->isValidWebhookUrl('https://discord.com/api/webhooks/1/abc'));
        self::assertFalse($svc->isValidWebhookUrl('https://discord.com/api/v10/users/@me'));
        self::assertFalse($svc->isValidWebhookUrl('https://evil.example/api/webhooks/1/abc'));
    }
}
