<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrbatContextMenuAssetTest extends TestCase
{
    public function testStructureHubExposesFullRightClickEditing(): void
    {
        $root = dirname(__DIR__, 2);
        $canvas = (string) file_get_contents($root . '/views/partials/orbat/orbat_canvas.php');
        $app = (string) file_get_contents($root . '/public/assets/js/orbat-app.js');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString('id="orb-cm"', $canvas);
        self::assertStringContainsString("rootEl.addEventListener('contextmenu'", $app);
        foreach (['function unitMenu', 'function postMenu', 'function canvasMenu', "t('menu_rename')", "t('menu_symbol')",
            "t('menu_posts')", "t('menu_move')", "t('menu_detach')", "t('menu_status')", "t('menu_visibility')",
            "t('menu_assign')", "t('menu_vacate'", "t('menu_new_group')", "t('menu_new_team')", "t('menu_delete')"] as $needle) {
            self::assertStringContainsString($needle, $app);
        }
        self::assertStringContainsString("t('invite_member')", $app);
        self::assertStringContainsString('document.body.appendChild(el)', $app);
        // L'ancien « Structure & recrutement » redirige vers l'ORBAT, qui reprend ses accès (dont site.support).
        self::assertStringContainsString("'site.support'", $ctrl);
        self::assertStringContainsString("orbat_page_url(['ouvrir' => (string) \$r->query('ouvrir', '')])", $routes);
    }
}
