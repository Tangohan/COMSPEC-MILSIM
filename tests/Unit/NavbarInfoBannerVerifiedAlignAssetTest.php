<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NavbarInfoBannerVerifiedAlignAssetTest extends TestCase
{
    public function testVerifiedBadgeIsAppendedToBannerEndNotStackedInContent(): void
    {
        $root = dirname(__DIR__, 2);
        $php = (string) file_get_contents($root . '/views/partials/navbar_info_banners.php');
        $css = (string) file_get_contents($root . '/public/assets/css/navbar-info-banners.css');

        self::assertStringContainsString("end.className = 'banner-end'", $php);
        self::assertStringContainsString('end.appendChild(v)', $php);
        self::assertStringNotContainsString('content.appendChild(v)', $php);
        self::assertStringContainsString('Site vérifié', $php);
        self::assertStringContainsString('Annonce officielle du site Athena', $php);

        self::assertStringContainsString('.banner-end', $css);
        self::assertStringContainsString('margin-left: auto', $css);
        self::assertMatchesRegularExpression('/\.banner-end\s*\{[^}]*display:\s*flex/s', $css);
        self::assertMatchesRegularExpression('/\.banner-end\s*\{[^}]*margin-left:\s*auto/s', $css);
    }
}
