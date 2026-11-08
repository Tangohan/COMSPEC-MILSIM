<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contraste des couleurs de marque d'une communauté sur fond sombre : une couleur choisie par l'équipe ne doit pas
 * rendre un texte illisible (seuil WCAG AA : 4,5 pour du texte courant).
 */
final class ColorContrast
{
    public const MIN_TEXT_RATIO = 4.5;

    /** @return array{0:int,1:int,2:int}|null */
    public static function parseHex(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $hex) === 1) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (preg_match('/^[0-9a-f]{6}$/i', $hex) !== 1) {
            return null;
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function luminance(array $rgb): float
    {
        $lin = static function (int $c): float {
            $v = $c / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $lin($rgb[0]) + 0.7152 * $lin($rgb[1]) + 0.0722 * $lin($rgb[2]);
    }

    public static function ratio(string $foregroundHex, string $backgroundHex): float
    {
        $fg = self::parseHex($foregroundHex);
        $bg = self::parseHex($backgroundHex);
        if ($fg === null || $bg === null) {
            return 1.0;
        }
        $l1 = self::luminance($fg);
        $l2 = self::luminance($bg);
        [$hi, $lo] = $l1 >= $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /**
     * Couleur utilisable comme texte sur ce fond : la couleur demandée si le contraste suffit, sinon elle est
     * éclaircie vers le blanc jusqu'au seuil. Couleur absente ou invalide : repli.
     */
    public static function readableOn(string $hex, string $backgroundHex, string $fallback, float $minRatio = self::MIN_TEXT_RATIO): string
    {
        $rgb = self::parseHex($hex);
        if ($rgb === null) {
            return $fallback;
        }
        for ($step = 0; $step <= 20; $step++) {
            $mix = $step / 20;
            $candidate = sprintf(
                '#%02x%02x%02x',
                (int) round($rgb[0] + (255 - $rgb[0]) * $mix),
                (int) round($rgb[1] + (255 - $rgb[1]) * $mix),
                (int) round($rgb[2] + (255 - $rgb[2]) * $mix)
            );
            if (self::ratio($candidate, $backgroundHex) >= $minRatio) {
                return $candidate;
            }
        }

        return $fallback;
    }
}
