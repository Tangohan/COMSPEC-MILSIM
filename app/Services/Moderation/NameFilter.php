<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Repère les mots inappropriés dans un nom (communauté, unité) et les masque.
 *
 * Pur calcul, sans base : la liste de mots est fournie par l’appelant
 * (NameModerationService la lit en base, liste modifiable par l’administration du site).
 *
 * Normalisation caractère par caractère (même longueur que l’original) : minuscules,
 * accents retirés, chiffres « leet » (p3n1s → penis). Les positions trouvées
 * s’appliquent donc directement au texte d’origine.
 */
final class NameFilter
{
    public const MODE_WORD = 'word';
    public const MODE_PARTIAL = 'partial';

    public const MASK_CHAR = '•';

    /**
     * Liste livrée par défaut (copiée en base au premier usage, puis modifiable).
     * word : mot entier (pluriel toléré) ; partial : n’importe où dans un mot.
     *
     * @var list<array{word: string, lang: string, mode: string}>
     */
    public const DEFAULT_WORDS = [
        // Français
        ['word' => 'bite', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'couille', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'chatte', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'connard', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'connasse', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'salope', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'salaud', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'pute', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'putain', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'merde', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'encul', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'nique', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'niquer', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'ntm', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'fdp', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'pd', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'pede', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'tapette', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'branleur', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'branlette', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'zizi', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'teub', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'chibre', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'vagin', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'nichon', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'foutre', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'trou du cul', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'pedophile', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'negre', 'lang' => 'fr', 'mode' => self::MODE_WORD],
        ['word' => 'bougnoul', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        ['word' => 'youpin', 'lang' => 'fr', 'mode' => self::MODE_PARTIAL],
        // Anglais
        ['word' => 'fuck', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'shit', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'bitch', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'cunt', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'dick', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'cock', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'pussy', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'penis', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'vagina', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'asshole', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'whore', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'slut', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'dildo', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'boobs', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'tits', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'twat', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'wanker', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'porn', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'porno', 'lang' => 'all', 'mode' => self::MODE_WORD],
        ['word' => 'rape', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'rapist', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'nigger', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'nigga', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'faggot', 'lang' => 'en', 'mode' => self::MODE_PARTIAL],
        ['word' => 'fag', 'lang' => 'en', 'mode' => self::MODE_WORD],
        ['word' => 'retard', 'lang' => 'en', 'mode' => self::MODE_WORD],
        // Les deux langues
        ['word' => 'hitler', 'lang' => 'all', 'mode' => self::MODE_PARTIAL],
        ['word' => 'nazi', 'lang' => 'all', 'mode' => self::MODE_WORD],
        ['word' => 'sieg heil', 'lang' => 'all', 'mode' => self::MODE_WORD],
        ['word' => 'kkk', 'lang' => 'all', 'mode' => self::MODE_WORD],
    ];

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'œ' => 'o', 'æ' => 'a',
    ];

    private const LEET = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't',
        '@' => 'a', '$' => 's', '!' => 'i', '€' => 'e',
    ];

    /** @var list<array{word: string, regex: string}> */
    private array $compiled = [];

    /**
     * @param list<array{word: string, mode?: string}> $words
     * @param list<string> $allowedNames noms entiers autorisés par l’administration (faux positifs)
     */
    public function __construct(array $words, private array $allowedNames = [])
    {
        foreach ($words as $entry) {
            $word = self::normalizeWord((string) ($entry['word'] ?? ''));
            if ($word === '') {
                continue;
            }
            $mode = ($entry['mode'] ?? self::MODE_WORD) === self::MODE_PARTIAL ? self::MODE_PARTIAL : self::MODE_WORD;
            $this->compiled[] = ['word' => $word, 'regex' => self::compile($word, $mode)];
        }
        $this->allowedNames = array_values(array_filter(array_map(
            static fn ($n): string => self::normalizeWord((string) $n),
            $allowedNames
        ), static fn (string $n): bool => $n !== ''));
    }

    /** Forme comparable d’un mot de la liste (minuscules, sans accents, espaces simples). */
    public static function normalizeWord(string $word): string
    {
        $word = trim(self::normalize($word));
        $word = preg_replace('/[^a-z0-9 ]+/u', ' ', $word) ?? $word;

        return trim(preg_replace('/\s+/', ' ', $word) ?? $word);
    }

    /** Normalisation caractère pour caractère : la longueur (en caractères) est conservée. */
    public static function normalize(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $low = mb_strtolower($ch, 'UTF-8');
            if (mb_strlen($low, 'UTF-8') !== 1) {
                $low = $ch;
            }
            $out .= self::ACCENTS[$low] ?? self::LEET[$low] ?? $low;
        }

        return $out;
    }

    /**
     * @return list<array{start: int, length: int, word: string}> positions en caractères dans le texte d’origine
     */
    public function findMatches(string $text): array
    {
        if ($text === '' || $this->compiled === []) {
            return [];
        }
        $norm = self::normalize($text);
        if (in_array(self::normalizeWord($text), $this->allowedNames, true)) {
            return [];
        }
        $found = [];
        foreach ($this->compiled as $c) {
            if (!preg_match_all($c['regex'], $norm, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as [$hit, $byteOffset]) {
                $found[] = [
                    'start' => mb_strlen(substr($norm, 0, (int) $byteOffset), 'UTF-8'),
                    'length' => mb_strlen((string) $hit, 'UTF-8'),
                    'word' => $c['word'],
                ];
            }
        }
        usort($found, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $found;
    }

    /** @return list<string> mots de la liste trouvés (sans doublon) */
    public function matchedWords(string $text): array
    {
        return array_values(array_unique(array_map(
            static fn (array $m): string => $m['word'],
            $this->findMatches($text)
        )));
    }

    public function isFlagged(string $text): bool
    {
        return $this->findMatches($text) !== [];
    }

    /** Remplace chaque caractère d’un mot repéré par « • » (même longueur). */
    public function maskText(string $text): string
    {
        $matches = $this->findMatches($text);
        if ($matches === []) {
            return $text;
        }
        $chars = mb_str_split($text, 1, 'UTF-8');
        foreach ($matches as $m) {
            for ($i = $m['start'], $end = $m['start'] + $m['length']; $i < $end && $i < count($chars); $i++) {
                if (trim($chars[$i]) !== '') {
                    $chars[$i] = self::MASK_CHAR;
                }
            }
        }

        return implode('', $chars);
    }

    /**
     * HTML échappé : les mots repérés deviennent un bloc flouté. Le mot réel n’est pas
     * envoyé au navigateur (on floute des « • »), il ne se lit donc pas dans le code source.
     */
    public function blurHtml(string $text): string
    {
        return self::blurMaskedHtml($this->maskText($text));
    }

    /** Échappe un texte déjà masqué et floute les suites de « • ». */
    public static function blurMaskedHtml(string $masked): string
    {
        $html = htmlspecialchars($masked, ENT_QUOTES, 'UTF-8');
        if (!str_contains($masked, self::MASK_CHAR)) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/' . preg_quote(self::MASK_CHAR, '/') . '(?:[ \x{2022}]*' . preg_quote(self::MASK_CHAR, '/') . ')*/u',
            static fn (array $m): string => '<span class="ath-name-blur" title="Mot masqué automatiquement" aria-label="mot masqué"'
                . ' style="filter:blur(4px);-webkit-user-select:none;user-select:none;display:inline-block">'
                . $m[0] . '</span>',
            $html
        );
    }

    private static function compile(string $word, string $mode): string
    {
        $parts = [];
        foreach (explode(' ', $word) as $token) {
            $letters = [];
            foreach (str_split($token) as $ch) {
                // Lettre répétable (fuuuck) ; séparateurs de contournement tolérés (p.e.n.i.s, p-e-n-i-s).
                $letters[] = preg_quote($ch, '/') . '+';
            }
            $parts[] = implode('[._\-*]*', $letters);
        }
        $core = implode('[^a-z0-9]+', $parts);
        if ($mode === self::MODE_PARTIAL) {
            return '/' . $core . '/u';
        }

        return '/(?<![a-z0-9])' . $core . '(?:e?s|x)?(?![a-z0-9])/u';
    }
}
