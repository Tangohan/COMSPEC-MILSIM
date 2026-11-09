<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Community\PublicPageBuilder;

/**
 * Texte riche à liste blanche : le texte saisi reste du texte simple (stocké tel quel), un balisage léger est
 * interprété à l'affichage et tout le reste est échappé. Aucun HTML saisi n'est jamais conservé.
 *
 *   # Titre, ## Sous-titre      -> h3, h4
 *   - élément ou * élément      -> liste à puces
 *   1. élément                  -> liste numérotée
 *   > citation                  -> citation
 *   **gras**, *italique*, `code`, [texte](https://adresse)   (liens http, https, mailto, #ancre ou /chemin)
 *
 * Balises produites : p, h3, h4, ul, ol, li, blockquote, strong, em, code, br, a (href, rel, target seulement).
 */
final class RichTextLite
{
    public static function render(string $source): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        // Les octets de contrôle servent de repères internes : jamais ceux du texte saisi.
        $source = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $source) ?? '';
        $out = [];
        $para = [];
        $list = null; // [type, items]
        $quote = [];

        $flushPara = static function () use (&$para, &$out): void {
            if ($para !== []) {
                $out[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = static function () use (&$list, &$out): void {
            if ($list !== null) {
                $out[] = '<' . $list[0] . '>' . implode('', array_map(static fn (string $i): string => '<li>' . self::inline($i) . '</li>', $list[1])) . '</' . $list[0] . '>';
                $list = null;
            }
        };
        $flushQuote = static function () use (&$quote, &$out): void {
            if ($quote !== []) {
                $out[] = '<blockquote>' . implode('<br>', array_map([self::class, 'inline'], $quote)) . '</blockquote>';
                $quote = [];
            }
        };

        foreach (explode("\n", $source) as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                $flushPara();
                $flushList();
                $flushQuote();
                continue;
            }
            if (preg_match('/^(#{1,2})\s+(.+)$/u', $line, $m) === 1) {
                $flushPara();
                $flushList();
                $flushQuote();
                $tag = strlen($m[1]) === 1 ? 'h3' : 'h4';
                $out[] = '<' . $tag . '>' . self::inline($m[2]) . '</' . $tag . '>';
            } elseif (preg_match('/^[-*]\s+(.+)$/u', $line, $m) === 1) {
                $flushPara();
                $flushQuote();
                if ($list === null || $list[0] !== 'ul') {
                    $flushList();
                    $list = ['ul', []];
                }
                $list[1][] = $m[1];
            } elseif (preg_match('/^\d{1,3}[.)]\s+(.+)$/u', $line, $m) === 1) {
                $flushPara();
                $flushQuote();
                if ($list === null || $list[0] !== 'ol') {
                    $flushList();
                    $list = ['ol', []];
                }
                $list[1][] = $m[1];
            } elseif (preg_match('/^>\s?(.*)$/u', $line, $m) === 1) {
                $flushPara();
                $flushList();
                $quote[] = $m[1];
            } else {
                $flushList();
                $flushQuote();
                $para[] = $line;
            }
        }
        $flushPara();
        $flushList();
        $flushQuote();

        return implode("\n", $out);
    }

    /** Texte sans balisage (aperçus, libellés). */
    public static function plain(string $source): string
    {
        $s = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $source) ?? $source;
        $s = preg_replace('/(\*\*|`)/u', '', $s) ?? $s;
        $s = preg_replace('/^(#{1,2}|[-*>]|\d{1,3}[.)])\s+/mu', '', $s) ?? $s;

        return trim($s);
    }

    private static function inline(string $text): string
    {
        // Les liens sont mis de côté (adresse validée) pour que *, _ ou ` d'une adresse ne soient pas interprétés.
        $links = [];
        $text = preg_replace_callback('/\[([^\]\n]{1,200})\]\(([^)\s]{1,500})\)/u', static function (array $m) use (&$links): string {
            $href = PublicPageBuilder::link($m[2]);
            if ($href === '') {
                return $m[1];
            }
            $ext = preg_match('#^https?://#i', $href) === 1 ? ' target="_blank" rel="noopener noreferrer nofollow"' : '';
            $links[] = '<a href="' . self::e($href) . '"' . $ext . '>' . self::e($m[1]) . '</a>';

            return "\x01" . (count($links) - 1) . "\x02";
        }, $text) ?? $text;

        $text = self::e($text);
        $text = preg_replace('/`([^`]{1,200})`/u', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<![\w*])\*(?=\S)([^*]+?)(?<=\S)\*(?![\w*])/u', '<em>$1</em>', $text) ?? $text;

        return preg_replace_callback('/\x01(\d+)\x02/', static fn (array $m): string => $links[(int) $m[1]] ?? '', $text) ?? $text;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
