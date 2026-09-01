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
        self::assertStringContainsString('Connexion Athena', $core);
        self::assertStringContainsString('openLogin', $core);
        self::assertStringContainsString('"COMSPEC_Account", "Connexion Athena"', $core);
        self::assertStringNotContainsString('accountLinkShow', $core);
        self::assertMatchesRegularExpression('/"COMSPEC_Account"[\\s\\S]{0,280}_condEnabled, _noChildren/', $core);
        self::assertStringContainsString('_condPhone', $core);
        self::assertStringContainsString('COMSPEC Athena', $ace);
        self::assertStringContainsString('Connexion Athena', $ace);
        self::assertStringContainsString('openLogin', $ace);
        self::assertStringNotContainsString('"COMSPEC Overwatch"', $ace);
        self::assertStringContainsString('Menus ACE Overwatch étendus', $pre);
        self::assertMatchesRegularExpression('/"comspec_overwatch_ace_menus"[\s\S]{0,900},\s*true\s*\]\s*call CBA_fnc_addSetting/', $pre);
        self::assertStringContainsString('[] call comspec_overwatch_connect_fnc_initACE;', $post);
        self::assertStringContainsString('[] call comspec_overwatch_connect_fnc_initATAKMenu;', $post);
        self::assertStringContainsString('addAtakRepairAction', $post);
        self::assertStringNotContainsString('menus étendus désactivés', $post);
        self::assertStringNotContainsString('Menus ACE désactivés (réglage comspec_overwatch_ace_menus)', $post);

        $atakMenu = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initATAKMenu.sqf');
        $atakInit = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initATAK.sqf');
        self::assertStringContainsString('Rapports tactiques', $atakMenu);
        self::assertStringContainsString('Demander appui', $atakMenu);
        self::assertStringContainsString('Demander service véhicule', $atakMenu);
        self::assertStringNotContainsString('comspec_overwatch_ace_menus', $atakInit);
    }
}
