<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Triangulation d'un téléphone du jeu par plusieurs sources du jeu (relais ATAK, téléphones alliés du maillage,
 * postes de collecte). Même principe que la triangulation par relais de l'ATAK en jeu (fn_relayNet « locate ») :
 *
 *  - chaque source qui « entend » le téléphone (la distance de jeu est dans sa portée) livre une distance estimée
 *    d'après la puissance reçue, entachée d'une erreur de 5 % (lien franc) à 25 % (lien faible), et un relèvement
 *    (gisement) lui aussi entaché d'une erreur ;
 *  - une source donne un cercle, deux sources deux points possibles, trois sources ou plus une position par moindres
 *    carrés sur les distances (descente de gradient depuis le barycentre) avec une zone d'incertitude.
 *
 * Uniquement des coordonnées du monde Arma 3 (mètres de jeu, x vers l'est, y vers le nord) : aucune position, adresse
 * ou appareil réel n'entre ni ne sort d'ici. Le résultat est toujours une ESTIMATION (is_estimate = true).
 *
 * Déterministe : le bruit vient d'un générateur à graine (mulberry32) mélangée à l'identifiant de chaque source, donc
 * mêmes entrées + même graine = même résultat, et ajouter une source ne change pas le bruit des autres.
 *
 * Précision :
 *  - nombre de sources : le rayon d'incertitude diminue en 1/√n (il contient la vraie position dans environ neuf cas
 *    sur dix) ; 1 source = cercle, 2 = ambiguïté (rayon x 2,5) ;
 *  - terrain (0 dégagé à 1 très couvert) : la qualité de lien est réduite jusqu'à 45 %, donc l'erreur de distance
 *    monte et les sources lointaines sortent de portée ;
 *  - bornes : 12 sources au plus (les mieux entendues), distance estimée d'au moins 5 m, rayon entre 15 m et 5 km.
 */
final class PhoneTriangulation
{
    public const MAX_SOURCES = 12;
    public const MIN_DISTANCE_M = 5.0;
    public const MIN_RADIUS_M = 15.0;
    public const MAX_RADIUS_M = 5000.0;
    public const ITERATIONS = 200;

    /** Facteur de la zone d'incertitude (rayon qui contient la vraie position dans environ neuf cas sur dix). */
    public const COVERAGE = 1.9;

    /** Qualité de lien (0 : non entendu, 1 : lien franc) d'après la distance, la portée et le terrain. */
    public static function linkQuality(float $distance, float $range, float $terrain): float
    {
        if ($range <= 0.0 || $distance > $range) {
            return 0.0;
        }
        $terrain = max(0.0, min(1.0, $terrain));
        $q = (1.0 - ($distance / $range) ** 1.5) * (1.0 - 0.45 * $terrain);

        return max(0.0, min(1.0, $q));
    }

    /** Erreur relative de la distance lue sur la puissance reçue : 5 % (lien franc) à 25 % (lien faible). */
    public static function distanceError(float $quality): float
    {
        return 0.05 + 0.20 * (1.0 - max(0.0, min(1.0, $quality)));
    }

    /** Erreur de relèvement en degrés : 3° (lien franc) à 15° (lien faible). */
    public static function bearingError(float $quality): float
    {
        return 3.0 + 12.0 * (1.0 - max(0.0, min(1.0, $quality)));
    }

    /** Graine stable d'une triangulation : communauté, téléphone et fenêtre de 30 s (un même résultat dans la fenêtre). */
    public static function seedFor(int $tenantId, string $targetKey, int $now): int
    {
        return (int) sprintf('%u', crc32($tenantId . '|' . $targetKey . '|' . intdiv($now, 30)));
    }

    /** mulberry32 : nombre dans [0, 1[ à partir d'un état entier 32 bits. */
    private static function rand(int &$state): float
    {
        $state = ($state + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $state;
        $t = self::imul($t ^ ($t >> 15), $t | 1);
        $t ^= ($t + self::imul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;

        return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296.0;
    }

    private static function imul(int $a, int $b): int
    {
        // Produit 32 bits sans passer par un flottant (le produit de deux entiers 32 bits dépasse 63 bits).
        return ((($a & 0xFFFF) * $b) + (((($a >> 16) * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    /** Bruit triangulaire dans [-1, 1] (somme de deux tirages centrés). */
    private static function noise(int &$state): float
    {
        return self::rand($state) + self::rand($state) - 1.0;
    }

    /**
     * @param array{0: float|int, 1: float|int} $target position réelle dans le jeu (x, y en mètres)
     * @param list<array{id: string, label?: string, kind?: string, x: float|int, y: float|int, range: float|int}> $sources
     * @return array{
     *   is_estimate: bool, status: string, sources: int, heard: int, position: ?array{0: float, 1: float},
     *   radius_m: ?float, accuracy: string, candidates: list<array{0: float, 1: float}>,
     *   readings: list<array<string, mixed>>, seed: int, terrain: float
     * }
     */
    public static function estimate(array $target, array $sources, int $seed, float $terrain = 0.3): array
    {
        $terrain = max(0.0, min(1.0, $terrain));
        $tx = (float) $target[0];
        $ty = (float) $target[1];
        $heard = [];
        foreach ($sources as $s) {
            $sx = (float) $s['x'];
            $sy = (float) $s['y'];
            $range = (float) $s['range'];
            $d = hypot($tx - $sx, $ty - $sy);
            $q = self::linkQuality($d, $range, $terrain);
            if ($q <= 0.0) {
                continue;
            }
            $heard[] = ['s' => $s, 'd' => $d, 'q' => $q, 'x' => $sx, 'y' => $sy];
        }
        usort($heard, static fn (array $a, array $b): int => ($b['q'] <=> $a['q']) ?: strcmp((string) $a['s']['id'], (string) $b['s']['id']));
        $heard = array_slice($heard, 0, self::MAX_SOURCES);
        usort($heard, static fn (array $a, array $b): int => strcmp((string) $a['s']['id'], (string) $b['s']['id']));

        $readings = [];
        foreach ($heard as $h) {
            $state = ($seed ^ (int) sprintf('%u', crc32((string) $h['s']['id']))) & 0xFFFFFFFF;
            $err = self::distanceError($h['q']);
            $dEst = max(self::MIN_DISTANCE_M, $h['d'] * (1.0 + self::noise($state) * $err));
            // Azimut de la source vers le téléphone : 0 = nord, sens horaire (x vers l'est, y vers le nord).
            $az = rad2deg(atan2($tx - $h['x'], $ty - $h['y']));
            $az = fmod(($az + 360.0), 360.0) + self::noise($state) * self::bearingError($h['q']);
            $az = fmod($az + 360.0, 360.0);
            $rssi = -38.0 - 22.0 * log10(max($h['d'], 1.0)) - 10.0 * $terrain + self::noise($state) * 2.0;
            $readings[] = [
                'id' => (string) $h['s']['id'],
                'label' => (string) ($h['s']['label'] ?? $h['s']['id']),
                'kind' => (string) ($h['s']['kind'] ?? 'relay'),
                'x' => round($h['x'], 1),
                'y' => round($h['y'], 1),
                'range_m' => round((float) $h['s']['range']),
                'quality' => round($h['q'], 3),
                'distance_m' => round($dEst, 1),
                'error_pct' => round($err * 100.0, 1),
                'bearing_deg' => round($az, 1),
                'bearing_error_deg' => round(self::bearingError($h['q']), 1),
                'signal_dbm' => (int) round($rssi),
            ];
        }

        $n = count($readings);
        $out = [
            'is_estimate' => true,
            'status' => 'none',
            'sources' => count($sources),
            'heard' => $n,
            'position' => null,
            'radius_m' => null,
            'accuracy' => 'none',
            'candidates' => [],
            'readings' => $readings,
            'seed' => $seed,
            'terrain' => round($terrain, 2),
        ];
        if ($n === 0) {
            return $out;
        }
        $meanErrM = 0.0;
        foreach ($readings as $r) {
            $meanErrM += $r['distance_m'] * $r['error_pct'] / 100.0;
        }
        $meanErrM /= $n;

        if ($n === 1) {
            $out['status'] = 'circle';
            $out['radius_m'] = self::clampRadius($readings[0]['distance_m']);
            $out['accuracy'] = 'faible';

            return $out;
        }
        if ($n === 2) {
            $c = self::intersect($readings[0], $readings[1]);
            $out['status'] = 'ambiguous';
            $out['candidates'] = $c;
            $out['position'] = $c[0];
            $out['radius_m'] = self::clampRadius(max(self::MIN_RADIUS_M, $meanErrM / sqrt(2.0)) * 2.5 + 0.0);
            $out['accuracy'] = 'faible';

            return $out;
        }

        // Moindres carrés : départ au barycentre décalé de 50 m dans une direction tirée de la graine (sources alignées).
        $bx = 0.0;
        $by = 0.0;
        foreach ($readings as $r) {
            $bx += $r['x'];
            $by += $r['y'];
        }
        $bx /= $n;
        $by /= $n;
        $a0 = (($seed % 3600) / 3600.0) * 2.0 * M_PI;
        $ex = $bx + 50.0 * cos($a0);
        $ey = $by + 50.0 * sin($a0);
        for ($k = 0; $k < self::ITERATIONS; $k++) {
            $gx = 0.0;
            $gy = 0.0;
            foreach ($readings as $r) {
                $vx = $ex - $r['x'];
                $vy = $ey - $r['y'];
                $len = max(hypot($vx, $vy), 0.1);
                $f = ($len - $r['distance_m']) / $len;
                $gx += $vx * $f;
                $gy += $vy * $f;
            }
            $ex -= $gx * (0.5 / $n);
            $ey -= $gy * (0.5 / $n);
        }
        $res = 0.0;
        foreach ($readings as $r) {
            $res += (hypot($ex - $r['x'], $ey - $r['y']) - $r['distance_m']) ** 2;
        }
        // Incertitude : écart type des distances (mélange de l'erreur annoncée des liens et du résidu de l'ajustement, à
        // n - 2 degrés de liberté) divisé par √n, d'où une zone qui rétrécit quand les sources se multiplient.
        $sigma = sqrt(($meanErrM ** 2 + $res / max(1, $n - 2)) / 2.0);
        $rad = self::COVERAGE * $sigma / sqrt((float) $n);
        $out['status'] = 'estimate';
        $out['position'] = [round($ex, 1), round($ey, 1)];
        $out['radius_m'] = self::clampRadius($rad);
        $out['accuracy'] = $out['radius_m'] <= 40.0 ? 'bonne' : ($out['radius_m'] <= 120.0 ? 'moyenne' : 'faible');

        return $out;
    }

    private static function clampRadius(float $r): float
    {
        return round(max(self::MIN_RADIUS_M, min(self::MAX_RADIUS_M, $r)), 1);
    }

    /**
     * Intersection de deux cercles (centre source, rayon distance estimée). Cercles qui ne se coupent pas : le point
     * de la ligne des centres au prorata des rayons. Les deux points sont rendus dans un ordre stable (x puis y).
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return list<array{0: float, 1: float}>
     */
    private static function intersect(array $a, array $b): array
    {
        $x0 = (float) $a['x'];
        $y0 = (float) $a['y'];
        $r0 = (float) $a['distance_m'];
        $x1 = (float) $b['x'];
        $y1 = (float) $b['y'];
        $r1 = (float) $b['distance_m'];
        $dx = $x1 - $x0;
        $dy = $y1 - $y0;
        $d = hypot($dx, $dy);
        if ($d < 0.001) {
            return [[round($x0 + $r0, 1), round($y0, 1)]];
        }
        if ($d > $r0 + $r1 || $d < abs($r0 - $r1)) {
            $t = $r0 / max($r0 + $r1, 0.001);

            return [[round($x0 + $dx * $t, 1), round($y0 + $dy * $t, 1)]];
        }
        $aa = ($r0 * $r0 - $r1 * $r1 + $d * $d) / (2.0 * $d);
        $h = sqrt(max(0.0, $r0 * $r0 - $aa * $aa));
        $mx = $x0 + $dx * $aa / $d;
        $my = $y0 + $dy * $aa / $d;
        $p = [
            [round($mx + $h * $dy / $d, 1), round($my - $h * $dx / $d, 1)],
            [round($mx - $h * $dy / $d, 1), round($my + $h * $dx / $d, 1)],
        ];
        usort($p, static fn (array $u, array $v): int => ($u[0] <=> $v[0]) ?: ($u[1] <=> $v[1]));

        return $p;
    }
}
