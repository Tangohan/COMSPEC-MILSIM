<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AppUpdateModalFontAssetTest extends TestCase
{
    public function testModalUsesPortalAndAtakFontStack(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/app-update-modal.css');

        self::assertStringContainsString('Archivo', $css);
        self::assertStringContainsString('--atak-font-ui', $css);
        self::assertStringNotContainsString('Source Sans 3', $css);
        self::assertStringNotContainsString('Inter,', $css);
        self::assertStringNotContainsString('"Source Serif 4"', $css);
    }
}
