<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakLayoutClampAssetTest extends TestCase
{
    public function testMenuToggleStaysInsidePhoneFrame(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        $hud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $phone = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_phoneDisplay.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );

        self::assertStringContainsString('Anim_CustomOffset', $layout);
        self::assertStringContainsString('COMSPEC_ATAK_PhoneFrame', $layout);
        self::assertStringContainsString('[5, 3] select _open', $layout);
        self::assertStringContainsString('_result max 0.08) min _phoneW', $layout);
        self::assertStringContainsString('[0, nil] select _ignoreFade', $layout);
        self::assertStringContainsString('[1, _fade] select', $layout);
        self::assertStringContainsString('displayCtrl 46600', $layout);
        self::assertStringNotContainsString('[0, 1] select _showMenu', $layout);
        self::assertStringNotContainsString('_endDrawerW', $layout);

        $drawer = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_layoutAppDrawer.sqf'
        );

        self::assertStringContainsString('athena_phoneDisplay', $hud);
        self::assertStringNotContainsString('ctrlCreate', $hud);
        self::assertStringNotContainsString('_fncEnsure', $hud);

        self::assertStringContainsString('cTab_Android_dlg', $phone);
        self::assertStringContainsString('layoutAppDrawer', $layout);
        self::assertStringContainsString('ctrlSetPositionX', $drawer);
        self::assertStringContainsString('ctrlSetPositionY', $drawer);
        self::assertStringContainsString('BCE_fnc_ATAK_getAPPs', $drawer);
        self::assertStringNotContainsString('ctrlSetPosition [_cellW', $drawer);
        self::assertStringContainsString('1.0.141', $cfg);
    }
}
