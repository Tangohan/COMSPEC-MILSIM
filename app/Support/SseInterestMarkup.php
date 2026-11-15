<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mini-balisage des textes d'un dossier d'intérêt : **gras**, *italique*, listes (lignes commençant par « - »), paragraphes.
 *
 * Le texte stocké reste celui que l'auteur a tapé. Au rendu, tout est d'abord échappé en HTML, puis seules les marques
 * ci-dessus sont reconnues : aucune balise, aucun lien, aucun attribut ne peut passer, quoi que contienne la saisie.
 */
final class SseInterestMarkup
{
    /** Texte propre à stocker : fins de ligne normalisées, signes de contrôle retirés, au plus $max signes. */
    public static function clean(string $raw, int $max): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", trim($text));

        return mb_substr($text, 0, $max);
    }

    /** HTML sûr (échappé) d'un texte à mini-balisage. */
    public static function toHtml(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $blocks = preg_split("/\n{2,}/", str_replace(["\r\n", "\r"], "\n", $text)) ?: [];
        $html = '';
        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            $isList = true;
            foreach ($lines as $line) {
                if (preg_match('/^\s*[-•]\s+\S/u', $line) !== 1) {
                    $isList = false;
                    break;
                }
            }
            if ($isList) {
                $html .= '<ul>';
                foreach ($lines as $line) {
                    $html .= '<li>' . self::inline((string) preg_replace('/^\s*[-•]\s+/u', '', $line)) . '</li>';
                }
                $html .= '</ul>';
                continue;
            }
            $html .= '<p>' . implode('<br>', array_map(static fn (string $l): string => self::inline($l), $lines)) . '</p>';
        }

        return $html;
    }

    /** Texte brut (sans marques) pour les extraits et le PDF. */
    public static function plain(?string $text): string
    {
        $text = (string) preg_replace('/\*\*(.+?)\*\*/su', '$1', (string) $text);
        $text = (string) preg_replace('/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?![\*\w])/su', '$1', $text);

        return trim((string) preg_replace('/^\s*[-•]\s+/mu', '· ', $text));
    }

    private static function inline(string $line): string
    {
        $safe = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safe = (string) preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $safe);

        return (string) preg_replace('/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?![\*\w])/su', '<em>$1</em>', $safe);
    }
}
