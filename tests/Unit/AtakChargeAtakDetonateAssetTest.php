<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakChargeAtakDetonateAssetTest extends TestCase
{
    public function testAtakTriggerAppearsOnceInAceSelector(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $ace = (string) file_get_contents($root . '/functions/fn_initChargeAceActions.sqf');
        $timers = (string) file_get_contents($root . '/functions/fn_initExplosiveTimers.sqf');

        self::assertStringContainsString('"Uniquement depuis ATAK"', $ace);
        self::assertStringContainsString('["ACE_MainActions", "ACE_SetTrigger"], _armAtak', $ace);
        self::assertStringNotContainsString('["ACE_MainActions"], _armAtak', $ace);
        self::assertStringNotContainsString('COMSPEC_ArmAtakObj', $timers);
        self::assertStringNotContainsString('"Uniquement depuis ATAK"', $timers);
        self::assertSame(1, substr_count($ace, '"COMSPEC_ArmAtak"'));
    }

    public function testDetonateReportsOnlyAfterChargeIsGone(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $byId = (string) file_get_contents($root . '/functions/fn_detonateChargeById.sqf');
        $local = (string) file_get_contents($root . '/functions/fn_detonateChargeLocal.sqf');
        $find = (string) file_get_contents($root . '/functions/fn_findChargeObject.sqf');
        $cfg = (string) file_get_contents($root . '/config.cpp');
        $timers = (string) file_get_contents($root . '/functions/fn_initExplosiveTimers.sqf');

        self::assertStringNotContainsString('"detonated"', $byId);
        self::assertStringContainsString('introuvable en jeu', $byId);
        self::assertStringContainsString('CBA_fnc_waitAndExecute', $local);
        self::assertStringContainsString('isNull _exp', $local);
        self::assertStringContainsString('"detonated"', $local);
        self::assertStringContainsString('#scripted', $local);
        self::assertStringContainsString('find "scripted"', $timers);
        self::assertStringContainsString('MineBase', $find);
        self::assertStringContainsString('1.5.16', $cfg);
    }
}
