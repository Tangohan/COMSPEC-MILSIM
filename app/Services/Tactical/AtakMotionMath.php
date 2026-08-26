<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Cinématique BFT : lissage, cap de déplacement, vitesse ETA, statut de route.
 *
 * Les coordonnées sont des mètres Arma (CRS Simple). Les caps sont en degrés
 * 0 = nord, 90 = est — même convention que getDir.
 */
final class AtakMotionMath
{
    public const CAT_INFANTRY = 'INFANTRY';
    public const CAT_GROUND_VEHICLE = 'GROUND_VEHICLE';
    public const CAT_HELICOPTER = 'HELICOPTER';
    public const CAT_FIXED_WING = 'FIXED_WING';
    public const CAT_UAV = 'UAV';

    public const STATUS_STATIC = 'STATIC';
    public const STATUS_MOVING = 'MOVING';
    public const STATUS_MANEUVERING = 'MANEUVERING';
    public const STATUS_FAST = 'FAST';
    public const STATUS_UNKNOWN = 'UNKNOWN';

    public const TREND_STABLE = 'STABLE';
    public const TREND_ACCEL = 'ACCEL';
    public const TREND_DECEL = 'DECEL';
    public const TREND_TURNING = 'TURNING';
    public const TREND_CLIMBING = 'CLIMBING';
    public const TREND_DESCENDING = 'DESCENDING';
    public const TREND_UNKNOWN = 'UNKNOWN';

    public const COURSE_ON_COURSE = 'ON_COURSE';
    public const COURSE_DIVERGING = 'DIVERGING';
    public const COURSE_CROSSING = 'CROSSING';
    public const COURSE_STATIC = 'STATIC';
    public const COURSE_ARRIVED = 'ARRIVED';

    public const ETA_DIRECT = 'ETA-D';
    public const ETA_ROUTE = 'ETA-R';
    public const ETA_PLANNED = 'ETA-P';

    public const HEADING_SMOOTH = 0.32;
    public const SPEED_SMOOTH = 0.35;
    public const MIN_SEGMENT_M = 0.45;
    public const MIN_SAMPLES = 4;
    public const WINDOW_SEC = 75.0;

    /**
     * @param list<array{x:float,y:float,z?:?float,t:float,heading_object?:?float,speed_ms?:?float,vel_x?:?float,vel_y?:?float,vel_z?:?float}> $samples
     *        t = unix seconds, oldest → newest
     * @param array<string, mixed> $arma
     * @param array<string, mixed>|null $previous snapshot motion
     * @return array<string, mixed>
     */
    public static function compute(array $samples, array $arma = [], ?array $previous = null): array
    {
        $category = self::classifyCategory($arma);
        $headingObject = self::finiteOrNull($arma['heading_object'] ?? $arma['heading'] ?? null);
        $armaSpeed = self::finiteOrNull($arma['speed_ms'] ?? $arma['speed'] ?? null);
        $armaVx = self::finiteOrNull($arma['vel_x'] ?? null);
        $armaVy = self::finiteOrNull($arma['vel_y'] ?? null);
        $armaVz = self::finiteOrNull($arma['vel_z'] ?? null);
        if ($armaSpeed === null && $armaVx !== null && $armaVy !== null) {
            $vz = $armaVz ?? 0.0;
            $armaSpeed = hypot(hypot($armaVx, $armaVy), $vz);
        }

        $armaMoveHeading = null;
        $explicitMove = self::finiteOrNull($arma['movement_heading'] ?? null);
        if ($armaVx !== null && $armaVy !== null && hypot($armaVx, $armaVy) >= 0.15) {
            $armaMoveHeading = self::headingFromDelta($armaVx, $armaVy);
        }
        if ($armaMoveHeading === null && $explicitMove !== null) {
            $armaMoveHeading = self::normHeading($explicitMove);
        }

        $window = self::windowedSamples($samples);
        $path = self::pathKinematics($window);

        $speedCurrent = $armaSpeed !== null ? max(0.0, $armaSpeed) : $path['speed_current'];
        $speed30 = $path['speed_30'] ?? $speedCurrent;
        $speed60 = $path['speed_60'] ?? $speed30;
        if ($previous !== null) {
            $prevSpeed = self::finiteOrNull($previous['speed_ms'] ?? null);
            if ($prevSpeed !== null && $speedCurrent !== null) {
                $speedCurrent = self::ema($prevSpeed, $speedCurrent, self::SPEED_SMOOTH);
            }
            $prev30 = self::finiteOrNull($previous['speed_avg_30'] ?? null);
            if ($prev30 !== null && $speed30 !== null) {
                $speed30 = self::ema($prev30, $speed30, 0.28);
            }
            $prev60 = self::finiteOrNull($previous['speed_avg_60'] ?? null);
            if ($prev60 !== null && $speed60 !== null) {
                $speed60 = self::ema($prev60, $speed60, 0.22);
            }
        }

        $rawHeading = $path['heading'];
        if ($rawHeading === null && $armaMoveHeading !== null) {
            $rawHeading = $armaMoveHeading;
        }
        if ($rawHeading !== null && $armaMoveHeading !== null && $path['heading'] !== null) {
            $agree = abs(self::circularDeltaDeg($path['heading'], $armaMoveHeading));
            if ($agree < 35.0) {
                $rawHeading = self::circularMeanDeg([$path['heading'], $path['heading'], $armaMoveHeading]);
            }
        }

        $smoothedHeading = $rawHeading;
        $prevHeading = self::finiteOrNull($previous['movement_heading'] ?? null);
        if ($smoothedHeading !== null && $prevHeading !== null) {
            $smoothedHeading = self::lerpHeading($prevHeading, $smoothedHeading, self::HEADING_SMOOTH);
        }

        $status = self::classifyStatus($category, $speedCurrent ?? 0.0);
        $confidence = self::confidence($window, $path, $armaMoveHeading, $status);
        if (count($window) < self::MIN_SAMPLES && $armaSpeed === null) {
            $status = self::STATUS_UNKNOWN;
            $confidence = min($confidence, 0.28);
        }

        $headingSpread = $path['heading_spread'];
        if (
            in_array($status, [self::STATUS_MOVING, self::STATUS_FAST], true)
            && $headingSpread !== null
            && $headingSpread > 28.0
        ) {
            $status = self::STATUS_MANEUVERING;
        }

        $alt = self::finiteOrNull($arma['altitude'] ?? $arma['asl_z'] ?? $arma['pos_z'] ?? $path['alt']);
        $vs = self::finiteOrNull($arma['vertical_speed'] ?? $arma['vel_z'] ?? $path['vs']);
        $trend = self::trend($speed30, $speed60, $smoothedHeading, $prevHeading, $vs, $category);

        $etaSpeed = self::etaSpeed($speedCurrent, $speed30, $speed60, $status, $category);
        $projHorizon = self::projectionHorizonSec($category);
        $projLen = min(($etaSpeed ?? 0.0) * $projHorizon, self::maxProjectionM($category));
        $showProjection = $confidence >= 0.45
            && $status !== self::STATUS_STATIC
            && $status !== self::STATUS_UNKNOWN
            && ($etaSpeed ?? 0.0) > 0.2;

        $trail = [];
        $prevSample = null;
        $isPhone = !empty($arma['phone_geoloc'])
            || strtolower(trim((string) ($arma['source'] ?? ''))) === 'phone';
        $gapSec = self::gapThresholdSec($category, $isPhone);
        foreach (array_slice($window, -16) as $s) {
            $dt = $prevSample !== null ? ((float) $s['t'] - (float) $prevSample['t']) : 0.0;
            $gap = $prevSample !== null && $dt >= $gapSec;
            $uncertain = $prevSample !== null && $dt >= ($gapSec * 0.55);
            $trail[] = [
                'x' => round((float) $s['x'], 2),
                'y' => round((float) $s['y'], 2),
                't' => (float) $s['t'],
                'gap' => $gap,
                'uncertain' => $uncertain,
            ];
            $prevSample = $s;
        }

        $air = null;
        if (in_array($category, [self::CAT_HELICOPTER, self::CAT_FIXED_WING, self::CAT_UAV], true)) {
            $gsMs = $speedCurrent ?? 0.0;
            $air = [
                'ground_speed' => round($gsMs, 3),
                'ground_speed_kt' => round($gsMs * 1.943844, 1),
                'altitude' => $alt,
                'altitude_ft' => $alt !== null ? round($alt * 3.28084) : null,
                'vertical_speed' => $vs,
                'vertical_speed_fpm' => $vs !== null ? (int) round($vs * 196.85) : null,
                'alt_trend' => self::altTrend($vs),
            ];
        }

        return [
            'category' => $category,
            'heading_object' => $headingObject !== null ? round(self::normHeading($headingObject), 1) : null,
            'movement_heading' => $smoothedHeading !== null ? round(self::normHeading($smoothedHeading), 1) : null,
            'speed_ms' => $speedCurrent !== null ? round($speedCurrent, 3) : null,
            'speed_avg_30' => $speed30 !== null ? round($speed30, 3) : null,
            'speed_avg_60' => $speed60 !== null ? round($speed60, 3) : null,
            'eta_speed_ms' => $etaSpeed !== null ? round($etaSpeed, 3) : null,
            'motion_status' => $status,
            'confidence' => round($confidence, 3),
            'trend' => $trend,
            'alt_msl' => $alt !== null ? round($alt, 2) : null,
            'vertical_speed' => $vs !== null ? round($vs, 3) : null,
            'alt_trend' => $air['alt_trend'] ?? self::altTrend($vs),
            'velocity' => [
                'x' => $armaVx,
                'y' => $armaVy,
                'z' => $armaVz ?? $vs,
            ],
            'projection' => [
                'horizon_s' => $projHorizon,
                'length_m' => $showProjection ? round($projLen, 1) : 0.0,
                'visible' => $showProjection,
            ],
            'trail' => $trail,
            'air' => $air,
            'sample_count' => count($window),
        ];
    }

    /**
     * @param array<string, mixed> $arma
     */
    public static function classifyCategory(array $arma): string
    {
        $explicit = strtoupper(trim((string) ($arma['platform'] ?? $arma['category'] ?? $arma['unit_category'] ?? '')));
        $allowed = [
            self::CAT_INFANTRY,
            self::CAT_GROUND_VEHICLE,
            self::CAT_HELICOPTER,
            self::CAT_FIXED_WING,
            self::CAT_UAV,
        ];
        if (in_array($explicit, $allowed, true)) {
            return $explicit;
        }
        $groundAliases = [
            'TANK', 'APC', 'IFV', 'TRUCK', 'LIGHT_VEHICLE', 'ARTILLERY', 'MORTAR',
            'BOAT', 'SHIP', 'CAR', 'ARMOR', 'MECH', 'MBT',
        ];
        if (in_array($explicit, $groundAliases, true)) {
            return self::CAT_GROUND_VEHICLE;
        }

        $kind = strtolower(trim((string) ($arma['unit_kind'] ?? '')));
        $ac = strtolower(trim((string) ($arma['aircraft_type'] ?? $arma['model'] ?? $arma['vehicle'] ?? '')));
        if ($kind === 'air' || $ac !== '') {
            if (str_contains($ac, 'uav') || str_contains($ac, 'drone') || str_contains($ac, 'quad') || str_contains($ac, 'mq-')) {
                return self::CAT_UAV;
            }
            if (str_contains($ac, 'heli') || str_contains($ac, 'heli') || str_contains($ac, 'ah-') || str_contains($ac, 'uh-') || str_contains($ac, 'mh-') || str_contains($ac, 'ch-') || str_contains($ac, 'ka-') || str_contains($ac, 'mi-') || str_contains($ac, 'ec635') || str_contains($ac, 'wildcat')) {
                return self::CAT_HELICOPTER;
            }
            if ($kind === 'air' || str_contains($ac, 'plane') || str_contains($ac, 'jet') || str_contains($ac, 'f-') || str_contains($ac, 'a-10') || str_contains($ac, 'c-130')) {
                if (str_contains($ac, 'heli')) {
                    return self::CAT_HELICOPTER;
                }

                return self::CAT_FIXED_WING;
            }
        }

        $inVeh = $arma['in_vehicle'] ?? $arma['inVehicle'] ?? false;
        $inVeh = $inVeh === true || $inVeh === 1 || $inVeh === '1' || $inVeh === 'true';
        if ($inVeh) {
            return self::CAT_GROUND_VEHICLE;
        }

        return self::CAT_INFANTRY;
    }

    public static function classifyStatus(string $category, float $speedMs): string
    {
        $kmh = $speedMs * 3.6;
        [$staticMax, $fastMin] = match ($category) {
            self::CAT_INFANTRY => [0.5, 18.0],
            self::CAT_GROUND_VEHICLE => [1.5, 50.0],
            self::CAT_HELICOPTER => [5.0, 180.0],
            self::CAT_FIXED_WING => [30.0, 400.0],
            self::CAT_UAV => [3.0, 80.0],
            default => [0.5, 50.0],
        };
        if ($kmh < $staticMax) {
            return self::STATUS_STATIC;
        }
        if ($kmh > $fastMin) {
            return self::STATUS_FAST;
        }

        return self::STATUS_MOVING;
    }

    public static function arrivalRadiusM(string $category): float
    {
        return match ($category) {
            self::CAT_INFANTRY => 30.0,
            self::CAT_GROUND_VEHICLE => 50.0,
            self::CAT_HELICOPTER => 100.0,
            self::CAT_FIXED_WING => 250.0,
            self::CAT_UAV => 80.0,
            default => 40.0,
        };
    }

    public static function projectionHorizonSec(string $category): int
    {
        return match ($category) {
            self::CAT_FIXED_WING => 30,
            self::CAT_HELICOPTER, self::CAT_UAV => 30,
            default => 30,
        };
    }

    public static function maxProjectionM(string $category): float
    {
        return match ($category) {
            self::CAT_INFANTRY => 250.0,
            self::CAT_GROUND_VEHICLE => 800.0,
            self::CAT_HELICOPTER => 1800.0,
            self::CAT_UAV => 1200.0,
            self::CAT_FIXED_WING => 4500.0,
            default => 600.0,
        };
    }

    /**
     * Délai au-delà duquel un segment de trace est un trou (doute / perte).
     */
    public static function gapThresholdSec(string $category, bool $isPhone = false): float
    {
        if ($isPhone) {
            return 20.0;
        }

        return match ($category) {
            self::CAT_GROUND_VEHICLE => 10.0,
            self::CAT_HELICOPTER, self::CAT_UAV => 8.0,
            self::CAT_FIXED_WING => 6.0,
            default => 15.0,
        };
    }

    /**
     * Vitesse plausible pour une enveloppe « où ça a pu aller » pendant un silence.
     */
    public static function plausibleSpeedMs(string $category, ?float $lastSpeedMs, bool $isPhone = false): float
    {
        $typical = match ($category) {
            self::CAT_INFANTRY => $isPhone ? 1.6 : 1.4,
            self::CAT_GROUND_VEHICLE => 16.0,
            self::CAT_HELICOPTER => 45.0,
            self::CAT_UAV => 18.0,
            self::CAT_FIXED_WING => 110.0,
            default => $isPhone ? 1.6 : 1.5,
        };
        $cap = match ($category) {
            self::CAT_INFANTRY => $isPhone ? 3.5 : 3.0,
            self::CAT_GROUND_VEHICLE => 33.0,
            self::CAT_HELICOPTER => 70.0,
            self::CAT_UAV => 35.0,
            self::CAT_FIXED_WING => 180.0,
            default => 4.0,
        };
        $v = $typical;
        if ($lastSpeedMs !== null && $lastSpeedMs > 0.3) {
            $v = max($lastSpeedMs * 1.12, $typical * 0.55);
        }

        return min($cap, $v);
    }

    /**
     * Rayon max depuis la dernière position connue (silence radio / GPS).
     */
    public static function reachRadiusM(
        string $category,
        float $elapsedSec,
        ?float $lastSpeedMs,
        bool $isPhone = false
    ): float {
        if ($elapsedSec < 6.0) {
            return 0.0;
        }
        $cap = match ($category) {
            self::CAT_INFANTRY => $isPhone ? 700.0 : 600.0,
            self::CAT_GROUND_VEHICLE => 3500.0,
            self::CAT_HELICOPTER => 7000.0,
            self::CAT_UAV => 4000.0,
            self::CAT_FIXED_WING => 14000.0,
            default => 800.0,
        };
        $r = self::plausibleSpeedMs($category, $lastSpeedMs, $isPhone) * $elapsedSec;

        return min($cap, max(0.0, $r));
    }

    public static function headingFromDelta(float $dx, float $dy): float
    {
        $deg = rad2deg(atan2($dx, $dy));

        return self::normHeading($deg);
    }

    public static function circularMeanDeg(array $headings): ?float
    {
        $n = 0;
        $sin = 0.0;
        $cos = 0.0;
        foreach ($headings as $h) {
            if (!is_numeric($h)) {
                continue;
            }
            $r = deg2rad((float) $h);
            $sin += sin($r);
            $cos += cos($r);
            $n++;
        }
        if ($n < 1) {
            return null;
        }

        return self::normHeading(rad2deg(atan2($sin, $cos)));
    }

    public static function circularDeltaDeg(float $from, float $to): float
    {
        $d = fmod(($to - $from) + 540.0, 360.0) - 180.0;

        return $d;
    }

    public static function lerpHeading(float $previous, float $next, float $alpha): float
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $delta = self::circularDeltaDeg($previous, $next);

        return self::normHeading($previous + $delta * $alpha);
    }

    public static function normHeading(float $deg): float
    {
        $h = fmod($deg, 360.0);
        if ($h < 0) {
            $h += 360.0;
        }
        // atan2 peut remonter ~360° pour un cap nord : équivalent à 0°.
        if ($h >= 360.0 - 1e-6) {
            return 0.0;
        }

        return $h;
    }

    public static function distanceM(float $x1, float $y1, float $x2, float $y2): float
    {
        return hypot($x2 - $x1, $y2 - $y1);
    }

    public static function bearingTo(float $fromX, float $fromY, float $toX, float $toY): ?float
    {
        $dx = $toX - $fromX;
        $dy = $toY - $fromY;
        if (hypot($dx, $dy) < 0.5) {
            return null;
        }

        return self::headingFromDelta($dx, $dy);
    }

    public static function courseStatus(?float $movementHeading, ?float $bearingToDest, float $speedMs, float $distanceM, string $category): string
    {
        if ($distanceM <= self::arrivalRadiusM($category)) {
            return self::COURSE_ARRIVED;
        }
        if ($speedMs * 3.6 < 0.5 || $movementHeading === null || $bearingToDest === null) {
            return self::COURSE_STATIC;
        }
        $delta = abs(self::circularDeltaDeg($movementHeading, $bearingToDest));
        if ($delta < 25.0) {
            return self::COURSE_ON_COURSE;
        }
        if ($delta > 110.0) {
            return self::COURSE_DIVERGING;
        }

        return self::COURSE_CROSSING;
    }

    /**
     * ETA directe (vol d’oiseau) avec lissage pour éviter les sauts de secondes.
     *
     * @return array{kind:string,seconds:?int,raw_seconds:?float,speed_ms:?float,arrived:bool}
     */
    public static function etaDirect(
        float $distanceM,
        ?float $etaSpeedMs,
        string $category,
        ?float $previousEtaSec = null,
        string $etaKind = self::ETA_DIRECT
    ): array {
        if ($distanceM <= self::arrivalRadiusM($category)) {
            return [
                'kind' => $etaKind,
                'seconds' => 0,
                'raw_seconds' => 0.0,
                'speed_ms' => $etaSpeedMs,
                'arrived' => true,
            ];
        }
        if ($etaSpeedMs === null || $etaSpeedMs < 0.15) {
            return [
                'kind' => $etaKind,
                'seconds' => null,
                'raw_seconds' => null,
                'speed_ms' => $etaSpeedMs,
                'arrived' => false,
            ];
        }
        $raw = $distanceM / $etaSpeedMs;
        $smoothed = $raw;
        if ($previousEtaSec !== null && $previousEtaSec > 0) {
            $ratio = $raw / $previousEtaSec;
            if ($ratio >= 0.72 && $ratio <= 1.38) {
                $smoothed = 0.65 * $previousEtaSec + 0.35 * $raw;
            } else {
                $smoothed = 0.88 * $previousEtaSec + 0.12 * $raw;
            }
        }

        return [
            'kind' => $etaKind,
            'seconds' => (int) max(0, round($smoothed)),
            'raw_seconds' => $smoothed,
            'speed_ms' => $etaSpeedMs,
            'arrived' => false,
        ];
    }

    /**
     * Point d’interception du premier ordre (mode INTERCEPT, V2).
     * L’intercepteur B peut changer de cap ; la cible A garde cap et vitesse.
     *
     * @return array{x:float,y:float,t:float}|null
     */
    public static function interceptPoint(
        float $ax,
        float $ay,
        ?float $aHeading,
        ?float $aSpeedMs,
        float $bx,
        float $by,
        ?float $bSpeedMs
    ): ?array {
        if ($bSpeedMs === null || $bSpeedMs < 0.3) {
            return null;
        }
        $avx = 0.0;
        $avy = 0.0;
        if ($aHeading !== null && $aSpeedMs !== null && $aSpeedMs > 0.15) {
            $r = deg2rad($aHeading);
            $avx = sin($r) * $aSpeedMs;
            $avy = cos($r) * $aSpeedMs;
        }
        for ($t = 1.0; $t <= 600.0; $t += 1.0) {
            $px = $ax + $avx * $t;
            $py = $ay + $avy * $t;
            $need = self::distanceM($bx, $by, $px, $py);
            if ($need / $bSpeedMs <= $t) {
                return ['x' => $px, 'y' => $py, 't' => $t];
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $samples
     * @return list<array<string, mixed>>
     */
    private static function windowedSamples(array $samples): array
    {
        if ($samples === []) {
            return [];
        }
        $lastT = (float) $samples[count($samples) - 1]['t'];
        $cut = $lastT - self::WINDOW_SEC;
        $out = [];
        foreach ($samples as $s) {
            if ((float) ($s['t'] ?? 0) < $cut) {
                continue;
            }
            $out[] = $s;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $samples
     * @return array{heading:?float,speed_current:?float,speed_30:?float,speed_60:?float,heading_spread:?float,alt:?float,vs:?float}
     */
    private static function pathKinematics(array $samples): array
    {
        $empty = [
            'heading' => null,
            'speed_current' => null,
            'speed_30' => null,
            'speed_60' => null,
            'heading_spread' => null,
            'alt' => null,
            'vs' => null,
        ];
        $n = count($samples);
        if ($n < 2) {
            if ($n === 1) {
                $empty['alt'] = self::finiteOrNull($samples[0]['z'] ?? null);
            }

            return $empty;
        }

        $headings = [];
        $distAll = 0.0;
        $dist30 = 0.0;
        $dist60 = 0.0;
        $tAll = 0.0;
        $t30 = 0.0;
        $t60 = 0.0;
        $zPairs = [];
        $endT = (float) $samples[$n - 1]['t'];

        for ($i = 1; $i < $n; $i++) {
            $a = $samples[$i - 1];
            $b = $samples[$i];
            $dt = (float) $b['t'] - (float) $a['t'];
            if ($dt < 0.2) {
                continue;
            }
            $dx = (float) $b['x'] - (float) $a['x'];
            $dy = (float) $b['y'] - (float) $a['y'];
            $dist = hypot($dx, $dy);
            $age = $endT - (float) $b['t'];
            if ($dist >= self::MIN_SEGMENT_M) {
                $headings[] = self::headingFromDelta($dx, $dy);
            }
            $distAll += $dist;
            $tAll += $dt;
            if ($age <= 30.0) {
                $dist30 += $dist;
                $t30 += $dt;
            }
            if ($age <= 60.0) {
                $dist60 += $dist;
                $t60 += $dt;
            }
            $za = self::finiteOrNull($a['z'] ?? null);
            $zb = self::finiteOrNull($b['z'] ?? null);
            if ($za !== null && $zb !== null && $age <= 20.0) {
                $zPairs[] = ($zb - $za) / $dt;
            }
        }

        $speedAll = $tAll > 0.4 ? $distAll / $tAll : null;
        $speed30 = $t30 > 0.4 ? $dist30 / $t30 : $speedAll;
        $speed60 = $t60 > 0.4 ? $dist60 / $t60 : $speed30;

        $spread = null;
        $meanH = self::circularMeanDeg($headings);
        if ($meanH !== null && count($headings) >= 2) {
            $acc = 0.0;
            foreach ($headings as $h) {
                $acc += abs(self::circularDeltaDeg($meanH, $h));
            }
            $spread = $acc / count($headings);
        }

        $vs = $zPairs !== [] ? array_sum($zPairs) / count($zPairs) : null;
        $alt = self::finiteOrNull($samples[$n - 1]['z'] ?? null);

        return [
            'heading' => $meanH,
            'speed_current' => $speedAll,
            'speed_30' => $speed30,
            'speed_60' => $speed60,
            'heading_spread' => $spread,
            'alt' => $alt,
            'vs' => $vs,
        ];
    }

    /**
     * @param list<array<string, mixed>> $samples
     * @param array{heading:?float,heading_spread:?float,speed_current:?float} $path
     */
    private static function confidence(array $samples, array $path, ?float $armaMoveHeading, string $status): float
    {
        $n = count($samples);
        if ($n < 2) {
            return $status === self::STATUS_STATIC ? 0.4 : 0.12;
        }
        $c = 0.28 + min(0.42, ($n - 1) * 0.07);
        if ($path['heading_spread'] !== null) {
            if ($path['heading_spread'] < 12.0) {
                $c += 0.18;
            } elseif ($path['heading_spread'] > 40.0) {
                $c -= 0.22;
            } else {
                $c -= ($path['heading_spread'] - 12.0) / 140.0;
            }
        }
        if ($armaMoveHeading !== null && $path['heading'] !== null) {
            $d = abs(self::circularDeltaDeg($path['heading'], $armaMoveHeading));
            $c += $d < 20.0 ? 0.12 : ($d > 70.0 ? -0.1 : 0.0);
        }
        if ($status === self::STATUS_STATIC) {
            $c = max($c, 0.55);
        }

        return max(0.0, min(0.99, $c));
    }

    private static function etaSpeed(?float $current, ?float $avg30, ?float $avg60, string $status, string $category): ?float
    {
        if ($status === self::STATUS_STATIC) {
            return 0.0;
        }
        $parts = [];
        if ($avg30 !== null) {
            $parts[] = [$avg30, 0.45];
        }
        if ($avg60 !== null) {
            $parts[] = [$avg60, 0.55];
        }
        if ($parts === [] && $current !== null) {
            $parts[] = [$current, 1.0];
        }
        if ($parts === []) {
            return null;
        }
        $num = 0.0;
        $den = 0.0;
        foreach ($parts as [$v, $w]) {
            $num += $v * $w;
            $den += $w;
        }
        $v = $den > 0 ? $num / $den : null;
        if ($v === null) {
            return null;
        }
        $floor = match ($category) {
            self::CAT_INFANTRY => 0.4,
            self::CAT_GROUND_VEHICLE => 1.5,
            self::CAT_HELICOPTER => 8.0,
            self::CAT_FIXED_WING => 40.0,
            self::CAT_UAV => 4.0,
            default => 0.4,
        };
        if ($status !== self::STATUS_UNKNOWN && $v < $floor * 0.35) {
            return $v;
        }

        return $v;
    }

    private static function trend(
        ?float $speed30,
        ?float $speed60,
        ?float $heading,
        ?float $prevHeading,
        ?float $vs,
        string $category
    ): string {
        $air = in_array($category, [self::CAT_HELICOPTER, self::CAT_FIXED_WING, self::CAT_UAV], true);
        if ($air && $vs !== null && abs($vs) > 1.2) {
            return $vs > 0 ? self::TREND_CLIMBING : self::TREND_DESCENDING;
        }
        if ($heading !== null && $prevHeading !== null && abs(self::circularDeltaDeg($prevHeading, $heading)) > 18.0) {
            return self::TREND_TURNING;
        }
        if ($speed30 !== null && $speed60 !== null && $speed60 > 0.4) {
            if ($speed30 > $speed60 * 1.22) {
                return self::TREND_ACCEL;
            }
            if ($speed30 < $speed60 * 0.78) {
                return self::TREND_DECEL;
            }
        }

        return self::TREND_STABLE;
    }

    private static function altTrend(?float $vs): ?string
    {
        if ($vs === null) {
            return null;
        }
        if ($vs > 0.8) {
            return 'CLIMBING';
        }
        if ($vs < -0.8) {
            return 'DESCENDING';
        }

        return 'LEVEL';
    }

    private static function ema(float $prev, float $next, float $alpha): float
    {
        return $prev * (1.0 - $alpha) + $next * $alpha;
    }

    private static function finiteOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;
        if (!is_finite($f)) {
            return null;
        }

        return $f;
    }
}
