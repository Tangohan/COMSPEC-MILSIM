<?php

declare(strict_types=1);

namespace App\Services\AtakToc\Purge;

/**
 * Périmètre d'une purge de la carte du poste TOC : types, auteurs ou unités, âge, zone (rectangle ou polygone), cycle
 * courant. Normalise ce que le navigateur envoie (jamais cru tel quel) et dit si un élément en fait partie.
 *
 * Types : marker (marqueurs), form, route, zone (tracés par famille), ping (pings), track (pistes périmées seulement :
 * seulement une piste muette depuis plus d'une heure). La communauté et la carte ne font pas partie du périmètre : elles
 * viennent de la session et de l'URL de la route, jamais d'un champ libre.
 *
 * @phpstan-type Scope array{types: list<string>, authors: list<string>, olderThanHours: ?int, zone: ?array{type: string, rect?: array{0: float, 1: float, 2: float, 3: float}, pts?: list<array{0: float, 1: float}>}, cycleOnly: bool, keepPinned: bool, archiveFirst: bool}
 */
final class TocScope
{
    public const TYPES = ['marker', 'form', 'route', 'zone', 'ping', 'track'];
    /** Types qu'on peut remettre (corbeille) ; les autres sont retirés pour de bon. */
    public const RECOVERABLE = ['marker', 'form', 'route', 'zone'];
    public const MAX_AUTHORS = 20;
    public const MAX_ZONE_POINTS = 64;
    public const MIN_TRACK_AGE_HOURS = 1;
    public const MAX_AGE_HOURS = 8760;

    /**
     * @param array<string, mixed> $in
     * @return Scope
     */
    public static function normalize(array $in): array
    {
        $types = [];
        foreach (is_array($in['types'] ?? null) ? $in['types'] : [] as $t) {
            if (is_string($t) && in_array(strtolower($t), self::TYPES, true)) {
                $types[strtolower($t)] = true;
            }
        }
        $authors = [];
        foreach (is_array($in['authors'] ?? null) ? $in['authors'] : [] as $a) {
            $a = is_string($a) ? trim(mb_substr($a, 0, 60)) : '';
            if ($a !== '' && !isset($authors[mb_strtolower($a)])) {
                $authors[mb_strtolower($a)] = $a;
            }
            if (count($authors) >= self::MAX_AUTHORS) {
                break;
            }
        }
        $age = $in['olderThanHours'] ?? $in['older_than_hours'] ?? null;
        $age = is_numeric($age) && (int) $age > 0 ? min(self::MAX_AGE_HOURS, (int) $age) : null;

        return [
            'types' => array_values(array_filter(self::TYPES, static fn (string $t): bool => isset($types[$t]))),
            'authors' => array_values($authors),
            'olderThanHours' => $age,
            'zone' => self::zone($in['zone'] ?? null),
            'cycleOnly' => !empty($in['cycleOnly'] ?? $in['cycle_only'] ?? false),
            'keepPinned' => !array_key_exists('keepPinned', $in) && !array_key_exists('keep_pinned', $in) ? true : !empty($in['keepPinned'] ?? $in['keep_pinned']),
            'archiveFirst' => !array_key_exists('archiveFirst', $in) && !array_key_exists('archive_first', $in) ? true : !empty($in['archiveFirst'] ?? $in['archive_first']),
        ];
    }

    /** @return array{type: string, rect?: array{0: float, 1: float, 2: float, 3: float}, pts?: list<array{0: float, 1: float}>}|null */
    private static function zone(mixed $z): ?array
    {
        if (!is_array($z)) {
            return null;
        }
        $type = (string) ($z['type'] ?? '');
        if ($type === 'rect' && is_array($z['rect'] ?? null) && count($z['rect']) === 4 && count(array_filter($z['rect'], 'is_numeric')) === 4) {
            [$x1, $y1, $x2, $y2] = array_map('floatval', array_values($z['rect']));

            return ['type' => 'rect', 'rect' => [min($x1, $x2), min($y1, $y2), max($x1, $x2), max($y1, $y2)]];
        }
        if ($type === 'polygon' && is_array($z['pts'] ?? null)) {
            $pts = [];
            foreach ($z['pts'] as $p) {
                if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])) {
                    $pts[] = [(float) $p[0], (float) $p[1]];
                }
                if (count($pts) >= self::MAX_ZONE_POINTS) {
                    break;
                }
            }
            if (count($pts) >= 3) {
                return ['type' => 'polygon', 'pts' => $pts];
            }
        }

        return null;
    }

    /** Aucun filtre hors types : la purge vise toute la carte pour ces types (phrase de confirmation renforcée). @param Scope $scope */
    public static function isWholeMap(array $scope): bool
    {
        return $scope['authors'] === [] && $scope['olderThanHours'] === null && $scope['zone'] === null && !$scope['cycleOnly'];
    }

    /**
     * @param Scope $scope
     * @param array<string, mixed> $el élément de TocElementStore
     * @param int $now secondes Unix
     * @param int $cycleStart début du cycle courant (0 : non demandé)
     */
    public static function matches(array $scope, array $el, int $now, int $cycleStart): bool
    {
        if (!in_array($el['kind'], $scope['types'], true)) {
            return false;
        }
        // Une piste vue depuis moins d'une heure est une unité en liaison : jamais purgée.
        if ($el['kind'] === 'track' && (int) $el['at'] > $now - self::MIN_TRACK_AGE_HOURS * 3600) {
            return false;
        }
        if ($scope['authors'] !== []) {
            $hay = [mb_strtolower((string) $el['author']), mb_strtolower((string) $el['unit'])];
            $ok = false;
            foreach ($scope['authors'] as $a) {
                if (in_array(mb_strtolower($a), $hay, true)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
        }
        if ($scope['olderThanHours'] !== null && (int) $el['at'] > $now - $scope['olderThanHours'] * 3600) {
            return false;
        }
        if ($scope['cycleOnly'] && (int) $el['at'] < $cycleStart) {
            return false;
        }
        if ($scope['zone'] !== null) {
            if ($el['x'] === null || $el['y'] === null || !self::inZone($scope['zone'], (float) $el['x'], (float) $el['y'])) {
                return false;
            }
        }

        return true;
    }

    /** @param array{type: string, rect?: array{0: float, 1: float, 2: float, 3: float}, pts?: list<array{0: float, 1: float}>} $zone */
    public static function inZone(array $zone, float $x, float $y): bool
    {
        if ($zone['type'] === 'rect') {
            [$x1, $y1, $x2, $y2] = $zone['rect'] ?? [0.0, 0.0, 0.0, 0.0];

            return $x >= $x1 && $x <= $x2 && $y >= $y1 && $y <= $y2;
        }
        $in = false;
        $pts = $zone['pts'] ?? [];
        $n = count($pts);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            if (($pts[$i][1] > $y) !== ($pts[$j][1] > $y)
                && $x < ($pts[$j][0] - $pts[$i][0]) * ($y - $pts[$i][1]) / ($pts[$j][1] - $pts[$i][1]) + $pts[$i][0]) {
                $in = !$in;
            }
        }

        return $in;
    }
}
