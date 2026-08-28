<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakGeoRoadRepository;

/**
 * Planification d’itinéraire sur le graphe routier ingéré depuis Arma (A* + repli ligne directe).
 */
final class AtakRoutePlanningService
{
    private const DEFAULT_SNAP_M = 150.0;
    private const BBOX_PADDING_M = 800.0;

    public function __construct(
        private ?AtakGeoRoadRepository $roads = null,
    ) {
        $this->roads ??= new AtakGeoRoadRepository();
    }

    /**
     * @param array{0: float, 1: float} $start
     * @param array{0: float, 1: float} $end
     * @param list<array{0: float, 1: float}> $via
     * @return array<string, mixed>
     */
    public function plan(
        int $tenantId,
        int $mapId,
        array $start,
        array $end,
        array $via = [],
        string $mode = 'foot',
        float $snapM = self::DEFAULT_SNAP_M,
    ): array {
        $start = $this->normalizePoint($start);
        $end = $this->normalizePoint($end);
        if ($start === null || $end === null) {
            return $this->error('Coordonnées de départ ou d’arrivée invalides.');
        }

        $legs = [$start];
        foreach ($via as $point) {
            $norm = $this->normalizePoint($point);
            if ($norm !== null) {
                $legs[] = $norm;
            }
        }
        $legs[] = $end;

        $allPoints = [];
        $totalDistance = 0.0;
        $planMode = 'ROAD';
        $warnings = [];

        for ($i = 0; $i < count($legs) - 1; $i++) {
            $leg = $this->planLeg($tenantId, $mapId, $legs[$i], $legs[$i + 1], $snapM);
            if ($leg['mode'] === 'DIRECT') {
                $planMode = 'DIRECT';
                if ($leg['warning'] !== '') {
                    $warnings[] = $leg['warning'];
                }
            }
            if ($i > 0 && count($allPoints) > 0) {
                array_shift($leg['points']);
            }
            array_push($allPoints, ...$leg['points']);
            $totalDistance += (float) $leg['distance_m'];
        }

        $minKph = $mode === 'vehicle' ? 5.0 : 4.5;
        $speedKph = $mode === 'vehicle' ? 40.0 : 5.0;
        $etaSec = $totalDistance / max($speedKph / 3.6, $minKph / 3.6, 1.0);

        return [
            'ok' => true,
            'plan_mode' => $planMode,
            'mode' => $mode === 'vehicle' ? 'vehicle' : 'foot',
            'points' => array_map(static fn (array $p): array => ['x' => $p[0], 'y' => $p[1]], $allPoints),
            'distance_m' => round($totalDistance, 1),
            'eta_seconds' => (int) round($etaSec),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param array{0: float, 1: float} $start
     * @param array{0: float, 1: float} $end
     * @return array{points: list<array{0: float, 1: float}>, distance_m: float, mode: string, warning: string}
     */
    private function planLeg(int $tenantId, int $mapId, array $start, array $end, float $snapM): array
    {
        $minX = min($start[0], $end[0]) - self::BBOX_PADDING_M;
        $maxX = max($start[0], $end[0]) + self::BBOX_PADDING_M;
        $minY = min($start[1], $end[1]) - self::BBOX_PADDING_M;
        $maxY = max($start[1], $end[1]) + self::BBOX_PADDING_M;

        $segments = $this->roads->segmentsForPlanning($tenantId, $mapId, $minX, $minY, $maxX, $maxY);
        if ($segments === []) {
            return $this->directLeg($start, $end, 'Aucun segment routier dans la zone — tracé direct.');
        }

        $graph = $this->buildGraph($segments);
        if ($graph['adj'] === []) {
            return $this->directLeg($start, $end, 'Graphe routier vide — tracé direct.');
        }

        $startNode = $this->snapNode($start, $graph['nodes'], $snapM);
        $endNode = $this->snapNode($end, $graph['nodes'], $snapM);
        if ($startNode === null || $endNode === null) {
            return $this->directLeg($start, $end, 'Impossible d’accrocher le départ ou l’arrivée au réseau routier.');
        }

        $path = $this->aStar($startNode, $endNode, $graph['adj'], $graph['coords']);
        if ($path === []) {
            return $this->directLeg($start, $end, 'Aucun chemin routier trouvé — tracé direct.');
        }

        $points = [$start];
        foreach ($path as $nodeKey) {
            $points[] = $graph['coords'][$nodeKey];
        }
        $points[] = $end;
        $points = $this->dedupePoints($points);

        return [
            'points' => $points,
            'distance_m' => $this->pathLength($points),
            'mode' => 'ROAD',
            'warning' => '',
        ];
    }

    /**
     * @param list<array{a: array{0: float, 1: float}, b: array{0: float, 1: float}, one_way: bool}> $segments
     * @return array{adj: array<string, list<array{0: string, 1: float}>>, nodes: list<string>, coords: array<string, array{0: float, 1: float}>}
     */
    private function buildGraph(array $segments): array
    {
        $adj = [];
        $coords = [];

        foreach ($segments as $seg) {
            $a = $this->nodeKey($seg['a'][0], $seg['a'][1]);
            $b = $this->nodeKey($seg['b'][0], $seg['b'][1]);
            $coords[$a] = [$seg['a'][0], $seg['a'][1]];
            $coords[$b] = [$seg['b'][0], $seg['b'][1]];
            $w = self::dist2d($seg['a'][0], $seg['a'][1], $seg['b'][0], $seg['b'][1]);
            if ($w < 0.5) {
                continue;
            }
            $adj[$a][] = [$b, $w];
            if (!$seg['one_way']) {
                $adj[$b][] = [$a, $w];
            }
        }

        return [
            'adj' => $adj,
            'nodes' => array_keys($coords),
            'coords' => $coords,
        ];
    }

    /**
     * @param array{0: float, 1: float} $point
     * @param list<string> $nodes
     */
    private function snapNode(array $point, array $nodes, float $maxM): ?string
    {
        $best = null;
        $bestD = $maxM;
        foreach ($nodes as $key) {
            [$x, $y] = array_map('floatval', explode(',', $key, 2));
            $d = self::dist2d($point[0], $point[1], $x, $y);
            if ($d <= $bestD) {
                $bestD = $d;
                $best = $key;
            }
        }

        return $best;
    }

    /**
     * @param array<string, list<array{0: string, 1: float}>> $adj
     * @param array<string, array{0: float, 1: float}> $coords
     * @return list<string>
     */
    private function aStar(string $start, string $goal, array $adj, array $coords): array
    {
        [$gx, $gy] = $coords[$goal];
        $open = [[0.0, $start]];
        $gScore = [$start => 0.0];
        $cameFrom = [];

        while ($open !== []) {
            usort($open, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            [, $current] = array_shift($open);
            if ($current === $goal) {
                $path = [$current];
                while (isset($cameFrom[$current])) {
                    $current = $cameFrom[$current];
                    array_unshift($path, $current);
                }

                return $path;
            }
            foreach ($adj[$current] ?? [] as [$neighbor, $cost]) {
                $tentative = ($gScore[$current] ?? INF) + $cost;
                if ($tentative >= ($gScore[$neighbor] ?? INF)) {
                    continue;
                }
                $cameFrom[$neighbor] = $current;
                $gScore[$neighbor] = $tentative;
                [$nx, $ny] = $coords[$neighbor];
                $f = $tentative + self::dist2d($nx, $ny, $gx, $gy);
                $open[] = [$f, $neighbor];
            }
        }

        return [];
    }

    /**
     * @param array{0: float, 1: float} $start
     * @param array{0: float, 1: float} $end
     * @return array{points: list<array{0: float, 1: float}>, distance_m: float, mode: string, warning: string}
     */
    private function directLeg(array $start, array $end, string $warning): array
    {
        $points = [$start, $end];

        return [
            'points' => $points,
            'distance_m' => self::dist2d($start[0], $start[1], $end[0], $end[1]),
            'mode' => 'DIRECT',
            'warning' => $warning,
        ];
    }

    /** @return array<string, mixed> */
    private function error(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }

    /**
     * @param array<int|string, mixed> $point
     * @return array{0: float, 1: float}|null
     */
    private function normalizePoint(array $point): ?array
    {
        $x = $point['x'] ?? $point[0] ?? null;
        $y = $point['y'] ?? $point[1] ?? null;
        if (!is_numeric($x) || !is_numeric($y)) {
            return null;
        }

        return [(float) $x, (float) $y];
    }

    private function nodeKey(float $x, float $y): string
    {
        return sprintf('%.1f,%.1f', $x, $y);
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return list<array{0: float, 1: float}>
     */
    private function dedupePoints(array $points): array
    {
        $out = [];
        foreach ($points as $p) {
            if ($out === []) {
                $out[] = $p;
                continue;
            }
            $last = $out[count($out) - 1];
            if (self::dist2d($last[0], $last[1], $p[0], $p[1]) > 1.0) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     */
    private function pathLength(array $points): float
    {
        $total = 0.0;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $total += self::dist2d($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }

        return $total;
    }

    private static function dist2d(float $ax, float $ay, float $bx, float $by): float
    {
        $dx = $bx - $ax;
        $dy = $by - $ay;

        return sqrt($dx * $dx + $dy * $dy);
    }
}
