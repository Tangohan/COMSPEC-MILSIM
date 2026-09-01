<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Email\EmailEvents;
use PHPUnit\Framework\TestCase;

final class CommunityCreatedOpsAlertAssetTest extends TestCase
{
    public function testNewCommunityAlertsTheIncidentMailbox(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        $mailer = (string) file_get_contents($root . '/app/Services/Monitoring/ErrorReportMailer.php');
        $events = (string) file_get_contents($root . '/app/Services/Email/EmailEvents.php');
        $envExample = (string) file_get_contents($root . '/.env.example');

        self::assertStringContainsString('notifyCommunityCreated', $bootstrap);
        self::assertStringContainsString('ErrorReportMailer', $bootstrap);
        self::assertStringContainsString('function notifyCommunityCreated', $mailer);
        self::assertStringContainsString('resolveAlertRecipient', $mailer);
        self::assertStringContainsString('ERROR_ALERT_EMAIL', $mailer);
        self::assertStringContainsString('Nouvelle communauté', $mailer);
        self::assertStringContainsString('PLATFORM_NEW_COMMUNITY', $events);
        self::assertSame('PLATFORM_NEW_COMMUNITY', EmailEvents::PLATFORM_NEW_COMMUNITY);
        self::assertContains(EmailEvents::PLATFORM_NEW_COMMUNITY, EmailEvents::EMAIL_EVENTS);
        self::assertStringContainsString('nouvelle communauté est créée', $envExample);
    }
}
