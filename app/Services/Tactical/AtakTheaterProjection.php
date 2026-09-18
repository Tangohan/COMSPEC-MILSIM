<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Théâtre Arma (mètres) → emprise locale à l’échelle WGS84 pour MapLibre.
 * 1 m ≈ 1 m près de l’équateur. Ne pas inventer une seconde formule côté JS.
 */
final class AtakTheaterProjection
{
    public const METERS_PER_DEGREE = 111319.49079327358;

    /**
     * @return array{0: float, 1: float} lng, lat
     */
    public static function worldToLngLat(float $x, float $y, float $offsetX = 0.0, float $offsetY = 0.0): array
    {
        return [
            ($x + $offsetX) / self::METERS_PER_DEGREE,
            ($y + $offsetY) / self::METERS_PER_DEGREE,
        ];
    }

    /**
     * @return array{x: float, y: float}
     */
    public static function lngLatToWorld(float $lng, float $lat, float $offsetX = 0.0, float $offsetY = 0.0): array
    {
        return [
            'x' => $lng * self::METERS_PER_DEGREE - $offsetX,
            'y' => $lat * self::METERS_PER_DEGREE - $offsetY,
        ];
    }

    /**
     * Emprise Web Mercator d’une tuile XYZ (lng/lat).
     *
     * @return array{west: float, south: float, east: float, north: float}
     */
    public static function mercatorTileLngLat(int $z, int $x, int $y): array
    {
        $z = max(0, min(22, $z));
        $n = 2 ** $z;
        $x = $x % $n;
        if ($x < 0) {
            $x += $n;
        }
        $west = $x / $n * 360.0 - 180.0;
        $east = ($x + 1) / $n * 360.0 - 180.0;

        return [
            'west' => $west,
            'south' => self::mercatorYToLat(($y + 1) / $n),
            'east' => $east,
            'north' => self::mercatorYToLat($y / $n),
        ];
    }

    /**
     * @return array{minX: float, minY: float, maxX: float, maxY: float}
     */
    public static function mercatorTileWorld(int $z, int $x, int $y, float $offsetX = 0.0, float $offsetY = 0.0): array
    {
        $ll = self::mercatorTileLngLat($z, $x, $y);
        $sw = self::lngLatToWorld($ll['west'], $ll['south'], $offsetX, $offsetY);
        $ne = self::lngLatToWorld($ll['east'], $ll['north'], $offsetX, $offsetY);

        return [
            'minX' => min($sw['x'], $ne['x']),
            'minY' => min($sw['y'], $ne['y']),
            'maxX' => max($sw['x'], $ne['x']),
            'maxY' => max($sw['y'], $ne['y']),
        ];
    }

    public static function mercatorYToLat(float $y): float
    {
        $y = min(1.0, max(0.0, $y));

        return rad2deg(atan(sinh(M_PI * (1 - 2 * $y))));
    }
}
