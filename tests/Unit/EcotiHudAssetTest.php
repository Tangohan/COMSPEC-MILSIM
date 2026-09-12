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
        $atakCfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );

        self::assertStringContainsString('comspec_overwatch_ecoti_hud', $pre);
        self::assertStringContainsString('Affichage situation (JVN)', $pre);
        self::assertStringContainsString('false', $pre); // default OFF
        self::assertStringContainsString('comspec_overwatch_ecoti_show_outline', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_show_route', $pre);
        self::assertStringContainsString('Découpage 3D des bâtiments (à venir)', $pre);
        self::assertStringContainsString('class ecotiInit', $cfg);
        self::assertStringContainsString('class ecotiDraw', $cfg);
        self::assertStringContainsString('class ecotiDrawBadge', $cfg);
        self::assertStringContainsString('class ecotiIlluminateZone', $cfg);
        self::assertStringContainsString('class ecotiApplyHudSetting', $cfg);
        self::assertStringContainsString('1.5.54', $cfg);
        self::assertStringContainsString('1.0.99', $atakCfg);
        self::assertStringContainsString('class athena_ecotiHudSave', $atakCfg);
        self::assertStringContainsString('ecotiInit', $post);
        self::assertStringContainsString('COMSPEC_EcotiHudEnabled', $post);
        self::assertStringContainsString('currentVisionMode', $active);
        self::assertStringContainsString('comspec_overwatch_ecoti_hud", false', $avail);
        self::assertStringContainsString('FPANO_ECOTI_PATCH', $fpano);
        self::assertStringContainsString('ecotiDrawBadge', $draw);
        self::assertStringContainsString('ecotiDrawBuilding', $draw);
        self::assertStringContainsString('ecotiDrawRoute', $draw);
        self::assertStringContainsString('ecotiDrawOutline', $draw);
        self::assertStringContainsString('COMSPEC_EcotiMarkBuilding', $ace);
        self::assertStringContainsString('COMSPEC_EcotiIlluminate', $ace);
        self::assertStringContainsString('COMSPEC_EcotiRouteAdd', $ace);
        self::assertStringContainsString('idc = 9862', $page);
        self::assertStringContainsString('Affichage situation (JVN)', $page);
        self::assertStringContainsString('Activé sous JVN', $settings);
        self::assertStringContainsString('COMSPEC_EcotiHudEnabled', $save);

        $ids = array_column((new AtakBridgeModulesService())->catalog(), 'id');
        self::assertContains('ecoti_hud', $ids);
    }
}
