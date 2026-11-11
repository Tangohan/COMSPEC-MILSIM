<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fichiers SSE — normalisation des textes pour la recherche tolérante et les recoupements.
 *
 * - norm() : minuscules, sans accents, lettres et chiffres seulement, espaces simples (« Leïla  AZIZ » → « leila aziz ») ;
 * - phonetic() : clé de prononciation d'un mot, tolérante aux doubles lettres et aux graphies proches (Haddad / Hadad,
 *   Mercier / Mercer, Youssef / Yusuf, Khalid / Kalid) ; calculée en PHP, sans service extérieur ;
 * - tokens() : mots utiles d'un texte libre (noms, lieux, numéros), sans les mots vides FR / EN / DE.
 */
final class SseFileText
{
    /** Mots vides : jamais indexés ni proposés comme recoupement. */
    private const STOP = [
        'les', 'des', 'une', 'aux', 'par', 'pour', 'sur', 'dans', 'avec', 'sans', 'est', 'sont', 'été', 'ete', 'qui', 'que', 'quoi',
        'dont', 'mais', 'donc', 'car', 'pas', 'plus', 'moins', 'tres', 'tout', 'tous', 'toute', 'toutes', 'leur', 'leurs', 'son', 'ses',
        'sa', 'nos', 'vos', 'notre', 'votre', 'cette', 'ces', 'cet', 'ont', 'avait', 'etait', 'fait', 'faire', 'apres', 'avant', 'vers',
        'entre', 'chez', 'sous', 'lors', 'puis', 'alors', 'ainsi', 'aussi', 'comme', 'meme', 'deux', 'trois', 'non', 'oui', 'ici',
        'the', 'and', 'for', 'with', 'from', 'that', 'this', 'are', 'was', 'were', 'has', 'have', 'had', 'not', 'but', 'his', 'her',
        'they', 'them', 'their', 'there', 'then', 'than', 'into', 'onto', 'out', 'over', 'under', 'via', 'per', 'about', 'after',
        'der', 'die', 'das', 'und', 'mit', 'von', 'den', 'dem', 'ist', 'sind', 'ein', 'eine', 'nicht', 'auf', 'aus', 'bei', 'nach',
        'contact', 'rapport', 'report', 'grille', 'grid', 'zone', 'secteur', 'heure', 'heures', 'jour', 'nuit', 'mission',
    ];

    /** Repli sans intl : lettres accentuées latines → lettre de base. */
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'ď' => 'd', 'Ď' => 'D',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ė' => 'e',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ę' => 'E', 'Ě' => 'E', 'Ė' => 'E',
        'ğ' => 'g', 'Ğ' => 'G', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ı' => 'i', 'İ' => 'I',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ō' => 'o', 'ő' => 'o',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ō' => 'O', 'Ő' => 'O',
        'ř' => 'r', 'Ř' => 'R', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S', 'Ș' => 'S',
        'ť' => 't', 'ț' => 't', 'Ť' => 'T', 'Ț' => 'T', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ū' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z',
    ];

    public static function norm(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($n)) {
                $s = (string) preg_replace('/\p{Mn}+/u', '', $n);
            }
        } else {
            // Sans l'extension intl : lettres accentuées courantes (latin) ramenées à leur base.
            $s = strtr($s, self::ACCENTS);
        }
        $s = strtr($s, ['ß' => 'ss', 'æ' => 'ae', 'Æ' => 'ae', 'œ' => 'oe', 'Œ' => 'oe', 'ø' => 'o', 'Ø' => 'o', 'đ' => 'd', 'ł' => 'l', 'Ł' => 'l']);
        $s = mb_strtolower($s, 'UTF-8');
        $s = (string) preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** Clé phonétique d'un mot (ou de chaque mot d'une suite, séparés par une espace). */
    public static function phonetic(string $s): string
    {
        $out = [];
        foreach (explode(' ', self::norm($s)) as $w) {
            $k = self::phoneticWord($w);
            if ($k !== '') {
                $out[] = $k;
            }
        }

        return implode(' ', $out);
    }

    private static function phoneticWord(string $w): string
    {
        if ($w === '' || ctype_digit($w)) {
            return $w;
        }
        $w = strtr($w, ['ph' => 'f', 'ck' => 'k', 'ch' => 'x', 'sh' => 'x', 'sch' => 'x', 'kh' => 'k', 'gh' => 'g', 'th' => 't', 'dh' => 'd',
            'ou' => 'u', 'oo' => 'u', 'ee' => 'i', 'qu' => 'k', 'q' => 'k', 'c' => 'k', 'z' => 's', 'w' => 'v', 'y' => 'i', 'j' => 'i', 'x' => 'ks']);
        $first = $w[0];
        $first = in_array($first, ['e', 'i'], true) ? 'i' : ($first === 'u' ? 'o' : $first);
        $rest = (string) preg_replace('/[aeiouh]/', '', substr($w, 1));
        $key = (string) preg_replace('/(.)\1+/', '$1', $first . $rest);
        // Terminaisons muettes ou variables (Mercier / Mercer, Dupont / Dupond).
        $key = (string) preg_replace('/(?<=.)[dt]$/', 't', $key);

        return substr($key, 0, 24);
    }

    /**
     * Mots utiles d'un texte : mots de 3 lettres au moins hors mots vides, et numéros (téléphones, plaques, papiers)
     * recollés sans séparateurs. Au plus $max entrées, sans doublon.
     *
     * @return list<array{kind: string, token: string}>
     */
    public static function tokens(string $text, int $max = 400): array
    {
        $out = [];
        $seen = [];
        $add = static function (string $kind, string $token) use (&$out, &$seen, $max): void {
            if ($token === '' || isset($seen[$token]) || count($out) >= $max) {
                return;
            }
            $seen[$token] = true;
            $out[] = ['kind' => $kind, 'token' => substr($token, 0, 64)];
        };
        // Téléphones : 7 chiffres au moins, séparateurs usuels ; gardés sous leur forme courte (9 derniers chiffres).
        if (preg_match_all('/(?<![\p{L}\d])\+?\d[\d \-.\/()]{5,20}\d(?![\p{L}\d])/u', $text, $m)) {
            foreach ($m[0] as $raw) {
                $phone = self::phoneKey($raw);
                if ($phone !== '') {
                    $add('phone', $phone);
                }
            }
        }
        // Plaques et numéros de papiers : lettres et chiffres mêlés, éventuellement en groupes reliés par des tirets.
        if (preg_match_all('/[\p{L}\d]+(?:[\-][\p{L}\d]+)+|[\p{L}]*\d[\p{L}\d]*/u', $text, $m)) {
            foreach ($m[0] as $raw) {
                $c = self::compact($raw);
                if (strlen($c) >= 5 && preg_match_all('/\d/', $c) >= 2 && preg_match('/[A-Z]/', $c) === 1) {
                    $add('number', $c);
                }
            }
        }
        foreach (explode(' ', self::norm($text)) as $w) {
            if (strlen($w) < 3 || ctype_digit($w) || preg_match('/\d/', $w) === 1 || in_array($w, self::STOP, true)) {
                continue;
            }
            $add('word', $w);
        }

        return $out;
    }

    /** Téléphone réduit à ses 9 derniers chiffres (indicatif et zéro initial ignorés) ; '' s'il a moins de 7 chiffres. */
    public static function phoneKey(string $s): string
    {
        $d = (string) preg_replace('/\D/', '', $s);

        return strlen($d) < 7 ? '' : substr($d, -9);
    }

    /** Numéro sans séparateurs, en majuscules (plaque, papier, téléphone). */
    public static function compact(string $s): string
    {
        return strtoupper((string) preg_replace('/[^\p{L}\d]+/u', '', self::norm($s)));
    }

    public static function isStopWord(string $w): bool
    {
        return in_array(self::norm($w), self::STOP, true);
    }

    /**
     * Ressemblance de deux noms normalisés (0 à 100) : égalité, préfixe, prononciation, distance d'édition.
     */
    public static function similarity(string $a, string $b): int
    {
        $a = self::norm($a);
        $b = self::norm($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }
        if (str_starts_with($b, $a) || str_starts_with($a, $b)) {
            return 88;
        }
        if (self::phonetic($a) === self::phonetic($b)) {
            return 78;
        }
        $d = levenshtein(substr($a, 0, 255), substr($b, 0, 255));
        $len = max(strlen($a), strlen($b));
        if ($d <= 1 || ($len >= 7 && $d <= 2)) {
            return 70;
        }
        if (str_contains(' ' . $b . ' ', ' ' . $a . ' ') || str_contains(' ' . $a . ' ', ' ' . $b . ' ')) {
            return 62;
        }

        return max(0, (int) round(100 - 100 * $d / max(1, $len)) - 30);
    }

    /** Extrait d'un texte autour du premier mot trouvé, avec le mot entouré de \x01 … \x02 (surligné à l'affichage). */
    public static function snippet(string $text, string $needleNorm, int $radius = 70): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || $needleNorm === '') {
            return mb_substr($text, 0, $radius * 2);
        }
        $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $pos = 0;
        foreach ($words as $w) {
            $n = self::norm($w);
            $c = self::compact($w);
            if ($n !== '' && ($n === $needleNorm || $c === strtoupper($needleNorm) || ($c !== '' && str_contains($c, strtoupper($needleNorm)) && strlen($needleNorm) >= 6))) {
                $before = mb_substr($text, 0, $pos);
                $after = mb_substr($text, $pos + mb_strlen($w));
                $b = mb_strlen($before) > $radius ? '…' . mb_substr($before, -$radius) : $before;
                $a = mb_strlen($after) > $radius ? mb_substr($after, 0, $radius) . '…' : $after;

                return $b . "\x01" . $w . "\x02" . $a;
            }
            $pos += mb_strlen($w);
        }

        return mb_strlen($text) > $radius * 2 ? mb_substr($text, 0, $radius * 2) . '…' : $text;
    }

    /** Extrait (snippet) rendu en HTML : texte échappé, mot trouvé dans <mark>. */
    public static function snippetHtml(string $snippet): string
    {
        $h = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');

        return str_replace(["\x01", "\x02"], ['<mark>', '</mark>'], $h);
    }
}
