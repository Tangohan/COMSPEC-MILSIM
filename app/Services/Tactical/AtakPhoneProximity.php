<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Alerte de proximité : ATAK allié vs téléphone suivi.
 * Distances en mètres Arma (plan 2D). Rayons fermés, libellés métier.
 */
final class AtakPhoneProximity
{
    public const PRESETS_M = [0, 50, 100, 200, 500, 1000, 2000];
    public const DEFAULT_M = 200;
    public const EXIT_FACTOR = 1.15;

    /**
     * @return list<int>
     */
    public static function presets(): array
    {
        return self::PRESETS_M;
    }

    public static function normalize(mixed $meters): int
    {
        $n = (int) round((float) $meters);
        if (in_array($n, self::PRESETS_M, true)) {
            return $n;
        }
        if ($n <= 0) {
            return 0;
        }
        $best = self::DEFAULT_M;
        $bestDelta = PHP_INT_MAX;
        foreach (self::PRESETS_M as $preset) {
            $delta = abs($preset - $n);
            if ($delta < $bestDelta) {
                $best = $preset;
                $bestDelta = $delta;
            }
        }

        return $best;
    }

    public static function label(int $meters): string
    {
        $m = self::normalize($meters);

        return match ($m) {
            0 => 'Désactivée',
            50 => '50 mètres',
            100 => '100 mètres',
            200 => '200 mètres',
            500 => '500 mètres',
            1000 => '1 kilomètre',
            2000 => '2 kilomètres',
            default => $m . ' mètres',
        };
    }

    public static function formatDistance(float $meters): string
    {
        if ($meters < 0) {
            $meters = 0.0;
        }
        if ($meters >= 1000) {
            $km = round($meters / 100) / 10;
            $txt = number_format($km, $km >= 10 ? 0 : 1, ',', '');

            return $txt . ' km';
        }

        return ((string) (int) round($meters)) . ' m';
    }

    /**
     * @return array{inside: bool, alert: bool}
     */
    public static function evaluate(bool $wasInside, float $distanceM, int $radiusM): array
    {
        $radius = self::normalize($radiusM);
        if ($radius <= 0) {
            return ['inside' => false, 'alert' => false];
        }
        if ($distanceM <= $radius) {
            return ['inside' => true, 'alert' => !$wasInside];
        }
        if ($wasInside && $distanceM <= ($radius * self::EXIT_FACTOR)) {
            return ['inside' => true, 'alert' => false];
        }

        return ['inside' => false, 'alert' => false];
    }

    public static function toastMessage(string $displayName, float $distanceM): string
    {
        $name = trim($displayName);
        if ($name === '') {
            $name = 'Téléphone suivi';
        }

        return sprintf(
            'Téléphone proche — %s (%s)',
            $name,
            self::formatDistance($distanceM)
        );
    }
}
