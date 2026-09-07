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
        $switcher = (string) file_get_contents($root . '/views/partials/personnel/file_view_switcher.php');
        $tableau = (string) file_get_contents($root . '/views/partials/personnel/file_tableau_admin_tab.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $edit = (string) file_get_contents($root . '/views/personnel/edit.php');

        self::assertStringContainsString('personnelFileIsRhFull', $file);
        self::assertStringContainsString('personnelFileRhContext', $file);
        self::assertStringNotContainsString('personnel-file--rh-gate', $file);
        self::assertStringNotContainsString('file_view_gate.php', $file);
        self::assertStringContainsString('file_view_switcher.php', $file);
        self::assertStringNotContainsString('min-h-screen pt-20 pb-24', $file);
        self::assertStringNotContainsString('getFlash(\'success\')', $file);

        self::assertStringContainsString('<details', $rh);
        self::assertStringContainsString('return_view=rh', $rh);
        self::assertStringContainsString("effectifs_workspace_url('membres/' . \$rhTargetUserId) . '#modifier-dossier'", $rh);
        self::assertStringContainsString('file_view_switcher.php', $rh);
        self::assertStringContainsString('Vue commandement', $rh);
        self::assertStringNotContainsString('Changer de vue', $rh);
        self::assertStringNotContainsString('Vue publique', $rh);
        self::assertStringNotContainsString('grid gap-2.5 lg:grid-cols-2', $rh);

        self::assertStringContainsString('>Fiche</a>', $switcher);
        self::assertStringContainsString('>Commandement</a>', $switcher);
        self::assertStringContainsString('?view=rh', $switcher);
        self::assertStringContainsString('empty($canAccessRhView)', $switcher);

        self::assertStringContainsString('$tableauAdminStandalone', $tableau);
        self::assertStringContainsString("'Qualifications'", $tableau);
        self::assertStringContainsString("'Formations'", $tableau);
        self::assertStringContainsString("'Absences'", $tableau);
        self::assertStringContainsString('character_name', $tableau);

        self::assertStringContainsString('personnelShowRedirectUrl', $controller);
        self::assertStringContainsString("\$returnView === 'rh'", $controller);
        self::assertStringContainsString('$returnToEffectifs', $controller);
        self::assertStringContainsString("\$returnView === 'rh' && EffectifsLmsAccess::allows", $controller);
        self::assertStringContainsString('$canSensitive || $isForumMod', $controller);
        self::assertStringContainsString('layoutMainCompact', $controller);
        self::assertStringContainsString("['rh', 'commandement']", $controller);
        self::assertStringContainsString("\$personnelViewMode === 'rh'", $controller);

        self::assertStringContainsString('return_view', $edit);
        self::assertStringContainsString('name="return_view" value="rh"', $edit);

        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $footerCss = (string) file_get_contents($root . '/public/assets/css/portal-footer.css');
        self::assertStringContainsString('layout-page-compact', $layout);
        self::assertStringContainsString('layoutMainCompact', $layout);
        self::assertStringContainsString('min-h-0', $layout);
        self::assertStringContainsString("str_replace(' min-h-screen'", $layout);
        self::assertStringContainsString('body.layout-page-compact .portal-footer', $footerCss);
        self::assertStringContainsString('body.layout-page-compact {', $footerCss);
    }
}
