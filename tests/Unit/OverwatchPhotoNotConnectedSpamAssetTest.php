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
        self::assertStringContainsString('return "ERR|not_connected";', $cs);
        $enqueue = strstr($cs, 'private static string EnqueueReconImage');
        self::assertNotFalse($enqueue);
        $head = substr((string) $enqueue, 0, 600);
        self::assertStringContainsString('HasPortalAuth()', $head);
        self::assertStringNotContainsString('_apiKey.Length == 0', $head);
    }

    public function testCaptureDoesNotReshootWhenNotConnected(): void
    {
        $sqf = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf'
        );
        self::assertStringContainsString('comspec_overwatch_connect_fnc_isReady', $sqf);
        self::assertStringContainsString('COMSPEC_ReconNotReadyHintAt', $sqf);
        self::assertStringContainsString('Session Athena pas encore prête', $sqf);
        self::assertStringNotContainsString(
            '[_path, _caption, _device, _feedId, false, false, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $sqf
        );
        self::assertStringContainsString(
            '[_path, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $sqf
        );
        self::assertStringContainsString(
            '[_png, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage',
            $sqf
        );
    }
}
