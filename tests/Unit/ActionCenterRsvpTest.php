<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ActionCenterRsvpTest extends TestCase
{
    public function testActionCenterProvidesCsrfProtectedInlineRsvpControls(): void
    {
        $view = file_get_contents(__DIR__ . '/../../views/portal/action_center.php');

        self::assertIsString($view);
        self::assertStringContainsString("['yes' => 'Présent', 'maybe' => 'Peut-être', 'no' => 'Absent']", $view);
        self::assertStringContainsString("url('aujourdhui/rsvp')", $view);
        self::assertStringContainsString('name="_csrf_token"', $view);
        self::assertStringContainsString('aria-pressed=', $view);
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
}
