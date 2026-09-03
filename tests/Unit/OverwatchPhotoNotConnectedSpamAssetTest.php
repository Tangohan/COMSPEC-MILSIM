<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchPhotoNotConnectedSpamAssetTest extends TestCase
{
    public function testNotifyNewPhotoAcceptsAthenaSessionWithoutApiKey(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('private static bool HasPortalAuth()', $cs);
        self::assertStringContainsString('_apiKey.Length > 0 || _gameAccessToken.Length > 0', $cs);
        self::assertStringContainsString('if (string.IsNullOrEmpty(_baseUrl) || !HasPortalAuth())', $cs);
        self::assertStringNotContainsString(
            'if (string.IsNullOrEmpty(_baseUrl) || _apiKey.Length == 0)
            return "ERR|not_connected";',
            $cs
        );
        self::assertStringContainsString('return "ERR|not_connected";', $cs);
        $enqueue = strstr($cs, 'private static string EnqueueReconImage');
        self::assertNotFalse($enqueue);
        $head = substr((string) $enqueue, 0, 600);
        self::assertStringContainsString('HasPortalAuth()', $head);
        self::assertStringNotContainsString('_apiKey.Length == 0', $head);
    }

    public function testReconUploadSendsBufferedMultipartWithContentLength(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('new ByteArrayContent(imageBytes)', $cs);
        self::assertStringContainsString('fileContent.Headers.ContentLength = imageBytes.Length', $cs);
        self::assertStringContainsString('multipart.Add(fileContent, "image", fileName)', $cs);
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('[atak/recon-images] missing_image', $ctrl);
    }

    public function testCaptureDoesNotRetryScreenshotsWhenAthenaIsDown(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons';
        $capture = (string) file_get_contents($root . '/connect/functions/fn_captureReconImage.sqf');
        $poll = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_pollIcemanPhotos.sqf');
        $bridge = (string) file_get_contents($root . '/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf');
        $post = (string) file_get_contents($root . '/atak_athena/XEH_postInitClient.sqf');
        $boot = (string) file_get_contents($root . '/connect/functions/auth/fn_applyBootstrap.sqf');

        self::assertStringContainsString('COMSPEC_AthenaReady', $capture);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_isReady', $capture);
        self::assertStringContainsString('COMSPEC_ReconNotReadyHintAt', $capture);
        self::assertStringContainsString('Session Athena pas encore prête', $capture);
        self::assertStringContainsString('_fnc_isConnErr', $capture);
        self::assertStringContainsString('if (!_ok && {[] call _fnc_isConnErr}) exitWith { false };', $capture);
        self::assertStringContainsString(
            '[_path, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $capture
        );
        self::assertStringContainsString(
            '[_png, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $capture
        );
        self::assertStringContainsString('COMSPEC_AthenaReady', $poll);
        self::assertStringContainsString('COMSPEC_HandshakeQuiet', $poll);
        self::assertStringContainsString('COMSPEC_AthenaReady', $bridge);
        self::assertStringContainsString('if (_state isNotEqualTo "ready") exitWith {};', $post);
        self::assertStringContainsString('if (!_wasReady) then {', $boot);
    }
}
