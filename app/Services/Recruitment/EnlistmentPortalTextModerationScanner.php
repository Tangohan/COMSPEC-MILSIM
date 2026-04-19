<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Détection locale (sans service externe) de contenus inacceptables sur le portail recrutement.
 * Heuristiques en français, sensibles aux fautes simples d’accentuation.
 */
final class EnlistmentPortalTextModerationScanner
{
    /**
     * @return array{code: string, public_label: string}|null
     */
    public function scan(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') {
            return null;
        }
        $n = $this->normalize($t);

        foreach (self::selfHarmNeedles() as $needle) {
            if (str_contains($n, $needle)) {
                return [
                    'code' => 'self_harm',
                    'public_label' => 'Contenu évoquant la mise en danger de personnes (suicide, automutilation, etc.)',
                ];
            }
        }

        foreach (self::harassmentPhrases() as $phrase) {
            if (str_contains($n, $phrase)) {
                return [
                    'code' => 'harassment',
                    'public_label' => 'Contenu injurieux, haineux ou de harcèlement',
                ];
            }
        }
        $tokens = preg_split('/[^a-z0-9]+/u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $badTokens = self::harassmentTokenSet();
        foreach ($tokens as $tok) {
            if (isset($badTokens[$tok])) {
                return [
                    'code' => 'harassment',
                    'public_label' => 'Contenu injurieux, haineux ou de harcèlement',
                ];
            }
        }

        return null;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ç' => 'c', 'ñ' => 'n',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return $s;
    }

    /**
     * @return list<string>
     */
    private static function selfHarmNeedles(): array
    {
        return [
            'suicide',
            'suicid',
            'me suicid',
            'me pendre',
            'me tuer',
            'mourir demain',
            'plus envie de vivre',
            'envie de mourir',
            'finir avec ma vie',
            'mettre fin a mes jours',
            'mettre fin à mes jours',
            'plus personne ne me manquera',
            'je veux plus vivre',
            'je ne veux plus vivre',
            'personne ne me comprend je veux mourir',
            'automutilation',
            'auto mutilation',
            'me couper les veines',
            'me blesser gravement',
        ];
    }

    /**
     * @return list<string> expressions sans accents.
     */
    private static function harassmentPhrases(): array
    {
        return [
            'ta gueule', 'ferme la', 'ferme ta gueule', 'fils de pute', 'nique ta mere', 'nique ta mère',
            'nique ta race', 'je te deteste', 'je te déteste', 'je te hais', 'je vais te tuer',
            'tuer ta famille', 'sale race', 'va crever', 'va crev', 'creve toi', 'crève-toi',
            'encule toi', 'enculé', 'enculer', 'nique bien', 'sale pute', 'sale connard',
        ];
    }

    /**
     * @return array<string, true>
     */
    private static function harassmentTokenSet(): array
    {
        $w = [
            'fdp', 'ptn', 'connard', 'connasse', 'salope', 'pute', 'merde', 'bouffon', 'bouffonne',
            'debile', 'imbecile', 'encule', 'niquer', 'nique', 'idiot', 'idiote', 'tg', 'fdp',
        ];

        return array_fill_keys($w, true);
    }
}
