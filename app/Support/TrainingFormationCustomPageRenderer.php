<?php

declare(strict_types=1);

namespace App\Support;

final class TrainingFormationCustomPageRenderer
{
    private const MAX_CHAPTERS = 120;

    /** @return list<array{title: string, slug: string, html: string}> */
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
        foreach ($data as $item) {
            if (!is_array($item) || count($out) >= self::MAX_CHAPTERS) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $html = (string) ($item['html'] ?? '');
            if ($title === '' && trim(strip_tags($html)) === '') {
                continue;
            }
            $slug = self::normalizeAnchorSlug((string) ($item['slug'] ?? ''), $title, count($out));
            $out[] = ['title' => $title !== '' ? $title : 'Chapitre ' . (count($out) + 1), 'slug' => $slug, 'html' => $html];
        }

        return self::ensureUniqueSlugs($out);
    }

    /** @param array<string, mixed> $row */
    public static function render(array $row, string $assetsBaseUrl): string
    {
        $title = trim((string) ($row['title'] ?? 'Documentation'));
        $subtitle = trim((string) ($row['subtitle'] ?? ''));
        $summary = trim((string) ($row['summary'] ?? ''));
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sections = self::decodeSections(isset($row['sections_json']) ? (string) $row['sections_json'] : null);
        $intro = trim((string) (($row['intro_html'] ?? '') ?: ($row['html_body'] ?? '')));
        $cssHref = rtrim($assetsBaseUrl, '/') . '/assets/css/training_formation_doc.css';

        $accent = trim((string) ($row['accent_color'] ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#0f766e';
        }

        if ($sections !== []) {
            return self::renderHandbook($titleEsc, $subtitle, $summary, $intro, $sections, $cssHref, $accent, !empty($row['show_toc']));
        }

        return self::renderSingleFragment($titleEsc, $subtitle, $summary, $intro, $cssHref, $accent);
    }

    private static function renderSingleFragment(string $titleEsc, string $subtitle, string $summary, string $innerHtml, string $cssHref, string $accent): string
    {
        $headMeta = self::headerMeta($subtitle, $summary);
        $body = '<div class="formation-doc-shell formation-doc-shell--single" style="--doc-accent:' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . '">'
            . '<header class="formation-doc-header"><div class="formation-doc-header__inner">'
            . '<p class="formation-doc-kicker">Document officiel</p>'
            . '<h1 class="formation-doc-title">' . $titleEsc . '</h1>'
            . $headMeta
            . '</div></header>'
            . '<main class="formation-doc-main"><article class="formation-doc-prose">' . $innerHtml . '</article></main>'
            . '</div>';

        return self::wrapShell($titleEsc, $cssHref, $body);
    }

    /** @param list<array{title:string,slug:string,html:string}> $sections */
    private static function renderHandbook(string $titleEsc, string $subtitle, string $summary, string $introHtml, array $sections, string $cssHref, string $accent, bool $showToc): string
    {
        $toc = '';
        if ($showToc) {
            $toc = '<nav class="formation-doc-toc" aria-label="Sommaire"><p class="formation-doc-toc__label">Sommaire</p><ol class="formation-doc-toc__list">';
            foreach ($sections as $s) {
                $slug = htmlspecialchars($s['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $t = htmlspecialchars($s['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $toc .= '<li><a href="#' . $slug . '">' . $t . '</a></li>';
            }
            $toc .= '</ol></nav>';
        }

        $main = '<main class="formation-doc-main formation-doc-main--book">';
        if ($introHtml !== '') {
            $main .= '<section class="formation-doc-prose formation-doc-intro" aria-label="Introduction">' . $introHtml . '</section>';
        }
        foreach ($sections as $idx => $s) {
            $slug = htmlspecialchars($s['slug'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = htmlspecialchars($s['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $main .= '<article id="' . $slug . '" class="formation-doc-chapter">'
                . '<h2 class="formation-doc-chapter__title"><span class="formation-doc-chapter__index">' . ($idx + 1) . '.</span> ' . $t . '</h2>'
                . '<div class="formation-doc-prose">' . $s['html'] . '</div>'
                . '</article>';
        }
        $main .= '</main>';

        $headMeta = self::headerMeta($subtitle, $summary);
        $body = '<div class="formation-doc-shell formation-doc-shell--book" style="--doc-accent:' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . '">'
            . '<header class="formation-doc-header"><div class="formation-doc-header__inner">'
            . '<p class="formation-doc-kicker">Manuel opérationnel</p>'
            . '<h1 class="formation-doc-title">' . $titleEsc . '</h1>'
            . $headMeta
            . '</div></header>'
            . '<div class="formation-doc-layout">' . $toc . $main . '</div>'
            . '</div>';

        return self::wrapShell($titleEsc, $cssHref, $body);
    }

    private static function headerMeta(string $subtitle, string $summary): string
    {
        $out = '';
        if ($subtitle !== '') {
            $out .= '<p class="formation-doc-subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        if ($summary !== '') {
            $out .= '<p class="formation-doc-summary">' . htmlspecialchars($summary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        return $out;
    }

    private static function wrapShell(string $titleEsc, string $cssHref, string $bodyInner): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $titleEsc . '</title><link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></head><body class="formation-doc-body">' . $bodyInner . '</body></html>';
    }

    private static function normalizeAnchorSlug(string $rawSlug, string $title, int $index): string
    {
        $s = strtolower(trim($rawSlug !== '' ? $rawSlug : $title));
        $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s !== '' ? substr($s, 0, 80) : ('chapitre-' . ($index + 1));
    }

    /** @param list<array{title:string,slug:string,html:string}> $items @return list<array{title:string,slug:string,html:string}> */
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
