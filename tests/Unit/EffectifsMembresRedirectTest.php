<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsMembresRedirectTest extends TestCase
{
    public function testBareMembersUrlRedirectsToRoster(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("'/back-office/ressources/effectifs/membres', [\App\Controllers\Admin\LegacyPersonnelRedirectController::class, 'roster']", $routes);
    }
}
