<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Passage aux fichiers — lecture et conversion des tailles et poids saisis librement (cm, m, pieds/pouces, kg, livres).
 *
 * Fonctions pures, sans état : la saisie d'un joueur américain (5'11", 172 lb) et celle d'un joueur français
 * (1,80 m, 78 kg) donnent la même valeur stockée (cm, kg).
 */
final class SseMeasure
{
    public const HEIGHT_MIN = 100;

    public const HEIGHT_MAX = 250;

    public const WEIGHT_MIN = 30;

    public const WEIGHT_MAX = 250;

    private const CM_PER_INCH = 2.54;

    private const LB_PER_KG = 2.2046226218;

    /**
     * Taille saisie librement → centimètres (100 à 250), sinon null.
     *
     * Formes acceptées : 180, 180cm, 180 cm, 1,80 m, 1.80, 1m80, 5'11, 5'11", 5′11″, 5 ft 11 in, 5ft11, 5-11, 71in, 6'.
     */
    public static function parseHeight(string $s): ?int
    {
        $s = strtolower(trim($s));
        // Primes typographiques et apostrophes courbes → ' et ".
        $s = str_replace(['′', '’', '‘', '´', '`', '″', '“', '”', "''"], ["'", "'", "'", "'", "'", '"', '"', '"', '"'], $s);
        $s = (string) preg_replace('/\s+/', ' ', $s);
        if ($s === '') {
            return null;
        }

        $cm = null;
        if (preg_match('/^(\d)\s?m\s?(\d{1,2})$/', $s, $m) === 1) {
            // 1m80 (1m8 = 1,80 m).
            $cm = (int) $m[1] * 100 + (strlen($m[2]) === 1 ? (int) $m[2] * 10 : (int) $m[2]);
        } elseif (preg_match('/^(\d+(?:[.,]\d+)?)\s?(?:m|mètres?|metres?|meters?)$/u', $s, $m) === 1) {
            $cm = (int) round(self::toFloat($m[1]) * 100);
        } elseif (preg_match('/^(\d+(?:[.,]\d+)?)\s?(?:cm|centimètres?|centimetres?|centimeters?)$/u', $s, $m) === 1) {
            $cm = (int) round(self::toFloat($m[1]));
        } elseif (preg_match('/^(\d+(?:[.,]\d+)?)\s?(?:in|inch|inches|")$/', $s, $m) === 1) {
            $cm = (int) round(self::toFloat($m[1]) * self::CM_PER_INCH);
        } elseif (preg_match('/^(\d)\s?(?:\'|ft|feet|foot|-)\s?(?:(\d{1,2}(?:[.,]\d+)?)\s?(?:"|in|inch|inches)?)?$/', $s, $m) === 1) {
            $in = isset($m[2]) ? self::toFloat($m[2]) : 0.0;
            if ($in >= 12) {
                return null;
            }
            $cm = (int) round(((int) $m[1] * 12 + $in) * self::CM_PER_INCH);
        } elseif (preg_match('/^(\d+(?:[.,]\d+)?)$/', $s, $m) === 1) {
            // Nombre seul : moins de 3 → mètres (1,80), sinon centimètres (180).
            $v = self::toFloat($m[1]);
            $cm = (int) round($v < 3 ? $v * 100 : $v);
        }

        return $cm !== null && $cm >= self::HEIGHT_MIN && $cm <= self::HEIGHT_MAX ? $cm : null;
    }

    /** Poids saisi librement → kilogrammes (30 à 250), sinon null. Formes : 78, 78kg, 78 kg, 172lb, 172 lbs, 172 pounds. */
    public static function parseWeight(string $s): ?int
    {
        $s = strtolower(trim($s));
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(kg|kgs|kilos?|kilogrammes?|kilograms?|lb|lbs|pounds?|livres?)?\.?$/', $s, $m) !== 1) {
            return null;
        }
        $v = self::toFloat($m[1]);
        $unit = $m[2] ?? '';
        $kg = in_array($unit, ['lb', 'lbs', 'pound', 'pounds', 'livre', 'livres'], true)
            ? (int) round($v / self::LB_PER_KG)
            : (int) round($v);

        return $kg >= self::WEIGHT_MIN && $kg <= self::WEIGHT_MAX ? $kg : null;
    }

    /**
     * Centimètres → [pieds, pouces], arrondi au pouce le plus proche (180 cm → 5'11").
     *
     * @return array{0: int, 1: int}
     */
    public static function cmToFeetInches(int $cm): array
    {
        $inches = (int) round($cm / self::CM_PER_INCH);

        return [intdiv($inches, 12), $inches % 12];
    }

    public static function feetInchesToCm(int $ft, int $in): int
    {
        return (int) round(($ft * 12 + $in) * self::CM_PER_INCH);
    }

    public static function kgToLb(int $kg): int
    {
        return (int) round($kg * self::LB_PER_KG);
    }

    public static function lbToKg(int $lb): int
    {
        return (int) round($lb / self::LB_PER_KG);
    }

    private static function toFloat(string $n): float
    {
        return (float) str_replace(',', '.', $n);
    }
}
