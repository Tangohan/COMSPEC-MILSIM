<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerrainHillshadeOverlayAssetTest extends TestCase
{
    public function testHillshadeGenerationDoesNotCrashTheMap(): void
    {
        $root = dirname(__DIR__, 2);
        $carto = (string) file_get_contents($root . '/app/Services/Tactical/AtakTerrainCartography.php');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakTerrainApiController.php');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-terrain.js');
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');

        self::assertStringContainsString('MAX_RASTER_EDGE = 512', $carto);
        self::assertStringContainsString('catch (Throwable)', $carto);
        self::assertStringContainsString('@imagecreatetruecolor($outW, $outH)', $carto);

        self::assertStringContainsString('catch (Throwable)', $api);
        self::assertStringContainsString('setBodyStream', $api);
        self::assertStringContainsString('Relief du théâtre non encore relevé.', $api);

        self::assertStringContainsString('function bindImageOverlay(layer, onFail)', $js);
        self::assertStringContainsString('function markImgEager(img)', $js);
        self::assertStringContainsString("img.setAttribute('loading', 'eager')", $js);

        self::assertStringContainsString("tileLayer.on('tileloadstart'", $map);
        self::assertStringContainsString("img.setAttribute('loading', 'eager')", $map);
    }
}
