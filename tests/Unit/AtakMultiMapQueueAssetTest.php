<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMultiMapQueueAssetTest extends TestCase
{
    public function testDllUsesCurrentMapIdAndPersistsQueue(): void
    {
        $root = dirname(__DIR__, 2);
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $connect = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_connect.sqf');
        $pre = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('private static int _mapId = 1;', $dll);
        self::assertStringContainsString('function == "SetMapId"', $dll);
        self::assertStringContainsString('CurrentMapId()', $dll);
        self::assertStringNotContainsString('/api/atak/markers?mapId=1"', $dll);
        self::assertStringContainsString('PersistQueueToDisk', $dll);
        self::assertStringContainsString('queue.jsonl', $dll);
        self::assertStringContainsString('LoadQueueFromDisk()', $dll);
        self::assertStringContainsString('str _mapId', $connect);
        self::assertStringContainsString('comspec_overwatch_map_id', $pre);
        self::assertStringContainsString('versionStr = "1.6.9"', $cfgC);
        self::assertMatchesRegularExpression('/ExtensionVersion = "2\\.0\\.50"/', $dll);
    }
}
