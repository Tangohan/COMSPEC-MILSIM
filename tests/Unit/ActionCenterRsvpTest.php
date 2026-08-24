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
        self::assertStringContainsString("url('evenements/rsvp')", $view);
        self::assertStringContainsString('name="_csrf_token"', $view);
        self::assertStringContainsString('name="return_to" value="aujourdhui"', $view);
        self::assertStringContainsString('aria-pressed=', $view);
    }

    public function testEventsControllerOnlyAcceptsKnownReturnDestination(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../app/Controllers/Web/CommunityEventsController.php');

        self::assertIsString($controller);
        self::assertStringContainsString("\$returnTo === 'aujourdhui'", $controller);
        self::assertStringContainsString("url('aujourdhui') . '#agenda-et-echeances'", $controller);
        self::assertStringNotContainsString('Response::redirect($returnTo)', $controller);
    }
}
