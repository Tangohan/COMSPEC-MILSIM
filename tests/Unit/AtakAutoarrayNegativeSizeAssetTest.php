<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAutoarrayNegativeSizeAssetTest extends TestCase
{
    public function testLogTailTimelineAndAcePurgeStayInEngineLimits(): void
    {
        $root = dirname(__DIR__, 2);
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $bugLog = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_collectBugReportLog.sqf'
        );
        $session = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_collectSessionLog.sqf'
        );
        $timeline = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_createTimeline.sqf'
        );
        $xeh = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $bridge = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeCtabMarkers.sqf'
        );
        $bft = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_relabelBft.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-17-atak-autoarray-negative-size.md'
        );

        $status = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateStatus.sqf'
        );
        $refresh = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_refreshLinkState.sqf'
        );
        $badges = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateStatusBadges.sqf'
        );
        $tx = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_canTransmit.sqf'
        );
        $sent = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_recordPacketSent.sqf'
        );
        $overlay = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateDeviceOverlay.sqf'
        );

        self::assertStringContainsString('Utf8Fit', $ext);
        self::assertStringContainsString('MaxOutputBytes', $ext);
        self::assertStringContainsString('GetLogTail', $ext);
        self::assertStringNotContainsString('maxBytes = 14000', $ext);
        self::assertStringNotContainsString('Math.Min(parsed, 32000)', $ext);

        self::assertStringContainsString('GetLogTail", ["8000"]', $bugLog);
        self::assertStringContainsString('GetLogTail", ["8000"]', $session);
        self::assertStringNotContainsString('["14000"]', $bugLog);
        self::assertStringNotContainsString('["14000"]', $session);

        self::assertStringContainsString('+_rawEv', $timeline);
        self::assertStringContainsString('_startIdx', $timeline);
        self::assertStringContainsString('_ev select [_startIdx, 4]', $timeline);
        self::assertStringNotContainsString('_ev select [(count _ev) - 4, 4]', $timeline);
        self::assertStringNotContainsString('0 max ((count _ev) - 4)', $timeline);

        self::assertStringContainsString('COMSPEC_MarkerSync_Lock', $xeh);
        self::assertStringContainsString('+allMapMarkers', $xeh);
        self::assertStringContainsString('_copyColl', $bridge);
        self::assertStringContainsString('COMSPEC_CtabMarkerSync_Lock', $bridge);
        self::assertStringContainsString('COMSPEC_BftRelabel_Lock', $bft);
        self::assertStringContainsString('} forEach cTabBFTmembers;', $bft);
        self::assertStringContainsString('} forEach (+allMapMarkers);', $bft);

        self::assertStringContainsString('_path + [_actionId]', $ace);
        self::assertStringContainsString('_mainPath + [_x]', $ace);
        self::assertStringContainsString('_mapLegacyPath + [_x]', $ace);
        self::assertStringNotContainsString('["CAManBase", 1, _path, _actionId]', $ace);
        self::assertStringNotContainsString('["CAManBase", 1, _mainPath, _x]', $ace);

        self::assertStringContainsString('1.0.137', $cfg);
        self::assertStringContainsString('2.0.45', $ext);

        self::assertStringContainsString('COMSPEC_StatusUpdating', $status);
        self::assertStringNotContainsString('fnc_refreshLinkState', $status);
        self::assertStringContainsString('COMSPEC_LinkStateRefreshing', $refresh);
        self::assertStringContainsString('COMSPEC_StatusBadgesUpdating', $badges);
        self::assertStringContainsString('COMSPEC_StatusBadgesUpdating", false', $badges);
        self::assertStringNotContainsString('fnc_refreshLinkState', $tx);
        self::assertStringContainsString('count _windowSent) > 100', $sent);
        self::assertStringNotContainsString('ctrlParent _fx isNotEqualTo', $overlay);

        self::assertStringContainsString('taille de tableau négative', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('callExtension', $bug);
    }
}
