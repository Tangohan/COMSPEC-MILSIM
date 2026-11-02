<?php

declare(strict_types=1);

namespace App\Support\Wiki;

/**
 * Recherche plein texte du wiki ATAK : sans accents ni casse, tous les mots demandés, titre favorisé.
 */
final class Search
{
    /** Accents → lettre simple, un caractère pour un caractère (les positions restent alignées). */
    private const FOLD = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'œ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'æ' => 'a',
        '’' => "'", '‘' => "'",
    ];

    public static function fold(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), self::FOLD);
    }

    /** @return list<string> mots de 2 lettres ou plus, sans doublon (6 au plus) */
    public static function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}\-+.]+/u', self::fold($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique(array_filter($words, static fn (string $w): bool => mb_strlen($w) >= 2)));

        return array_slice($words, 0, 6);
    }

    /**
     * @param list<array{slug: string, title: string, summary: string, text: string}> $docs
     * @return list<array{slug: string, title: string, summary: string, snippet: string, score: int}>
     */
    public static function run(array $docs, string $query, int $limit = 40): array
    {
        $terms = self::terms($query);
        if ($terms === []) {
            return [];
        }
        $hits = [];
        foreach ($docs as $doc) {
            $title = self::fold($doc['title']);
            $head = self::fold($doc['summary']);
            $body = self::fold($doc['text']);
            $score = 0;
            foreach ($terms as $term) {
                $inTitle = str_contains($title, $term);
                $n = substr_count($body, $term);
                if (!$inTitle && $n === 0 && !str_contains($head, $term)) {
                    continue 2;
                }
                $score += ($inTitle ? 50 : 0) + (str_contains($head, $term) ? 10 : 0) + min($n, 20);
            }
            $hits[] = [
                'slug' => $doc['slug'],
                'title' => $doc['title'],
                'summary' => $doc['summary'],
                'snippet' => self::snippet($doc['text'], $body, $terms),
                'score' => $score,
            ];
        }
        usort($hits, static fn (array $a, array $b): int => [$b['score'], $a['title']] <=> [$a['score'], $b['title']]);

        return array_slice($hits, 0, $limit);
    }

    /** Extrait d'environ 220 caractères autour du premier mot trouvé. */
    private static function snippet(string $text, string $folded, array $terms): string
    {
        $at = null;
        foreach ($terms as $term) {
            $p = mb_strpos($folded, $term, 0, 'UTF-8');
            if ($p !== false && ($at === null || $p < $at)) {
                $at = $p;
            }
        }
        if ($at === null) {
            return mb_substr($text, 0, 220, 'UTF-8');
        }
        $start = max(0, $at - 80);
        $out = mb_substr($text, $start, 220, 'UTF-8');

        return ($start > 0 ? '… ' : '') . trim($out) . ($start + 220 < mb_strlen($text, 'UTF-8') ? ' …' : '');
    }

    /** Surligne les mots dans un texte déjà échappé (positions sur le texte sans accents). */
    public static function highlight(string $text, array $terms): string
    {
        $folded = self::fold($text);
        $marks = [];
        foreach ($terms as $term) {
            $len = mb_strlen($term, 'UTF-8');
            $offset = 0;
            while (($p = mb_strpos($folded, $term, $offset, 'UTF-8')) !== false) {
                $marks[] = [$p, $p + $len];
                $offset = $p + $len;
            }
        }
        if ($marks === []) {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        sort($marks);
        $html = '';
        $cursor = 0;
        foreach ($marks as [$a, $b]) {
            if ($a < $cursor) {
                continue;
            }
            $html .= htmlspecialchars(mb_substr($text, $cursor, $a - $cursor, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '<mark>' . htmlspecialchars(mb_substr($text, $a, $b - $a, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</mark>';
            $cursor = $b;
        }

        return $html . htmlspecialchars(mb_substr($text, $cursor, null, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
