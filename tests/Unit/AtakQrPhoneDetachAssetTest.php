<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakQrPhoneDetachAssetTest extends TestCase
{
    public function testQrHubAndScreenModalUsePhoneDetachedPanelShell(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-c2-shell.css');
        $hub = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-qr-hub.js');
        $chrome = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-panel-chrome.js');

        self::assertStringContainsString('atak-qr-phone', $view);
        self::assertStringContainsString('Panneau détaché', $view);
        self::assertStringContainsString('Fenêtre détachée sur téléphone', $view);
        self::assertStringContainsString('comspec_phone_bg_ca.png', $view);
        self::assertStringContainsString('.atak-qr-phone', $css);
        self::assertStringContainsString('--aqp-scr-l', $css);
        self::assertStringContainsString('paintPhone', $hub);
        self::assertStringContainsString('destinationFromTab', $chrome);
        self::assertStringContainsString("destination: 'chat'", $chrome);
        self::assertStringContainsString('SITAC', $chrome);
    }
}
