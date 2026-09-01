<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakHeaderSelectContrastAssetTest extends TestCase
{
    public function testHeaderSelectsUseDarkNativeList(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString('body.atak-page {', $css);
        self::assertStringContainsString('color-scheme: dark;', $css);
        self::assertStringContainsString('.atak-header-field-control option', $css);
        self::assertStringContainsString('background-color: #12151c;', $css);
        self::assertStringContainsString('.atak-header-field-control option:checked', $css);
        self::assertStringContainsString('body.atak-page.atak-theme-light', $css);
        self::assertStringContainsString('color-scheme: light;', $css);
        self::assertStringNotContainsString('endpoint', $css);
    }
}
