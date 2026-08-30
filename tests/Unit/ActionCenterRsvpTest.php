<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ActionCenterRsvpTest extends TestCase
{
    public function testActionCenterProvidesDynamicRsvpControls(): void
    {
        $view = file_get_contents(__DIR__ . '/../../views/portal/action_center.php');

        self::assertIsString($view);
        self::assertStringContainsString('dashboard_rsvp_buttons.php', $view);
        self::assertStringContainsString('$rsvpEventId = $eventId', $view);
        self::assertStringContainsString('$rsvpCurrentStatus = $rsvpStatus', $view);
    }

    public function testDedicatedControllerValidatesTenantStatusAndCsrf(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Web/ActionCenterController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('Csrf::validate', $controller);
        self::assertStringContainsString("in_array(\$status, ['yes', 'no', 'maybe'], true)", $controller);
        self::assertStringContainsString('belongsToTenant($eventId, $tenantId)', $controller);
        self::assertStringContainsString('setRsvpWithNotifications', $controller);
    }

    public function testDashboardRsvpPartialExposesPressedStateAndStatusLabel(): void
    {
        $partial = file_get_contents(__DIR__ . '/../../views/partials/dashboard_rsvp_buttons.php');
        $assets = file_get_contents(__DIR__ . '/../../views/partials/dashboard_rsvp_assets.php');
        $js = file_get_contents(__DIR__ . '/../../public/assets/js/dashboard-rsvp.js');

        self::assertIsString($partial);
        self::assertIsString($assets);
        self::assertIsString($js);
        self::assertStringContainsString('aria-pressed', $partial);
        self::assertStringContainsString('data-rsvp-status-label', $partial);
        self::assertStringContainsString('Je participe', $partial);
        self::assertStringContainsString('dashboard-rsvp.js', $assets);
        self::assertStringContainsString('pending[eventId]', $js);
        self::assertStringContainsString('aria-pressed', $js);
        self::assertStringContainsString("previous === choice", $js);
    }
}
