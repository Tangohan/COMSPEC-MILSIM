<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackOfficePortalReturnAssetTest extends TestCase
{
    public function testSidebarAndTopbarLinkBackToDashboard(): void
    {
        $sidebar = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/partials/back_office_sidebar.php'
        );
        $topbar = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/partials/back_office_topbar.php'
        );
        $css = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/css/back-office-shell.css'
        );

        self::assertStringContainsString("url('dashboard')", $sidebar);
        self::assertStringContainsString('Retour au tableau de bord', $sidebar);
        self::assertStringContainsString('ath-sidebar__portal', $sidebar);

        self::assertStringContainsString("url('dashboard')", $topbar);
        self::assertStringContainsString('Tableau de bord', $topbar);
        self::assertStringContainsString('ath-topbar__portal', $topbar);

        self::assertStringContainsString('.ath-sidebar__portal', $css);
        self::assertStringContainsString('.ath-topbar__portal', $css);
    }
}
