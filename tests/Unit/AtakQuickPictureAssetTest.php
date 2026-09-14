<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakQuickPictureAssetTest extends TestCase
{
    public function testPhoneOverlayKeepsUnitControlAndRestoresAfterShot(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $fs = (string) file_get_contents($root . '/atak_athena/functions/fn_ATAK_FullScreenCamera.sqf');
        $promote = (string) file_get_contents($root . '/connect/functions/fn_promoteCaptureCam.sqf');
        $restore = (string) file_get_contents($root . '/connect/functions/fn_restoreCaptureCam.sqf');
        $get = (string) file_get_contents($root . '/connect/functions/fn_getActiveCaptureCam.sqf');
        $bridge = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $take = (string) file_get_contents($root . '/atak_athena/functions/fn_ATAK_TakePicture.sqf');
        $bceShot = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bceScreenShot.sqf');
        $post = (string) file_get_contents($root . '/atak_athena/XEH_postInitClient.sqf');
        $capture = (string) file_get_contents($root . '/connect/functions/fn_captureReconImage.sqf');
        $dll = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $note = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/bugs/2026-09-01-atak-quick-picture-bloque.md');
        $discordNote = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/bugs/2026-09-13-quick-picture-discord-coupe.md');

        self::assertStringContainsString('rttN', $fs);
        self::assertStringContainsString('switchCamera _Init_Cam', $fs);
        self::assertStringContainsString('["phone", "hcam"]', $get);
        self::assertStringContainsString('phone', $promote);
        self::assertStringContainsString('_kind isEqualTo "phone"', $restore);
        self::assertStringContainsString('switchCamera _cam', $restore);

        self::assertStringContainsString('private _skipShot = true;', $bridge);
        self::assertStringNotContainsString(
            'false, true, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $bridge
        );
        self::assertStringContainsString('athena_bceScreenShot', $take);
        self::assertStringContainsString('bce_took_screenshot', $take);
        self::assertStringContainsString('Arma 3!Workshop', $bceShot);
        self::assertStringContainsString('GetBceScreenshotDir', $bceShot);
        self::assertStringContainsString('COMSPEC_BCE_screenShotOrig', $post);
        self::assertStringContainsString('RepairBrokenWorkshopScreenshotPath', $dll);
        self::assertStringContainsString('GetBceScreenshotDir', $dll);
        self::assertStringNotContainsString('promoteCaptureCam', $take);
        self::assertStringNotContainsString(
            '["", _cap, "CTAB", "", false, false, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $take
        );
        self::assertStringContainsString('_filePath = format', $bridge);
        self::assertStringContainsString('_path = if (_base isEqualTo "") then { _name }', $post);
        self::assertStringContainsString('if (!_skipArmaShot) then {', $capture);
        self::assertStringNotContainsString('_skipArmaShot || _isJpeg', $capture);
        self::assertStringContainsString('relais Discord', $capture);
        self::assertStringContainsString('true, false, true] call comspec_overwatch_connect_fnc_captureReconImage', $bridge);
        self::assertStringContainsString('COMSPEC_OverlayCaptureRtt', $get);

        self::assertStringContainsString('AddSeconds(-180)', $dll);
        self::assertMatchesRegularExpression('/ExtensionVersion = "2\\.0\\.\\d+"/', $dll);

        self::assertStringContainsString('Quick Picture', $note);
        self::assertStringContainsString('file_not_found', $note);
        self::assertStringNotContainsString('endpoint', $note);
        self::assertStringContainsString('Discord', $discordNote);
        self::assertStringContainsString('BCE', $discordNote);
        self::assertStringContainsString('dossier', $discordNote);
        self::assertStringNotContainsString('endpoint', $discordNote);
    }
}
