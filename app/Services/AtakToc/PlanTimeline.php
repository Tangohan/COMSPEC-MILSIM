<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Position et présence des éléments d'un plan dans le temps (minutes relatives à l'heure H). Même algorithme que
 * l'éditeur (TocPlanLogic, contrat plans3d §3) : images clés (props.track), suivi d'un itinéraire (props.route),
 * fenêtre de présence (t0, t1), début des phases (meta.timeline.phases). Fonctions pures.
 */
final class PlanTimeline
{
    /**
     * Position d'un élément à l'instant t. Sans image clé (ou hors élément ponctuel) : son premier point. Entre deux
     * images clés : le long de l'itinéraire désigné par props.route s'il existe (trait de 2 points ou plus), sinon en
     * ligne droite.
     *
     * @param array<string, mixed> $el élément validé
     * @param array<string, array<string, mixed>> $byUid éléments du plan par uid (pour l'itinéraire suivi)
     * @return array{0: float, 1: float}
     */
    public static function positionAt(array $el, float $t, array $byUid): array
    {
        $base = [(float) ($el['pts'][0][0] ?? 0.0), (float) ($el['pts'][0][1] ?? 0.0)];
        $track = $el['props']['track'] ?? null;
        if (($el['geom'] ?? '') !== 'point' || !is_array($track) || $track === []) {
            return $base;
        }
        $track = array_values($track);
        $n = count($track);
        if ($t <= (float) $track[0]['t']) {
            return self::pt($track[0]['p']);
        }
        if ($t >= (float) $track[$n - 1]['t']) {
            return self::pt($track[$n - 1]['p']);
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $ta = (float) $track[$i]['t'];
            $tb = (float) $track[$i + 1]['t'];
            if ($t < $ta || $t > $tb) {
                continue;
            }
            $a = self::pt($track[$i]['p']);
            $b = self::pt($track[$i + 1]['p']);
            $u = $tb > $ta ? ($t - $ta) / ($tb - $ta) : 0.0;
            $routeUid = (string) ($el['props']['route'] ?? '');
            $route = $routeUid !== '' ? ($byUid[$routeUid] ?? null) : null;
            if (is_array($route) && ($route['geom'] ?? '') === 'line' && is_array($route['pts'] ?? null) && count($route['pts']) >= 2) {
                $line = array_map([self::class, 'pt'], array_values($route['pts']));
                $sa = self::abscissa($line, $a);
                $sb = self::abscissa($line, $b);

                return self::pointAt($line, $sa + $u * ($sb - $sa));
            }

            return [$a[0] + $u * ($b[0] - $a[0]), $a[1] + $u * ($b[1] - $a[1])];
        }

        return $base;
    }

    /**
     * Présent à l'instant t : ni masqué ni « non posé », et dans sa fenêtre [t0, t1[.
     *
     * @param array<string, mixed> $el
     */
    public static function visibleAt(array $el, float $t): bool
    {
        if (!self::placed($el)) {
            return false;
        }
        $p = is_array($el['props'] ?? null) ? $el['props'] : [];
        $t0 = is_numeric($p['t0'] ?? null) ? (float) $p['t0'] : null;
        $t1 = is_numeric($p['t1'] ?? null) ? (float) $p['t1'] : null;

        return ($t0 === null || $t >= $t0) && ($t1 === null || $t < $t1);
    }

    /**
     * Élément montré hors du temps (plan entier) : ni masqué (hidden) ni unité de l'ORBAT pas encore posée.
     *
     * @param array<string, mixed> $el
     */
    public static function placed(array $el): bool
    {
        $p = is_array($el['props'] ?? null) ? $el['props'] : [];

        return empty($p['hidden']) && ($p['placed'] ?? true) !== false;
    }

    /**
     * Début d'une phase en minutes (meta.timeline.phases), ou null si la phase n'est pas placée dans le temps.
     *
     * @param array<string, mixed> $meta
     */
    public static function phaseStart(array $meta, string $phaseUid): ?float
    {
        $v = $meta['timeline']['phases'][$phaseUid] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Plan à l'instant t : éléments présents, les éléments ponctuels déplacés à leur position du moment.
     *
     * @param list<array<string, mixed>> $elements
     * @return list<array<string, mixed>>
     */
    public static function atTime(array $elements, float $t): array
    {
        $byUid = [];
        foreach ($elements as $el) {
            $byUid[(string) ($el['uid'] ?? '')] = $el;
        }
        $out = [];
        foreach ($elements as $el) {
            if (!self::visibleAt($el, $t)) {
                continue;
            }
            if (($el['geom'] ?? '') === 'point' && !empty($el['props']['track'])) {
                [$x, $y] = self::positionAt($el, $t, $byUid);
                $el['pts'] = [[round($x, 1), round($y, 1)]];
            }
            $out[] = $el;
        }

        return $out;
    }

    /**
     * @param mixed $p
     * @return array{0: float, 1: float}
     */
    private static function pt(mixed $p): array
    {
        return [(float) ($p[0] ?? 0.0), (float) ($p[1] ?? 0.0)];
    }

    /**
     * Abscisse curviligne du point de la polyligne le plus proche de $p (le premier trouvé en cas d'égalité).
     *
     * @param list<array{0: float, 1: float}> $line
     * @param array{0: float, 1: float} $p
     */
    public static function abscissa(array $line, array $p): float
    {
        $best = INF;
        $bestS = 0.0;
        $run = 0.0;
        for ($i = 0, $n = count($line); $i < $n - 1; $i++) {
            [$ax, $ay] = $line[$i];
            [$bx, $by] = $line[$i + 1];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $len = sqrt($len2);
            $k = $len2 > 0.0 ? max(0.0, min(1.0, (($p[0] - $ax) * $dx + ($p[1] - $ay) * $dy) / $len2)) : 0.0;
            $qx = $ax + $k * $dx;
            $qy = $ay + $k * $dy;
            $d = ($p[0] - $qx) ** 2 + ($p[1] - $qy) ** 2;
            if ($d < $best) {
                $best = $d;
                $bestS = $run + $k * $len;
            }
            $run += $len;
        }

        return $bestS;
    }

    /**
     * Point de la polyligne à l'abscisse curviligne $s (bornée aux extrémités).
     *
     * @param list<array{0: float, 1: float}> $line
     * @return array{0: float, 1: float}
     */
    public static function pointAt(array $line, float $s): array
    {
        $n = count($line);
        if ($s <= 0.0) {
            return $line[0];
        }
        $run = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            [$ax, $ay] = $line[$i];
            [$bx, $by] = $line[$i + 1];
            $len = sqrt(($bx - $ax) ** 2 + ($by - $ay) ** 2);
            if ($len > 0.0 && $s <= $run + $len) {
                $k = ($s - $run) / $len;

                return [$ax + $k * ($bx - $ax), $ay + $k * ($by - $ay)];
            }
            $run += $len;
        }

        return $line[$n - 1];
    }
}
