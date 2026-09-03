<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AtakThemeSelectorAssetTest extends TestCase
{
    public function testAtakViewExposesDayNightSelectorAndLoadsController(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak.php');
        $script = (string) file_get_contents($root . '/public/assets/js/atak-theme.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak.css');

        self::assertStringContainsString('id="atak-theme-select"', $view);
        self::assertStringContainsString('<option value="day">', $view);
        self::assertStringContainsString('<option value="night">', $view);
        self::assertStringContainsString('/assets/js/atak-theme.js', $view);
        self::assertStringContainsString("localStorage.setItem(STORAGE_KEY, normalized)", $script);
        self::assertStringContainsString('html[data-atak-theme="day"] body.atak-page', $css);
    }
}
