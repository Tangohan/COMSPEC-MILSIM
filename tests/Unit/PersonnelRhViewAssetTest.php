<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelRhViewAssetTest extends TestCase
{
    public function testRhViewHasExtendedTableauAndCleanNotifications(): void
    {
        $root = dirname(__DIR__, 2);
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $rh = (string) file_get_contents($root . '/views/partials/personnel/file_rh_view.php');
        $tableau = (string) file_get_contents($root . '/views/partials/personnel/file_tableau_admin_tab.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $edit = (string) file_get_contents($root . '/views/personnel/edit.php');

        self::assertStringContainsString('personnelFileIsRhFull', $file);
        self::assertStringContainsString('personnelFileRhContext', $file);
        self::assertStringContainsString('personnel-file--rh-gate', $file);
        self::assertStringNotContainsString('min-h-screen pt-20 pb-24', $file);
        self::assertStringNotContainsString('getFlash(\'success\')', $file);

        self::assertStringContainsString('<details', $rh);
        self::assertStringContainsString('return_view=rh', $rh);
        self::assertStringNotContainsString('grid gap-2.5 lg:grid-cols-2', $rh);

        self::assertStringContainsString('$tableauAdminStandalone', $tableau);
        self::assertStringContainsString("'Qualifications'", $tableau);
        self::assertStringContainsString("'Formations'", $tableau);
        self::assertStringContainsString("'Absences'", $tableau);
        self::assertStringContainsString('character_name', $tableau);

        self::assertStringContainsString('personnelShowRedirectUrl', $controller);
        self::assertStringContainsString("return_view === 'rh'", $controller);
        self::assertStringContainsString('$canSensitive || $isForumMod', $controller);
        self::assertStringContainsString('layoutMainCompact', $controller);

        self::assertStringContainsString('return_view', $edit);
        self::assertStringContainsString('name="return_view" value="rh"', $edit);

        $gate = (string) file_get_contents($root . '/views/partials/personnel/file_view_gate.php');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $footerCss = (string) file_get_contents($root . '/public/assets/css/portal-footer.css');
        self::assertStringContainsString('py-8 md:py-10', $gate);
        self::assertStringContainsString('py-6 md:py-8', $gate);
        self::assertStringNotContainsString('py-14 md:py-20', $gate);
        self::assertStringContainsString('layout-page-compact', $layout);
        self::assertStringContainsString('layoutMainCompact', $layout);
        self::assertStringContainsString('min-h-0', $layout);
        self::assertStringContainsString('body.layout-page-compact .portal-footer', $footerCss);
    }
}
