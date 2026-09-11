<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPlaytimeAndSettingsSelectAssetTest extends TestCase
{
    public function testPlaytimeIsReportedFromPackWhenLinked(): void
    {
        $root = dirname(__DIR__, 2);
        $atak = (string) file_get_contents(
            $root . '/mod/Overwatch 2026/ProdVersion/GPT/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_playtimeTracker.sqf'
        );
        $ow = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_playtimeTracker.sqf'
        );
        $pre = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');

        self::assertStringContainsString('ReportPlaytime', $atak);
        self::assertStringContainsString('COMSPEC_ATAK_AthenaReady', $atak);
        self::assertStringContainsString('comspec_overwatch_connect', $atak);
        self::assertStringContainsString('is3DEN', $atak);
        self::assertStringContainsString('_flushCtx', $atak);
        self::assertStringContainsString('ReportPlaytime', $ow);
        self::assertStringContainsString('COMSPEC_AthenaReady', $ow);
        self::assertStringContainsString('COMSPEC_PlaytimeAccum', $ow);
        $forcePt = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_forcePlaytimeReport.sqf'
        );
        $forceSync = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_forceSyncData.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        self::assertStringContainsString('class forcePlaytimeReport', $cfg);
        self::assertStringContainsString('forcePlaytimeReport', $forceSync);
        self::assertStringContainsString('temps de mission', $forceSync);
        self::assertStringContainsString('ReportPlaytime', $forcePt);
        self::assertStringContainsString('Remonter le temps', $page);
        self::assertStringContainsString('comspec_overwatch_playtime_enabled', $pre);
        self::assertStringContainsString('ReportPlaytime', $ext);
        self::assertStringContainsString('ctx != "zeus" && ctx != "editor"', $ext);
        self::assertStringContainsString('/api/atak/playtime', $ext);
        self::assertStringContainsString('HasPortalAuth()', $ext);
    }
}
