<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Contrôle des volumes relevés : les hitbox de mods communautaires
 * dépassent parfois 300 m. On clamp avant toute extrusion.
 */
final class AtakSceneBounds
{
    public const MAX_WIDTH = 80.0;
    public const MAX_DEPTH = 80.0;
    public const MAX_HEIGHT = 48.0;
    public const MIN_EDGE = 2.0;
    public const MIN_HEIGHT = 2.0;

    public const SHAPE_BUILDING = 'building';
    public const SHAPE_POLE = 'pole';
    public const SHAPE_RIBBON = 'ribbon';
    public const SHAPE_PANEL = 'panel';

    /**
     * Classe morphologique avant tout clamp d’arête minimale.
     * Une emprise < 1 m sur une dimension avec une grande hauteur n’est pas un bâtiment.
     */
    public static function shapeClass(float $width, float $depth, float $height): string
    {
        $short = min($width, $depth);
        $long = max($width, $depth);
        $area = max(0.01, $width * $depth);
        if ($short < 1.15 && $height >= 3.2 && $height >= ($long * 1.15)) {
            return self::SHAPE_POLE;
        }
        if ($short < 1.0 && $height > ($short * 3.5)) {
            return self::SHAPE_POLE;
        }
        if ($short < 1.55 && $long / max(0.01, $short) >= 4.0) {
            return self::SHAPE_RIBBON;
        }
        if ($area < 9.0 && $height > 2.8 && $short < 2.2) {
            return self::SHAPE_PANEL;
        }

        return self::SHAPE_BUILDING;
    }

    /**
     * @param array<string, mixed> $object
     * @return array{width: float, depth: float, height: float, clipped: bool, reasons: list<string>, shape: string}
     */
    public static function sanitize(array $object): array
    {
        $width = self::num($object['width'] ?? $object['width_m'] ?? null, 4.0);
        $depth = self::num($object['depth'] ?? $object['depth_m'] ?? null, 4.0);
        $height = self::num($object['height'] ?? $object['height_m'] ?? null, 6.0);
        $reasons = [];
        $shape = self::shapeClass($width, $depth, $height);

        if ($shape === self::SHAPE_POLE) {
            return [
                'width' => max(0.35, min(1.1, $width)),
                'depth' => max(0.35, min(1.1, $depth)),
                'height' => max(2.0, min(16.0, $height)),
                'clipped' => true,
                'reasons' => ['pole'],
                'shape' => $shape,
            ];
        }
        if ($shape === self::SHAPE_PANEL) {
            return [
                'width' => max(0.4, min(2.4, $width)),
                'depth' => max(0.25, min(1.0, $depth)),
                'height' => max(1.2, min(6.0, $height)),
                'clipped' => true,
                'reasons' => ['panel'],
                'shape' => $shape,
            ];
        }
        if ($shape === self::SHAPE_RIBBON) {
            $long = max($width, $depth);
            $short = min($width, $depth);

            return [
                'width' => max(2.0, min(220.0, $long)),
                'depth' => max(0.35, min(1.1, $short)),
                'height' => max(0.8, min(2.6, $height)),
                'clipped' => true,
                'reasons' => ['ribbon'],
                'shape' => $shape,
            ];
        }

        if ($width > self::MAX_WIDTH) {
            $reasons[] = 'width:' . round($width, 1);
            $width = self::MAX_WIDTH;
        }
        if ($depth > self::MAX_DEPTH) {
            $reasons[] = 'depth:' . round($depth, 1);
            $depth = self::MAX_DEPTH;
        }
        if ($height > self::MAX_HEIGHT) {
            $reasons[] = 'height:' . round($height, 1);
            $height = self::MAX_HEIGHT;
        }
        $width = max(self::MIN_EDGE, $width);
        $depth = max(self::MIN_EDGE, $depth);
        $height = max(self::MIN_HEIGHT, $height);

        /* Dalles fines restantes après classification. */
        $short = min($width, $depth);
        $long = max($width, $depth);
        if ($short > 0.01 && ($long / $short) >= 5.0 && $short < 4.5 && $height > 3.0) {
            $reasons[] = 'slab:' . round($long, 1) . 'x' . round($short, 1);
            $height = min($height, 2.5);
            $shape = self::SHAPE_RIBBON;
        }

        return [
            'width' => $width,
            'depth' => $depth,
            'height' => $height,
            'clipped' => $reasons !== [],
            'reasons' => $reasons,
            'shape' => $shape,
        ];
    }

    /**
     * Murs / clôtures : allongés, plus bas, profondeur limitée.
     * Poteaux / pylônes : cylindre fin, pas une dalle verticale.
     *
     * @param array<string, mixed> $object
     * @return array{width: float, depth: float, height: float, clipped: bool, reasons: list<string>, shape: string}
     */
    public static function sanitizeObstacle(array $object): array
    {
        $kind = strtolower(trim((string) ($object['kind'] ?? 'wall')));
        $width = self::num($object['width'] ?? $object['width_m'] ?? null, 8.0);
        $depth = self::num($object['depth'] ?? $object['depth_m'] ?? null, 1.2);
        $height = self::num($object['height'] ?? $object['height_m'] ?? null, 2.0);
        $reasons = [];
        $shape = self::shapeClass($width, $depth, $height);

        if ($kind === 'pylon' || $kind === 'power' || $shape === self::SHAPE_POLE) {
            return [
                'width' => max(0.35, min(1.2, min($width, $depth))),
                'depth' => max(0.35, min(1.2, min($width, $depth))),
                'height' => max(2.0, min(18.0, $height)),
                'clipped' => true,
                'reasons' => ['pole'],
                'shape' => self::SHAPE_POLE,
            ];
        }

        if ($width > 220.0) {
            $reasons[] = 'width:' . round($width, 1);
            $width = 220.0;
        }
        if ($depth > 12.0) {
            $reasons[] = 'depth:' . round($depth, 1);
            $depth = 12.0;
        }
        /* Murs / clôtures / glissières : ruban bas — évite la ligne verticale géante. */
        if ($height > 3.2) {
            $reasons[] = 'height:' . round($height, 1);
            $height = 2.4;
        }
        $long = max($width, $depth);
        $short = min($width, $depth);
        if ($short > 1.2 && $long / max(0.01, $short) >= 3.5) {
            $depth = min($short, 0.85);
            $width = $long;
            $reasons[] = 'ribbon';
            $shape = self::SHAPE_RIBBON;
        }
        $width = max(1.0, $width);
        $depth = max(0.35, min(1.2, $depth));
        $height = max(0.7, min(2.6, $height));

        return [
            'width' => $width,
            'depth' => $depth,
            'height' => $height,
            'clipped' => $reasons !== [],
            'reasons' => $reasons,
            'shape' => $shape === self::SHAPE_BUILDING ? self::SHAPE_RIBBON : $shape,
        ];
    }

    private static function num(mixed $v, float $fallback): float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return $fallback;
        }
        $f = (float) $v;

        return is_finite($f) && $f > 0.05 ? $f : $fallback;
    }
}
