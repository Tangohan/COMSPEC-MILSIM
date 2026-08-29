<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Détecteur local (sans API externe) de textes susceptibles d’avoir été générés par une IA.
 * Heuristiques FR / EN : tournures « ChatGPT », structure trop lisse, buzzwords, faible idiosyncrasie.
 *
 * Score 0–100. Niveaux : none (<35), low (35–54), medium (55–74), high (≥75).
 * À utiliser comme signal d’instruction pour le staff — jamais comme preuve absolue.
 */
final class AiTextLikelihoodDetector
{
    public const LEVEL_NONE = 'none';
    public const LEVEL_LOW = 'low';
    public const LEVEL_MEDIUM = 'medium';
    public const LEVEL_HIGH = 'high';

    /** Seuil à partir duquel on journalise un signal côté dossier. */
    public const FLAG_THRESHOLD = 45;

    /**
     * @return array{
     *   score: int,
     *   level: string,
     *   label: string,
     *   signals: list<string>,
     *   char_count: int,
     *   word_count: int
     * }
     */
    public function analyze(string $text): array
    {
        $raw = trim($text);
        $charCount = function_exists('mb_strlen') ? mb_strlen($raw) : strlen($raw);
        $words = $this->words($raw);
        $wordCount = count($words);

        if ($raw === '' || $wordCount < 25) {
            return [
                'score' => 0,
                'level' => self::LEVEL_NONE,
                'label' => 'Pas assez de texte pour estimer',
                'signals' => [],
                'char_count' => $charCount,
                'word_count' => $wordCount,
            ];
        }

        $norm = $this->normalize($raw);
        $score = 0;
        $signals = [];

        $phraseHits = $this->countPhraseHits($norm, self::aiPhrases());
        if ($phraseHits > 0) {
            $add = min(40, 12 + ($phraseHits * 8));
            $score += $add;
            $signals[] = 'Tournures typiques d’assistants IA (' . $phraseHits . ')';
        }

        $buzzHits = $this->countPhraseHits($norm, self::buzzwords());
        if ($buzzHits >= 2) {
            $score += min(18, 6 + ($buzzHits * 3));
            $signals[] = 'Vocabulaire corporate / « LinkedIn » saturé (' . $buzzHits . ')';
        }

        $avgLen = $this->averageSentenceLength($raw);
        if ($avgLen >= 22 && $avgLen <= 38 && $wordCount >= 80) {
            $score += 10;
            $signals[] = 'Phrases de longueur très régulière';
        }

        $variance = $this->sentenceLengthVariance($raw);
        if ($wordCount >= 90 && $variance > 0 && $variance < 18) {
            $score += 12;
            $signals[] = 'Faible variation de rythme (texte très lisse)';
        }

        $paraCount = $this->paragraphCount($raw);
        if ($paraCount >= 3 && $wordCount >= 120) {
            $balanced = $this->paragraphsLookBalanced($raw);
            if ($balanced) {
                $score += 8;
                $signals[] = 'Paragraphes de taille homogène';
            }
        }

        if ($this->hasNumberedOrBulletEssayStructure($raw) && $wordCount >= 80) {
            $score += 10;
            $signals[] = 'Structure type dissertation / liste numérotée';
        }

        if ($this->startsWithFormalOpener($norm)) {
            $score += 8;
            $signals[] = 'Ouverture formelle type modèle';
        }

        if ($this->endsWithGenericCloser($norm)) {
            $score += 6;
            $signals[] = 'Conclusion générique type modèle';
        }

        $typoDensity = $this->informalMarkerDensity($norm, $wordCount);
        if ($typoDensity < 0.01 && $wordCount >= 100) {
            $score += 8;
            $signals[] = 'Presque aucun marqueur oral / fautes usuelles';
        } elseif ($typoDensity >= 0.04) {
            $score = max(0, $score - 15);
            $signals[] = 'Marqueurs oraux / fautes : signal IA atténué';
        }

        if ($this->emojiOrMarkupHeavy($raw)) {
            $score = max(0, $score - 8);
        }

        $score = min(100, max(0, $score));
        $level = $this->levelFor($score);

        return [
            'score' => $score,
            'level' => $level,
            'label' => $this->labelFor($level),
            'signals' => array_values(array_unique($signals)),
            'char_count' => $charCount,
            'word_count' => $wordCount,
        ];
    }

    /**
     * Analyse plusieurs champs et agrège (utile pour un dossier candidature).
     *
     * @param array<string, string> $fields clé → texte
     * @return array{
     *   score: int,
     *   level: string,
     *   label: string,
     *   signals: list<string>,
     *   char_count: int,
     *   word_count: int,
     *   fields_scanned: int
     * }
     */
    public function analyzeFields(array $fields): array
    {
        $chunks = [];
        foreach ($fields as $key => $value) {
            $v = trim((string) $value);
            if ($v === '') {
                continue;
            }
            $chunks[] = $v;
        }
        $combined = implode("\n\n", $chunks);
        $result = $this->analyze($combined);
        $result['fields_scanned'] = count($chunks);

        return $result;
    }

    public function shouldFlag(array $analysis): bool
    {
        return (int) ($analysis['score'] ?? 0) >= self::FLAG_THRESHOLD;
    }

    private function levelFor(int $score): string
    {
        if ($score >= 75) {
            return self::LEVEL_HIGH;
        }
        if ($score >= 55) {
            return self::LEVEL_MEDIUM;
        }
        if ($score >= 35) {
            return self::LEVEL_LOW;
        }

        return self::LEVEL_NONE;
    }

    private function labelFor(string $level): string
    {
        return match ($level) {
            self::LEVEL_HIGH => 'Forte suspicion de texte généré par IA',
            self::LEVEL_MEDIUM => 'Suspicion modérée de texte généré par IA',
            self::LEVEL_LOW => 'Faible suspicion de texte généré par IA',
            default => 'Pas de signal IA notable',
        };
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}\']+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? array_values($parts) : [];
    }

    /**
     * @param list<string> $needles
     */
    private function countPhraseHits(string $norm, array $needles): int
    {
        $hits = 0;
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($norm, $needle)) {
                $hits++;
            }
        }

        return $hits;
    }

    private function averageSentenceLength(string $text): float
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($sentences === []) {
            return 0.0;
        }
        $total = 0;
        foreach ($sentences as $s) {
            $total += count($this->words($s));
        }

        return $total / max(1, count($sentences));
    }

    private function sentenceLengthVariance(string $text): float
    {
        $sentences = preg_split('/(?<=[.!?…])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($sentences) < 3) {
            return 999.0;
        }
        $lens = [];
        foreach ($sentences as $s) {
            $lens[] = count($this->words($s));
        }
        $mean = array_sum($lens) / count($lens);
        $acc = 0.0;
        foreach ($lens as $l) {
            $acc += ($l - $mean) ** 2;
        }

        return $acc / count($lens);
    }

    private function paragraphCount(string $text): int
    {
        $parts = preg_split("/\n\s*\n/u", trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($parts);
    }

    private function paragraphsLookBalanced(string $text): bool
    {
        $parts = preg_split("/\n\s*\n/u", trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 3) {
            return false;
        }
        $lens = array_map(static fn (string $p): int => function_exists('mb_strlen') ? mb_strlen($p) : strlen($p), $parts);
        $mean = array_sum($lens) / count($lens);
        if ($mean < 80) {
            return false;
        }
        foreach ($lens as $l) {
            if ($l < $mean * 0.55 || $l > $mean * 1.55) {
                return false;
            }
        }

        return true;
    }

    private function hasNumberedOrBulletEssayStructure(string $text): bool
    {
        $lines = preg_split("/\n+/u", $text) ?: [];
        $hits = 0;
        foreach ($lines as $line) {
            $t = trim($line);
            if (preg_match('/^(?:\d+[\).\:\-]|[\-\*•])\s+\S/u', $t) === 1) {
                $hits++;
            }
        }

        return $hits >= 3;
    }

    private function startsWithFormalOpener(string $norm): bool
    {
        foreach ([
            'je me permets de',
            'je souhaiterais exprimer',
            'dans le cadre de ma candidature',
            'il me tient a coeur de',
            'c est avec un grand enthousiasme',
            'c est avec enthousiasme que',
            'permettez-moi de',
            'je tiens tout d abord a',
            'en premier lieu je',
            'as an avid',
            'i am writing to express',
            'i would like to express my interest',
        ] as $opener) {
            if (str_starts_with($norm, $opener)) {
                return true;
            }
        }

        return false;
    }

    private function endsWithGenericCloser(string $norm): bool
    {
        foreach ([
            'dans l attente de votre retour',
            'je reste a votre disposition',
            'cordialement',
            'bien a vous',
            'merci de l attention portee',
            'thank you for your time and consideration',
            'i look forward to hearing from you',
            'n hesitatez pas a me contacter',
        ] as $closer) {
            if (str_contains(mb_substr($norm, -120), $closer)) {
                return true;
            }
        }

        return false;
    }

    private function informalMarkerDensity(string $norm, int $wordCount): float
    {
        if ($wordCount < 1) {
            return 0.0;
        }
        $markers = [
            ' mdr ', ' ptdr ', ' lol ', ' wsh ', ' wesh ', ' frerot ', ' frere ', ' mec ',
            ' grave ', ' trop bien ', ' trop cool ', ' jsuis ', ' j ai kiff', ' kiffer ',
            ' ptetre ', ' ptet ', ' chui ', ' chuis ', ' tjr ', ' tjrs ', ' auj ', ' aujd ',
            ' bcp ', ' qd ', ' pcq ', ' pq ', ' jsp ', ' idk ', ' imo ', ' tbh ',
            '...', '!!', '??',
        ];
        $hits = 0;
        $padded = ' ' . $norm . ' ';
        foreach ($markers as $m) {
            if (str_contains($padded, $m)) {
                $hits++;
            }
        }

        return $hits / $wordCount;
    }

    private function emojiOrMarkupHeavy(string $text): bool
    {
        return preg_match('/[\x{1F300}-\x{1FAFF}]/u', $text) === 1
            || substr_count($text, '#') >= 3;
    }

    /**
     * @return list<string>
     */
    private static function aiPhrases(): array
    {
        return [
            'il est important de noter',
            'il convient de souligner',
            'il est essentiel de',
            'dans le monde d aujourd hui',
            'en conclusion',
            'pour conclure',
            'en resume',
            'dans un premier temps',
            'dans un second temps',
            'd une part',
            'd autre part',
            'cela etant dit',
            'cela dit',
            'par ailleurs il est',
            'en outre',
            'de plus il est',
            'non seulement',
            'mais egalement',
            'au sein de cette',
            'au sein de votre',
            'je suis convaincu que',
            'je suis persuade que',
            'mon engagement indéfectible',
            'mon engagement indefectible',
            'passion indéfectible',
            'passion indefectible',
            'environnement stimulant',
            'dynamique et engageant',
            'valeurs qui me tiennent a coeur',
            'alignement avec les valeurs',
            'mettre a profit mes competences',
            'mettre a profit mon experience',
            'contribuer de maniere significative',
            'apporter une contribution significative',
            'dans le cadre de cette opportunite',
            'cette opportunite unique',
            'enrichir mutuellement',
            'synergie',
            'leverage',
            'delve into',
            'tapestry',
            'in today s rapidly evolving',
            'it is important to note',
            'it is worth noting',
            'in conclusion',
            'furthermore',
            'moreover',
            'as an ai language model',
            'as a large language model',
            'i hope this helps',
            'n hesitatez pas si vous avez d autres questions',
        ];
    }

    /**
     * @return list<string>
     */
    private static function buzzwords(): array
    {
        return [
            'leadership',
            'proactif',
            'proactive',
            'resilience',
            'resilient',
            'collaboratif',
            'collaborative',
            'transversal',
            'transversale',
            'soft skills',
            'hard skills',
            'mindset',
            'ecosysteme',
            'ecosystem',
            'innovation',
            'disrupt',
            'disruptif',
            'holistique',
            'holistic',
            'paradigme',
            'paradigm',
            'optimiser',
            'optimisation',
            'valoriser mon parcours',
            'monter en competences',
            'monte en competence',
            'culture d entreprise',
            'esprit d equipe',
            'sens du collectif',
            'cohesion',
            'immersif',
            'immersive',
            'gameplay realiste',
            'experience immersive',
        ];
    }
}
