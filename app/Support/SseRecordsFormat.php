<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Passage aux fichiers — affichage et contrôle des valeurs selon le pack pays (SseRecordsCountryPacks) : dates, tailles,
 * poids, numéros de téléphone, plaques, codes postaux et numéros de papiers.
 */
final class SseRecordsFormat
{
    /** Types de valeur contrôlables. */
    public const KINDS = ['phone', 'plate', 'postal', 'national_id', 'id_card', 'passport', 'driving_license'];

    /** Lettres de contrôle du DNI espagnol (n mod 23). */
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /** Date Y-m-d (heure éventuelle ignorée) au format du pays, '' si absente ou invalide. */
    public static function date(?string $ymd, string $iso3): string
    {
        if ($ymd === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($ymd), $m) !== 1) {
            return '';
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return '';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $m[1] . '-' . $m[2] . '-' . $m[3]);

        return $d === false ? '' : $d->format((string) SseRecordsCountryPacks::get($iso3)['date_format']);
    }

    /** Taille : impérial « 5'11" (180 cm) », métrique « 180 cm » ; '' si absente. */
    public static function height(?int $cm, string $iso3): string
    {
        if ($cm === null || $cm <= 0) {
            return '';
        }
        if (SseRecordsCountryPacks::get($iso3)['units'] === 'imperial') {
            [$ft, $in] = SseMeasure::cmToFeetInches($cm);

            return sprintf('%d\'%d" (%d cm)', $ft, $in, $cm);
        }

        return $cm . ' cm';
    }

    /** Poids : impérial « 172 lb (78 kg) », métrique « 78 kg » ; '' si absent. */
    public static function weight(?int $kg, string $iso3): string
    {
        if ($kg === null || $kg <= 0) {
            return '';
        }
        if (SseRecordsCountryPacks::get($iso3)['units'] === 'imperial') {
            return SseMeasure::kgToLb($kg) . ' lb (' . $kg . ' kg)';
        }

        return $kg . ' kg';
    }

    /**
     * Valeur conforme au format national ? Vrai si le pays n'a pas de motif pour ce type.
     * Contrôles en plus du motif : clé du NIR (FRA), lettre du DNI (ESP).
     */
    public static function validate(string $kind, string $value, string $iso3): bool
    {
        $pattern = self::pattern($kind, $iso3);
        if ($pattern === null) {
            return true;
        }
        $value = trim($value);
        if ($kind !== 'phone') {
            $value = mb_strtoupper($value, 'UTF-8');
        }
        if ($value === '' || preg_match($pattern, $value) !== 1) {
            return false;
        }
        $code = SseRecordsCountryPacks::normalize($iso3);
        if ($code === 'FRA' && $kind === 'national_id') {
            return self::nirKeyOk($value);
        }
        if ($code === 'ESP' && in_array($kind, ['national_id', 'driving_license'], true)) {
            return self::dniLetterOk($value);
        }

        return true;
    }

    /** Exemple de saisie pour ce type dans ce pays ('' si le pays n'en a pas). */
    public static function example(string $kind, string $iso3): string
    {
        $pack = SseRecordsCountryPacks::get($iso3);
        if (in_array($kind, ['phone', 'plate', 'postal'], true)) {
            return (string) ($pack[$kind]['example'] ?? '');
        }

        return (string) ($pack['ids'][$kind]['example'] ?? '');
    }

    /** Plaque comparable : majuscules, sans espaces ni tirets. */
    public static function normalizePlate(string $plate): string
    {
        return (string) preg_replace('/[\s\-.]+/u', '', mb_strtoupper(trim($plate), 'UTF-8'));
    }

    /** Téléphone comparable : chiffres seulement (le préfixe +33 devient 33). */
    public static function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    /** Clé NIR : 97 − (13 premiers chiffres mod 97), Corse 2A → 19, 2B → 18. */
    public static function nirKeyOk(string $nir): bool
    {
        $n = strtoupper((string) preg_replace('/\s+/', '', $nir));
        if (strlen($n) !== 15) {
            return false;
        }
        $body = self::nirDigits(substr($n, 0, 13));
        if (!ctype_digit($body)) {
            return false;
        }

        return 97 - ((int) $body % 97) === (int) substr($n, 13, 2);
    }

    /** Clé NIR à deux chiffres pour un corps de 13 signes (Corse comprise). */
    public static function nirKey(string $body13): string
    {
        $body = self::nirDigits(strtoupper((string) preg_replace('/\s+/', '', $body13)));

        return str_pad((string) (97 - ((int) $body % 97)), 2, '0', STR_PAD_LEFT);
    }

    /** Département corse (positions 6-7) : 2A → 19, 2B → 18 pour le calcul de la clé. */
    private static function nirDigits(string $body13): string
    {
        return substr($body13, 0, 5) . strtr(substr($body13, 5, 2), ['2A' => '19', '2B' => '18']) . substr($body13, 7);
    }

    /** Lettre de contrôle d'un DNI/NIE (8 chiffres, ou X/Y/Z + 7 chiffres). */
    public static function dniLetter(string $digits8): string
    {
        $n = (int) strtr(strtoupper($digits8), ['X' => '0', 'Y' => '1', 'Z' => '2']);

        return self::DNI_LETTERS[$n % 23];
    }

    private static function dniLetterOk(string $value): bool
    {
        $v = (string) preg_replace('/[\s-]+/', '', $value);

        return strlen($v) === 9 && self::dniLetter(substr($v, 0, 8)) === $v[8];
    }

    private static function pattern(string $kind, string $iso3): ?string
    {
        $pack = SseRecordsCountryPacks::get($iso3);
        $p = in_array($kind, ['phone', 'plate', 'postal'], true)
            ? ($pack[$kind]['pattern'] ?? null)
            : ($pack['ids'][$kind]['pattern'] ?? null);

        return is_string($p) && $p !== '' ? $p : null;
    }
}
