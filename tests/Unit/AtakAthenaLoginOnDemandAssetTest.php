<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAthenaLoginOnDemandAssetTest extends TestCase
{
    public function testLoginWindowIsNotOpenedAtMissionStart(): void
    {
        $root = dirname(__DIR__, 2);
        $restore = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_restoreSession.sqf'
        );
        $logout = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_logout.sqf'
        );
        $open = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_openLogin.sqf'
        );
        $desk = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installDesktopShortcut.sqf'
        );
        $wait = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_waitAthenaReady.sqf'
        );
        $init = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_initAuth.sqf'
        );
        $steam = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_loginSteam.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACEAthena.sqf'
        );
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );

        self::assertStringNotContainsString('openLogin', $restore);
        self::assertStringNotContainsString('openLogin', $logout);
        self::assertStringContainsString('createDisplay "COMSPEC_AthenaAuth_Dialog"', $open);
        self::assertStringContainsString('jamais au démarrage', $open);
        self::assertStringContainsString('Connexion<br/>Athena', $desk);
        self::assertStringContainsString('"login"', $desk);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_openLogin', $desk);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_openLogin', $ace);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_openLogin', $page);
        self::assertStringNotContainsString('accountLinkShow', $ace);
        self::assertStringNotContainsString('athena_showLinkDialog', $page);
        self::assertStringContainsString('tuile Connexion Athena', $wait);
        self::assertStringContainsString('diag_tickTime + 20', $wait);
        self::assertStringContainsString('loginSteam', $wait);
        self::assertStringContainsString('[true] call comspec_overwatch_connect_fnc_loginSteam', $init);
        self::assertStringContainsString('findDisplay 46', $init);
        self::assertStringContainsString('_silent', $steam);
        self::assertStringContainsString('getPlayerUID player', $steam);
        self::assertStringContainsString('AuthSteam', $steam);
        self::assertStringContainsString('COMSPEC_AthenaLoginSyncEH', $post);
        self::assertStringContainsString('COMSPEC_MedicalAlertsArmed', $post);
        self::assertFileExists($root . '/docs/bugs/2026-09-01-atak-connexion-auto-debut.md');
        self::assertFileExists($root . '/docs/bugs/2026-09-01-athena-steam-auto-boot.md');
    }
}
