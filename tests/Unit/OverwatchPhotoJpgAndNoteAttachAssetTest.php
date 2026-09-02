<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchPhotoJpgAndNoteAttachAssetTest extends TestCase
{
    public function testJpgBridgeTakesArmaPngInsteadOfDeadPath(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $bridge = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $capture = (string) file_get_contents($root . '/connect/functions/fn_captureReconImage.sqf');
        $folder = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_showPhotoFolder.sqf');
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $note = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/bugs/2026-09-01-photo-jpg-srcdir-missing.md');

        self::assertStringContainsString('.jpg', $bridge);
        self::assertStringContainsString(
            '[_path, _caption, _device, _feedId, false, true, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $bridge
        );
        self::assertStringNotContainsString(
            '[_path, _caption, _device, _feedId, true, false, true] call comspec_overwatch_connect_fnc_captureReconImage',
            $bridge
        );
        self::assertStringContainsString('_fnc_isJpegPath', $capture);
        self::assertStringContainsString('StageCapture', $capture);
        self::assertStringContainsString('GetPhotoSaveDir', $folder);
        self::assertStringContainsString('Arma 3 - COMSPEC\\Captures', $folder);
        self::assertStringContainsString('StageCapture', $cs);
        self::assertStringContainsString('EnumeratePhotoLookupDirs', $cs);
        self::assertStringContainsString('IsLocalArma3Root', $cs);
        self::assertStringContainsString('GetPhotoSaveDir', $cs);
        self::assertStringNotContainsString(
            'if (!_ok && {_path isNotEqualTo _png}) then {',
            $capture
        );
        self::assertStringContainsString('srcdir_missing', $note);
        self::assertStringContainsString('COMSPEC\\Captures', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }

    public function testNoteAttachmentWaitsAndSendsCompleteFile(): void
    {
        $root = dirname(__DIR__, 2);
        $submit = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteSubmit.sqf');
        $cs = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $note = (string) file_get_contents($root . '/docs/bugs/2026-09-01-fiche-piece-jointe-400.md');

        self::assertStringContainsString('uiSleep 2.2;', $submit);
        self::assertStringContainsString('UploadSseNoteAttachment', $submit);
        self::assertStringContainsString('StageCapture', $submit);
        self::assertStringContainsString('ReadStableImageBytes', $cs);
        self::assertStringContainsString('new ByteArrayContent(bytes)', $cs);
        self::assertStringContainsString('multipart.Add(fileContent, "piece", fileName)', $cs);
        self::assertStringContainsString('UploadKind = "sse_note"', $cs);
        self::assertStringContainsString('ProcessSseNoteAttachmentUploadAsync', $cs);
        self::assertStringContainsString('IsSseNoteCaptureName', $cs);
        self::assertStringContainsString('newestFallback: null', $cs);
        self::assertStringContainsString('AddSeconds(-180)', $cs);
        self::assertStringNotContainsString('if (resolved != null || attempt >= 8)', $cs);
        self::assertStringContainsString('pièce jointe', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }

    public function testNoteAttachmentUsesCapturesPipelineNotGameThreadRead(): void
    {
        $root = dirname(__DIR__, 2);
        $cs = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $callback = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        $face = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseCaptureFacePhoto.sqf');

        self::assertStringContainsString('EnsurePhotoWorker()', $cs);
        self::assertStringContainsString('MirrorCapture(resolved)', $cs);
        self::assertStringContainsString('COMSPEC_Fiche_', $cs);
        self::assertStringContainsString('case "SseNoteAttachment":', $callback);
        self::assertStringContainsString('StageCapture', $face);
        self::assertStringNotContainsString('FindNewestScreenshot(TimeSpan.FromSeconds(90))', $cs);
    }
}
