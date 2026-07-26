<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisation UTF-8 pour textes métier (journal ATAK, libellés…).
 */
final class Utf8Text
{
    public static function normalize(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        if (!self::looksMojibake($text)) {
            return $text;
        }

        $repaired = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $text);
        if (!is_string($repaired) || $repaired === '' || !mb_check_encoding($repaired, 'UTF-8')) {
            return $text;
        }

        return self::looksMojibake($repaired) ? $text : $repaired;
    }

    private static function looksMojibake(string $text): bool
    {
        return (bool) preg_match(
            '/(?:Ã[\x80-\xBF]|â€[\x80-\xBF]|â€™|â€œ|â€"|Ã©|Ã¨|Ã |Ã§|Ã®|Ã´|Ã»|Ã‰)/u',
            $text
        );
    }
}
