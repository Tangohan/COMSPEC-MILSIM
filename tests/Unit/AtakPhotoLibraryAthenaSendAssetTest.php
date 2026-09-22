<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPhotoLibraryAthenaSendAssetTest extends TestCase
{
    public function testPhotoLibraryExposesTransferButtons(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena';
        $install = (string) file_get_contents($root . '/functions/fn_athena_installPhotoLibraryAthena.sqf');
        $send = (string) file_get_contents($root . '/functions/fn_athena_sendLibraryPhoto.sqf');
        $remove = (string) file_get_contents($root . '/functions/fn_athena_removeIcemanPhoto.sqf');
        $cfg = (string) file_get_contents($root . '/config.cpp');
        $poll = (string) file_get_contents($root . '/functions/fn_athena_pollIcemanPhotos.sqf');
        $bridge = (string) file_get_contents($root . '/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $note = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/bugs/2026-09-19-photo-library-transferer.md');

        self::assertStringContainsString('TRANSF�RER', $install);
        self::assertStringContainsString('TOUT TRANSF�RER', $install);
        self::assertStringContainsString('ctrlCreate', $install);
        self::assertStringContainsString('RscButton', $install);
        self::assertStringContainsString('CBA_fnc_addPerFrameHandler', $install);
        self::assertStringContainsString('athena_sendLibraryPhoto', $install);
        self::assertStringContainsString('athena_bridgeIcemanPhoto', $send);
        self::assertStringContainsString('true] call comspec_overwatch_atak_athena_fnc_athena_bridgeIcemanPhoto', $send);
        self::assertStringContainsString('athena_removeIcemanPhoto', $send);
        self::assertStringContainsString('DeleteLocalFile', $remove);
        self::assertStringContainsString('deleteFile _p', $remove);
        self::assertStringContainsString('class athena_sendLibraryPhoto {}', $cfg);
        self::assertStringContainsString('class athena_removeIcemanPhoto {}', $cfg);
        self::assertStringContainsString('versionStr = "1.0.165"', $cfg);
        self::assertStringContainsString('listLocalScreenshots', $send);
        self::assertStringContainsString('Aucune vue', $send);
        $ext = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('ATAK_PhotoLibrary', $ext);
        self::assertStringContainsString('EnumerateIcemanPhotoLibraryDirs', $ext);
        self::assertStringContainsString('2.0.51', $ext);
        self::assertStringContainsString('_started = _started + 1', $poll);
        self::assertStringContainsString('rememberLocalPhoto', $bridge);
        self::assertTrue(
            strpos($bridge, 'rememberLocalPhoto') < strpos($bridge, 'COMSPEC_AthenaReady')
        );
        self::assertStringContainsString('Transf�rer', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }
}
