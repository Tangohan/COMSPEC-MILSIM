<?php

declare(strict_types=1);

namespace App\Services\Sse\Detection;

/**
 * Découpe un texte en MOTS ENTIERS repliés, avec leur position dans le texte d'origine (pour surligner l'extrait).
 *
 * Repli : minuscules, accents et diacritiques retirés (é → e, ç → c, œ → oe…), ligatures dépliées. Tout ce qui n'est ni lettre
 * ni chiffre sépare deux mots : ponctuation collée, apostrophes droites ou typographiques (« l’otage » → « l », « otage »),
 * traits d'union, espaces insécables. Aucune correspondance ne se fait donc à l'intérieur d'un mot : « otage » n'est jamais
 * trouvé dans « sabotage ». Sans extension intl : la table de repli est écrite ici.
 */
final class SseTextNormalizer
{
    /** Lettres accentuées et ligatures (minuscules, après mb_strtolower) → lettres simples. */
    private const FOLD = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c',
        'ď' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ğ' => 'g', 'ĝ' => 'g', 'ġ' => 'g', 'ģ' => 'g',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o',
        'ŕ' => 'r', 'ř' => 'r',
        'ś' => 's', 'š' => 's', 'ş' => 's', 'ŝ' => 's', 'ș' => 's',
        'ť' => 't', 'ţ' => 't', 'ț' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    /** Un mot replié : sans accent, en minuscules. */
    public static function foldWord(string $word): string
    {
        $w = strtr(mb_strtolower($word, 'UTF-8'), self::FOLD);

        // Diacritiques combinants (texte décomposé, « e » + accent) : retirés.
        return (string) preg_replace('/\p{Mn}+/u', '', $w);
    }

    /**
     * Mots du texte, dans l'ordre : forme repliée, position et longueur en octets dans le texte d'origine.
     *
     * @return list<array{w: string, at: int, len: int}>
     */
    public static function tokens(string $text): array
    {
        if ($text === '' || preg_match_all('/[\p{L}\p{N}\p{Mn}]+/u', $text, $m, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }
        $out = [];
        foreach ($m[0] as [$word, $at]) {
            $folded = self::foldWord((string) $word);
            if ($folded !== '') {
                $out[] = ['w' => $folded, 'at' => (int) $at, 'len' => strlen((string) $word)];
            }
        }

        return $out;
    }

    /**
     * Mots repliés d'une expression du lexique (« prise d’otage(s) » → [prise, d, otage(s)]) : même découpe que le texte,
     * en gardant les marques de motif « (s|x) » et « * » attachées au mot.
     *
     * @return list<string>
     */
    public static function patternWords(string $pattern): array
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return [];
        }
        // Les suffixes déclarés « mot(s|es) » et le joker final « mot* » sont protégés avant la découpe.
        $out = [];
        foreach (preg_split('/[^\p{L}\p{N}\p{Mn}()|*]+/u', $pattern) ?: [] as $chunk) {
            if ($chunk === '' || $chunk === '()') {
                continue;
            }
            if (preg_match('/^([\p{L}\p{N}\p{Mn}]+)(\([\p{L}\p{N}\p{Mn}|]*\))?(\*)?$/u', $chunk, $p) === 1) {
                $out[] = self::foldWord($p[1]) . self::foldWord($p[2] ?? '') . ($p[3] ?? '');
            } else {
                foreach (preg_split('/[^\p{L}\p{N}\p{Mn}]+/u', $chunk) ?: [] as $plain) {
                    if ($plain !== '') {
                        $out[] = self::foldWord($plain);
                    }
                }
            }
        }

        return $out;
    }

    /** Texte replié d'un seul tenant (« Prise d’Otages ! » → « prise d otages ») : pour les recherches et l'affichage. */
    public static function fold(string $text): string
    {
        return implode(' ', array_column(self::tokens($text), 'w'));
    }
}
