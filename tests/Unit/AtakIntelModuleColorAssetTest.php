<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakIntelModuleColorAssetTest extends TestCase
{
    public function testIntelModuleTabsHaveDistinctColors(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-c2-shell.css');

        self::assertStringContainsString('atak-tab--fiches', $view);
        self::assertStringContainsString('atak-tab--personnes', $view);
        self::assertStringContainsString('atak-tab--photos', $view);
        self::assertStringContainsString('atak-tab--frs', $view);

        self::assertStringContainsString('.atak-left-aside .atak-tab--fiches {', $css);
        self::assertStringContainsString('#e8a84a', $css);
        self::assertStringContainsString('.atak-left-aside .atak-tab--personnes {', $css);
        self::assertStringContainsString('#4ec4f0', $css);
        self::assertStringContainsString('.atak-left-aside .atak-tab--photos {', $css);
        self::assertStringContainsString('.atak-left-aside .atak-tab--frs {', $css);
        self::assertStringContainsString('.atak-left-aside .atak-tab--fiches.active {', $css);
        self::assertStringContainsString('border-left-color: #ffc56a;', $css);
        self::assertStringContainsString('.atak-left-aside .atak-tab--personnes.active {', $css);
        self::assertStringContainsString('border-left-color: #7ad8f8;', $css);
    }
}
