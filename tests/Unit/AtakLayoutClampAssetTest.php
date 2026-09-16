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
        $phone = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_phoneDisplay.sqf'
        );
        $anim = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Anim_Type.sqf'
        );
        $poll = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf'
        );
        $toggle = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_toggleDrawer.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $geoloc = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installPhoneGeolocMap.sqf'
        );
        $noPhone = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-16-atak-crash-sans-telephone.md'
        );
        $all = $layout . "\n" . $enforce;

        self::assertStringContainsString('ctrlShow false', $all);
        self::assertStringContainsString('displayCtrl 46600', $all);
        self::assertStringContainsString('17000 + 4660', $all);
        self::assertStringContainsString('COMSPEC_Athena_FsAlert', $layout);
        self::assertStringContainsString('COMSPEC_ATAK_DrawerOpen', $all);
        self::assertStringContainsString('Anim_ToggleMenu", false', $layout);
        self::assertStringContainsString('athena_enforceDrawer', $layout);
        self::assertStringContainsString('isNil "cTabIfOpen"', $layout);
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
        self::assertStringContainsString('1.0.142', $cfg);
        self::assertStringContainsString('COMSPEC_ATAK_DrawerWantOpen', $enforce);
        self::assertStringContainsString('cTabIfOpen', $enforce);
        self::assertStringContainsString('cTabIfOpen', $phone);
        self::assertStringNotContainsString('cTab_Android_dsp', $phone);
        self::assertStringContainsString('0.001', $anim);
        self::assertStringContainsString('ctrlSetPositionW', $anim);
        self::assertStringNotContainsString('from 1 to 4000', $geoloc);
        self::assertStringContainsString('sans ouvrir', strtolower($noPhone));
        self::assertStringContainsString('RscStructuredText', $hud);
        self::assertStringContainsString('Iceman_ReportsDetailText', $hud);
        self::assertStringContainsString('setPlainText', $hud);
        self::assertStringContainsString('_cursorHtml', $hud);
        self::assertStringNotContainsString('ctrlSetText _cursorTxt', $hud);
        self::assertStringNotContainsString('ctrlSetStructuredText parseText', $hud);

        self::assertStringContainsString('1607', $enforce);
        self::assertStringContainsString('ctrlSetEventHandler', $enforce);
        self::assertStringContainsString('_sm set [1, true]', $enforce);
        self::assertStringContainsString('ctrlShow true', $enforce);
        self::assertStringContainsString('class athena_toggleDrawer {}', $cfg);
        self::assertStringContainsString('COMSPEC_ATAK_DrawerWantOpen', $toggle);
        self::assertStringContainsString('isFinal cTab_fnc_showMenu_toggle', $post);
        self::assertStringContainsString('COMSPEC_ChatInFingerprints', $poll);
        self::assertStringContainsString('select [0, 160]', $poll);
        self::assertStringNotContainsString('cTab_fnc_addNotification', $poll);

        self::assertStringContainsString('Arrêt anormal', $bug);
        self::assertStringContainsString('tiroir', $bug);
        self::assertStringContainsString('accueil', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
