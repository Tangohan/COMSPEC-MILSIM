<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakTerrainRgb;
use App\Services\Tactical\AtakTheaterProjection;
use PHPUnit\Framework\TestCase;

final class AtakTerrainRgbTest extends TestCase
{
    public function testTerrainRgbRoundTripKeepsMeters(): void
    {
        foreach ([0.0, 12.4, 118.0, -3.0, 512.7] as $z) {
            $rgb = AtakTerrainRgb::encodeMeters($z);
            $back = AtakTerrainRgb::decodeMeters($rgb['r'], $rgb['g'], $rgb['b']);
            self::assertEqualsWithDelta($z, $back, 0.06);
        }
    }

    public function testTheaterProjectionKeepsMeterScaleNearEquator(): void
    {
        [$lng, $lat] = AtakTheaterProjection::worldToLngLat(1000, 500, 0, 0);
        $world = AtakTheaterProjection::lngLatToWorld($lng, $lat, 0, 0);
        self::assertEqualsWithDelta(1000, $world['x'], 0.01);
        self::assertEqualsWithDelta(500, $world['y'], 0.01);
        $oneMeter = AtakTheaterProjection::worldToLngLat(1, 0);
        self::assertEqualsWithDelta(1 / AtakTheaterProjection::METERS_PER_DEGREE, $oneMeter[0], 1e-12);
    }
}
