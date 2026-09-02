<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPhoneSettingsIdentityAssetTest extends TestCase
{
    public function testSettingsAndHudRejectCommunityTitleAsCallsign(): void
    {
        $root = dirname(__DIR__, 2);
        $get = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getCallsign.sqf'
        );
        $usable = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isUsableCallsign.sqf'
        );
        $set = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setCallsign.sqf'
        );
        $boot = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_applyBootstrap.sqf'
        );
        $authCells = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_authStateCells.sqf'
        );
        $settings = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateSettings.sqf'
        );
        $hud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf'
        );
        $sync = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncCallsignFromAthena.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        $php = (string) file_get_contents($root . '/app/Services/Game/GameAuthService.php');
        $web = (string) file_get_contents($root . '/app/Controllers/Web/AtakController.php');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');

        self::assertStringContainsString('class isUsableCallsign', $cfg);
        self::assertStringContainsString('class inGameGroupLabel', $cfg);
        self::assertStringContainsString('class splitKeepEmpty', $cfg);
        self::assertStringContainsString('comspec_tenant_name', $usable);
        self::assertStringContainsString('count _cs) > 40', $usable);
        self::assertStringContainsString('_allowEmpty', $get);
        self::assertStringNotContainsString('groupId (group player)', $get);
        self::assertStringNotContainsString('name player', $get);
        self::assertStringNotContainsString('setGroupIdGlobal', $set);
        self::assertStringContainsString('isUsableCallsign', $set);
        self::assertStringContainsString('authStateCells', $boot);
        self::assertStringContainsString('isUsableCallsign', $boot);
        self::assertStringContainsString('splitKeepEmpty', $authCells);
        self::assertStringContainsString('[true] call comspec_overwatch_connect_fnc_getCallsign', $settings);
        self::assertStringContainsString('inGameGroupLabel', $settings);
        self::assertStringContainsString('Rester dans le groupe actuel', $settings);
        self::assertStringContainsString('ctrlSetText _shown', $settings);
        self::assertStringContainsString('COMSPEC_BftLabelMode', $settings);
        self::assertStringContainsString('Indicatif et rôle', $settings);
        $save = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_settingsSave.sqf'
        );
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp'
        );
        self::assertStringContainsString('ctrlText _roleEdit', $save);
        self::assertStringContainsString('COMSPEC_BftLabelMode', $save);
        self::assertStringContainsString('class EditRole', $page);
        self::assertStringNotContainsString('class ComboRole', $page);
        self::assertStringContainsString('idc = 9850', $page);
        self::assertStringContainsString('Affichage sur la carte', $page);
        self::assertStringContainsString('athena_bftUnitLabel', $hud);
        self::assertStringContainsString('inGameGroupLabel', $hud);
        self::assertStringNotContainsString('name _man', $hud);
        $grp = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_inGameGroupLabel.sqf'
        );
        $pos = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf'
        );
        $layout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        self::assertStringContainsString('comspec_profile_unit', $grp);
        self::assertStringContainsString('%1 · %2', $grp);
        self::assertStringContainsString('inGameGroupLabel', $pos);
        self::assertStringNotContainsString('trim (groupId (group _unit))', $pos);
        self::assertStringContainsString('inGameGroupLabel', $layout);
        self::assertStringNotContainsString('groupId group cTab_player, [cTab_player] call CBA_fnc_getGroupIndex', $layout);
        self::assertStringContainsString('Ne reprend jamais le nom de communauté', $sync);
        self::assertStringContainsString('CallsignCell', $dll);
        self::assertStringContainsString('ComposeBftGroupLabel', $dll);
        self::assertStringContainsString('OperatorTacticalIdentity', $php);
        self::assertStringContainsString('OperatorTacticalIdentity', $web);
        self::assertStringContainsString('OperatorTacticalIdentity::callsign', $api);
        self::assertStringContainsString('OperatorTacticalIdentity::groupLabel', $api);
        self::assertFileExists($root . '/docs/bugs/2026-09-01-atak-parametres-indicatif-communaute.md');
    }
}
