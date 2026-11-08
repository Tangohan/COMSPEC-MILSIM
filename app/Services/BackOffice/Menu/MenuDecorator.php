<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

/**
 * Pose sur l'arbre du menu back-office (groupes → entrées → pages) les pastilles, la marque « Nouveau » et l'état
 * favori de chaque page. Calcul pur : l'arbre a déjà été filtré par les droits (BackOfficeNavTree::filter).
 *
 * Champs ajoutés à chaque nœud qui a un href :
 *   _path (chemin sans requête), _fav_key (chemin + requête), _fav, _new,
 *   _badge_keys (clés de la page et de ses sous-pages), _badge_count, _badge_important, _badge_title, _has_new.
 */
final class MenuDecorator
{
    /**
     * @param list<array<string, mixed>> $groups
     * @param array<string, array{count: int, important: bool, title: string, path: string}> $badges
     * @param list<string> $newPaths
     * @param list<string> $favKeys
     * @param callable(string): string $pathOf href → chemin d'application sans requête
     * @return list<array<string, mixed>>
     */
    public static function decorate(array $groups, array $badges, array $newPaths, array $favKeys, callable $pathOf): array
    {
        $byPath = [];
        foreach ($badges as $key => $b) {
            $byPath[$b['path']][] = (string) $key;
        }
        $newSet = array_fill_keys($newPaths, true);
        $favSet = array_fill_keys($favKeys, true);

        $walk = static function (array $node) use (&$walk, $byPath, $newSet, $favSet, $badges, $pathOf): array {
            $keys = [];
            if (isset($node['href']) && is_string($node['href']) && $node['href'] !== '') {
                $path = $pathOf($node['href']);
                $node['_path'] = $path;
                $node['_fav_key'] = FavoritesList::normalizePath($node['href']) ?? $path;
                $node['_fav'] = isset($favSet[$node['_fav_key']]);
                $node['_new'] = $path !== '' && isset($newSet[$path]);
                foreach ($byPath[$path] ?? [] as $k) {
                    $keys[$k] = true;
                }
            }
            $hasNewChild = false;
            foreach (['children', 'items'] as $field) {
                if (!is_array($node[$field] ?? null)) {
                    continue;
                }
                foreach ($node[$field] as $i => $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    $child = $walk($child);
                    $node[$field][$i] = $child;
                    foreach ($child['_badge_keys'] ?? [] as $k) {
                        $keys[$k] = true;
                    }
                    if (!empty($child['_new']) || !empty($child['_has_new'])) {
                        $hasNewChild = true;
                    }
                }
            }
            $node['_has_new'] = $hasNewChild;
            $node['_badge_keys'] = array_keys($keys);
            $count = 0;
            $important = false;
            $titles = [];
            foreach ($node['_badge_keys'] as $k) {
                $b = $badges[$k] ?? null;
                if ($b === null || $b['count'] < 1) {
                    continue;
                }
                $count += $b['count'];
                $important = $important || $b['important'];
                $titles[] = $b['title'];
            }
            $node['_badge_count'] = $count;
            $node['_badge_important'] = $important;
            $node['_badge_title'] = implode(' · ', $titles);

            return $node;
        };

        return array_map(static fn (array $g): array => $walk($g), $groups);
    }

    /**
     * Favoris à afficher : seulement les pages encore autorisées, libellé repris du menu quand la page y figure.
     *
     * @param list<array<string, mixed>> $decoratedGroups
     * @param list<array{path: string, label: string}> $favorites
     * @param callable(string): bool $visible chemin → la personne y a-t-elle encore accès
     * @param callable(string): string $titleFor chemin → titre connu (config/back_office_pages.php), '' sinon
     * @return list<array{path: string, label: string, new: bool, badge_keys: list<string>}>
     */
    public static function favorites(array $decoratedGroups, array $favorites, callable $visible, callable $titleFor): array
    {
        $labels = [];
        $meta = [];
        $walk = static function (array $node, int $depth) use (&$walk, &$labels, &$meta): void {
            if (isset($node['_fav_key'], $node['label'])) {
                $k = (string) $node['_fav_key'];
                // La page la plus profonde donne le libellé : « Candidatures » plutôt que « Recrutement ».
                if (!isset($labels[$k]) || $depth >= $labels[$k][1]) {
                    $labels[$k] = [(string) $node['label'], $depth];
                    $meta[$k] = ['new' => !empty($node['_new']), 'badge_keys' => array_values(array_filter(
                        (array) ($node['_badge_keys'] ?? []),
                        'is_string'
                    ))];
                }
            }
            foreach (['items', 'children'] as $field) {
                foreach ((array) ($node[$field] ?? []) as $child) {
                    if (is_array($child)) {
                        $walk($child, $depth + 1);
                    }
                }
            }
        };
        foreach ($decoratedGroups as $g) {
            $walk($g, 0);
        }

        $out = [];
        foreach ($favorites as $fav) {
            $path = $fav['path'];
            if (!$visible($path)) {
                continue;
            }
            $label = $labels[$path][0] ?? '';
            if ($label === '') {
                $label = $fav['label'] !== '' ? $fav['label'] : $titleFor(strtok($path, '?') ?: $path);
            }
            $out[] = [
                'path' => $path,
                'label' => $label !== '' ? $label : $path,
                'new' => $meta[$path]['new'] ?? false,
                'badge_keys' => $meta[$path]['badge_keys'] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Pages « Nouveau » encore actives : dans la fenêtre de N jours, pas encore visitées.
     *
     * @param list<array{path?: mixed, since?: mixed}> $declared
     * @param list<string> $seen
     * @return list<string>
     */
    public static function activeNew(array $declared, int $days, array $seen, int $now): array
    {
        $seenSet = array_fill_keys($seen, true);
        $out = [];
        foreach ($declared as $row) {
            $path = trim((string) ($row['path'] ?? ''), '/');
            $since = strtotime((string) ($row['since'] ?? ''));
            if ($path === '' || $since === false || isset($seenSet[$path])) {
                continue;
            }
            if ($now >= $since && $now < $since + max(1, $days) * 86400) {
                $out[] = $path;
            }
        }

        return $out;
    }

    /**
     * La page courante fait-elle partie des nouveautés ? (elle-même ou l'une de ses sous-pages)
     *
     * @param list<string> $newPaths
     */
    public static function visitedNew(array $newPaths, string $currentPath): ?string
    {
        $currentPath = trim($currentPath, '/');
        foreach ($newPaths as $path) {
            if ($currentPath === $path || str_starts_with($currentPath, $path . '/')) {
                return $path;
            }
        }

        return null;
    }
}
