<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AtakQrNavigationTest extends TestCase
{
    public function testQrRailButtonDirectlyActivatesItsPanel(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertIsString($view);
        self::assertMatchesRegularExpression(
            '/data-section="qr"[^>]+onclick="[^"]*activateTab\(\'qrcode\'\)/',
            $view
        );
        self::assertStringContainsString('id="tab-qrcode"', $view);
    }
}
