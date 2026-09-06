<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rendu Markdown léger et sûr pour la page de maintenance publique.
 * Indépendant du forum (pas de dépendance ExternalLeave / BDD).
 */
final class MaintenanceMarkdown
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        if ($markdown === '') {
            return '';
        }

        $escaped = htmlspecialchars($markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $blocks = preg_split("/\n{2,}/", $escaped) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = explode("\n", $block);
            $first = trim($lines[0]);

            if (preg_match('/^(?:\*{3}|-{3}|_{3})$/', $first) && count($lines) === 1) {
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^(FR|EN|DE|ES|IT|NL|PT)$/i', $first)) {
                $html[] = '<p class="lang">' . strtoupper($first) . '</p>';
                $rest = array_slice($lines, 1);
                $rest = array_values(array_filter($rest, static fn (string $l): bool => trim($l) !== ''));
                if ($rest !== []) {
                    if (self::isListBlock($rest)) {
                        $html[] = self::renderList($rest);
                    } else {
                        $joined = implode('<br>', array_map(
                            static fn (string $line): string => self::inline(trim($line)),
                            $rest
                        ));
                        $html[] = '<p>' . $joined . '</p>';
                    }
                }
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/u', $first, $hm) && count($lines) === 1) {
                $level = strlen($hm[1]);
                $tag = 'h' . min(3, $level + 1);
                $html[] = '<' . $tag . '>' . self::inline($hm[2]) . '</' . $tag . '>';
                continue;
            }

            if (self::isListBlock($lines)) {
                $html[] = self::renderList($lines);
                continue;
            }

            $joined = implode('<br>', array_map(
                static fn (string $line): string => self::inline(trim($line)),
                $lines
            ));
            $html[] = '<p>' . $joined . '</p>';
        }

        return implode('', $html);
    }

    /**
     * @param list<string> $lines
     */
    private static function isListBlock(array $lines): bool
    {
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            if (!preg_match('/^(?:[-*+]|\d+\.)\s+/', $t)) {
                return false;
            }
        }

        return $lines !== [];
    }

    /**
     * @param list<string> $lines
     */
    private static function renderList(array $lines): string
    {
        $ordered = false;
        $items = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/', $t, $m)) {
                $ordered = true;
                $items[] = self::inline($m[1]);
            } elseif (preg_match('/^[-*+]\s+(.+)$/', $t, $m)) {
                $items[] = self::inline($m[1]);
            }
        }
        if ($items === []) {
            return '';
        }
        $tag = $ordered ? 'ol' : 'ul';
        $lis = implode('', array_map(static fn (string $i): string => '<li>' . $i . '</li>', $items));

        return '<' . $tag . '>' . $lis . '</' . $tag . '>';
    }

    private static function inline(string $text): string
    {
        $text = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/i',
            static function (array $m): string {
                $href = htmlspecialchars(
                    html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );

                return '<a href="' . $href . '" rel="noopener noreferrer">' . $m[1] . '</a>';
            },
            $text
        ) ?? $text;

        return $text;
    }
}
