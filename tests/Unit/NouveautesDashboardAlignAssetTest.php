<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NouveautesDashboardAlignAssetTest extends TestCase
{
    public function testChangelogUsesDashboardPrimaryPaletteAndOpsShell(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/changelog.css');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/site/changelog.php');
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/marketing.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/changelog.js');

        self::assertStringContainsString('--cl-primary: #059669', $css);
        self::assertStringContainsString('--cl-primary-strong: #065f46', $css);
        self::assertStringContainsString('--ops-green: #059669', $css);
        self::assertStringContainsString('--ops-bg: #050505', $css);
        self::assertStringContainsString('body.site-marketing--changelog .site-marketing__topbar', $css);
        self::assertStringContainsString('.cl-ops-layout', $css);
        self::assertStringContainsString('.cl-ops-metric-strip', $css);
        self::assertStringContainsString('.cl-ops-table', $css);
        self::assertStringContainsString('.cl-ops-report', $css);

        self::assertStringContainsString('data-cl-ops', $view);
        self::assertStringContainsString('cl-ops-layout', $view);
        self::assertStringContainsString('cl-ops-sidebar', $view);
        self::assertStringContainsString('cl-ops-table', $view);
        self::assertStringContainsString('class="cl-cta cl-cta--primary"', $view);
        self::assertStringContainsString('class="cl-cta cl-cta--ghost"', $view);
        self::assertStringContainsString('id="cl-ops-data"', $view);

        self::assertStringContainsString('data-cl-ops', $js);
        self::assertStringContainsString('openReport', $js);

        self::assertStringContainsString('site-marketing--', $layout);
        self::assertStringContainsString('site-marketing__topbar', $layout);
        self::assertStringContainsString('site-marketing__brand-mark', $layout);
    }
}
