<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Assainissement strict d'un e-mail HTML reçu : le HTML est relu dans un arbre DOM, puis réécrit balise par balise à partir
 * d'une liste blanche (aucune copie du texte d'origine). Ni script, ni style, ni iframe, ni formulaire, ni attribut
 * d'événement ou de style. Liens : http, https, mailto et tel seulement, rel="noopener noreferrer". Images distantes
 * remplacées par un texte tant que l'administrateur ne les affiche pas ; data: et javascript: refusés partout.
 */
final class HtmlSanitizer
{
    private const MAX_NODES = 20000;
    private const MAX_DEPTH = 40;

    /** Balises gardées (sans leurs attributs, sauf ceux listés dans attributes()). */
    private const ALLOWED = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'dd', 'del', 'div', 'dl', 'dt', 'em', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'ol', 'p', 'pre', 'q', 's', 'small', 'span', 'strike', 'strong', 'sub',
        'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul', 'center', 'font',
    ];

    private const VOID = ['br' => true, 'hr' => true, 'img' => true];

    /** Balises retirées avec tout leur contenu. */
    private const DROPPED = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select',
        'option', 'textarea', 'svg', 'math', 'head', 'title', 'meta', 'link', 'base', 'noscript', 'template', 'audio', 'video',
        'source', 'track', 'canvas', 'map', 'area', 'dialog', 'xmp', 'plaintext', 'listing', 'noframes', 'noembed', 'portal',
        'slot', 'datalist', 'optgroup', 'fieldset', 'legend', 'label', 'output', 'param', 'bgsound', 'marquee', 'blink',
    ];

    private int $nodes = 0;

    private bool $allowImages = false;

    /** @var (callable(string): ?string)|null */
    private $cidUrl = null;

    private string $blockedLabel = 'Image masquée';

    /**
     * @param (callable(string): ?string)|null $cidUrl adresse d'une pièce jointe à partir de son Content-ID (images « cid: »)
     */
    public static function sanitize(string $html, bool $allowImages = false, ?callable $cidUrl = null, string $blockedLabel = 'Image masquée'): string
    {
        $s = new self();
        $s->allowImages = $allowImages;
        $s->cidUrl = $cidUrl;
        $s->blockedLabel = $blockedLabel;

        return $s->run($html);
    }

    private function run(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (strlen($html) > 2097152) {
            $html = substr($html, 0, 2097152);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            $loaded = $dom->loadHTML(
                '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
            if (!$loaded) {
                return self::escapeText($html);
            }
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body === null) {
                return '';
            }
            $this->nodes = 0;

            return trim($this->children($body, 0));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function children(\DOMNode $node, int $depth): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if (++$this->nodes > self::MAX_NODES) {
                break;
            }
            $out .= $this->node($child, $depth);
        }

        return $out;
    }

    private function node(\DOMNode $node, int $depth): string
    {
        if ($node instanceof \DOMText) {
            return $node instanceof \DOMCdataSection ? '' : self::escapeText($node->nodeValue ?? '');
        }
        if (!$node instanceof \DOMElement || $depth > self::MAX_DEPTH) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROPPED, true)) {
            return '';
        }
        if (!in_array($tag, self::ALLOWED, true)) {
            // Balise inconnue : on garde son texte et ses enfants autorisés, pas la balise.
            return $this->children($node, $depth + 1);
        }
        if ($tag === 'img') {
            return $this->image($node);
        }
        if ($tag === 'a') {
            $href = $this->url((string) $node->getAttribute('href'), ['http', 'https', 'mailto', 'tel']);
            $inner = $this->children($node, $depth + 1);
            if ($href === null) {
                return $inner;
            }
            $title = $node->hasAttribute('title') ? ' title="' . self::escapeAttr(mb_substr($node->getAttribute('title'), 0, 200)) . '"' : '';

            return '<a href="' . $href . '"' . $title . ' rel="noopener noreferrer nofollow" target="_blank">' . $inner . '</a>';
        }
        $attrs = '';
        if (in_array($tag, ['td', 'th'], true)) {
            foreach (['colspan', 'rowspan'] as $name) {
                $v = $node->getAttribute($name);
                if (preg_match('/^\d{1,2}$/', $v) === 1 && (int) $v >= 1) {
                    $attrs .= ' ' . $name . '="' . (int) $v . '"';
                }
            }
        }
        if (in_array($tag, ['td', 'th', 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr'], true)) {
            $align = strtolower($node->getAttribute('align'));
            if (in_array($align, ['left', 'right', 'center'], true)) {
                $attrs .= ' align="' . $align . '"';
            }
        }
        $dir = strtolower($node->getAttribute('dir'));
        if ($dir === 'ltr' || $dir === 'rtl') {
            $attrs .= ' dir="' . $dir . '"';
        }
        if (isset(self::VOID[$tag])) {
            return '<' . $tag . $attrs . '>';
        }

        return '<' . $tag . $attrs . '>' . $this->children($node, $depth + 1) . '</' . $tag . '>';
    }

    private function image(\DOMElement $img): string
    {
        $alt = mb_substr(trim($img->getAttribute('alt')), 0, 200);
        $src = trim($img->getAttribute('src'));
        $clean = preg_replace('/[\x00-\x20\x7F]+/', '', $src) ?? '';
        $cid = null;
        if (stripos($clean, 'cid:') === 0 && $this->cidUrl !== null) {
            $cid = ($this->cidUrl)(substr($clean, 4));
        }
        $remote = $this->url($src, ['http', 'https']);
        $placeholder = '<span class="cm-img-off">[' . self::escapeText($alt !== '' ? $alt : $this->blockedLabel) . ']</span>';

        if ($cid !== null && $cid !== '') {
            // Pièce jointe du message : servie par la route protégée, donc sûre à afficher même sans « images distantes ».
            return '<img src="' . self::escapeAttr($cid) . '" alt="' . self::escapeAttr($alt) . '" loading="lazy">';
        }
        if ($remote === null || !$this->allowImages) {
            return $placeholder;
        }
        $w = $img->getAttribute('width');
        $h = $img->getAttribute('height');
        $dims = (preg_match('/^\d{1,4}$/', $w) === 1 ? ' width="' . (int) $w . '"' : '')
            . (preg_match('/^\d{1,4}$/', $h) === 1 ? ' height="' . (int) $h . '"' : '');

        return '<img src="' . $remote . '" alt="' . self::escapeAttr($alt) . '"' . $dims . ' loading="lazy" referrerpolicy="no-referrer">';
    }

    /**
     * Adresse nettoyée et échappée pour un attribut, ou null si son schéma n'est pas permis.
     *
     * @param list<string> $schemes
     */
    private function url(string $value, array $schemes): ?string
    {
        // Les navigateurs ignorent blancs et caractères de contrôle dans le schéma (« java\tscript: »).
        $v = preg_replace('/[\x00-\x20\x7F\x{00A0}\x{200B}-\x{200F}\x{2028}\x{2029}\x{FEFF}]+/u', '', $value) ?? '';
        if ($v === '' || strlen($v) > 2000 || preg_match('/^([a-z][a-z0-9+.\-]*):/i', $v, $m) !== 1) {
            return null;
        }
        if (!in_array(strtolower($m[1]), $schemes, true)) {
            return null;
        }
        if (in_array(strtolower($m[1]), ['http', 'https'], true)) {
            $host = parse_url($v, PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                return null;
            }
        }
        if (preg_match('/[<>"\'`\\\\]/', $v) === 1) {
            return null;
        }

        return self::escapeAttr($v);
    }

    private static function escapeText(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function escapeAttr(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Corps texte : échappé, adresses http(s) rendues cliquables (rel neutralisé), retours à la ligne gardés par le style
     * « white-space: pre-wrap » de l'écran.
     */
    public static function textToHtml(string $text): string
    {
        $parts = preg_split('~(https?://[^\s<>"\'`]+)~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $out = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $out .= self::escapeText($part);
                continue;
            }
            $trail = '';
            while ($part !== '' && strpbrk(substr($part, -1), '.,;:!?)]}') !== false) {
                $trail = substr($part, -1) . $trail;
                $part = substr($part, 0, -1);
            }
            $host = parse_url($part, PHP_URL_HOST);
            if (!is_string($host) || $host === '' || preg_match('/[\\\\]/', $part) === 1) {
                $out .= self::escapeText($part . $trail);
                continue;
            }
            $out .= '<a href="' . self::escapeAttr($part) . '" rel="noopener noreferrer nofollow" target="_blank">' . self::escapeText($part) . '</a>' . self::escapeText($trail);
        }

        return $out;
    }
}
