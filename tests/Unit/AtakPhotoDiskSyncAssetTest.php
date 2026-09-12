<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPhotoDiskSyncAssetTest extends TestCase
{
    public function testWatcherRoutesThroughQuickPictureBridge(): void
    {
        $root = dirname(__DIR__, 2);
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $cb = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        $pos = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $note = (string) file_get_contents($root . '/docs/bugs/2026-09-12-photo-sync-sidecar-ne-remonte-pas.md');

        self::assertStringContainsString('InvokeCallback("PhotoDiskSync"', $dll);
        self::assertStringContainsString('IsPhotoDedupHot', $dll);
        self::assertStringContainsString('leaf|', $dll);
        self::assertStringNotContainsString('Photo ATAK (sidecar)', $dll);
        self::assertMatchesRegularExpression('/ExtensionVersion = "2\\.0\\.\\d+"/', $dll);

        self::assertStringContainsString('case "PhotoDiskSync":', $cb);
        self::assertStringContainsString('athena_bridgeIcemanPhoto', $cb);
        self::assertStringContainsString('COMSPEC_Athena_PhotoDiskSeen', $cb);

        self::assertStringContainsString('_grid = mapGridPosition _unit', $pos);
        self::assertStringContainsString('_modVersion,', $pos);
        self::assertStringContainsString('_grid', $pos);

        self::assertMatchesRegularExpression('/versionStr = "1\\.5\\.\\d+"/', $cfg);
        self::assertStringContainsString('sidecar', $note);
        self::assertStringContainsString('Quick Picture', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }
}
