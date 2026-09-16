<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakLayoutClampAssetTest extends TestCase
{
    public function testCheckLayoutNeverAnimatesNegativeOrZeroWidth(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        $enforce = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_enforceDrawer.sqf'
        );
        $hud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $home = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf'
        );
        $footer = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsFooter.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-15-atak-access-violation-autoarray.md'
        );
        $all = $layout . "\n" . $enforce;

        self::assertStringContainsString('ctrlShow false', $all);
        self::assertStringContainsString('displayCtrl 46600', $all);
        self::assertStringContainsString('17000 + 4660', $all);
        self::assertStringContainsString('COMSPEC_Athena_FsAlert', $layout);
        self::assertStringContainsString('COMSPEC_ATAK_DrawerOpen', $all);
        self::assertStringContainsString('Anim_ToggleMenu", false', $layout);
        self::assertStringContainsString('athena_enforceDrawer', $layout);
        self::assertStringNotContainsString('ctrlSetPosition', $all);
        self::assertStringNotContainsString('ctrlMapSetPosition', $all);
        self::assertStringNotContainsString('_fncSetXYWH', $all);
        self::assertStringNotContainsString('_phoneW * 0.4', $all);
        self::assertStringNotContainsString('[0.001, _bgW]', $all);
        self::assertStringNotContainsString('ATAK_Toggle_Spring', $all);
        self::assertStringNotContainsString('_skipApp', $all);
        self::assertStringNotContainsString('else { true }', $all);

        self::assertStringContainsString('COMSPEC_ATAK_FullMapRect', $hud);
        self::assertStringContainsString('_mw != _mw', $hud);
        self::assertStringContainsString('17000 + 2620', $hud);

        self::assertStringContainsString('_full > 2', $footer);
        self::assertStringContainsString('_rw <= 0', $home);
        self::assertStringContainsString('1.0.140', $cfg);
        self::assertStringContainsString('COMSPEC_ATAK_DrawerWantOpen', $enforce);
        self::assertStringContainsString('cTabIfOpen', $enforce);
        self::assertStringContainsString('RscStructuredText', $hud);
        self::assertStringContainsString('Iceman_ReportsDetailText', $hud);
        self::assertStringContainsString('setPlainText', $hud);
        self::assertStringContainsString('_cursorHtml', $hud);
        self::assertStringNotContainsString('ctrlSetText _cursorTxt', $hud);
        self::assertStringNotContainsString('ctrlSetStructuredText parseText', $hud);

        self::assertStringContainsString('Arrêt anormal', $bug);
        self::assertStringContainsString('tiroir', $bug);
        self::assertStringContainsString('accueil', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
