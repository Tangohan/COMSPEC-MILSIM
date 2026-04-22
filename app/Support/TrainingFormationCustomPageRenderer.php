<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rendu HTML des documentations autonomes (page unique ou manuel à chapitres).
 */
final class TrainingFormationCustomPageRenderer
{
    private const MAX_CHAPTERS = 80;

    /**
     * @return list<array{title: string, slug: string, html: string}>
     */
    public static function decodeSections(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (count($out) >= self::MAX_CHAPTERS) {
                break;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $html = (string) ($item['html'] ?? '');
            $slug = self::normalizeAnchorSlug((string) ($item['slug'] ?? ''), $title, count($out));
            if ($title === '' && trim(strip_tags($html)) === '') {
                continue;
            }
            if ($title === '') {
                $title = 'Chapitre ' . (count($out) + 1);
            }
            $out[] = ['title' => $title, 'slug' => $slug, 'html' => $html];
        }

        return self::ensureUniqueSlugs($out);
    }

    /**
     * @param array<string, mixed> $row ligne training_formation_custom_pages
     */
    public static function render(array $row, string $assetsBaseUrl): string
    {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = 'Documentation';
        }
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sections = self::decodeSections(isset($row['sections_json']) ? (string) $row['sections_json'] : null);
        $intro = trim((string) ($row['html_body'] ?? ''));
        $cssHref = rtrim($assetsBaseUrl, '/') . '/assets/css/training_formation_doc.css';

        if ($sections !== []) {
            return self::renderHandbook($titleEsc, $intro, $sections, $cssHref);
        }

        $html = $intro;
        $isFullDoc = preg_match('/^\s*<!DOCTYPE\s+html/i', $html) === 1
            || preg_match('/^\s*<html[\s>]/i', $html) === 1;
        if ($isFullDoc) {
            return $html;
        }

        return self::renderSingleFragment($titleEsc, $html, $cssHref);
    }

    private static function renderSingleFragment(string $titleEsc, string $innerHtml, string $cssHref): string
    {
        $body = '<div class="formation-doc-shell formation-doc-shell--single">'
            . '<header class="formation-doc-header"><div class="formation-doc-header__inner">'
            . '<p class="formation-doc-kicker">Documentation</p>'
            . '<h1 class="formation-doc-title">' . $titleEsc . '</h1>'
            . '</div></header>'
            . '<main class="formation-doc-main"><article class="formation-doc-prose">' . $innerHtml . '</article></main>'
            . '</div>';

        return self::wrapShell($titleEsc, $cssHref, $body);
    }

    /**
     * @param list<array{title: string, slug: string, html: string}> $sections
     */
    private static function renderHandbook(string $titleEsc, string $introHtml, array $sections, string $cssHref): string
    {
        $toc = '<nav class="formation-doc-toc" aria-label="Sommaire"><p class="formation-doc-toc__label">Sommaire</p><ol class="formation-doc-toc__list">';
        foreach ($sections as $s) {
            $slug = htmlspecialchars($s['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = htmlspecialchars($s['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $toc .= '<li><a href="#' . $slug . '">' . $t . '</a></li>';
        }
        $toc .= '</ol></nav>';

        $main = '<main class="formation-doc-main formation-doc-main--book">';
        if ($introHtml !== '') {
            $main .= '<section class="formation-doc-prose formation-doc-intro" aria-label="Introduction">' . $introHtml . '</section>';
        }
        foreach ($sections as $s) {
            $slug = htmlspecialchars($s['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = htmlspecialchars($s['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $main .= '<article id="' . $slug . '" class="formation-doc-chapter">'
                . '<h2 class="formation-doc-chapter__title">' . $t . '</h2>'
                . '<div class="formation-doc-prose">' . $s['html'] . '</div>'
                . '</article>';
        }
        $main .= '</main>';

        $body = '<div class="formation-doc-shell formation-doc-shell--book">'
            . '<header class="formation-doc-header"><div class="formation-doc-header__inner">'
            . '<p class="formation-doc-kicker">Documentation</p>'
            . '<h1 class="formation-doc-title">' . $titleEsc . '</h1>'
            . '</div></header>'
            . '<div class="formation-doc-layout">' . $toc . $main . '</div>'
            . '</div>';

        return self::wrapShell($titleEsc, $cssHref, $body);
    }

    private static function wrapShell(string $titleEsc, string $cssHref, string $bodyInner): string
    {
        return '<!DOCTYPE html><html lang="fr"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $titleEsc . '</title>'
            . '<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . '</head><body class="formation-doc-body">' . $bodyInner . '</body></html>';
    }

    private static function normalizeAnchorSlug(string $rawSlug, string $title, int $index): string
    {
        $s = strtolower(trim($rawSlug));
        if ($s === '') {
            $t = $title;
            if (function_exists('iconv')) {
                $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
                if ($conv !== false && $conv !== '') {
                    $t = $conv;
                }
            }
            $s = strtolower($t);
        }
        $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'chapitre-' . ($index + 1);
        }

        return substr($s, 0, 80);
    }

    /**
     * @param list<array{title: string, slug: string, html: string}> $items
     * @return list<array{title: string, slug: string, html: string}>
     */
    private static function ensureUniqueSlugs(array $items): array
    {
        $seen = [];
        foreach ($items as $i => $item) {
            $base = $item['slug'];
            $slug = $base;
            $n = 2;
            while (isset($seen[$slug])) {
                $slug = substr($base, 0, 72) . '-' . $n;
                ++$n;
            }
            $seen[$slug] = true;
            $items[$i]['slug'] = $slug;
        }

        return $items;
    }
}
