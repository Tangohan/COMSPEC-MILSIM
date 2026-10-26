<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Nettoyage HTML par parcours DOM avec liste blanche de balises et d'attributs.
 *
 * Contrairement aux regex (on*= sans guillemets, attributs conservés par strip_tags),
 * chaque attribut non listé est retiré ; href/src sont vérifiés, style est filtré propriété par propriété.
 */
final class SafeHtml
{
    /** Balises supprimées avec tout leur contenu. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'textarea', 'select', 'button',
        'input', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base', 'frame', 'frameset',
        'applet', 'audio', 'video', 'source', 'head', 'title',
    ];

    /** Propriétés CSS conservées dans style (pas de position, z-index, inset…). */
    private const STYLE_PROPERTIES = [
        'text-align', 'color', 'background-color', 'font-weight', 'font-style', 'font-size', 'font-family',
        'text-decoration', 'text-transform', 'line-height', 'letter-spacing', 'vertical-align',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-collapse', 'border-color',
        'border-width', 'border-style', 'width', 'max-width', 'min-width', 'height', 'max-height', 'white-space',
        'list-style-type',
    ];

    /** Profil mini-articles / doctrine (éditeur riche). */
    public const PROFILE_ARTICLE = [
        'p' => ['style'], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'],
        'blockquote' => [], 'a' => ['href'], 'img' => ['src', 'alt'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'style'],
        'td' => ['colspan', 'rowspan', 'style'], 'div' => ['style'], 'span' => ['style'], 'hr' => [],
    ];

    /** Profil texte simple (manuscrit FM, charte RH) : aucun attribut hors liens. */
    public const PROFILE_TEXT = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [],
    ];

    /**
     * Profil courrier (corps de lettres, modèles avec classes de mise en page).
     *
     * @return array<string, list<string>>
     */
    public static function documentProfile(): array
    {
        $common = ['class', 'style', 'align', 'title'];
        $tags = [
            'p', 'div', 'span', 'section', 'header', 'footer', 'article', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sup', 'sub', 'mark', 'code', 'pre', 'blockquote',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'caption',
            'colgroup', 'col', 'figure', 'figcaption', 'hr', 'br',
        ];
        $profile = [];
        foreach ($tags as $t) {
            $profile[$t] = $common;
        }
        $profile['th'] = [...$common, 'colspan', 'rowspan', 'width'];
        $profile['td'] = [...$common, 'colspan', 'rowspan', 'width'];
        $profile['a'] = [...$common, 'href'];
        $profile['img'] = [...$common, 'src', 'alt', 'width', 'height'];

        return $profile;
    }

    /**
     * @param array<string, list<string>> $allowed balise => attributs autorisés
     */
    public static function clean(string $html, array $allowed): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="safe-html-root">' . $html . '</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $ok ? $doc->getElementById('safe-html-root') : null;
        if ($root === null) {
            // Repli : texte seul, échappé.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        self::cleanChildren($root, $allowed);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * @param array<string, list<string>> $allowed
     */
    private static function cleanChildren(\DOMNode $parent, array $allowed): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMText) {
                continue;
            }
            if (!$node instanceof \DOMElement) {
                // Commentaires, CDATA, instructions de traitement.
                $parent->removeChild($node);
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }
            self::cleanChildren($node, $allowed);
            if (!array_key_exists($tag, $allowed)) {
                // Balise non autorisée : on garde son contenu.
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            self::cleanAttributes($node, $tag, $allowed[$tag]);
            if ($tag === 'img' && !$node->hasAttribute('src')) {
                $parent->removeChild($node);
            }
        }
    }

    /**
     * @param list<string> $allowedAttrs
     */
    private static function cleanAttributes(\DOMElement $el, string $tag, array $allowedAttrs): void
    {
        $attrs = [];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $attrs[strtolower($attr->nodeName)] = (string) $attr->nodeValue;
        }
        foreach (array_keys($attrs) as $name) {
            $el->removeAttribute($name);
        }
        foreach ($allowedAttrs as $name) {
            if (!isset($attrs[$name])) {
                continue;
            }
            $value = trim($attrs[$name]);
            if ($name === 'href') {
                $value = self::safeUrl($value, false);
            } elseif ($name === 'src') {
                $value = self::safeUrl($value, true);
            } elseif ($name === 'style') {
                $value = self::safeStyle($value);
            } elseif (in_array($name, ['colspan', 'rowspan', 'width', 'height'], true)) {
                $value = preg_match('/^\d{1,4}(%|px)?$/', $value) ? $value : null;
            } elseif ($name === 'class') {
                $value = preg_replace('/[^A-Za-z0-9_\- :\/\[\].#%]+/', '', $value) ?? '';
            } elseif ($name === 'align') {
                $value = in_array(strtolower($value), ['left', 'right', 'center', 'justify'], true) ? $value : null;
            }
            if ($value !== null && $value !== '') {
                $el->setAttribute($name, $value);
            }
        }
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function safeUrl(string $url, bool $image): ?string
    {
        // Retire les caractères de contrôle/espaces qui permettent « java\tscript: ».
        $url = preg_replace('/[\x00-\x20\x7f]+/', '', $url) ?? '';
        if ($url === '') {
            return null;
        }
        $pattern = $image ? '~^(https?://|/(?!/)|uploads/)~i' : '~^(https?://|/(?!/)|#|mailto:)~i';

        return preg_match($pattern, $url) ? $url : null;
    }

    private static function safeStyle(string $style): ?string
    {
        $kept = [];
        foreach (explode(';', $style) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $val = trim($parts[1]);
            if (!in_array($prop, self::STYLE_PROPERTIES, true) || $val === '') {
                continue;
            }
            // Valeurs simples uniquement : pas d'url(), expression(), échappements CSS…
            if (!preg_match('/^[#a-z0-9 .,%"\'-]+$/i', $val) && !preg_match('/^rgba?\([\d\s.,%]+\)$/i', $val)) {
                continue;
            }
            $kept[] = $prop . ': ' . $val;
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
