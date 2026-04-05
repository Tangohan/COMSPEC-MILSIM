<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Extrait du texte lisible depuis HTML pour heuristiques.
 */
final class HtmlTextExtractor
{
    public function toPlainText(string $html): string
    {
        $t = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $t = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t);
    }
}
