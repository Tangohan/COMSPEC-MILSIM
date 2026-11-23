<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte riche des documents du bureau SSE : assainissement par liste blanche, rendu papier et conversion du texte historique.
 *
 * Un corps « riche » commence par le repère {@see self::MARK} ; tout autre corps reste du texte historique (rendu inchangé
 * par SseDocumentRepository::bodyToHtml). Le HTML enregistré passe TOUJOURS par {@see self::sanitize()} (à l'enregistrement
 * puis à l'affichage) : seules les balises, attributs et déclarations de style ci-dessous survivent. Aucun lien, image,
 * script, gestionnaire d'événement, identifiant ni URL n'est conservé.
 */
final class SseRichText
{
    public const MARK = '<!--sse-rich-->';

    public const MAX_BYTES = 600000;

    /** Palette sobre pour la couleur du texte et le surlignage. */
    public const TEXT_COLORS = ['#0b1220', '#475569', '#b91c1c', '#b45309', '#1d4ed8', '#15803d'];

    public const HIGHLIGHT_COLORS = ['#fef08a', '#fed7aa', '#fecaca', '#bfdbfe', '#bbf7d0', '#e2e8f0'];

    /** Champs dynamiques du dossier. */
    public const FIELDS = ['nom', 'grade', 'date', 'dossier', 'classification', 'reference', 'titre', 'type', 'id_fiche', 'nom_code', 'dse'];

    private const MAX_DEPTH = 24;

    /** Balises dont le contenu est supprimé avec elles. */
    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'template',
        'noscript', 'textarea', 'select', 'option', 'button', 'input', 'form', 'link', 'meta', 'base', 'head', 'title', 'audio',
        'video', 'canvas', 'img', 'picture', 'source', 'track', 'map', 'xml', 'xmp', 'plaintext', 'listing', 'noembed', 'noframes'];

    /** Balise d'entrée → balise de sortie ('' : on garde le contenu sans la balise). */
    private const TAGS = [
        'p' => 'p', 'div' => 'div', 'br' => 'br', 'hr' => 'hr', 'span' => 'span',
        'h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h4', 'h6' => 'h4',
        'blockquote' => 'blockquote',
        'strong' => 'strong', 'b' => 'strong', 'em' => 'em', 'i' => 'em', 'u' => 'u',
        's' => 's', 'strike' => 's', 'del' => 's', 'sub' => 'sub', 'sup' => 'sup', 'mark' => 'span',
        'ul' => 'ul', 'ol' => 'ol', 'li' => 'li',
        'table' => 'table', 'thead' => 'thead', 'tbody' => 'tbody', 'tfoot' => 'tbody', 'tr' => 'tr', 'td' => 'td', 'th' => 'th',
        'caption' => '', 'colgroup' => '', 'col' => '',
    ];

    /** Balises qui peuvent porter data-lock (texte verrouillé). */
    private const LOCKABLE = ['p', 'div', 'span', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'table'];

    private const VOID_TAGS = ['br', 'hr'];

    private const ALIGN = ['left', 'right', 'center', 'justify'];

    /** sse-hint : texte d'aide grisé des modèles (span), à remplacer ; il n'apparaît ni sur le papier ni dans le PDF. */
    private const CLASSES = ['sse-pagebreak', 'sse-field', 'sse-hint'];

    public static function isRich(string $body): bool
    {
        return str_starts_with(ltrim($body), self::MARK);
    }

    /** Corps enregistrable : repère + HTML assaini. */
    public static function wrap(string $html): string
    {
        return self::MARK . self::sanitize($html);
    }

    /** HTML assaini d'un corps riche (sans le repère) ; texte historique converti au passage. */
    public static function htmlOf(string $body): string
    {
        if (self::isRich($body)) {
            return self::sanitize(substr(ltrim($body), strlen(self::MARK)));
        }

        return self::fromLegacyText($body);
    }

    /** Texte brut (comptage, contrôle « non vide », recherche). */
    public static function plainText(string $body): string
    {
        $html = self::isRich($body) ? self::sanitize(substr(ltrim($body), strlen(self::MARK))) : '';
        if ($html === '') {
            return self::isRich($body) ? '' : trim($body);
        }
        $html = preg_replace('~<(?:br|/p|/h[2-4]|/li|/tr|/blockquote|hr)\b[^>]*>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text));
    }

    /**
     * HTML assaini d'un fragment quelconque. Idempotent : sanitize(sanitize(x)) === sanitize(x).
     */
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }
        if (strlen($html) > self::MAX_BYTES) {
            $html = substr($html, 0, self::MAX_BYTES);
        }
        // Les caractères de contrôle (hors tabulation et sauts de ligne) servent à contourner les filtres.
        $html = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $html);
        if (!class_exists(\DOMDocument::class)) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div id="r">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $root = $dom->getElementById('r');
        if (!$root instanceof \DOMElement) {
            foreach ($dom->getElementsByTagName('div') as $candidate) {
                $root = $candidate;
                break;
            }
        }
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= self::node($child, 0);
        }

        return trim($out);
    }

    private static function node(\DOMNode $node, int $depth): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$node instanceof \DOMElement) {
            return ''; // commentaires, instructions de traitement, CDATA
        }
        $name = strtolower($node->nodeName);
        if (in_array($name, self::DROP, true)) {
            return '';
        }
        $inner = static function () use ($node, $depth): string {
            if ($depth >= self::MAX_DEPTH) {
                return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $buf = '';
            foreach ($node->childNodes as $child) {
                $buf .= self::node($child, $depth + 1);
            }

            return $buf;
        };
        if (!isset(self::TAGS[$name])) {
            return $inner(); // balise inconnue : contenu gardé, balise retirée
        }
        $tag = self::TAGS[$name];
        if ($tag === '') {
            return $inner();
        }
        if (in_array($tag, self::VOID_TAGS, true)) {
            return '<' . $tag . '>';
        }

        $attrs = self::attributes($node, $tag, $name);
        // Saut de page : bloc vide.
        if ($tag === 'div') {
            if (!str_contains($attrs, 'sse-pagebreak')) {
                $attrs = str_replace(' class="sse-field"', '', $attrs);
            }
            if (str_contains($attrs, 'sse-pagebreak')) {
                return '<div' . $attrs . '></div>';
            }
        }
        $body = $inner();
        if ($tag === 'span' && $attrs === '') {
            return $body;
        }

        return '<' . $tag . $attrs . '>' . $body . '</' . $tag . '>';
    }

    private static function attributes(\DOMElement $el, string $tag, string $original): string
    {
        $out = '';
        $classes = [];
        if ($el->hasAttribute('class')) {
            foreach (preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [] as $c) {
                if (in_array($c, self::CLASSES, true)) {
                    $classes[$c] = $c;
                }
            }
        }
        // Le champ dynamique est un span ; le saut de page est un div. Pas d'autre emploi de ces classes.
        if (isset($classes['sse-pagebreak']) && $tag !== 'div') {
            unset($classes['sse-pagebreak']);
        }
        if (isset($classes['sse-field']) && $tag !== 'span') {
            unset($classes['sse-field']);
        }
        if (isset($classes['sse-hint']) && ($tag !== 'span' || isset($classes['sse-field']))) {
            unset($classes['sse-hint']);
        }
        $field = '';
        if ($tag === 'span' && isset($classes['sse-field'])) {
            $f = strtolower(trim($el->getAttribute('data-field')));
            if (in_array($f, self::FIELDS, true)) {
                $field = $f;
            } else {
                unset($classes['sse-field']);
            }
        }
        if ($classes !== []) {
            $out .= ' class="' . implode(' ', $classes) . '"';
        }
        if ($field !== '') {
            $out .= ' data-field="' . $field . '"';
        }
        // Verrou : seule la valeur « 1 » survit, sur un bloc ou un texte en ligne ; aucun autre attribut data-*.
        if (trim($el->getAttribute('data-lock')) === '1' && in_array($tag, self::LOCKABLE, true)) {
            $out .= ' data-lock="1"';
        }
        if ($tag === 'td' || $tag === 'th') {
            foreach (['colspan', 'rowspan'] as $a) {
                $v = trim($el->getAttribute($a));
                if (preg_match('/^\d{1,2}$/', $v) === 1 && (int) $v >= 2 && (int) $v <= 20) {
                    $out .= ' ' . $a . '="' . (int) $v . '"';
                }
            }
        }
        $style = self::style($el->getAttribute('style'), $tag);
        // Anciens attributs de mise en forme générés par certains navigateurs.
        if ($el->hasAttribute('align') && !str_contains($style, 'text-align')) {
            $al = strtolower(trim($el->getAttribute('align')));
            if (in_array($al, self::ALIGN, true) && in_array($tag, ['p', 'div', 'h2', 'h3', 'h4', 'blockquote', 'li', 'td', 'th'], true)) {
                $style .= 'text-align:' . $al . ';';
            }
        }
        if ($original === 'mark' && !str_contains($style, 'background-color')) {
            $style .= 'background-color:' . self::HIGHLIGHT_COLORS[0] . ';';
        }
        if ($style !== '') {
            $out .= ' style="' . $style . '"';
        }

        return $out;
    }

    /** Déclarations de style autorisées : alignement, interligne, couleur et surlignage de la palette. */
    private static function style(string $raw, string $tag): string
    {
        if ($raw === '' || strlen($raw) > 600) {
            return '';
        }
        $raw = (string) preg_replace('~/\*.*?\*/~s', '', $raw);
        $found = [];
        foreach (explode(';', $raw) as $decl) {
            $pos = strpos($decl, ':');
            if ($pos === false) {
                continue;
            }
            $prop = strtolower(trim(substr($decl, 0, $pos)));
            $val = strtolower(trim(substr($decl, $pos + 1)));
            if ($val === '' || preg_match('/[\\\\<>"\'&(]/', $val) === 1 && !str_starts_with($val, 'rgb(')) {
                continue;
            }
            switch ($prop) {
                case 'text-align':
                    if (in_array($val, self::ALIGN, true) && in_array($tag, ['p', 'div', 'h2', 'h3', 'h4', 'blockquote', 'li', 'td', 'th', 'ul', 'ol'], true)) {
                        $found['text-align'] = $val;
                    }
                    break;
                case 'line-height':
                    if (preg_match('/^(\d(?:\.\d{1,2})?)$/', $val, $m) === 1 && (float) $m[1] >= 1 && (float) $m[1] <= 3) {
                        $found['line-height'] = $m[1];
                    }
                    break;
                case 'color':
                    $c = self::paletteColor($val, self::TEXT_COLORS);
                    if ($c !== null) {
                        $found['color'] = $c;
                    }
                    break;
                case 'background-color':
                case 'background':
                    $c = self::paletteColor($val, self::HIGHLIGHT_COLORS);
                    if ($c !== null) {
                        $found['background-color'] = $c;
                    }
                    break;
                default:
                    break;
            }
        }
        $out = '';
        foreach ($found as $p => $v) {
            $out .= $p . ':' . $v . ';';
        }

        return $out;
    }

    /** @param list<string> $palette */
    private static function paletteColor(string $val, array $palette): ?string
    {
        $val = trim($val);
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*1(?:\.0+)?\s*)?\)$/', $val, $m) === 1) {
            $val = sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
        } elseif (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $val, $m) === 1) {
            $val = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return in_array($val, $palette, true) ? $val : null;
    }

    /**
     * Texte historique (sauts de ligne, puces « • », titres en majuscules, séparateurs) vers du HTML riche.
     * Les marques de caviardage [[…]] restent telles quelles.
     */
    public static function fromLegacyText(string $text): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $html = [];
        $list = false;
        $close = static function () use (&$html, &$list): void {
            if ($list) {
                $html[] = '</ul>';
                $list = false;
            }
        };
        $blank = 0;
        foreach ($lines as $raw) {
            $t = trim($raw);
            if ($t === '') {
                $close();
                $blank++;
                continue;
            }
            if ($blank > 1) {
                $html[] = '<p><br></p>';
            }
            $blank = 0;
            $e = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (preg_match('/^[═─\-_=]{6,}$/u', $t) === 1) {
                $close();
                $html[] = '<hr>';
                continue;
            }
            if (preg_match('/^[•\-]\s+(.*)$/u', $t, $m) === 1) {
                if (!$list) {
                    $html[] = '<ul>';
                    $list = true;
                }
                $html[] = '<li>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
                continue;
            }
            $close();
            $upper = mb_strtoupper($t, 'UTF-8');
            if ($t === $upper && mb_strlen($t) < 80 && !str_contains($t, ':') && preg_match('/\p{L}/u', $t) === 1) {
                $html[] = '<h2>' . $e . '</h2>';
            } elseif (preg_match('/^\d+\.\s+\S/u', $t) === 1 && mb_strlen($t) < 90) {
                $html[] = '<h3>' . $e . '</h3>';
            } elseif (preg_match('/^([^:]{2,60}):\s*(.*)$/u', $t, $m) === 1 && mb_strlen($t) < 120) {
                $html[] = '<p><strong>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' :</strong> '
                    . htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            } else {
                $html[] = '<p>' . $e . '</p>';
            }
        }
        $close();

        return implode('', $html);
    }

    /**
     * Rend l'HTML assaini lisible sur la feuille : marques de caviardage, valeurs à jour des champs dynamiques.
     *
     * @param array<string,string> $fields valeurs courantes par champ (nom, grade, date, dossier, classification)
     */
    public static function toPaperHtml(string $body, array $fields = []): string
    {
        $html = self::htmlOf($body);
        if ($html === '') {
            return '';
        }
        $html = self::refreshFields(self::stripHints($html), $fields);
        $html = self::redactions($html);

        return '<div class="sse-rich">' . $html . '</div>';
    }

    /** Aperçu d'un modèle (galerie) : comme le papier, mais les textes d'aide grisés restent visibles. */
    public static function toPreviewHtml(string $body, array $fields = []): string
    {
        $html = self::htmlOf($body);

        return $html === '' ? '' : '<div class="sse-rich">' . self::redactions(self::refreshFields($html, $fields)) . '</div>';
    }

    /** Retire les textes d'aide des modèles restés en place (sse-hint) : ils guident la rédaction, ils ne s'impriment pas. */
    public static function stripHints(string $html): string
    {
        if (!str_contains($html, 'sse-hint')) {
            return $html;
        }

        return (string) preg_replace('~<span class="sse-hint">.*?</span>~s', '', $html);
    }

    /** Vrai si le corps s'ouvre sur l'en-tête officiel d'un modèle (tableau portant le champ « référence »). */
    public static function hasTemplateHeader(string $body): bool
    {
        $html = self::htmlOf($body);

        return preg_match('~^<table(?: data-lock="1")?>~', $html) === 1 && str_contains(substr($html, 0, 1500), 'data-field="reference"');
    }

    /** Nombre de textes d'aide encore en place dans un corps. */
    public static function hintCount(string $body): int
    {
        return substr_count(self::htmlOf($body), '<span class="sse-hint">');
    }

    /** @param array<string,string> $fields */
    public static function refreshFields(string $html, array $fields): string
    {
        if ($fields === [] || !str_contains($html, 'data-field')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '~<span class="sse-field" data-field="([a-z_]+)"([^>]*)>(.*?)</span>~s',
            static function (array $m) use ($fields): string {
                $v = $fields[$m[1]] ?? null;
                $text = $v !== null && trim($v) !== '' ? htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $m[3];

                return '<span class="sse-field" data-field="' . $m[1] . '"' . $m[2] . '>' . $text . '</span>';
            },
            $html
        );
    }

    /** [[texte]] ou [[#12]] → barre noire (même rendu que le texte historique). */
    public static function redactions(string $html): string
    {
        if (!str_contains($html, '[[')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '~\[\[(.*?)\]\]~u',
            static function (array $m): string {
                $raw = trim($m[1]);
                if (preg_match('/^#(\d{1,3})$/', $raw, $s) === 1) {
                    $width = (int) $s[1];
                } else {
                    $width = mb_strlen(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
                }
                $width = max(3, min(90, $width));

                return '<span class="sse-doc-paper__redact" style="display:inline-block;width:' . $width
                    . 'ch;height:0.95em;background:#0b1220" role="img" aria-label="Passage caviardé" title="Passage caviardé"></span>';
            },
            $html
        );
    }

    /**
     * HTML adapté à TCPDF (tableaux bordés, sauts de page, caviardage noirci, pas de classes).
     *
     * @param array<string,string> $fields
     */
    public static function toPdfHtml(string $body, array $fields = []): string
    {
        $html = self::refreshFields(self::stripHints(self::htmlOf($body)), $fields);
        $html = (string) preg_replace_callback(
            '~\[\[(.*?)\]\]~u',
            static function (array $m): string {
                $raw = trim($m[1]);
                $n = preg_match('/^#(\d{1,3})$/', $raw, $s) === 1 ? (int) $s[1] : mb_strlen($raw, 'UTF-8');

                return '<span style="color:#0b1220;background-color:#0b1220">' . str_repeat('x', max(3, min(90, $n))) . '</span>';
            },
            $html
        );
        $html = str_replace(' data-lock="1"', '', $html);
        $html = (string) preg_replace('~<div class="sse-pagebreak"></div>~', '<br pagebreak="true"/>', $html);
        $html = (string) preg_replace('~<table(?: data-lock="1")?>~', '<table border="1" cellpadding="4" cellspacing="0" style="border-color:#94a3b8">', $html);
        $html = (string) preg_replace('~<hr>~', '<hr style="height:1px;color:#94a3b8">', $html);
        $html = (string) preg_replace('~<blockquote>~', '<blockquote style="color:#475569;font-style:italic">', $html);
        $html = (string) preg_replace('~<span class="sse-field" data-field="[a-z_]+">~', '<span>', $html);

        return $html;
    }
}
