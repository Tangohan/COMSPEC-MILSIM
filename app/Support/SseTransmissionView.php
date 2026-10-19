<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lecture d'une fiche de transmission terrain : repérer les images dans le contenu transmis
 * et préparer les valeurs longues (identifiants, codes) pour qu'elles restent lisibles.
 */
final class SseTransmissionView
{
    /** Longueur au-delà de laquelle une valeur est repliée (on l'ouvre au besoin). */
    public const LONG_VALUE = 220;

    /**
     * Valeur qui désigne une image : URL http(s), chemin du portail ou data URI d'image.
     */
    public static function isImageValue(string $value): bool
    {
        $v = trim($value);
        if ($v === '' || str_contains($v, ' ')) {
            return false;
        }
        if (preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $v) === 1) {
            return true;
        }
        $path = (string) (parse_url($v, PHP_URL_PATH) ?? '');
        $isUrl = preg_match('#^https?://#i', $v) === 1 || (str_starts_with($v, '/') && !str_starts_with($v, '//'));

        return $isUrl && preg_match('#\.(png|jpe?g|webp|gif)$#i', $path) === 1;
    }

    public static function isLong(string $value): bool
    {
        return mb_strlen($value) > self::LONG_VALUE;
    }

    /** Début d'une valeur longue, coupé proprement. */
    public static function excerpt(string $value, int $max = 140): string
    {
        $value = trim($value);

        return mb_strlen($value) <= $max ? $value : rtrim(mb_substr($value, 0, $max)) . '…';
    }

    /** Statut d'un relevé biométrique : relevé (ok) ou absent. */
    public static function bioTone(string $label): string
    {
        return in_array(trim($label), ['', '—', 'Absente', 'Non relevé', 'Non relevées', 'Non relevée'], true) ? 'missing' : 'ok';
    }
}
