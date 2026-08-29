<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakQrPhoneDetachAssetTest extends TestCase
{
    public function testQrHubAndScreenModalUseMobileDetachedShell(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $partial = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/atak_qr_phone_preview.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-c2-shell.css');
        $hub = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-qr-hub.js');
        $chrome = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-panel-chrome.js');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakPhoneConnectController.php');

        self::assertStringContainsString('atak_qr_phone_preview.php', $view);
        self::assertStringContainsString('data-qr-destination="sitac"', $view);
        self::assertStringContainsString('Fenêtre détachée sur téléphone', $view);
        self::assertStringContainsString('COMSPEC', $partial);
        self::assertStringContainsString('atak-qr-phone__topbar', $partial);
        self::assertStringContainsString('data-skin="sitac"', $partial);
        self::assertStringContainsString('atak-qr-phone__qr-overlay', $partial);
        self::assertStringContainsString('.atak-qr-phone__mobile', $css);
        self::assertStringContainsString('paintPhone', $hub);
        self::assertStringContainsString("sitac: 'SITAC'", $hub);
        self::assertStringContainsString("destination: 'sitac'", $chrome);
        self::assertStringContainsString("openSitac", $controller);
        self::assertStringContainsString("/connect/{token}/sitac", $routes);
    }
}
