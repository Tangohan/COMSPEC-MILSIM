<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Heuristiques locales : termes, PII grossier, URLs suspectes, bruit Unicode type zalgo.
 */
final class HeuristicTextModerator
{
    /** @var string[] */
    private const PROFANITY_FRAGMENTS = [
        'putain', 'merde', 'connard', 'salope', 'encul', 'fdp', 'nique', 'bite', 'couille',
    ];

    /** @var string[] */
    private const HATE_FRAGMENTS = [
        'mort aux', 'sale juif', 'sale arabe', 'sale noir', 'hitler',
    ];

    public function __construct(
        private HtmlTextExtractor $htmlExtractor
    ) {
    }

    /**
     * @return array{score: int, codes: string[]}
     */
    public function scorePlainText(string $text): array
    {
        $codes = [];
        $score = 0;
        $norm = mb_strtolower($text, 'UTF-8');
        $norm = $this->stripCombiningForCheck($norm);

        foreach (self::PROFANITY_FRAGMENTS as $w) {
            if ($w !== '' && str_contains($norm, $w)) {
                $codes[] = 'profanity';
                $score += 25;
                break;
            }
        }
        foreach (self::HATE_FRAGMENTS as $w) {
            if ($w !== '' && str_contains($norm, $w)) {
                $codes[] = 'hate_speech';
                $score += 60;
                break;
            }
        }

        if (preg_match_all('/\b(?:https?:\/\/|www\.)[^\s<>"\']+/iu', $text, $m)) {
            foreach ($m[0] as $url) {
                if ($this->looksLikePhishingUrl($url)) {
                    $codes[] = 'suspicious_url';
                    $score += 35;
                    break;
                }
            }
        }

        if (preg_match('/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', $text)) {
            $codes[] = 'pii_email';
            $score += 10;
        }
        if (preg_match('/\b(?:0[1-9]|(?:\+33|0033)[\s.-]?[1-9])(?:[\s.-]?\d{2}){4}\b/u', $text)) {
            $codes[] = 'pii_phone_fr';
            $score += 10;
        }

        $zalgo = $this->zalgoIntensity($text);
        if ($zalgo > 0.15) {
            $codes[] = 'unicode_obfuscation';
            $score += 20;
        }

        $score = min(100, $score);
        $codes = array_values(array_unique($codes));

        return ['score' => $score, 'codes' => $codes];
    }

    /**
     * @return array{score: int, codes: string[]}
     */
    public function scoreHtml(string $html): array
    {
        return $this->scorePlainText($this->htmlExtractor->toPlainText($html));
    }

    private function stripCombiningForCheck(string $s): string
    {
        return preg_replace('/\p{M}+/u', '', $s) ?? $s;
    }

    private function zalgoIntensity(string $text): float
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len < 5) {
            return 0.0;
        }
        $combining = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            if (preg_match('/\p{M}/u', $ch)) {
                $combining++;
            }
        }

        return $combining / max(1, $len);
    }

    private function looksLikePhishingUrl(string $url): bool
    {
        $lower = mb_strtolower($url, 'UTF-8');
        if (preg_match('/@(paypal|secure-login|verify|account)/i', $lower)) {
            return true;
        }
        if (preg_match('/bit\.ly|tinyurl|t\.co/i', $lower) && str_contains($lower, '@')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{score: int, codes: string[]}
     */
    public function scoreFilename(string $originalName): array
    {
        $codes = [];
        $score = 0;
        $base = basename($originalName);
        $r = $this->scorePlainText($base);
        $score += (int) ($r['score'] * 0.5);
        $codes = array_merge($codes, $r['codes']);
        if (preg_match('/\.(exe|bat|cmd|scr|pif|com|dll)(\s|$)/i', $base)) {
            $codes[] = 'executable_extension';
            $score += 40;
        }

        return ['score' => min(100, $score), 'codes' => array_values(array_unique($codes))];
    }
}
