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
        $fpano = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiFpanoPresent.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );

        self::assertStringContainsString('comspec_overwatch_ecoti_hud', $pre);
        self::assertStringContainsString('Affichage situation (JVN)', $pre);
        self::assertStringContainsString('comspec_overwatch_ecoti_show_allies', $pre);
        self::assertStringContainsString('class ecotiInit', $cfg);
        self::assertStringContainsString('class ecotiDraw', $cfg);
        self::assertStringContainsString('1.5.43', $cfg);
        self::assertStringContainsString('ecotiInit', $post);
        self::assertStringContainsString('currentVisionMode', $active);
        self::assertStringContainsString('FPANO_ECOTI_PATCH', $fpano);
        self::assertStringContainsString('drawIcon3D', $draw);
        self::assertStringContainsString('ecotiDrawBuilding', $draw);
        self::assertStringContainsString('COMSPEC_EcotiMarkBuilding', $ace);

        $ids = array_column((new AtakBridgeModulesService())->catalog(), 'id');
        self::assertContains('ecoti_hud', $ids);
    }
}
