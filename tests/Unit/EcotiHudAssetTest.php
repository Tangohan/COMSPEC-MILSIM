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
        $active = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiIsActive.sqf'
        );
        $avail = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiIsAvailable.sqf'
        );
        $fpano = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiFpanoPresent.sqf'
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
        self::assertStringContainsString('Découpage d’étage (silhouette)', $pre);
        self::assertStringContainsString('class ecotiInit', $cfg);
        self::assertStringContainsString('class ecotiDrawBadge', $cfg);
        self::assertStringContainsString('class ecotiThemeColors', $cfg);
        self::assertStringContainsString('class ecotiDrawUnitOutline', $cfg);
        self::assertStringContainsString('class ecotiRefreshBuildingFootprint', $cfg);
        self::assertStringContainsString('class ecotiCutAtLook', $cfg);
        self::assertStringContainsString('class ecotiApplyThemeSetting', $cfg);
        self::assertStringContainsString('1.5.60', $cfg);
        self::assertStringContainsString('1.0.108', $atakCfg);
        self::assertStringContainsString('class athena_ecotiThemeSave', $atakCfg);
        self::assertStringContainsString('ecotiInit', $post);
        self::assertStringContainsString('COMSPEC_EcotiTheme', $post);
        self::assertStringContainsString('currentVisionMode', $active);
        self::assertStringContainsString('comspec_overwatch_ecoti_hud", false', $avail);
        self::assertStringContainsString('FPANO_ECOTI_PATCH', $fpano);
        self::assertStringContainsString('ecotiDrawUnitOutline', $draw);
        self::assertStringContainsString('ecotiThemeColors', $draw);
        self::assertStringContainsString('ecotiRefreshBuildingFootprint', $bldg);
        self::assertStringContainsString('PuristaSemibold', $badge);
        self::assertStringContainsString('COMSPEC_EcotiCutAtLook', $ace);
        self::assertStringContainsString('idc = 9873', $page);
        self::assertStringContainsString('Couleurs situation (JVN)', $page);
        self::assertStringContainsString('JVN (cyan clair)', $settings);
        self::assertStringContainsString('COMSPEC_EcotiHudEnabled', $save);
        self::assertStringContainsString('ecotiApplyCutawaySetting', $cutSave);
        self::assertStringContainsString('ecotiApplyThemeSetting', $themeSave);

        $ids = array_column((new AtakBridgeModulesService())->catalog(), 'id');
        self::assertContains('ecoti_hud', $ids);
    }
}
