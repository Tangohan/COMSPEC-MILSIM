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
        $mobileController = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakMobileController.php');
        $mobileView = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/mobile.php');
        $mobileJs = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-mobile/atak-mobile.js');
        $mobileCss = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-mobile.css');

        self::assertStringContainsString('atak_qr_phone_preview.php', $view);
        self::assertStringContainsString('data-qr-destination="sitac"', $view);
        self::assertStringContainsString('Fenêtre détachée sur téléphone', $view);
        self::assertStringContainsString('COMSPEC', $partial);
        self::assertStringContainsString('atak-qr-phone__topbar', $partial);
        self::assertStringContainsString('data-skin="sitac"', $partial);
        self::assertStringContainsString('atak-qr-scan-pack__code', $partial);
        self::assertStringContainsString('atak-qr-scan-pack', $css);
        self::assertStringContainsString('image-rendering: pixelated', $css);
        self::assertStringContainsString('.atak-qr-phone__mobile', $css);
        self::assertStringContainsString('paintPhone', $hub);
        self::assertStringContainsString("sitac: 'SITAC'", $hub);
        self::assertStringContainsString("destination: 'sitac'", $chrome);
        self::assertStringContainsString('openSitac', $controller);
        self::assertStringContainsString('/connect/{token}/sitac', $routes);
        self::assertStringContainsString('/atak/mobile', $routes);
        self::assertStringContainsString('/atak/mobile/{module}', $routes);
        self::assertStringContainsString("url('atak/mobile/'", $controller);
        self::assertStringContainsString("'map' => 'sitac'", $controller);
        self::assertStringContainsString("'chat' => 'chat'", $controller);
        self::assertStringNotContainsString('section=c2', $controller);
        self::assertStringNotContainsString('iframe', $mobileView);
        self::assertStringContainsString('am-bottom', $mobileView);
        self::assertStringContainsString('atak-mobile.js', $mobileView);
        self::assertStringContainsString('AtakMobileController', $mobileController);
        self::assertStringContainsString('safe-area-inset-bottom', $mobileCss);
        self::assertStringContainsString('setModule', $mobileJs);
        self::assertStringContainsString('MGRS_CRS', $mobileJs);
    }
}
