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
        // Coupe avant nettoyage : une troncature après coup pourrait laisser une balise ouverte.
        if (strlen($html) > self::MAX_BODY) {
            $html = substr($html, 0, self::MAX_BODY);
        }
        // Parcours DOM à liste blanche : tout attribut non prévu (on*, avec ou sans guillemets) est retiré,
        // href/src sont vérifiés et style est limité à des propriétés de mise en forme.
        $html = SafeHtml::clean($html, SafeHtml::PROFILE_ARTICLE);

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
