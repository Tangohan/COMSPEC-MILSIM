<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMapTileZoomAssetTest extends TestCase
{
    public function testAtakMapExposesNativeZoomParentFillAndRepair(): void
    {
        $root = dirname(__DIR__, 2);
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $altis = (string) file_get_contents($root . '/public/assets/js/maps/altis.js');
        $mobile = (string) file_get_contents($root . '/public/assets/js/atak-mobile/atak-mobile.js');
        $view = (string) file_get_contents($root . '/views/atak.php');

        self::assertStringContainsString('maxNativeZoom', $map);
        self::assertStringContainsString('fillTileFromParent', $map);
        self::assertStringContainsString('repairBaseTiles', $map);
        self::assertStringContainsString('maxNativeZoom: 6', $altis);
        self::assertStringContainsString('maxZoom: 8', $altis);
        self::assertStringContainsString('maxNativeZoom', $mobile);
        self::assertStringContainsString('Réparer le fond', $view);
        self::assertStringContainsString('atak-repair-base-tiles', $view);
    }
}
