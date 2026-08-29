<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMobileShellTest extends TestCase
{
    public function testMobileShellAssetsAndModulesAreWired(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakMobileController.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/mobile.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-mobile/atak-mobile.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-mobile.css');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $docs = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/technique/atak-mobile.md');

        foreach (['c2', 'sitac', 'chat', 'bft', 'pings', 'intel', 'jtac', 'air', 'sigint', 'status'] as $mod) {
            self::assertStringContainsString("'" . $mod . "'", $controller);
            self::assertStringContainsString('data-screen="' . $mod . '"', $view);
        }
        self::assertStringContainsString("\$router->get('/atak/mobile'", $routes);
        self::assertStringContainsString('am-bottom__btn', $view);
        self::assertStringContainsString('env(safe-area-inset-top', $css);
        self::assertStringContainsString('schedulePolls', $js);
        self::assertStringContainsString('/api/atak/units', $js);
        self::assertStringContainsString('/api/chat', $js);
        self::assertStringContainsString('ATAK Mobile', $docs);
    }
}
