<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Arbre de navigation du back-office : groupe → entrée → page ou sous-menu → page.
 *
 * Un nœud est un tableau `label`/`href`/`active` (+ `icon`, `badge`, `warn`…).
 * Un sous-menu (3e niveau) est un enfant avec `children` et sans `href`.
 */
final class BackOfficeNavTree
{
    /** Vrai si l’enfant est un sous-menu repliable (et non une page). */
    public static function isSection(array $node): bool
    {
        return is_array($node['children'] ?? null) && ($node['children'] !== []) && (string) ($node['href'] ?? '') === '';
    }

    /**
     * Retire les pages sans droit ; un sous-menu vide disparaît, un sous-menu d’une seule page devient cette page.
     *
     * @param list<array<string, mixed>> $groups
     * @param callable(string): bool $allowed
     * @return list<array<string, mixed>>
     */
    public static function filter(array $groups, callable $allowed): array
    {
        $out = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] ?? [] as $item) {
                if (!is_array($item) || (string) ($item['href'] ?? '') === '') {
                    continue;
                }
                $children = self::filterChildren(is_array($item['children'] ?? null) ? $item['children'] : [], $allowed);
                $parentOk = $allowed((string) $item['href']);
                // Parent sans droit mais enfants autorisés : conserver le bloc, cibler le 1er enfant.
                if (!$parentOk && $children === []) {
                    continue;
                }
                if (!$parentOk) {
                    $item['href'] = self::firstHref($children) ?? (string) $item['href'];
                }
                $item['children'] = $children;
                $items[] = $item;
            }
            if ($items === []) {
                continue;
            }
            $group['items'] = $items;
            $out[] = $group;
        }

        return $out;
    }

    /**
     * @param list<mixed> $children
     * @param callable(string): bool $allowed
     * @return list<array<string, mixed>>
     */
    private static function filterChildren(array $children, callable $allowed): array
    {
        $out = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            if (self::isSection($child)) {
                $links = [];
                foreach ($child['children'] as $link) {
                    if (is_array($link) && (string) ($link['href'] ?? '') !== '' && $allowed((string) $link['href'])) {
                        $links[] = $link;
                    }
                }
                if (count($links) === 1) {
                    $out[] = $links[0];
                } elseif ($links !== []) {
                    $child['children'] = $links;
                    $out[] = $child;
                }
                continue;
            }
            if ((string) ($child['href'] ?? '') === '' || !$allowed((string) $child['href'])) {
                continue;
            }
            unset($child['children']);
            $out[] = $child;
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $nodes */
    public static function firstHref(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            if (self::isSection($node)) {
                $href = self::firstHref($node['children']);
                if ($href !== null) {
                    return $href;
                }
                continue;
            }
            if ((string) ($node['href'] ?? '') !== '') {
                return (string) $node['href'];
            }
        }

        return null;
    }

    /**
     * Désigne une seule page courante dans tout le menu.
     *
     * Priorité aux entrées marquées `active` (la plus spécifique par chemin, puis la plus profonde),
     * sinon correspondance de chemin. Pose ensuite `current` sur la page, `active` sur elle et
     * tous ses ancêtres (entrée, sous-menu), `false` partout ailleurs.
     *
     * @param list<array<string, mixed>> $groups
     * @return list<array<string, mixed>>
     */
    public static function resolve(array $groups, string $currentPath): array
    {
        $currentPath = trim($currentPath, '/');
        $flat = [];
        foreach ($groups as $g => $group) {
            foreach ($group['items'] ?? [] as $i => $item) {
                foreach ($item['children'] ?? [] as $c => $child) {
                    if (self::isSection($child)) {
                        foreach ($child['children'] as $l => $link) {
                            $flat[] = [[$g, $i, $c, $l], $link];
                        }
                    } else {
                        $flat[] = [[$g, $i, $c], $child];
                    }
                }
                // Le parent après ses enfants : à égalité, la page la plus profonde gagne.
                $flat[] = [[$g, $i], $item];
            }
        }

        $winner = self::pickWinner($flat, $currentPath);

        foreach ($groups as $g => &$group) {
            foreach ($group['items'] as $i => &$item) {
                $itemTrail = $winner !== null && $winner[0] === $g && $winner[1] === $i;
                $item['active'] = $itemTrail;
                $item['current'] = $itemTrail && count($winner) === 2;
                if (!is_array($item['children'] ?? null)) {
                    $item['children'] = [];
                }
                foreach ($item['children'] as $c => &$child) {
                    $childTrail = $itemTrail && count($winner) >= 3 && $winner[2] === $c;
                    $child['active'] = $childTrail;
                    $child['current'] = $childTrail && count($winner) === 3;
                    if (self::isSection($child)) {
                        foreach ($child['children'] as $l => &$link) {
                            $link['active'] = $childTrail && count($winner) === 4 && $winner[3] === $l;
                            $link['current'] = $link['active'];
                        }
                        unset($link);
                    }
                }
                unset($child);
            }
            unset($item);
        }
        unset($group);

        return $groups;
    }

    /**
     * @param list<array{0: list<int|string>, 1: array<string, mixed>}> $flat
     * @return list<int|string>|null
     */
    private static function pickWinner(array $flat, string $currentPath): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($flat as [$pos, $node]) {
            if (empty($node['active'])) {
                continue;
            }
            $score = self::score((string) ($node['href'] ?? ''), $currentPath);
            if ($score > $bestScore) {
                $best = $pos;
                $bestScore = $score;
            }
        }
        if ($best !== null) {
            return $best;
        }

        foreach ($flat as [$pos, $node]) {
            $path = self::hrefPath((string) ($node['href'] ?? ''));
            $score = self::score((string) ($node['href'] ?? ''), $currentPath);
            // Un préfixe trop court (« back-office », « jnet ») ne désigne pas une page.
            if ($score <= 0 || ($score < 1000 && substr_count($path, '/') < 1)) {
                continue;
            }
            if ($score > $bestScore) {
                $best = $pos;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function hrefPath(string $href): string
    {
        return function_exists('back_office_nav_href_to_path')
            ? back_office_nav_href_to_path($href)
            : trim((string) (parse_url($href, PHP_URL_PATH) ?? ''), '/');
    }

    private static function score(string $href, string $currentPath): int
    {
        $path = self::hrefPath($href);
        if ($path === '' || $currentPath === '') {
            return 0;
        }
        if ($currentPath === $path) {
            return 1000 + strlen($path);
        }

        return str_starts_with($currentPath, $path . '/') ? strlen($path) : 0;
    }
}
