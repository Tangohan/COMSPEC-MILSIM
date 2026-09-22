<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchUplinkCrashAssetTest extends TestCase
{
    public function testOwnedMarkersAreMutedAndEnabledStopsPosition(): void
    {
        $root = dirname(__DIR__, 2);
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf'
        );
        $loops = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'
        );
        $shapes = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollMapShapes.sqf'
        );
        $receive = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveMapShape.sqf'
        );
        $pos = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf'
        );
        $ace = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf'
        );
        $chat = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf'
        );
        $cfgA = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $cfgC = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-17-overwatch-echo-reperes.md'
        );

        self::assertStringContainsString('COMSPEC_fnc_isOwnedMapMarker', $post);
        self::assertStringContainsString('COMSPEC_MarkerEhMuted', $post);
        self::assertStringNotContainsString('[_marker, false, true]', $post);
        self::assertStringNotContainsString('athena_bridgeCtabMarkers', $post);

        self::assertStringContainsString('comspec_overwatch_enabled', $loops);
        self::assertStringContainsString('COMSPEC_fnc_isOwnedMapMarker', $loops);

        self::assertStringContainsString('count _raw) >= 7990', $shapes);
        self::assertStringContainsString('_prefixLen', $shapes);
        self::assertStringNotContainsString('select [14, count _markerName - 14]', $shapes);
        self::assertStringContainsString('(count _flat) % 2) == 1', $receive);
        self::assertStringContainsString('COMSPEC_MarkerEhMuted', $receive);

        self::assertStringContainsString('comspec_overwatch_enabled', $pos);
        self::assertStringContainsString('COMSPEC_ACEClassTreeVer', $ace);
        self::assertStringContainsString('cTab_Android_dlg', $chat);

        self::assertStringContainsString('1.0.165', $cfgA);
        self::assertStringContainsString('1.6.9', $cfgC);

        self::assertStringContainsString('�cho des rep�res', strtolower($bug));
        self::assertStringContainsString('corrig�', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
        self::assertStringNotContainsString('callExtension', $bug);
    }
}
