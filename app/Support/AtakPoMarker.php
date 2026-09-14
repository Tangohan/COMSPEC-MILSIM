<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Points d’objectif carte : libellé PO (éventuellement numéroté) et rayon de confirmation.
 */
final class AtakPoMarker
{
    public const RADIUS_M = 20.0;

    public static function isPoLabel(string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        return preg_match('/^PO(?:[\s\-_.#]*\d+|[\s\-_.]+[A-Z0-9]{1,16})?$/iu', $label) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeData(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function labelOf(array $data, string $armaName = ''): string
    {
        return ArmaMarkerLabel::displayLabel($armaName, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function isPoMarker(array $data, string $armaName = ''): bool
    {
        if (!empty($data['po'])) {
            return true;
        }

        return self::isPoLabel(self::labelOf($data, $armaName));
    }

    /**
     * @param array<string, mixed> $data
     * @return array{x: float, y: float}|null
     */
    public static function worldPosition(array $data): ?array
    {
        $pos = $data['pos'] ?? null;
        if (is_array($pos) && isset($pos[0], $pos[1]) && is_numeric($pos[0]) && is_numeric($pos[1])) {
            $x = (float) $pos[0];
            $y = (float) $pos[1];
        } else {
            $xRaw = $data['pos_x'] ?? $data['x'] ?? null;
            $yRaw = $data['pos_y'] ?? $data['y'] ?? null;
            if (!is_numeric($xRaw) || !is_numeric($yRaw)) {
                return null;
            }
            $x = (float) $xRaw;
            $y = (float) $yRaw;
        }
        if (!is_finite($x) || !is_finite($y) || (abs($x) < 0.5 && abs($y) < 0.5)) {
            return null;
        }

        return ['x' => $x, 'y' => $y];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function radiusM(array $data): float
    {
        $raw = $data['po_radius_m'] ?? $data['radius_m'] ?? self::RADIUS_M;
        $radius = is_numeric($raw) ? (float) $raw : self::RADIUS_M;
        if (!is_finite($radius) || $radius < 1.0) {
            return self::RADIUS_M;
        }

        return $radius;
    }

    public static function distanceM(float $ax, float $ay, float $bx, float $by): float
    {
        return hypot($bx - $ax, $by - $ay);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function annotate(array $data): array
    {
        $data['po'] = true;
        if (!isset($data['po_radius_m']) || !is_numeric($data['po_radius_m'])) {
            $data['po_radius_m'] = self::RADIUS_M;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function withReached(array $data, string $callsign, float $distanceM): array
    {
        $data = self::annotate($data);
        $data['reached'] = true;
        if (empty($data['reached_at'])) {
            $data['reached_at'] = gmdate('c');
        }
        $data['reached_by'] = $callsign;
        $data['reached_distance_m'] = round($distanceM, 1);

        return $data;
    }

    /**
     * Conserve un point déjà confirmé quand le jeu republie le marqueur.
     *
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $previous
     * @return array<string, mixed>
     */
    public static function preserveReached(array $incoming, array $previous): array
    {
        if (empty($previous['reached'])) {
            return $incoming;
        }
        $incoming['reached'] = true;
        $incoming['reached_at'] = $previous['reached_at'] ?? $incoming['reached_at'] ?? null;
        $incoming['reached_by'] = $previous['reached_by'] ?? $incoming['reached_by'] ?? null;
        $incoming['reached_distance_m'] = $previous['reached_distance_m'] ?? $incoming['reached_distance_m'] ?? null;
        $incoming['po'] = true;
        $incoming['po_radius_m'] = $previous['po_radius_m'] ?? $incoming['po_radius_m'] ?? self::RADIUS_M;

        return $incoming;
    }
}
