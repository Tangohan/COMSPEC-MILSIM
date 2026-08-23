<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Lecture d’une grille d’altitudes métriques Arma (int16, mètres, little-endian).
 */
final class AtakTerrainMath
{
    public const MISSING = -32768;

    /**
     * @param array<string, mixed> $grid
     */
    public static function heightAt(array $grid, float $x, float $y): ?float
    {
        $blob = $grid['heights'] ?? null;
        if (!is_string($blob) || $blob === '') {
            return null;
        }
        $cols = (int) ($grid['cols'] ?? 0);
        $rows = (int) ($grid['rows'] ?? 0);
        $cell = (float) ($grid['cell_m'] ?? 50);
        $ox = (float) ($grid['origin_x'] ?? 0);
        $oy = (float) ($grid['origin_y'] ?? 0);
        if ($cols < 2 || $rows < 2 || $cell <= 0) {
            return null;
        }
        $fx = ($x - $ox) / $cell;
        $fy = ($y - $oy) / $cell;
        if ($fx < 0 || $fy < 0 || $fx > $cols - 1 || $fy > $rows - 1) {
            return null;
        }
        $x0 = (int) floor($fx);
        $y0 = (int) floor($fy);
        $x1 = min($cols - 1, $x0 + 1);
        $y1 = min($rows - 1, $y0 + 1);
        $tx = $fx - $x0;
        $ty = $fy - $y0;
        $z00 = self::cell($blob, $cols, $x0, $y0);
        $z10 = self::cell($blob, $cols, $x1, $y0);
        $z01 = self::cell($blob, $cols, $x0, $y1);
        $z11 = self::cell($blob, $cols, $x1, $y1);
        if ($z00 === null || $z10 === null || $z01 === null || $z11 === null) {
            return $z00 ?? $z10 ?? $z01 ?? $z11;
        }

        return (1 - $tx) * (1 - $ty) * $z00
            + $tx * (1 - $ty) * $z10
            + (1 - $tx) * $ty * $z01
            + $tx * $ty * $z11;
    }

    /**
     * Profil unité → destination : dénivelé, pentes, ETA corrigée.
     *
     * @param array<string, mixed> $grid
     * @return array<string, mixed>|null
     */
    public static function pathAnalysis(array $grid, float $x0, float $y0, float $x1, float $y1, ?float $etaKinematicSec): ?array
    {
        $dist = hypot($x1 - $x0, $y1 - $y0);
        if ($dist < 5) {
            return null;
        }
        $cell = max(25.0, (float) ($grid['cell_m'] ?? 50));
        $steps = (int) max(4, min(240, ceil($dist / $cell)));
        $prevZ = null;
        $climb = 0.0;
        $descent = 0.0;
        $maxSlope = 0.0;
        $slopeSum = 0.0;
        $slopeN = 0;
        $samples = 0;
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $x = $x0 + ($x1 - $x0) * $t;
            $y = $y0 + ($y1 - $y0) * $t;
            $z = self::heightAt($grid, $x, $y);
            if ($z === null) {
                continue;
            }
            $samples++;
            if ($prevZ !== null) {
                $dz = $z - $prevZ;
                if ($dz > 0) {
                    $climb += $dz;
                } else {
                    $descent += -$dz;
                }
                $seg = $dist / $steps;
                if ($seg > 0.5) {
                    $slope = abs($dz) / $seg;
                    $maxSlope = max($maxSlope, $slope);
                    $slopeSum += $slope;
                    $slopeN++;
                }
            }
            $prevZ = $z;
        }
        if ($samples < 3) {
            return null;
        }
        $meanSlope = $slopeN > 0 ? $slopeSum / $slopeN : 0.0;
        $meanPct = $meanSlope * 100.0;
        $maxPct = $maxSlope * 100.0;
        $factor = 1.0 + min(1.8, $meanSlope * 6.0) + min(0.7, ($climb / max(1.0, $dist)) * 2.5);
        $factor = max(1.0, min(3.5, $factor));
        $etaTerrain = ($etaKinematicSec !== null && $etaKinematicSec > 0)
            ? (int) round($etaKinematicSec * $factor)
            : null;
        $zStart = self::heightAt($grid, $x0, $y0);
        $zEnd = self::heightAt($grid, $x1, $y1);
        $confidence = $samples / ($steps + 1);

        return [
            'distance_m' => round($dist, 1),
            'climb_m' => round($climb, 1),
            'descent_m' => round($descent, 1),
            'delta_m' => ($zStart !== null && $zEnd !== null) ? round($zEnd - $zStart, 1) : null,
            'mean_slope_pct' => round($meanPct, 1),
            'max_slope_pct' => round($maxPct, 1),
            'eta_kinematic_s' => $etaKinematicSec !== null ? (int) round($etaKinematicSec) : null,
            'eta_terrain_s' => $etaTerrain,
            'factor' => round($factor, 3),
            'confidence' => round(max(0.15, min(0.95, $confidence * 0.9)), 2),
            'samples' => $samples,
        ];
    }

    public static function packInt16Le(array $values): string
    {
        $out = '';
        foreach ($values as $v) {
            $n = (int) round((float) $v);
            if ($n < -32767) {
                $n = -32767;
            }
            if ($n > 32767) {
                $n = 32767;
            }
            $u = $n < 0 ? $n + 65536 : $n;
            $out .= pack('v', $u);
        }

        return $out;
    }

    public static function unpackInt16Le(string $blob, int $index): ?int
    {
        $off = $index * 2;
        if ($off < 0 || $off + 1 >= strlen($blob)) {
            return null;
        }
        $u = unpack('v', substr($blob, $off, 2));
        if (!is_array($u) || !isset($u[1])) {
            return null;
        }
        $n = (int) $u[1];
        $s = $n >= 32768 ? $n - 65536 : $n;
        if ($s === self::MISSING) {
            return null;
        }

        return $s;
    }

    public static function emptyBlob(int $cells): string
    {
        $cells = max(0, $cells);
        $u = self::MISSING < 0 ? self::MISSING + 65536 : self::MISSING;

        return str_repeat(pack('v', $u), $cells);
    }

    private static function cell(string $blob, int $cols, int $c, int $r): ?float
    {
        $v = self::unpackInt16Le($blob, $r * $cols + $c);

        return $v === null ? null : (float) $v;
    }
}
