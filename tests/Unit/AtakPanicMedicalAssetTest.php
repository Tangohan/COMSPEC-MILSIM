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
        self::assertStringContainsString('class pushIcemanMedicalAlert {};', $cfg);
        self::assertStringContainsString('1.4.95', $cfg);
        self::assertStringContainsString('PANIC', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
