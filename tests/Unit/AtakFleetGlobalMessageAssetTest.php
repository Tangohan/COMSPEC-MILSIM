<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Message global du parc ATAK : routes protégées, portée imposée par l'espace, confirmation sans confirm(). */
final class AtakFleetGlobalMessageAssetTest extends TestCase
{
    public function testRoutesAreProtectedAndScopeComesFromTheServer(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertMatchesRegularExpression("#'/admin/parc-atak/message/tous'.*SystemAdminMiddleware#", $routes);
        self::assertStringContainsString("'/back-office/atak/parc-atak/message/tous', [\$boFleet, 'messageAll'], \$boFleetMw", $routes);

        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/AdminAtakFleetController.php');
        self::assertStringContainsString("\$tenantId = \$scope > 0 ? \$scope : max(0, (int) \$request->input('communaute_message', 0));", $controller);
        self::assertStringContainsString("input('confirmation_globale', '') !== '1'", $controller);
    }

    public function testFormWorksWithoutScriptAndWithoutConfirm(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/atak-fleet/index.php');
        $start = (int) strpos($view, 'paf-global');
        $block = substr($view, $start, (int) strpos($view, '</details>', $start) - $start);

        self::assertStringContainsString("\$base . '/message/tous'", $block);
        self::assertStringContainsString('name="confirmation_globale"', $block);
        self::assertStringContainsString('$returnFields', $block);
        self::assertStringNotContainsString('confirm(', $block);
    }
}
