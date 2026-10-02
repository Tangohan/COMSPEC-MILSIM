<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackOfficeDarkModeAssetTest extends TestCase
{
    public function testDarkModeIsWiredInBackOfficeShell(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $topbar = (string) file_get_contents($root . '/views/partials/back_office_topbar.php');
        $js = (string) file_get_contents($root . '/public/assets/js/back-office-theme.js');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-dark.css');
        $generated = (string) file_get_contents($root . '/public/assets/css/back-office-dark.generated.css');

        self::assertStringContainsString("athena.bo.theme", $layout);
        self::assertStringContainsString('back-office-dark.generated.css', $layout);
        self::assertStringContainsString('back-office-theme.js', $layout);
        self::assertStringContainsString('data-ath-theme-toggle', $topbar);
        self::assertStringContainsString('athena.bo.theme', $js);
        self::assertStringContainsString('html[data-bo-theme="dark"]', $css);
        self::assertStringContainsString('html[data-bo-theme="dark"]', $generated);
        self::assertFileExists($root . '/tools/build-bo-dark-css.py');
    }
}
