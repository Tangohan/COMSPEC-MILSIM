<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Profil d’itinéraire et visée (ligne de vue) sur la grille de relief.
 * Les altitudes manquantes restent nulles : pas d’invention de sol.
 */
final class AtakTerrainSight
{
    public const GAP_MESSAGE = 'Relief pas encore relevé sur ce tronçon';

    public const VERDICT_CLEAR = 'clear';
    public const VERDICT_MASKED = 'masked';
    public const VERDICT_UNKNOWN = 'unknown';

    private const MIN_DIST_M = 5.0;
    private const MAX_VERTICES = 64;
    private const MAX_SAMPLES = 320;
    private const WIRE_SAMPLES = 180;
    private const LOS_EPSILON_M = 0.5;

    /**
     * @param array<string, mixed> $grid
     * @param list<mixed> $rawPoints
     * @return array<string, mixed>
     */
    public static function profile(array $grid, array $rawPoints): array
    {
        $points = self::normalizePoints($rawPoints);
        if (count($points) < 2) {
            return self::emptyProfile('Tracez au moins deux points sur la carte.');
        }
        $cell = max(25.0, (float) ($grid['cell_m'] ?? 50));
        $samples = self::walk($grid, $points, $cell, self::MAX_SAMPLES);
        if ($samples === []) {
            return self::emptyProfile('Trace trop courte pour un profil.');
        }

        $climb = 0.0;
        $descent = 0.0;
        $minZ = null;
        $maxZ = null;
        $known = 0;
        $prevZ = null;
        $prevD = null;
        foreach ($samples as $s) {
            $z = $s['z'];
            if ($z === null) {
                $prevZ = null;
                $prevD = $s['d'];
                continue;
            }
            $known++;
            $zf = (float) $z;
            $minZ = $minZ === null ? $zf : min($minZ, $zf);
            $maxZ = $maxZ === null ? $zf : max($maxZ, $zf);
            if ($prevZ !== null && $prevD !== null) {
                $dz = $zf - $prevZ;
                if ($dz > 0) {
                    $climb += $dz;
                } else {
                    $descent += -$dz;
                }
            }
            $prevZ = $zf;
            $prevD = $s['d'];
        }

        $dist = (float) ($samples[count($samples) - 1]['d'] ?? 0);
        $total = max(1, count($samples));
        $coverage = $known / $total;
        $gaps = $known < $total;
        $zStart = $samples[0]['z'] ?? null;
        $zEnd = $samples[count($samples) - 1]['z'] ?? null;

        return [
            'ok' => true,
            'ready' => $known >= 2,
            'mode' => 'profile',
            'distance_m' => round($dist, 1),
            'climb_m' => round($climb, 1),
            'descent_m' => round($descent, 1),
            'delta_m' => ($zStart !== null && $zEnd !== null) ? round((float) $zEnd - (float) $zStart, 1) : null,
            'min_z' => $minZ !== null ? round($minZ, 1) : null,
            'max_z' => $maxZ !== null ? round($maxZ, 1) : null,
            'coverage_pct' => (int) round(100 * $coverage),
            'cell_m' => (int) $cell,
            'samples_n' => $known,
            'gaps' => $gaps,
            'gap_message' => $gaps || $known < 2 ? self::GAP_MESSAGE : null,
            'samples' => self::downsample($samples, self::WIRE_SAMPLES),
        ];
    }

    /**
     * @param array<string, mixed> $grid
     * @return array<string, mixed>
     */
    public static function lineOfSight(
        array $grid,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $observerEyeM = 1.6,
        float $targetEyeM = 0.0,
    ): array {
        $dist = hypot($x1 - $x0, $y1 - $y0);
        $cell = max(25.0, (float) ($grid['cell_m'] ?? 50));
        if ($dist < self::MIN_DIST_M) {
            return [
                'ok' => true,
                'ready' => false,
                'mode' => 'los',
                'verdict' => self::VERDICT_UNKNOWN,
                'verdict_label' => 'Points trop proches',
                'detail' => 'Écartez l’observateur et la cible d’au moins quelques mètres.',
                'distance_m' => round($dist, 1),
                'gaps' => false,
                'gap_message' => null,
                'samples' => [],
            ];
        }

        $obsEye = max(0.0, min(50.0, $observerEyeM));
        $tgtEye = max(0.0, min(50.0, $targetEyeM));
        $samples = self::walk($grid, [[$x0, $y0], [$x1, $y1]], $cell, self::MAX_SAMPLES);
        $zObsGnd = AtakTerrainMath::heightAt($grid, $x0, $y0);
        $zTgtGnd = AtakTerrainMath::heightAt($grid, $x1, $y1);

        if ($zObsGnd === null || $zTgtGnd === null) {
            return [
                'ok' => true,
                'ready' => false,
                'mode' => 'los',
                'verdict' => self::VERDICT_UNKNOWN,
                'verdict_label' => 'Relief pas encore relevé',
                'detail' => 'L’observateur ou la cible est hors de la zone relevée.',
                'distance_m' => round($dist, 1),
                'observer_z' => $zObsGnd !== null ? round($zObsGnd + $obsEye, 1) : null,
                'target_z' => $zTgtGnd !== null ? round($zTgtGnd + $tgtEye, 1) : null,
                'gaps' => true,
                'gap_message' => self::GAP_MESSAGE,
                'samples' => self::downsample($samples, self::WIRE_SAMPLES),
                'obstruction' => null,
            ];
        }

        $zObs = $zObsGnd + $obsEye;
        $zTgt = $zTgtGnd + $tgtEye;
        $known = 0;
        $obstruction = null;
        $wire = [];
        $last = count($samples) - 1;
        foreach ($samples as $i => $s) {
            $d = (float) $s['d'];
            $t = $dist > 0 ? min(1.0, $d / $dist) : 0.0;
            $ray = $zObs + ($zTgt - $zObs) * $t;
            $z = $s['z'];
            $clear = null;
            if ($z !== null) {
                $known++;
                $zf = (float) $z;
                $blocked = $i > 0 && $i < $last && ($zf > $ray + self::LOS_EPSILON_M);
                $clear = !$blocked;
                if ($blocked && $obstruction === null) {
                    $obstruction = [
                        'd' => round($d, 1),
                        'z' => round($zf, 1),
                        'x' => $s['x'],
                        'y' => $s['y'],
                    ];
                }
            }
            $wire[] = [
                'd' => round($d, 1),
                'x' => $s['x'],
                'y' => $s['y'],
                'z' => $z !== null ? round((float) $z, 1) : null,
                'ray' => round($ray, 1),
                'clear' => $clear,
            ];
        }

        $coverage = $known / max(1, count($samples));
        $gaps = $known < count($samples);

        if ($obstruction !== null) {
            $verdict = self::VERDICT_MASKED;
            $label = 'Masqué par le relief';
            $detail = 'Le sol coupe la visée à ' . self::formatMeters($obstruction['d'])
                . ', à ' . self::formatAlt($obstruction['z']) . ' d’altitude.';
        } elseif ($coverage < 0.7) {
            $verdict = self::VERDICT_UNKNOWN;
            $label = 'Relief pas encore relevé';
            $detail = 'Pas assez de sol relevé pour juger la visée.';
        } else {
            $verdict = self::VERDICT_CLEAR;
            $label = 'Visée dégagée';
            $detail = $gaps
                ? 'Le relief relevé ne masque pas la cible. Un tronçon n’est pas encore relevé.'
                : 'Le relief ne masque pas la cible.';
        }

        return [
            'ok' => true,
            'ready' => $verdict !== self::VERDICT_UNKNOWN,
            'mode' => 'los',
            'verdict' => $verdict,
            'verdict_label' => $label,
            'detail' => $detail,
            'distance_m' => round($dist, 1),
            'observer_z' => round($zObs, 1),
            'target_z' => round($zTgt, 1),
            'observer_ground_z' => round($zObsGnd, 1),
            'target_ground_z' => round($zTgtGnd, 1),
            'observer_eye_m' => round($obsEye, 1),
            'target_eye_m' => round($tgtEye, 1),
            'coverage_pct' => (int) round(100 * $coverage),
            'cell_m' => (int) $cell,
            'gaps' => $gaps,
            'gap_message' => $gaps ? self::GAP_MESSAGE : null,
            'obstruction' => $obstruction,
            'samples' => self::downsample($wire, self::WIRE_SAMPLES),
        ];
    }

    /**
     * @param list<mixed> $raw
     * @return list<array{0:float,1:float}>
     */
    public static function normalizePoints(array $raw): array
    {
        $out = [];
        foreach ($raw as $p) {
            if (count($out) >= self::MAX_VERTICES) {
                break;
            }
            $x = null;
            $y = null;
            if (is_array($p)) {
                if (isset($p['x'], $p['y'])) {
                    $x = self::num($p['x']);
                    $y = self::num($p['y']);
                } elseif (isset($p[0], $p[1])) {
                    $x = self::num($p[0]);
                    $y = self::num($p[1]);
                }
            }
            if ($x === null || $y === null) {
                continue;
            }
            $out[] = [$x, $y];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $grid
     * @param list<array{0:float,1:float}> $points
     * @return list<array{d:float,x:float,y:float,z:?float}>
     */
    public static function walk(array $grid, array $points, float $stepM, int $maxSamples): array
    {
        if (count($points) < 2 || $stepM < 5) {
            return [];
        }
        $samples = [];
        $x0 = $points[0][0];
        $y0 = $points[0][1];
        $samples[] = [
            'd' => 0.0,
            'x' => round($x0, 1),
            'y' => round($y0, 1),
            'z' => self::roundZ(AtakTerrainMath::heightAt($grid, $x0, $y0)),
        ];
        $distAcc = 0.0;
        $n = count($points);
        for ($i = 0; $i < $n - 1; $i++) {
            $ax = $points[$i][0];
            $ay = $points[$i][1];
            $bx = $points[$i + 1][0];
            $by = $points[$i + 1][1];
            $seg = hypot($bx - $ax, $by - $ay);
            if ($seg < 0.5) {
                continue;
            }
            $steps = (int) max(1, min(240, (int) ceil($seg / $stepM)));
            for ($s = 1; $s <= $steps; $s++) {
                if (count($samples) >= $maxSamples) {
                    break 2;
                }
                $t = $s / $steps;
                $x = $ax + ($bx - $ax) * $t;
                $y = $ay + ($by - $ay) * $t;
                $d = $distAcc + $seg * $t;
                $samples[] = [
                    'd' => round($d, 1),
                    'x' => round($x, 1),
                    'y' => round($y, 1),
                    'z' => self::roundZ(AtakTerrainMath::heightAt($grid, $x, $y)),
                ];
            }
            $distAcc += $seg;
        }

        return $samples;
    }

    /**
     * @param array<string, mixed> $grid
     */
    public static function gridReady(array $grid): bool
    {
        $blob = $grid['heights'] ?? null;
        if (!is_string($blob) || $blob === '') {
            return false;
        }

        return (int) ($grid['filled_cells'] ?? 0) >= 9
            && (int) ($grid['cols'] ?? 0) >= 2
            && (int) ($grid['rows'] ?? $grid['grid_rows'] ?? 0) >= 2;
    }

    /**
     * @param list<array<string, mixed>> $samples
     * @return list<array<string, mixed>>
     */
    private static function downsample(array $samples, int $max): array
    {
        $n = count($samples);
        if ($n <= $max) {
            return $samples;
        }
        $out = [];
        $step = ($n - 1) / ($max - 1);
        $seen = [];
        for ($i = 0; $i < $max; $i++) {
            $idx = (int) round($i * $step);
            if (isset($seen[$idx])) {
                continue;
            }
            $seen[$idx] = true;
            $out[] = $samples[$idx];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyProfile(string $detail): array
    {
        return [
            'ok' => true,
            'ready' => false,
            'mode' => 'profile',
            'distance_m' => 0,
            'climb_m' => 0,
            'descent_m' => 0,
            'delta_m' => null,
            'min_z' => null,
            'max_z' => null,
            'coverage_pct' => 0,
            'gaps' => true,
            'gap_message' => $detail,
            'samples' => [],
        ];
    }

    private static function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }

    private static function roundZ(?float $z): ?float
    {
        return $z === null ? null : round($z, 1);
    }

    private static function formatMeters(float $m): string
    {
        if ($m >= 1000) {
            $km = $m / 1000;
            $txt = number_format($km, $km < 10 ? 2 : 1, ',', ' ');

            return $txt . ' km';
        }

        return (string) (int) round($m) . ' m';
    }

    private static function formatAlt(float $z): string
    {
        return (string) (int) round($z) . ' m';
    }
}
