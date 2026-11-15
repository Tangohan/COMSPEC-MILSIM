<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\AtakDataRepository;

/**
 * Calques SSE des cartes : fonctions pures (validation des paramètres, conversions de coordonnées, simplification des
 * pistes). Aucun accès base ni session.
 *
 * Repère : toutes les coordonnées servies sont les MÈTRES DU MONDE ARMA de la carte demandée, [x (est), y (nord)].
 * C'est le repère que lisent déjà les cartes du site (Leaflet « MGRS_CRS » : latlng = [y, x]) ; le décalage propre à
 * chaque carte (offsetX/offsetY du poste TOC, applyOffset de la carte ATAK) reste appliqué côté carte, comme pour tous
 * les autres calques. Les grilles des téléphones (6, 8 ou 10 chiffres) passent par
 * AtakDataRepository::mapGridToWorldApprox, la conversion déjà utilisée pour les comptes rendus.
 */
final class SseMapLayersGeo
{
    /** Nom du repère déclaré dans le GeoJSON (ce n'est pas du WGS84). */
    public const CRS_NAME = 'urn:comspec:crs:arma-world-m';

    /** Valeurs de « periode » reconnues (heures ; 0 = sans borne basse). */
    public const PERIODS = ['24h' => 24, '7j' => 168, '30j' => 720, 'tout' => 0];

    public const DEFAULT_PERIOD = '30j';

    /** Écart maximal entre « depuis » et « jusqua » quand les deux sont donnés (jours). */
    public const MAX_SPAN_DAYS = 366;

    /** Plus grande coordonnée acceptée (mètres) : au-delà, ce n'est pas un monde Arma. */
    public const MAX_WORLD_M = 100000.0;

    /**
     * Types demandés (« controles,lieux,pistes ») → liste dédoublonnée dans l'ordre canonique ; null si un type est inconnu.
     *
     * @param array<string, string> $allowed
     * @return list<string>|null
     */
    public static function parseTypes(string $raw, array $allowed): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $asked = [];
        foreach (explode(',', $raw) as $t) {
            $t = strtolower(trim($t));
            if ($t === '') {
                continue;
            }
            if (!isset($allowed[$t])) {
                return null;
            }
            $asked[$t] = true;
        }

        return array_values(array_filter(array_keys($allowed), static fn (string $k): bool => isset($asked[$k])));
    }

    /**
     * Boîte « xmin,ymin,xmax,ymax » en mètres du monde ; null si absente, false si invalide.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null|false
     */
    public static function parseBbox(string $raw): array|null|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $parts = explode(',', $raw);
        if (count($parts) !== 4) {
            return false;
        }
        $v = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (preg_match('/^-?\d{1,7}(\.\d{1,3})?$/', $p) !== 1) {
                return false;
            }
            $f = (float) $p;
            if (abs($f) > self::MAX_WORLD_M) {
                return false;
            }
            $v[] = $f;
        }
        if ($v[0] > $v[2] || $v[1] > $v[3]) {
            return false;
        }

        return [$v[0], $v[1], $v[2], $v[3]];
    }

    /**
     * Date ou date-heure ISO (« 2026-11-14 », « 2026-11-14T10:20 », « …:30Z », « …+01:00 ») → « Y-m-d H:i:s » en UTC ;
     * null si vide, false si invalide.
     */
    public static function parseInstant(string $raw, bool $endOfDay = false): string|null|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return false;
            }

            return $raw . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?$/', $raw) !== 1) {
            return false;
        }
        try {
            $dt = new \DateTimeImmutable(str_replace(' ', 'T', $raw), new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return false;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * Fenêtre de temps : « depuis » / « jusqua » explicites, sinon « periode », sinon la fenêtre par défaut.
     *
     * @return array{since: ?string, until: ?string, period: string}|null null = paramètres invalides
     */
    public static function window(string $since, string $until, string $period, ?\DateTimeImmutable $now = null): ?array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $s = self::parseInstant($since);
        $u = self::parseInstant($until, true);
        if ($s === false || $u === false) {
            return null;
        }
        if ($s !== null || $u !== null) {
            if ($s !== null && $u !== null) {
                if ($s > $u || (strtotime($u . ' UTC') - strtotime($s . ' UTC')) > self::MAX_SPAN_DAYS * 86400) {
                    return null;
                }
            }

            return ['since' => $s, 'until' => $u, 'period' => 'custom'];
        }
        $period = $period === '' ? self::DEFAULT_PERIOD : strtolower(trim($period));
        if (!isset(self::PERIODS[$period])) {
            return null;
        }
        $hours = self::PERIODS[$period];

        return [
            'since' => $hours > 0 ? $now->modify('-' . $hours . ' hours')->format('Y-m-d H:i:s') : null,
            'until' => null,
            'period' => $period,
        ];
    }

    /** Position de jeu plausible : nombres finis, bornés, et pas le (0, 0) que le jeu envoie quand il ne sait pas. */
    public static function validWorldPoint(mixed $x, mixed $y): bool
    {
        if (!is_numeric($x) || !is_numeric($y)) {
            return false;
        }
        $fx = (float) $x;
        $fy = (float) $y;
        if (!is_finite($fx) || !is_finite($fy) || abs($fx) > self::MAX_WORLD_M || abs($fy) > self::MAX_WORLD_M) {
            return false;
        }

        return abs($fx) >= 0.5 || abs($fy) >= 0.5;
    }

    /**
     * Grille Arma compacte d'un téléphone (« 180097 », « 18000970 »…) → [x, y] en mètres du monde (centre de case) ; null
     * si la grille n'est pas lisible (moins de 6 chiffres, nombre impair de chiffres).
     *
     * @return array{0: float, 1: float}|null
     */
    public static function gridToWorld(?string $grid): ?array
    {
        $grid = trim((string) $grid);
        // Une grille ne contient que des chiffres et des séparateurs : jamais un texte libre.
        if ($grid === '' || preg_match('/^[0-9 ]{6,24}$/', $grid) !== 1) {
            return null;
        }
        [$x, $y] = AtakDataRepository::mapGridToWorldApprox($grid);

        return $x !== null && $y !== null ? [$x, $y] : null;
    }

    public static function inBbox(float $x, float $y, ?array $bbox): bool
    {
        return $bbox === null || ($x >= $bbox[0] && $x <= $bbox[2] && $y >= $bbox[1] && $y <= $bbox[3]);
    }

    /**
     * La piste touche-t-elle la boîte (au moins un sommet dedans, ou boîte englobante qui recoupe) ?
     *
     * @param list<array{0: float, 1: float}> $coords
     * @param array{0: float, 1: float, 2: float, 3: float}|null $bbox
     */
    public static function trackTouchesBbox(array $coords, ?array $bbox): bool
    {
        if ($bbox === null || $coords === []) {
            return true;
        }
        $minX = $maxX = $coords[0][0];
        $minY = $maxY = $coords[0][1];
        foreach ($coords as $c) {
            $minX = min($minX, $c[0]);
            $maxX = max($maxX, $c[0]);
            $minY = min($minY, $c[1]);
            $maxY = max($maxY, $c[1]);
        }

        return !($maxX < $bbox[0] || $minX > $bbox[2] || $maxY < $bbox[1] || $minY > $bbox[3]);
    }

    /**
     * Simplification d'une piste (Douglas-Peucker itératif, tolérance en mètres) puis plafond de sommets par
     * échantillonnage régulier. Les extrémités sont toujours gardées. Renvoie les INDICES gardés, dans l'ordre, pour que
     * l'appelant reporte les horodatages des sommets conservés.
     *
     * @param list<array{0: float, 1: float}> $pts
     * @return list<int>
     */
    public static function simplifyIndices(array $pts, float $toleranceM, int $maxVertices): array
    {
        $n = count($pts);
        if ($n <= 2) {
            return array_keys($pts);
        }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        while ($stack !== []) {
            [$a, $b] = array_pop($stack);
            $maxD = 0.0;
            $idx = -1;
            for ($i = $a + 1; $i < $b; $i++) {
                $d = self::segmentDistance($pts[$i], $pts[$a], $pts[$b]);
                if ($d > $maxD) {
                    $maxD = $d;
                    $idx = $i;
                }
            }
            if ($idx !== -1 && $maxD > $toleranceM) {
                $keep[$idx] = true;
                $stack[] = [$a, $idx];
                $stack[] = [$idx, $b];
            }
        }
        $out = [];
        foreach ($keep as $i => $k) {
            if ($k) {
                $out[] = $i;
            }
        }
        $maxVertices = max(2, $maxVertices);
        if (count($out) > $maxVertices) {
            $m = count($out);
            $sampled = [];
            for ($j = 0; $j < $maxVertices; $j++) {
                $sampled[] = $out[(int) round($j * ($m - 1) / ($maxVertices - 1))];
            }
            $out = array_values(array_unique($sampled));
        }

        return $out;
    }

    /**
     * @param array{0: float, 1: float} $p
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private static function segmentDistance(array $p, array $a, array $b): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 <= 0.0) {
            return hypot($p[0] - $a[0], $p[1] - $a[1]);
        }
        $t = max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $len2));

        return hypot($p[0] - ($a[0] + $t * $dx), $p[1] - ($a[1] + $t * $dy));
    }

    /** « Y-m-d H:i:s » (UTC, tel que stocké) → ISO 8601 « …Z » ; chaîne vide si illisible. */
    public static function isoUtc(?string $stored): string
    {
        $stored = trim((string) $stored);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $stored, $m) !== 1) {
            return '';
        }

        return $m[1] . 'T' . $m[2] . 'Z';
    }
}
