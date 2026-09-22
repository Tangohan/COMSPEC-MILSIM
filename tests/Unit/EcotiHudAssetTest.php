<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakBridgeModulesService;
use PHPUnit\Framework\TestCase;

final class EcotiHudAssetTest extends TestCase
{
    public function testEcotiHudGatesAndFunctionsAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $pre = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $draw = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiDraw.sqf'
        );
        $bldg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiDrawBuilding.sqf'
        );
        $badge = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiDrawBadge.sqf'
        );
        $hud = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiHudRender.sqf'
        );
        $init = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiInit.sqf'
        );
        $active = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiIsActive.sqf'
        );
        $avail = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiIsAvailable.sqf'
        );
        $fpano = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiFpanoPresent.sqf'
        );
        $a3ti = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiA3tiPresent.sqf'
        );
        $postFx = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiInitPostFX.sqf'
        );
        $chrome = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiChromeRender.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp'
        );
        $settings = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateSettings.sqf'
        );
        $save = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_ecotiHudSave.sqf'
        );
        $cutSave = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_ecotiCutawaySave.sqf'
        );
        $cutApply = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiApplyCutawaySetting.sqf'
        );
        $mark = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiMarkBuilding.sqf'
        );
        $cutLook = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiCutAtLook.sqf'
        );
        $mkName = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiBuildingMarkerName.sqf'
        );
        $themeSave = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_ecotiThemeSave.sqf'
        );
        $atakCfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );

        self::assertStringContainsString('comspec_overwatch_ecoti_hud', $pre);
        self::assertStringContainsString('Affichage situation (JVN)', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_theme', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_show_unit_outline', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_tube_fx', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_autogate', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_gps_hud', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_compass_only', $pre);
        self::assertStringContainsString('Boussole uniquement', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_fusion', $pre);
        self::assertStringContainsString('D�coupage d��tage (silhouette)', $pre);
        self::assertStringContainsString('class ecotiInit', $cfg);
        self::assertStringContainsString('class ecotiDrawBadge', $cfg);
        self::assertStringContainsString('class ecotiThemeColors', $cfg);
        self::assertStringContainsString('class ecotiDrawUnitOutline', $cfg);
        self::assertStringContainsString('class ecotiRefreshBuildingFootprint', $cfg);
        self::assertStringContainsString('class ecotiCutAtLook', $cfg);
        self::assertStringContainsString('class ecotiBuildingMarkerName', $cfg);
        self::assertStringContainsString('class ecotiApplyThemeSetting', $cfg);
        self::assertStringContainsString('class ecotiInitPostFX', $cfg);
        self::assertStringContainsString('class ecotiChromeRender', $cfg);
        self::assertStringContainsString('class ecotiDrawFusion', $cfg);
        self::assertStringContainsString('class ecotiApplyTubeInfoSetting', $cfg);
        self::assertStringContainsString('class COMSPEC_EcotiChromeHud', $cfg);
        self::assertStringContainsString('1.6.9', $cfg);
        self::assertStringContainsString('1.0.165', $atakCfg);
        self::assertStringContainsString('class athena_ecotiThemeSave', $atakCfg);
        self::assertStringContainsString('class athena_ecotiTubeInfoSave', $atakCfg);
        self::assertStringContainsString('ecotiInit', $post);
        self::assertStringContainsString('COMSPEC_EcotiTheme', $post);
        self::assertStringContainsString('currentVisionMode', $active);
        self::assertStringContainsString('comspec_overwatch_ecoti_hud", false', $avail);
        self::assertStringContainsString('FPANO_ECOTI_PATCH', $fpano);
        self::assertStringContainsString('A3TI', $a3ti);
        self::assertStringContainsString('FilmGrain', $postFx);
        self::assertStringContainsString('COMSPEC ECOTI', $chrome);
        self::assertStringContainsString('ecotiDrawUnitOutline', $draw);
        self::assertStringContainsString('ecotiThemeColors', $draw);
        self::assertStringContainsString('ecotiDrawFusion', $draw);
        self::assertStringContainsString('ecotiRefreshBuildingFootprint', $bldg);
        self::assertStringContainsString('_i == _lastFloor', $bldg);
        self::assertStringNotContainsString('_i == (_sel + 1)', $bldg);
        self::assertStringContainsString('(_sel + 1) min _floors', $bldg);
        self::assertStringContainsString('PuristaMedium', $badge);
        self::assertStringContainsString('ecotiIconPath', $badge);
        self::assertStringContainsString('ecotiIconPath', $hud);
        self::assertStringContainsString('CBA_fnc_addPerFrameHandler', $init);
        self::assertFileExists(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/img/ecoti/ecoti_icon_bracket_ca.png'
        );
        self::assertFileExists(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/img/ecoti/ecoti_vignette_ca.png'
        );
        self::assertFileExists(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/img/ecoti/ecoti_glow_ca.png'
        );
        self::assertStringContainsString('COMSPEC_EcotiCutAtLook', $ace);
        self::assertStringContainsString('idc = 9873', $page);
        self::assertStringContainsString('idc = 9877', $page);
        self::assertStringContainsString('Dans le tube (JVN)', $page);
        self::assertStringContainsString('Boussole uniquement', $settings);
        self::assertStringContainsString('ecoti_compass_only', $chrome);
        self::assertStringContainsString('Couleurs situation (JVN)', $page);
        self::assertStringContainsString('JVN (cyan clair)', $settings);
        self::assertStringContainsString('COMSPEC_EcotiHudEnabled', $save);
        self::assertStringContainsString('ecotiApplyCutawaySetting', $cutSave);
        self::assertStringContainsString('cba_settings_fnc_set', $cutApply);
        self::assertStringNotContainsString('COMSPEC_EcotiCutawayEnabled', $cutApply);
        self::assertStringNotContainsString('profileNamespace', $cutApply);
        self::assertStringNotContainsString('COMSPEC_EcotiCutawayEnabled', $settings);
        self::assertStringContainsString('COMSPEC_EcotiCutawayEnabled', $post);
        self::assertStringContainsString('profileNamespace setVariable ["COMSPEC_EcotiCutawayEnabled", nil]', $post);
        self::assertStringContainsString('ecotiBuildingMarkerName', $mark);
        self::assertStringContainsString('ecotiBuildingMarkerName', $cutLook);
        self::assertStringContainsString('COMSPEC_EcotiBldgMkSeq', $mkName);
        self::assertStringNotContainsString('random 99999', $mark);
        self::assertStringNotContainsString('random 99999', $cutLook);
        self::assertStringContainsString('ecotiApplyThemeSetting', $themeSave);

        $ids = array_column((new AtakBridgeModulesService())->catalog(), 'id');
        self::assertContains('ecoti_hud', $ids);
    }
}
