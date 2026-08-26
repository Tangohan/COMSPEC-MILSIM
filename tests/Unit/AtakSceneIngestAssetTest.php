<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakSceneIngestAssetTest extends TestCase
{
    public function testGameCollectsBuildingsAndForestsForAthena(): void
    {
        $sqf = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleScene.sqf');
        self::assertStringContainsString('nearestTerrainObjects', $sqf);
        self::assertStringContainsString('"HOUSE"', $sqf);
        self::assertStringContainsString('"TREE"', $sqf);
        self::assertStringContainsString('Scene.Ingest', $sqf);
        self::assertStringContainsString('visibleMap', $sqf);
        self::assertStringContainsString('curatorCamera', $sqf);
    }

    public function testExtensionQueuesSceneIngest(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('Scene.Ingest', $cs);
        self::assertStringContainsString('/api/atak/scene/ingest', $cs);
        self::assertStringContainsString('HandleSceneIngest', $cs);
    }

    public function testConnectRegistersSampleScene(): void
    {
        $cfg = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        self::assertStringContainsString('class sampleScene {};', $cfg);
        self::assertStringContainsString('1.4.75', $cfg);
    }
}
