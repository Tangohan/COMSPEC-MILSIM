<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchAceAthenaMenuAssetTest extends TestCase
{
    public function testAceSelfMenuAlwaysExposesComspecAthena(): void
    {
        $root = dirname(__DIR__, 2);
        $core = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACEAthena.sqf');
        $ace = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf');
        $post = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $pre = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf');

        self::assertStringContainsString('class initACEAthena', $cfg);
        self::assertStringContainsString('initACEAthena', $post);
        self::assertStringContainsString('COMSPEC Athena', $core);
        self::assertStringContainsString('Compte Athena', $core);
        self::assertStringContainsString('accountLinkShow', $core);
        self::assertStringContainsString('"COMSPEC_Account", "Compte Athena"', $core);
        self::assertMatchesRegularExpression('/"COMSPEC_Account"[\\s\\S]{0,280}_condEnabled, _noChildren/', $core);
        self::assertStringContainsString('_condPhone', $core);
        self::assertStringContainsString('COMSPEC Athena', $ace);
        self::assertStringNotContainsString('"COMSPEC Overwatch"', $ace);
        self::assertStringContainsString('Menus ACE Overwatch étendus', $pre);
        self::assertStringNotContainsString('Menus ACE désactivés (réglage comspec_overwatch_ace_menus)', $post);
    }
}
