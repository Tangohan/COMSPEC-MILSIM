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
        self::assertStringContainsString('_useInstant = true', $layout);
        self::assertStringContainsString('_bgW max 0.04', $layout);
        self::assertStringContainsString('athena_updateMapHud', $layout);
        self::assertStringContainsString('displayCtrl 46600', $layout);
        self::assertStringContainsString('COMSPEC_Athena_FsAlert', $layout);
        self::assertStringNotContainsString('[0.001, _bgW]', $layout);

        self::assertStringContainsString('COMSPEC_ATAK_FullMapRect', $hud);
        self::assertStringContainsString('Animation_Queue', $hud);
        self::assertStringContainsString('_mw != _mw', $hud);

        self::assertStringContainsString('_full > 2', $footer);
        self::assertStringContainsString('_rw <= 0', $home);
        self::assertStringContainsString('1.0.131', $cfg);
        self::assertStringContainsString('&lt;', $hud);

        self::assertStringContainsString('Arrêt anormal', $bug);
        self::assertStringContainsString('tiroir', $bug);
        self::assertStringContainsString('accueil', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
