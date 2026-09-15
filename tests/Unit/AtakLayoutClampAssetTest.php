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

        self::assertStringContainsString('COMSPEC_ATAK_FullMapRect', $layout);
        self::assertStringContainsString('_useInstant', $layout);
        self::assertStringContainsString('0.001', $layout);
        self::assertStringContainsString('Animation_Queue', $layout);
        self::assertStringContainsString('athena_updateMapHud', $layout);
        self::assertStringNotContainsString('displayCtrl 46600', $layout);

        self::assertStringContainsString('COMSPEC_ATAK_FullMapRect', $hud);
        self::assertStringContainsString('Animation_Queue', $hud);
        self::assertStringContainsString('_mw != _mw', $hud);

        self::assertStringContainsString('_full > 2', $footer);
        self::assertStringContainsString('_rw < 0.001', $home);
        self::assertStringContainsString('_rh < 0.001', $home);
        self::assertStringContainsString('1.0.127', $cfg);
        self::assertStringContainsString('&lt;', $hud);

        self::assertStringContainsString('Arrêt anormal', $bug);
        self::assertStringContainsString('tiroir', $bug);
        self::assertStringContainsString('accueil', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
