<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchAthenaLoginReopenAssetTest extends TestCase
{
    public function testLoginCanBeReopenedFromPauseAndPhone(): void
    {
        $root = dirname(__DIR__, 2);
        $open = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_openLogin.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );
        $phone = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_showLinkDialog.sqf'
        );
        $hub = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_accountLinkShow.sqf'
        );

        self::assertStringContainsString('createDialog "COMSPEC_AthenaAuth_Dialog"', $open);
        self::assertStringContainsString('COMSPEC_Account', $ace);
        self::assertStringContainsString('Connexion Athena', $ace);
        self::assertStringContainsString('openLogin', $ace);
        self::assertStringContainsString('COMSPEC_AccountLink_Dialog', $phone);
        self::assertStringContainsString('Connexion Athena', $phone);
        self::assertStringContainsString('COMSPEC_AccountLink_Dialog', $hub);
        self::assertStringContainsString('Connexion Athena', $hub);
    }
}
