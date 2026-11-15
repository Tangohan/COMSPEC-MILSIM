<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AtakThemeSelectorAssetTest extends TestCase
{
    public function testAtakViewExposesDayNightSelectorAndLoadsController(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string) file_get_contents($root . '/public/assets/js/atak-theme.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak.css');

        self::assertStringContainsString("localStorage.setItem(STORAGE_KEY, normalized)", $script);
        self::assertStringContainsString('html[data-atak-theme="day"] body.atak-page', $css);
    }
}
