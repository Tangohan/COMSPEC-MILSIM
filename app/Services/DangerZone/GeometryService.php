<?php

declare(strict_types=1);

namespace App\Services\DangerZone;

class GeometryService
{
    public function pointInCircle(float $px, float $py, array $center, float $radius): bool
    {
        $cx = (float) ($center[0] ?? 0);
        $cy = (float) ($center[1] ?? 0);
        $distSq = ($px - $cx) ** 2 + ($py - $cy) ** 2;
        return $distSq <= $radius * $radius;
    }

    public function distanceToCircleCenter(float $px, float $py, array $center): float
    {
        $cx = (float) ($center[0] ?? 0);
        $cy = (float) ($center[1] ?? 0);
        return (float) sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);
    }

    /**
     * Point-in-polygon (ray casting). Points as list of [x, y].
     */
    public function pointInPolygon(float $px, float $py, array $points): bool
    {
        $n = count($points);
        if ($n < 3) {
            return false;
        }
        $inside = false;
        $j = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $xi = (float) ($points[$i][0] ?? 0);
            $yi = (float) ($points[$i][1] ?? 0);
            $xj = (float) ($points[$j][0] ?? 0);
            $yj = (float) ($points[$j][1] ?? 0);
            if ((($yi > $py) !== ($yj > $py)) && ($px < ($xj - $xi) * ($py - $yi) / ($yj - $yi + 1e-10) + $xi)) {
                $inside = !$inside;
            }
            $j = $i;
        }
        return $inside;
    }

    /**
     * Check if point (px, py) is inside geometry. geometry is decoded geometry_json (circle with center/radius or polygon with points).
     */
    public function pointInGeometry(float $px, float $py, string $geometryType, array $geometry): bool
    {
        if ($geometryType === 'CIRCLE') {
            $center = $geometry['center'] ?? [0, 0];
            $radius = (float) ($geometry['radius'] ?? 0);
            return $this->pointInCircle($px, $py, $center, $radius);
        }
        if ($geometryType === 'POLYGON' || $geometryType === 'polygon') {
            $points = $geometry['points'] ?? [];
            return $this->pointInPolygon($px, $py, $points);
        }
        return false;
    }
}
