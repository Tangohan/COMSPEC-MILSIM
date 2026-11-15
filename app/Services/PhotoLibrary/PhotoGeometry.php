<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

/**
 * Rectangles normalisés (0 à 1, origine en haut à gauche) des cadres de visages, des masques et des ancres de
 * commentaires : validation stricte de ce qui vient du navigateur, conversion en pixels, empreinte des zones.
 */
final class PhotoGeometry
{
    public const MODES = ['blur', 'pixel', 'fill'];

    /** Côté minimal d'un cadre (1 % de l'image) : écarte les clics sans glisser. */
    public const MIN_SIDE = 0.01;

    /** Tolérance de dépassement du bord (arrondis du navigateur), ramenée au bord. */
    public const EDGE_SLACK = 0.002;

    public const MAX_TAGS = 40;
    public const MAX_MASKS = 40;

    /**
     * @return array{x: float, y: float, w: float, h: float}|null null : valeurs absentes, non numériques, hors de l'image ou trop petites
     */
    public static function rect(mixed $x, mixed $y, mixed $w, mixed $h): ?array
    {
        $v = [];
        foreach (['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h] as $k => $raw) {
            if (!is_int($raw) && !is_float($raw) && !(is_string($raw) && preg_match('/^-?\d{1,3}(?:\.\d{1,9})?$/', trim($raw)) === 1)) {
                return null;
            }
            $f = (float) $raw;
            if (!is_finite($f)) {
                return null;
            }
            $v[$k] = $f;
        }
        $slack = self::EDGE_SLACK;
        if ($v['x'] < -$slack || $v['y'] < -$slack || $v['w'] <= 0.0 || $v['h'] <= 0.0 || $v['x'] + $v['w'] > 1.0 + $slack || $v['y'] + $v['h'] > 1.0 + $slack) {
            return null;
        }
        $x0 = max(0.0, $v['x']);
        $y0 = max(0.0, $v['y']);
        $w0 = min($v['w'], 1.0 - $x0);
        $h0 = min($v['h'], 1.0 - $y0);
        if ($w0 < self::MIN_SIDE || $h0 < self::MIN_SIDE) {
            return null;
        }

        return ['x' => round($x0, 6), 'y' => round($y0, 6), 'w' => round($w0, 6), 'h' => round($h0, 6)];
    }

    public static function mode(mixed $mode): ?string
    {
        $m = is_string($mode) ? strtolower(trim($mode)) : '';

        return in_array($m, self::MODES, true) ? $m : null;
    }

    /**
     * Boîte en pixels couvrant tout le rectangle (arrondi vers l'extérieur, jamais hors de l'image).
     *
     * @param array{x: float, y: float, w: float, h: float} $r
     * @return array{0: int, 1: int, 2: int, 3: int} x0, y0, x1, y1 (x1 et y1 exclus)
     */
    public static function pixels(array $r, int $width, int $height): array
    {
        $x0 = max(0, (int) floor($r['x'] * $width) - 1);
        $y0 = max(0, (int) floor($r['y'] * $height) - 1);
        $x1 = min($width, (int) ceil(($r['x'] + $r['w']) * $width) + 1);
        $y1 = min($height, (int) ceil(($r['y'] + $r['h']) * $height) + 1);

        return [$x0, $y0, max($x0 + 1, $x1), max($y0 + 1, $y1)];
    }

    /**
     * Empreinte des zones appliquées (ordre indifférent) : sert de clé de cache et d'invalidation.
     *
     * @param list<array{x: float|string, y: float|string, w: float|string, h: float|string, mode: string}> $zones
     */
    public static function hash(array $zones): string
    {
        $lines = [];
        foreach ($zones as $z) {
            $lines[] = sprintf('%.6F,%.6F,%.6F,%.6F,%s', (float) $z['x'], (float) $z['y'], (float) $z['w'], (float) $z['h'], (string) $z['mode']);
        }
        sort($lines, SORT_STRING);

        return sha1(implode('|', $lines));
    }

    /** Étiquette libre : 2 à 40 caractères, lettres, chiffres, espaces, tirets et apostrophes. */
    public static function label(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $s = trim((string) preg_replace('/\s+/u', ' ', $raw));
        $s = ltrim($s, '#');
        if (mb_strlen($s) < 2 || mb_strlen($s) > 40 || preg_match('/^[\p{L}\p{N}][\p{L}\p{N} \'’_.\-]*$/u', $s) !== 1) {
            return null;
        }

        return $s;
    }

    public static function labelNorm(string $label): string
    {
        $s = strtr(mb_strtolower($label), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
        ]);
        $s = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));

        return mb_substr($s, 0, 40);
    }
}
