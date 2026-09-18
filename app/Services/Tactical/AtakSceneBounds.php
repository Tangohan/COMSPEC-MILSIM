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

    /**
     * @param array<string, mixed> $object
     * @return array{width: float, depth: float, height: float, clipped: bool, reasons: list<string>}
     */
    public static function sanitize(array $object): array
    {
        $width = self::num($object['width'] ?? $object['width_m'] ?? null, 4.0);
        $depth = self::num($object['depth'] ?? $object['depth_m'] ?? null, 4.0);
        $height = self::num($object['height'] ?? $object['height_m'] ?? null, 6.0);
        $reasons = [];
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

        return [
            'width' => $width,
            'depth' => $depth,
            'height' => $height,
            'clipped' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    /**
     * Murs / clôtures : allongés, plus bas, profondeur limitée.
     *
     * @param array<string, mixed> $object
     * @return array{width: float, depth: float, height: float, clipped: bool, reasons: list<string>}
     */
    public static function sanitizeObstacle(array $object): array
    {
        $width = self::num($object['width'] ?? $object['width_m'] ?? null, 8.0);
        $depth = self::num($object['depth'] ?? $object['depth_m'] ?? null, 1.2);
        $height = self::num($object['height'] ?? $object['height_m'] ?? null, 2.0);
        $reasons = [];
        if ($width > 220.0) {
            $reasons[] = 'width:' . round($width, 1);
            $width = 220.0;
        }
        if ($depth > 12.0) {
            $reasons[] = 'depth:' . round($depth, 1);
            $depth = 12.0;
        }
        if ($height > 28.0) {
            $reasons[] = 'height:' . round($height, 1);
            $height = 28.0;
        }
        $width = max(1.0, $width);
        $depth = max(0.6, $depth);
        $height = max(0.8, $height);

        return [
            'width' => $width,
            'depth' => $depth,
            'height' => $height,
            'clipped' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    private static function num(mixed $v, float $fallback): float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return $fallback;
        }
        $f = (float) $v;

        return is_finite($f) && $f > 0.5 ? $f : $fallback;
    }
}
