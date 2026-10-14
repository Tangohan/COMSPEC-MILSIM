<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Détecte les titres de morceaux trop proches (doublons probables) avant une mise en ligne.
 *
 * On compare des titres « normalisés » : minuscules, sans accents, sans extension, sans mentions
 * entre parenthèses ou crochets (« Official Video », « Remastered 2011 », « HD »), sans « feat. … »
 * ni ponctuation. Deux titres sont similaires s’ils sont identiques une fois normalisés, si l’un
 * contient entièrement l’autre (au moins 6 caractères), ou si leur ressemblance dépasse 85 %.
 */
final class MusicTitleSimilarity
{
    public const THRESHOLD = 0.85;

    private const NOISE = [
        'official', 'officiel', 'video', 'clip', 'audio', 'lyrics', 'lyric', 'paroles', 'hd', 'hq', '4k',
        'remaster', 'remastered', 'version', 'radio', 'edit', 'extended', 'mix', 'live', 'mp3', 'full',
    ];

    public static function normalize(string $title): string
    {
        $s = mb_strtolower(trim($title));
        $s = (string) preg_replace('/\.(mp3|m4a|aac|wav|ogg|flac)$/u', '', $s);
        if (class_exists(\Normalizer::class)) {
            $nfd = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($nfd)) {
                $s = (string) preg_replace('/\p{Mn}+/u', '', $nfd);
            }
        }
        $s = (string) preg_replace('/[\(\[\{][^\)\]\}]*[\)\]\}]/u', ' ', $s);
        $s = (string) preg_replace('/\b(feat|ft|featuring)\b.*$/u', ' ', $s);
        $s = (string) preg_replace('/[^a-z0-9]+/u', ' ', $s);
        $words = array_filter(
            preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $w): bool => !in_array($w, self::NOISE, true) && !preg_match('/^(19|20)\d\d$/', $w)
        );

        return implode(' ', $words);
    }

    /** Score entre 0 et 1 ; 1 = identique une fois normalisé. */
    public static function score(string $a, string $b): float
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }
        $short = strlen($na) <= strlen($nb) ? $na : $nb;
        $long = $short === $na ? $nb : $na;
        if (strlen($short) >= 6 && str_contains(' ' . $long . ' ', ' ' . $short . ' ')) {
            return 0.9;
        }
        $max = max(strlen($na), strlen($nb));
        if ($max <= 255) {
            $lev = 1 - levenshtein($na, $nb) / $max;
        } else {
            similar_text($na, $nb, $pct);
            $lev = $pct / 100;
        }

        return round(max(0.0, $lev), 3);
    }

    public static function isSimilar(string $a, string $b): bool
    {
        return self::score($a, $b) >= self::THRESHOLD;
    }

    /**
     * Titre déduit d’un nom de fichier, comme au moment de l’enregistrement.
     */
    public static function titleFromFilename(string $filename): string
    {
        return trim((string) preg_replace('/[_]+/', ' ', pathinfo($filename, PATHINFO_FILENAME)));
    }
}
