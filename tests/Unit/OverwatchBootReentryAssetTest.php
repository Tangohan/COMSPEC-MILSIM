<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchBootReentryAssetTest extends TestCase
{
    public function testPreInitAndPostInitRefuseASecondPass(): void
    {
        $root = dirname(__DIR__, 2);
        $base = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $pre = (string) file_get_contents($base . '/connect/XEH_preInit.sqf');
        $post = (string) file_get_contents($base . '/connect/XEH_postInit.sqf');
        $client = (string) file_get_contents($base . '/connect/XEH_postInitClient.sqf');
        $ace = (string) file_get_contents($base . '/connect/functions/fn_initACE.sqf');
        $add = (string) file_get_contents($base . '/connect/functions/fn_aceAddSelfAction.sqf');
        $sweep = (string) file_get_contents($base . '/connect/functions/fn_aceSweepPlayerSelfActions.sqf');
        $athPre = (string) file_get_contents($base . '/atak_athena/XEH_preInit.sqf');
        $athPost = (string) file_get_contents($base . '/atak_athena/XEH_postInitClient.sqf');
        $cfg = (string) file_get_contents($base . '/connect/config.cpp');
        $bug = (string) file_get_contents($root . '/docs/bugs/2026-09-18-overwatch-boot-reentrant.md');

        self::assertStringStartsWith(
            '// Une seule PreInit par mission',
            ltrim(substr($pre, 0, 80))
        );
        self::assertStringContainsString('COMSPEC_Overwatch_PreInitDone', $pre);
        self::assertTrue(strpos($pre, 'COMSPEC_Overwatch_PreInitDone') < strpos($pre, 'mavic_setting_enableConnectionDistance'));

        self::assertStringContainsString('COMSPEC_Overwatch_PostInitDone', $post);
        self::assertTrue(strpos($post, 'COMSPEC_Overwatch_PostInitDone') < strpos($post, 'initProxyTrackServer'));
        self::assertStringContainsString('COMSPEC_CbaSettingsEhDump', $post);
        self::assertStringContainsString('COMSPEC_CbaSettingsEhArmed', $post);
        self::assertStringContainsString('COMSPEC_BootHandshakeSpawn', $post);
        self::assertStringContainsString('COMSPEC_AtakPublicVarsPFH', $post);
        self::assertStringContainsString('COMSPEC_Overwatch_PostInitClientDone', $client);

        self::assertStringContainsString(', true] call ace_interact_menu_fnc_removeActionFromClass', $ace);
        self::assertStringContainsString('[player, 1, _path + [_actionId]]', $ace);
        self::assertStringContainsString('_path + [_actionId]] call ace_interact_menu_fnc_removeActionFromObject', $add);
        self::assertStringContainsString('_path + [_actionId]] call ace_interact_menu_fnc_removeActionFromObject', $sweep);
        self::assertStringNotContainsString('[player, 1, _path, _actionId]', $add);
        self::assertStringNotContainsString('[player, 1, _path, _actionId]', $sweep);

        self::assertStringContainsString('COMSPEC_Athena_PreInitDone', $athPre);
        self::assertStringContainsString('COMSPEC_Athena_PostInitDone', $athPost);
        self::assertStringContainsString('1.6.4', $cfg);

        self::assertStringContainsString('corrigé', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('callExtension', $bug);
    }
}
