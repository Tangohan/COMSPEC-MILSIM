<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NouveautesDashboardAlignAssetTest extends TestCase
{
    public function testChangelogUsesDashboardPrimaryPaletteAndCtas(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/changelog.css');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/site/changelog.php');
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/marketing.php');

        self::assertStringContainsString('--cl-primary: #059669', $css);
        self::assertStringContainsString('--cl-primary-strong: #065f46', $css);
        self::assertStringContainsString('color: var(--cl-primary)', $css);
        self::assertStringContainsString('.cl-cta--primary', $css);
        self::assertStringContainsString('body.site-marketing--changelog .site-marketing__topbar', $css);
        self::assertStringContainsString('class="cl-cta cl-cta--primary"', $view);
        self::assertStringContainsString('class="cl-cta cl-cta--ghost"', $view);
        self::assertStringContainsString('site-marketing--', $layout);
        self::assertStringContainsString('site-marketing__topbar', $layout);
        self::assertStringContainsString('site-marketing__brand-mark', $layout);
    }
}
