<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sanitiseur HTML par liste blanche (DOMDocument, sans dépendance externe).
 * Pour tout contenu riche saisi par du staff et rendu tel quel côté public
 * (ex. Documentations HTML) — retire les vecteurs XSS (scripts, gestionnaires
 * d'événements, URLs javascript:, iframes/objets, etc.) tout en conservant
 * le formatage éditorial courant.
 */
final class HtmlContentSanitizer
{
    /** Balises toujours retirées avec leur sous-arbre (dangereuses par nature). */
    private const BLOCK_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'form', 'input', 'button', 'select', 'option', 'textarea',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
        'audio', 'video', 'source', 'track', 'canvas', 'dialog',
    ];

    /** Balises conservées (formatage éditorial), attributs filtrés. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'span', 'div', 'figure', 'figcaption', 'small', 'mark', 'dl', 'dt', 'dd', 'abbr',
    ];

    /** Attributs spécifiques autorisés par balise, en plus de GLOBAL_ATTRIBUTES. */
    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /** Attributs autorisés sur toutes les balises conservées. */
    private const GLOBAL_ATTRIBUTES = ['class', 'id'];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // Le préfixe force l'interprétation UTF-8 sans que loadHTML() ajoute <html><body>.
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        foreach (iterator_to_array($dom->childNodes) as $child) {
            if ($child instanceof \DOMProcessingInstruction) {
                $dom->removeChild($child);
            }
        }

        self::sanitizeChildren($dom);

        $out = '';
        foreach ($dom->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeChildren(\DOMNode $context): void
    {
        foreach (iterator_to_array($context->childNodes) as $node) {
            if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) {
                $context->removeChild($node);
                continue;
            }
            if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
                continue;
            }
            if (!$node instanceof \DOMElement) {
                $context->removeChild($node);
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::BLOCK_TAGS, true)) {
                $context->removeChild($node);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Balise non supportée : on garde le contenu, on retire l'enveloppe.
                self::sanitizeChildren($node);
                while ($node->firstChild) {
                    $context->insertBefore($node->firstChild, $node);
                }
                $context->removeChild($node);
                continue;
            }

            self::sanitizeAttributes($node, $tag);
            self::sanitizeChildren($node);
        }
    }

    private static function sanitizeAttributes(\DOMElement $node, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::TAG_ATTRIBUTES[$tag] ?? []);
        foreach (iterator_to_array($node->attributes) as $attr) {
            /** @var \DOMAttr $attr */
            $name = strtolower($attr->name);
            if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                $node->removeAttribute($attr->name);
                continue;
            }
            if ($name === 'href') {
                $value = self::sanitizeUrl($attr->value, ['http', 'https', 'mailto', 'tel']);
                $value === null ? $node->removeAttribute('href') : $node->setAttribute('href', $value);
            } elseif ($name === 'src') {
                $value = self::sanitizeImageSrc($attr->value);
                $value === null ? $node->removeAttribute('src') : $node->setAttribute('src', $value);
            } elseif ($name === 'target') {
                if ($attr->value !== '_blank') {
                    $node->removeAttribute('target');
                }
            } elseif ($name === 'rel') {
                $node->setAttribute('rel', 'noopener noreferrer');
            } elseif (in_array($name, ['width', 'height', 'colspan', 'rowspan'], true) && !preg_match('/^\d{1,4}$/', $attr->value)) {
                $node->removeAttribute($name);
            }
        }
    }

    /** @param list<string> $allowedSchemes */
    private static function sanitizeUrl(string $value, array $allowedSchemes): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '#') || str_starts_with($value, '/')) {
            return $value;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme === '') {
            return $value;
        }

        return in_array($scheme, $allowedSchemes, true) ? $value : null;
    }

    private static function sanitizeImageSrc(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-zA-Z0-9+/=]+$#', $value)) {
            return $value;
        }

        return self::sanitizeUrl($value, ['http', 'https']);
    }
}
