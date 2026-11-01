<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Le parc ATAK de la communauté doit être construit par le Container (sinon le routeur fait new sans arguments : 500). */
final class BackOfficeAtakFleetContainerTest extends TestCase
{
    public function testBackOfficeFleetControllerIsWiredInContainer(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Container.php');
        self::assertStringContainsString(
            '\App\Controllers\Admin\BackOfficeAtakFleetController::class => new \App\Controllers\Admin\BackOfficeAtakFleetController(',
            $src
        );
    }
}
