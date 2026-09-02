<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Assainissement HTML des mini-articles (éditeurs TinyMCE).
 */
final class MiniArticleHtml
{
    private const MAX_BODY = 200_000;

    public static function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|form|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('/\bon\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? $html;
        $html = strip_tags(
            $html,
            '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><img><table><thead><tbody><tr><th><td><div><span><hr>'
        );

        $html = preg_replace_callback(
            '#<a\b([^>]*)>#i',
            static function (array $m): string {
                $attrs = $m[1];
                $href = '';
                if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $hm)) {
                    $href = trim((string) $hm[2]);
                }
                if ($href === '' || preg_match('#^(javascript|data|vbscript):#i', $href)) {
                    return '<a>';
                }
                // Délimiteur ~ : un # dans le motif (ancre) ne doit pas clôturer le pattern.
                if (!preg_match('~^(https?://|/|#|mailto:)~i', $href)) {
                    return '<a>';
                }

                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">';
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '#<img\b([^>]*)>#i',
            static function (array $m): string {
                $attrs = $m[1];
                $src = '';
                if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $sm)) {
                    $src = trim((string) $sm[2]);
                }
                if ($src === '' || preg_match('#^(javascript|data|vbscript):#i', $src)) {
                    return '';
                }
                if (!preg_match('#^(https?://|/|uploads/)#i', $src)) {
                    return '';
                }
                $alt = '';
                if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $attrs, $am)) {
                    $alt = trim((string) $am[2]);
                }

                return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                    . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">';
            },
            $html
        ) ?? $html;

        if (strlen($html) > self::MAX_BODY) {
            $html = substr($html, 0, self::MAX_BODY);
        }

        return trim($html);
    }

    /**
     * @return list<string>
     */
    public static function parseTags(string $raw): array
    {
        $parts = preg_split('/[,;#]+/u', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $tag = mb_strtolower(trim((string) $part));
            $tag = preg_replace('/\s+/u', '-', $tag) ?? $tag;
            $tag = preg_replace('/[^\p{L}\p{N}\-_]+/u', '', $tag) ?? $tag;
            if ($tag === '' || mb_strlen($tag) > 40) {
                continue;
            }
            $out[$tag] = $tag;
            if (count($out) >= 12) {
                break;
            }
        }

        return array_values($out);
    }

    public static function slugify(string $title, int $fallbackId = 0): string
    {
        $s = mb_strtolower(trim($title));
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'article' . ($fallbackId > 0 ? '-' . $fallbackId : '');
        }
        if (strlen($s) > 240) {
            $s = rtrim(substr($s, 0, 240), '-');
        }

        return $s;
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }
        $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($rel, 'uploads/tenant-articles/')) {
            return null;
        }

        return asset_url($rel);
    }
}
