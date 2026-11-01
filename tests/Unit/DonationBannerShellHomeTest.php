<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\DonationBanner;
use PHPUnit\Framework\TestCase;

final class DonationBannerShellHomeTest extends TestCase
{
    public function testShellHomesOnlyDashboardAndBackOfficeLanding(): void
    {
        foreach (['dashboard', '/public/dashboard', 'back-office', 'public/back-office/'] as $p) {
            self::assertTrue(DonationBanner::isShellHomePath($p), $p);
        }
        foreach (['back-office/effectifs', 'admin', 'dashboard/x', ''] as $p) {
            self::assertFalse(DonationBanner::isShellHomePath($p), $p);
        }
    }

    public function testLayoutRendersBannerInShellHome(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/main.php');
        self::assertStringContainsString("\$dnbShellHome = true; require base_path('views/partials/donation_banner.php')", $layout);
    }
}
