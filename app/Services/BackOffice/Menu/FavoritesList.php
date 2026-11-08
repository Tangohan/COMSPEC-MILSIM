<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu;

/**
 * Favoris du menu back-office : liste ordonnée de pages (chemin relatif + libellé de secours). Calculs purs ;
 * l'enregistrement se fait dans BackOfficeNavPrefsRepository, par personne et par communauté.
 */
final class FavoritesList
{
    public const DEFAULT_MAX = 12;

    private const PATH_MAX = 190;

    private const LABEL_MAX = 80;

    /**
     * Chemin d'application accepté : relatif, sans « .. » ni double barre, requête simple facultative.
     * Une URL complète du site est ramenée à son chemin (le préfixe /public et le domaine sont retirés).
     */
    public static function normalizePath(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > 400 || str_starts_with($raw, '//') || str_contains($raw, '\\')) {
            return null;
        }
        if (preg_match('#^https?://#i', $raw)) {
            $parts = parse_url($raw);
            if (!is_array($parts)) {
                return null;
            }
            $raw = (string) ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
        $hash = strpos($raw, '#');
        if ($hash !== false) {
            $raw = substr($raw, 0, $hash);
        }
        $query = '';
        $q = strpos($raw, '?');
        if ($q !== false) {
            $query = substr($raw, $q + 1);
            $raw = substr($raw, 0, $q);
        }
        $path = trim($raw, '/');
        foreach (['public/', 'index.php/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip));
            }
        }
        if ($path === '' || str_contains($path, '..') || str_contains($path, '//')
            || !preg_match('#^[a-z0-9][a-z0-9\-_./]*$#i', $path)) {
            return null;
        }
        if ($query !== '' && !preg_match('#^[A-Za-z0-9_\-.=&%]{1,120}$#', $query)) {
            $query = '';
        }
        $out = $path . ($query !== '' ? '?' . $query : '');

        return strlen($out) <= self::PATH_MAX ? $out : null;
    }

    public static function cleanLabel(string $label): string
    {
        $label = trim(preg_replace('/\s+/u', ' ', strip_tags($label)) ?? '');

        return mb_substr($label, 0, self::LABEL_MAX, 'UTF-8');
    }

    /**
     * Liste lue en base, nettoyée (doublons, entrées invalides, plafond).
     *
     * @return list<array{path: string, label: string}>
     */
    public static function sanitize(mixed $raw, int $max = self::DEFAULT_MAX): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            $path = is_array($row) ? (string) ($row['path'] ?? '') : (is_string($row) ? $row : '');
            $path = self::normalizePath($path);
            if ($path === null || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $out[] = ['path' => $path, 'label' => self::cleanLabel(is_array($row) ? (string) ($row['label'] ?? '') : '')];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** @param list<array{path: string, label: string}> $list */
    public static function has(array $list, string $path): bool
    {
        foreach ($list as $row) {
            if ($row['path'] === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ajoute ou retire une page.
     *
     * @param list<array{path: string, label: string}> $list
     * @return array{0: list<array{path: string, label: string}>, 1: 'added'|'removed'|'full'}
     */
    public static function toggle(array $list, string $path, string $label = '', int $max = self::DEFAULT_MAX): array
    {
        if (self::has($list, $path)) {
            return [self::remove($list, $path), 'removed'];
        }
        if (count($list) >= $max) {
            return [$list, 'full'];
        }
        $list[] = ['path' => $path, 'label' => self::cleanLabel($label)];

        return [$list, 'added'];
    }

    /**
     * @param list<array{path: string, label: string}> $list
     * @return list<array{path: string, label: string}>
     */
    public static function remove(array $list, string $path): array
    {
        return array_values(array_filter($list, static fn (array $row): bool => $row['path'] !== $path));
    }

    /**
     * Monte (-1) ou descend (+1) une page d'un cran.
     *
     * @param list<array{path: string, label: string}> $list
     * @return list<array{path: string, label: string}>
     */
    public static function move(array $list, string $path, int $step): array
    {
        $i = null;
        foreach ($list as $k => $row) {
            if ($row['path'] === $path) {
                $i = $k;
                break;
            }
        }
        $j = $i === null ? null : $i + ($step < 0 ? -1 : 1);
        if ($i === null || $j < 0 || $j >= count($list)) {
            return $list;
        }
        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];

        return $list;
    }

    /**
     * Nouvel ordre envoyé par le glisser-déposer. Les pages absentes de l'ordre gardent leur place relative, à la fin ;
     * les chemins inconnus sont ignorés.
     *
     * @param list<array{path: string, label: string}> $list
     * @param list<string> $order
     * @return list<array{path: string, label: string}>
     */
    public static function reorder(array $list, array $order): array
    {
        $byPath = [];
        foreach ($list as $row) {
            $byPath[$row['path']] = $row;
        }
        $out = [];
        foreach ($order as $path) {
            $path = self::normalizePath((string) $path);
            if ($path !== null && isset($byPath[$path])) {
                $out[] = $byPath[$path];
                unset($byPath[$path]);
            }
        }

        return array_values(array_merge($out, array_values($byPath)));
    }
}
