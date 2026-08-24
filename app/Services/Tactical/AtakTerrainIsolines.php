<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Isolignes (Marching Squares) sur une grille DEM Arma (x,y en mètres).
 */
final class AtakTerrainIsolines
{
    /**
     * @param array<string, mixed> $grid
     * @return array{type:string,features:list<array<string,mixed>>}
     */
    public static function geoJson(array $grid, int $minorM = 10, int $majorM = 50): array
    {
        $blob = is_string($grid['heights'] ?? null) ? (string) $grid['heights'] : '';
        $cols = (int) ($grid['cols'] ?? 0);
        $rows = (int) ($grid['rows'] ?? $grid['grid_rows'] ?? 0);
        $cell = (float) ($grid['cell_m'] ?? 50);
        $ox = (float) ($grid['origin_x'] ?? 0);
        $oy = (float) ($grid['origin_y'] ?? 0);
        $minorM = max(5, min(200, $minorM));
        $majorM = max($minorM, min(500, $majorM));
        $features = [];
        if ($blob === '' || $cols < 2 || $rows < 2 || $cell <= 0) {
            return ['type' => 'FeatureCollection', 'features' => $features];
        }
        $bbox = AtakTerrainMath::filledBBox($blob, $cols, $rows);
        if ($bbox === null) {
            return ['type' => 'FeatureCollection', 'features' => $features];
        }
        $minZ = $grid['min_z'] !== null ? (int) $grid['min_z'] : 0;
        $maxZ = $grid['max_z'] !== null ? (int) $grid['max_z'] : 0;
        $level0 = (int) (floor($minZ / $minorM) * $minorM);
        $level1 = (int) (ceil($maxZ / $minorM) * $minorM);
        if ($level1 < $level0) {
            return ['type' => 'FeatureCollection', 'features' => $features];
        }

        $c0 = max(0, $bbox['min_c'] - 1);
        $c1 = min($cols - 2, $bbox['max_c']);
        $r0 = max(0, $bbox['min_r'] - 1);
        $r1 = min($rows - 2, $bbox['max_r']);

        /** @var array<int, list<array{0:array{0:float,1:float},1:array{0:float,1:float}}>> $segs */
        $segs = [];

        for ($r = $r0; $r <= $r1; $r++) {
            for ($c = $c0; $c <= $c1; $c++) {
                $sw = AtakTerrainMath::cellZ($blob, $cols, $c, $r);
                $se = AtakTerrainMath::cellZ($blob, $cols, $c + 1, $r);
                $nw = AtakTerrainMath::cellZ($blob, $cols, $c, $r + 1);
                $ne = AtakTerrainMath::cellZ($blob, $cols, $c + 1, $r + 1);
                if ($sw === null || $se === null || $nw === null || $ne === null) {
                    continue;
                }
                $x0 = $ox + $c * $cell;
                $y0 = $oy + $r * $cell;
                $x1 = $x0 + $cell;
                $y1 = $y0 + $cell;
                $lo = (int) (floor(min($sw, $se, $nw, $ne) / $minorM) * $minorM);
                $hi = (int) (ceil(max($sw, $se, $nw, $ne) / $minorM) * $minorM);
                for ($level = $lo; $level <= $hi; $level += $minorM) {
                    if ($level < $level0 || $level > $level1) {
                        continue;
                    }
                    $bits = 0;
                    if ($sw >= $level) {
                        $bits |= 1;
                    }
                    if ($se >= $level) {
                        $bits |= 2;
                    }
                    if ($ne >= $level) {
                        $bits |= 4;
                    }
                    if ($nw >= $level) {
                        $bits |= 8;
                    }
                    if ($bits === 0 || $bits === 15) {
                        continue;
                    }
                    $bottom = self::lerp($x0, $y0, $x1, $y0, $sw, $se, (float) $level);
                    $right = self::lerp($x1, $y0, $x1, $y1, $se, $ne, (float) $level);
                    $top = self::lerp($x0, $y1, $x1, $y1, $nw, $ne, (float) $level);
                    $left = self::lerp($x0, $y0, $x0, $y1, $sw, $nw, (float) $level);
                    $pair = self::casePair($bits, $bottom, $right, $top, $left, $sw, $se, $ne, $nw, (float) $level);
                    if ($pair === []) {
                        continue;
                    }
                    if (!isset($segs[$level])) {
                        $segs[$level] = [];
                    }
                    foreach ($pair as $seg) {
                        $segs[$level][] = $seg;
                    }
                }
            }
        }

        ksort($segs, SORT_NUMERIC);
        foreach ($segs as $level => $list) {
            if ($list === []) {
                continue;
            }
            $lines = self::join($list);
            $major = $majorM > 0 && ($level % $majorM === 0);
            foreach ($lines as $coords) {
                if (count($coords) < 2) {
                    continue;
                }
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'elevation' => $level,
                        'major' => $major,
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => $coords,
                    ],
                ];
            }
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @return array{0:float,1:float}|null
     */
    private static function lerp(float $x0, float $y0, float $x1, float $y1, float $z0, float $z1, float $level): ?array
    {
        $dz = $z1 - $z0;
        if (abs($dz) < 1e-6) {
            return null;
        }
        if (($level - $z0) * ($level - $z1) > 0) {
            return null;
        }
        $t = ($level - $z0) / $dz;
        $t = max(0.0, min(1.0, $t));

        return [round($x0 + ($x1 - $x0) * $t, 2), round($y0 + ($y1 - $y0) * $t, 2)];
    }

    /**
     * @param array{0:float,1:float}|null $bottom
     * @param array{0:float,1:float}|null $right
     * @param array{0:float,1:float}|null $top
     * @param array{0:float,1:float}|null $left
     * @return list<array{0:array{0:float,1:float},1:array{0:float,1:float}}>
     */
    private static function casePair(
        int $bits,
        ?array $bottom,
        ?array $right,
        ?array $top,
        ?array $left,
        float $sw,
        float $se,
        float $ne,
        float $nw,
        float $level
    ): array {
        $out = [];
        $add = static function (?array $a, ?array $b) use (&$out): void {
            if ($a !== null && $b !== null) {
                $out[] = [$a, $b];
            }
        };
        // Ambiguïté 5 / 10 : départager par la moyenne des coins.
        $avg = ($sw + $se + $ne + $nw) / 4.0;
        switch ($bits) {
            case 1:
            case 14:
                $add($left, $bottom);
                break;
            case 2:
            case 13:
                $add($bottom, $right);
                break;
            case 3:
            case 12:
                $add($left, $right);
                break;
            case 4:
            case 11:
                $add($right, $top);
                break;
            case 6:
            case 9:
                $add($bottom, $top);
                break;
            case 7:
            case 8:
                $add($left, $top);
                break;
            case 5:
                if ($avg >= $level) {
                    $add($left, $top);
                    $add($bottom, $right);
                } else {
                    $add($left, $bottom);
                    $add($right, $top);
                }
                break;
            case 10:
                if ($avg >= $level) {
                    $add($left, $bottom);
                    $add($right, $top);
                } else {
                    $add($left, $top);
                    $add($bottom, $right);
                }
                break;
        }

        return $out;
    }

    /**
     * @param list<array{0:array{0:float,1:float},1:array{0:float,1:float}}> $segs
     * @return list<list<array{0:float,1:float}>>
     */
    private static function join(array $segs): array
    {
        $key = static function (array $p): string {
            return ((string) round($p[0], 1)) . ',' . ((string) round($p[1], 1));
        };
        $used = [];
        $n = count($segs);
        $lines = [];
        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$i])) {
                continue;
            }
            $used[$i] = true;
            $line = [$segs[$i][0], $segs[$i][1]];
            $head = $key($line[0]);
            $tail = $key($line[count($line) - 1]);
            $grew = true;
            while ($grew) {
                $grew = false;
                for ($j = 0; $j < $n; $j++) {
                    if (isset($used[$j])) {
                        continue;
                    }
                    $a = $key($segs[$j][0]);
                    $b = $key($segs[$j][1]);
                    if ($a === $tail) {
                        $line[] = $segs[$j][1];
                        $tail = $b;
                        $used[$j] = true;
                        $grew = true;
                    } elseif ($b === $tail) {
                        $line[] = $segs[$j][0];
                        $tail = $a;
                        $used[$j] = true;
                        $grew = true;
                    } elseif ($b === $head) {
                        array_unshift($line, $segs[$j][0]);
                        $head = $a;
                        $used[$j] = true;
                        $grew = true;
                    } elseif ($a === $head) {
                        array_unshift($line, $segs[$j][1]);
                        $head = $b;
                        $used[$j] = true;
                        $grew = true;
                    }
                }
            }
            $lines[] = $line;
        }

        return $lines;
    }
}
