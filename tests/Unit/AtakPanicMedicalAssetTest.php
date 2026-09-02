<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPanicMedicalAssetTest extends TestCase
{
    public function testUnconsciousAndKiaReachIcemanPanicList(): void
    {
        $root = dirname(__DIR__, 2);
        $mod = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $push = (string) file_get_contents($mod . '/functions/fn_pushIcemanMedicalAlert.sqf');
        $report = (string) file_get_contents($mod . '/functions/fn_reportMedicalAlert.sqf');
        $poll = (string) file_get_contents($mod . '/functions/fn_pollMedicalAlerts.sqf');
        $hit = (string) file_get_contents($mod . '/functions/fn_attachAtakDamageHandlers.sqf');
        $bridge = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanAlert.sqf');
        $stable = (string) file_get_contents($mod . '/functions/fn_isPlayerSpawnStable.sqf');
        $post = (string) file_get_contents($mod . '/XEH_postInit.sqf');
        $cfg = (string) file_get_contents($mod . '/config.cpp');
        $bug = (string) file_get_contents($root . '/docs/bugs/2026-08-28-atak-panic-inconscient-mort.md');

        self::assertStringContainsString('Iceman_ATAK_Panic_reports', $push);
        self::assertStringContainsString('EAGLE_DOWN', $push);
        self::assertStringContainsString('INCONSCIENT', $push);
        self::assertStringContainsString('KIA', $push);
        self::assertStringContainsString('COMSPEC_IcemanMedicalPanic', $push);
        self::assertStringContainsString('Iceman_fnc_alerts_receive', $push);
        self::assertStringContainsString('pushIcemanMedicalAlert', $report);
        self::assertStringContainsString('"kia"', $report);
        self::assertStringContainsString('pushIcemanMedicalAlert', $poll);
        self::assertStringContainsString('addEventHandler ["Killed"', $hit);
        self::assertStringContainsString('COMSPEC_IcemanMedicalPanic', $post);
        self::assertStringContainsString('attente d’une connexion', $post);
        self::assertStringContainsString('class pushIcemanMedicalAlert {};', $cfg);
        self::assertStringContainsString('1.5.12', $cfg);
        self::assertStringContainsString('local _sender', $bridge);
        self::assertStringContainsString('_isDistress', $bridge);
        self::assertStringContainsString('cTab_player', $bridge);
        self::assertStringNotContainsString('COMSPEC_MedicalAlertsArmed', $stable);
        self::assertStringContainsString('PANIC', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertFileExists($root . '/docs/bugs/2026-09-01-atak-panic-poste.md');
    }
}
