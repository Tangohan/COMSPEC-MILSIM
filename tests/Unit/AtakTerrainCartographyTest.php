<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakTerrainIsolines;
use App\Services\Tactical\AtakTerrainMath;
use PHPUnit\Framework\TestCase;

final class AtakTerrainCartographyTest extends TestCase
{
    public function testHornShadeIsNeutralOnFlatGround(): void
    {
        $blob = AtakTerrainMath::packInt16Le(array_fill(0, 9, 120));
        $hs = AtakTerrainMath::hornShade($blob, 3, 1, 1, 50.0, 315.0);
        self::assertNotNull($hs);
        self::assertEqualsWithDelta(0.0, $hs['slope_deg'], 0.01);
        self::assertGreaterThan(0.5, $hs['shade']);
        self::assertLessThan(0.9, $hs['shade']);
    }

    public function testSlopeClassLabels(): void
    {
        self::assertSame('praticable', AtakTerrainMath::slopeClass(3));
        self::assertSame('moderee', AtakTerrainMath::slopeClass(10));
        self::assertSame('forte', AtakTerrainMath::slopeClass(20));
        self::assertSame('tres_forte', AtakTerrainMath::slopeClass(40));
        self::assertSame('critique', AtakTerrainMath::slopeClass(50));
    }

    public function testIsolinesCrossAStepAtTenMeters(): void
    {
        $vals = [
            0, 0, 0,
            0, 20, 20,
            0, 20, 20,
        ];
        $grid = [
            'heights' => AtakTerrainMath::packInt16Le($vals),
            'cols' => 3,
            'rows' => 3,
            'cell_m' => 50,
            'origin_x' => 0,
            'origin_y' => 0,
            'min_z' => 0,
            'max_z' => 20,
        ];
        $geo = AtakTerrainIsolines::geoJson($grid, 10, 50);
        self::assertSame('FeatureCollection', $geo['type']);
        self::assertNotSame([], $geo['features']);
        $elevations = [];
        foreach ($geo['features'] as $feat) {
            $elevations[] = $feat['properties']['elevation'] ?? null;
            self::assertSame('LineString', $feat['geometry']['type'] ?? '');
            self::assertGreaterThanOrEqual(2, count($feat['geometry']['coordinates'] ?? []));
        }
        self::assertContains(10, $elevations);
    }

    public function testFilledBBoxIgnoresMissingCells(): void
    {
        $blob = AtakTerrainMath::emptyBlob(16);
        $p40 = AtakTerrainMath::packInt16Le([40]);
        $p42 = AtakTerrainMath::packInt16Le([42]);
        $blob = substr($blob, 0, 10) . $p40 . $p42 . substr($blob, 14);
        $box = AtakTerrainMath::filledBBox($blob, 4, 4);
        self::assertNotNull($box);
        self::assertSame(2, $box['filled']);
        self::assertSame(1, $box['min_c']);
        self::assertSame(2, $box['max_c']);
    }
}
