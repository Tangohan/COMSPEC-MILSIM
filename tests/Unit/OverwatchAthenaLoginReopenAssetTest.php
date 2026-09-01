<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchAthenaLoginReopenAssetTest extends TestCase
{
    public function testLoginCanBeReopenedFromPauseAndPhone(): void
    {
        $root = dirname(__DIR__, 2);
        $esc = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onInterruptLoad.sqf'
        );
        $open = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_openLogin.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );
        $pm = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/web/pause_manager.html'
        );
        $pmJs = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pauseManagerJSDialog.sqf'
        );
        $phone = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_showLinkDialog.sqf'
        );
        $hub = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_accountLinkShow.sqf'
        );

        self::assertStringContainsString('Connexion Athena', $esc);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_openLogin', $esc);
        self::assertStringContainsString('9606', $esc);
        self::assertStringContainsString('createDialog "COMSPEC_AthenaAuth_Dialog"', $open);
        self::assertStringContainsString('COMSPEC_Account', $ace);
        self::assertStringContainsString('Connexion Athena', $ace);
        self::assertStringContainsString('openLogin', $ace);
        self::assertStringContainsString('tool:openlogin', $pm);
        self::assertStringContainsString('Ouvrir la connexion Athena', $pm);
        self::assertStringContainsString('tool:openlogin', $pmJs);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_openLogin', $phone);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_openLogin', $hub);
    }
}
