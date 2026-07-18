<?php

declare(strict_types=1);

/**
 * Parcours « canvas » : JSON stocké dans training_lessons.content (lesson_type = canvas).
 *
 * @return array<string, mixed>|null
 */
function training_canvas_decode(?string $json): ?array
{
    if ($json === null || trim($json) === '') {
        return null;
    }
    $d = json_decode($json, true);
    if (!is_array($d) || !isset($d['slides']) || !is_array($d['slides'])) {
        return null;
    }

    return $d;
}

/**
 * @return list<string>
 */
function training_canvas_allowed_dom_tags(): array
{
    return [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li',
        'a',
        'h2', 'h3', 'h4', 'h5',
        'blockquote',
        'span', 'div',
        'pre', 'code',
        'hr',
    ];
}

/**
 * Classes réservées au rendu « article de lecture » (canvas). Toute autre classe est retirée.
 *
 * @return array<string, true>
 */
function training_canvas_allowed_class_map(): array
{
    $classes = [
        'lms-reading-callout',
        'lms-reading-callout--info',
        'lms-reading-callout--tip',
        'lms-reading-code',
        'lms-reading-terminal',
        'lms-reading-hl-kw',
        'lms-reading-hl-str',
        'lms-reading-hl-fn',
        'lms-reading-hl-num',
        'lms-reading-hl-com',
        'lms-reading-hl-var',
    ];

    return array_fill_keys($classes, true);
}

function training_canvas_filter_class_attr(string $classAttr): string
{
    $map = training_canvas_allowed_class_map();
    $parts = preg_split('/\s+/u', trim($classAttr)) ?: [];
    $keep = [];
    foreach ($parts as $p) {
        if ($p !== '' && isset($map[$p])) {
            $keep[] = $p;
        }
    }

    return implode(' ', $keep);
}

function training_canvas_sanitize_href(string $href): ?string
{
    $h = trim($href);
    if ($h === '') {
        return null;
    }
    if (preg_match('/^\s*javascript:/i', $h) || preg_match('/^\s*data:/i', $h)) {
        return null;
    }
    if (preg_match('#\Ahttps?://#i', $h)) {
        return filter_var($h, FILTER_VALIDATE_URL) !== false ? $h : null;
    }
    if (str_starts_with($h, '/') && !str_starts_with($h, '//') && preg_match('#\A/[a-zA-Z0-9/_\\-.?=&%#+:~]*\z#', $h)) {
        return $h;
    }
    if (preg_match('/\Amailto:[^\s<>"\']+\z/i', $h)) {
        return $h;
    }

    return null;
}

function training_canvas_unwrap_dom_element(\DOMElement $el): void
{
    $p = $el->parentNode;
    if ($p === null) {
        return;
    }
    while ($el->firstChild !== null) {
        $p->insertBefore($el->firstChild, $el);
    }
    $p->removeChild($el);
}

function training_canvas_sanitize_dom_node(\DOMNode $node): void
{
    if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
        return;
    }
    if ($node->nodeType === XML_COMMENT_NODE) {
        $node->parentNode?->removeChild($node);

        return;
    }
    if (!$node instanceof \DOMElement) {
        return;
    }
    $el = $node;
    $tag = strtolower($el->tagName);

    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'option', 'button', 'svg', 'math', 'link', 'meta', 'base'], true)) {
        $el->parentNode?->removeChild($el);

        return;
    }

    $snapshot = [];
    for ($c = $el->firstChild; $c !== null; $c = $c->nextSibling) {
        $snapshot[] = $c;
    }
    foreach ($snapshot as $c) {
        training_canvas_sanitize_dom_node($c);
    }

    $allowed = training_canvas_allowed_dom_tags();
    if (!in_array($tag, $allowed, true)) {
        training_canvas_unwrap_dom_element($el);

        return;
    }

    $classBefore = $el->getAttribute('class');
    $hrefBefore = $el->getAttribute('href');
    while ($el->attributes->length > 0) {
        $el->removeAttribute($el->attributes->item(0)->name);
    }

    $tagsWithClass = ['span', 'div', 'pre', 'code', 'blockquote', 'a', 'p', 'h2', 'h3', 'h4', 'h5', 'li', 'ul', 'ol'];
    if (in_array($tag, $tagsWithClass, true)) {
        $filtered = training_canvas_filter_class_attr($classBefore);
        if ($filtered !== '') {
            $el->setAttribute('class', $filtered);
        }
    }

    if ($tag === 'a') {
        $safe = training_canvas_sanitize_href($hrefBefore);
        if ($safe === null) {
            training_canvas_unwrap_dom_element($el);

            return;
        }
        $el->setAttribute('href', $safe);
        if (preg_match('#\Ahttps?://#i', $safe)) {
            $el->setAttribute('rel', 'noopener noreferrer');
            $el->setAttribute('target', '_blank');
        }
    }
}

/**
 * HTML autorisé dans les corps de slide / modales (contenu auteur admin).
 * Balises sémantiques + liens (href filtré) + classes « lms-reading-* » pour encadrés et blocs de code.
 */
function training_canvas_sanitize_html(string $html): string
{
    if ($html === '') {
        return '';
    }

    $doc = new \DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $wrapped = '<div id="lms-canvas-sanitize">' . $html . '</div>';
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $wrapped, LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('lms-canvas-sanitize');
    if ($root === null) {
        $allowed = '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><h5><blockquote><span><div><pre><code><hr>';
        $out = strip_tags($html, $allowed);

        return preg_replace('/javascript:/i', '', $out) ?? $out;
    }

    $snapshot = [];
    for ($c = $root->firstChild; $c !== null; $c = $c->nextSibling) {
        $snapshot[] = $c;
    }
    foreach ($snapshot as $c) {
        training_canvas_sanitize_dom_node($c);
    }

    $htmlOut = '';
    for ($c = $root->firstChild; $c !== null; $c = $c->nextSibling) {
        $htmlOut .= $doc->saveHTML($c);
    }

    $htmlOut = preg_replace('/javascript:/i', '', $htmlOut) ?? $htmlOut;

    return $htmlOut;
}

/**
 * Remplace [[réponse]] dans un HTML déjà nettoyé par des champs saisie (texte à trous).
 */
function training_canvas_fill_blanks_html(string $sanitizedHtml): string
{
    $out = preg_replace_callback(
        '/\[\[([^\]]{1,200})\]\]/u',
        static function (array $m): string {
            $expected = trim($m[1]);
            $id = 'lms-blank-' . substr(sha1($expected . random_bytes(4)), 0, 10);

            return '<input type="text" name="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
                . '" autocomplete="off" spellcheck="false" class="lms-fill-blank-input inline-block min-w-[6rem] max-w-[16rem] border-b-2 border-violet-400 bg-violet-50/60 px-1.5 py-0.5 mx-0.5 rounded-t text-sm font-medium text-slate-900 focus:ring-2 focus:ring-violet-300 outline-none align-baseline" data-lms-blank data-expected="'
                . htmlspecialchars($expected, ENT_QUOTES, 'UTF-8') . '" aria-label="Compléter le mot manquant" />';
        },
        $sanitizedHtml
    );

    return $out ?? $sanitizedHtml;
}

/**
 * @return list<array{time: string, title: string, html: string}>
 */
function training_canvas_parse_timeline_events(string $body): array
{
    $t = trim($body);
    if ($t === '') {
        return [];
    }
    $j = json_decode($t, true);
    if (is_array($j)) {
        $out = [];
        foreach ($j as $row) {
            if (!is_array($row)) {
                continue;
            }
            $time = trim((string) ($row['time'] ?? $row['date'] ?? $row['label'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $html = trim((string) ($row['html'] ?? $row['text'] ?? $row['body'] ?? ''));
            if ($time === '' && $title === '' && $html === '') {
                continue;
            }
            $out[] = [
                'time' => $time,
                'title' => $title,
                'html' => $html !== '' ? training_canvas_sanitize_html($html) : '',
            ];
        }

        return $out;
    }
    $lines = preg_split('/\r\n|\r|\n/', $t) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 3));
        $out[] = [
            'time' => (string) ($parts[0] ?? ''),
            'title' => (string) ($parts[1] ?? ''),
            'html' => isset($parts[2]) && $parts[2] !== '' ? training_canvas_sanitize_html((string) $parts[2]) : '',
        ];
    }

    return $out;
}

/**
 * @param array<string, mixed> $slide
 * @return list<array{title: string, url: string}>
 */
function training_canvas_slide_resources(array $slide): array
{
    $raw = $slide['resources'] ?? null;
    if (is_array($raw)) {
        $r = [];
        foreach ($raw as $it) {
            if (!is_array($it)) {
                continue;
            }
            $u = trim((string) ($it['url'] ?? ''));
            if ($u === '') {
                continue;
            }
            $lab = trim((string) ($it['title'] ?? ''));
            $r[] = ['title' => $lab !== '' ? $lab : 'Ressource', 'url' => $u];
        }

        return $r;
    }
    $body = trim((string) ($slide['body'] ?? ''));
    if ($body === '') {
        return [];
    }
    $j = json_decode($body, true);
    if (!is_array($j)) {
        return [];
    }
    $r = [];
    foreach ($j as $it) {
        if (!is_array($it)) {
            continue;
        }
        $u = trim((string) ($it['url'] ?? $it['href'] ?? ''));
        if ($u === '') {
            continue;
        }
        $lab = trim((string) ($it['title'] ?? $it['label'] ?? ''));
        $r[] = ['title' => $lab !== '' ? $lab : 'Ressource', 'url' => $u];
    }

    return $r;
}
