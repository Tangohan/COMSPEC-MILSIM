<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakTerrainIsolines;
use PHPUnit\Framework\TestCase;

/**
 * /api/atak/terrain/contours : « Allowed memory size of 268435456 bytes exhausted » (14/11, 7 incidents). Les isolignes sont
 * écrites ligne à ligne dans le fichier, le fichier est servi sans être relu en mémoire, et une génération tombée n'est pas
 * relancée par chaque requête.
 */
final class AtakTerrainContoursMemoryTest extends TestCase
{
    private static function grid(): array
    {
        $cols = 80;
        $rows = 60;
        $blob = '';
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $blob .= pack('s', (int) round(200 + 150 * sin($c / 9) * cos($r / 11)));
            }
        }

        return ['heights' => $blob, 'cols' => $cols, 'rows' => $rows, 'cell_m' => 50, 'origin_x' => 10, 'origin_y' => 20, 'min_z' => 0, 'max_z' => 420];
    }

    public function testWrittenFileHoldsTheSameLinesAsTheArray(): void
    {
        $path = sys_get_temp_dir() . '/athena_contours_' . getmypid() . '.json';
        @unlink($path);
        self::assertTrue(AtakTerrainIsolines::writeGeoJson(self::grid(), $path, 10, 50));
        $file = json_decode((string) file_get_contents($path), true);
        @unlink($path);
        $array = AtakTerrainIsolines::geoJson(self::grid(), 10, 50);
        self::assertSame('FeatureCollection', $file['type']);
        self::assertTrue(count($file['features']) > 10);
        self::assertSame(count($array['features']), count($file['features']));
        self::assertTrue($array['features'][0]['geometry']['coordinates'][0] == $file['features'][0]['geometry']['coordinates'][0]);
        self::assertSame([], glob($path . '.*.tmp') ?: [], 'Aucun fichier temporaire ne reste.');
    }

    public function testEmptyGridWritesAnEmptyCollection(): void
    {
        $path = sys_get_temp_dir() . '/athena_contours_empty_' . getmypid() . '.json';
        self::assertTrue(AtakTerrainIsolines::writeGeoJson(['heights' => '', 'cols' => 0, 'rows' => 0], $path));
        self::assertSame('{"type":"FeatureCollection","features":[]}', file_get_contents($path));
        @unlink($path);
    }

    public function testEndpointServesTheFileAndAFailedGenerationIsNotRetriedByEveryRequest(): void
    {
        $root = dirname(__DIR__, 2);
        $c = str_replace("\r\n", "\n", (string) file_get_contents($root . '/app/Controllers/Api/AtakTerrainApiController.php'));
        self::assertStringContainsString('->setBodyStream(static function () use ($path): void {', $c);
        self::assertStringNotContainsString('$this->cartography->contours($tenantId, $mapId)', $c);
        $s = str_replace("\r\n", "\n", (string) file_get_contents($root . '/app/Services/Tactical/AtakTerrainCartography.php'));
        self::assertStringContainsString("'/contours.generating'", $s);
        self::assertStringContainsString('(time() - (int) @filemtime($marker)) < 1800', $s);
        self::assertStringContainsString('AtakTerrainIsolines::writeGeoJson($grid, $paths[$name], 10, 50);', $s);
        self::assertStringNotContainsString("json_encode(\$geo, JSON_UNESCAPED_UNICODE)", $s);
    }
}
