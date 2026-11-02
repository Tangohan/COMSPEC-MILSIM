<?php

declare(strict_types=1);

namespace App\Support\Wiki;

/**
 * BBCode Steam → HTML sûr, pour le wiki ATAK (docs/steam/discussions).
 *
 * Tout le texte est échappé ; seules les balises connues produisent du HTML, avec des attributs fixes :
 * h1/h2/h3, b/i/u/s, list/olist/[*], url (http, https ou chemin du site), hr, quote, table/tr/th/td,
 * code (contenu brut), noparse, spoiler. Une balise inconnue ou orpheline reste du texte (« [URGENT] »).
 * Les balises ouvertes sont refermées en fin de texte : le HTML produit est toujours bien formé.
 */
final class BbcodeRenderer
{
    private const BLOCK = ['h1', 'h2', 'h3', 'list', 'olist', '*', 'hr', 'quote', 'table', 'tr', 'th', 'td', 'spoiler'];
    private const INLINE = ['b', 'i', 'u', 's', 'url', 'url-bare', 'url-dead'];
    /** Conteneurs où le texte libre va dans des paragraphes. */
    private const FLOW = ['root', 'quote', 'spoiler'];

    /** @var array<string, string> balise BBCode => titre HTML */
    private array $headings;
    /** @var (callable(string): ?string)|null */
    private $boldLinker;
    private string $spoilerLabel;

    /** @var list<array{tag: string, html: string, start: int}> pile des éléments ouverts */
    private array $stack = [];
    private string $out = '';
    /** @var list<array{id: string, text: string}> */
    private array $toc = [];
    /** @var array<string, int> */
    private array $ids = [];

    /**
     * @param array<string, string> $headings ex. ['h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3']
     * @param (callable(string): ?string)|null $boldLinker texte brut d'un [b] sans balise intérieure => HTML du lien, ou null
     */
    public function __construct(array $headings = [], ?callable $boldLinker = null, string $spoilerLabel = 'Afficher')
    {
        $this->headings = $headings + ['h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3'];
        $this->boldLinker = $boldLinker;
        $this->spoilerLabel = $spoilerLabel;
    }

    /** @return array{html: string, toc: list<array{id: string, text: string}>} */
    public function render(string $bbcode): array
    {
        $this->stack = [['tag' => 'root', 'html' => '', 'start' => 0]];
        $this->out = '';
        $this->toc = [];
        $this->ids = [];
        $tokens = self::tokenize(str_replace(["\r\n", "\r"], "\n", $bbcode));
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if ($t['type'] === 'text') {
                $prevBlock = $i === 0 || self::isBlockToken($tokens[$i - 1]);
                $nextBlock = $i === $count - 1 || self::isBlockToken($tokens[$i + 1]);
                $this->text($t['raw'], $prevBlock, $nextBlock);
                continue;
            }
            $name = $t['name'];
            if (($name === 'code' || $name === 'noparse') && !$t['close']) {
                $i = $this->raw($tokens, $i, $name);
                continue;
            }
            if ($name === 'b' && !$t['close'] && $this->boldLinker !== null
                && isset($tokens[$i + 2]) && $tokens[$i + 1]['type'] === 'text'
                && $tokens[$i + 2]['type'] === 'tag' && $tokens[$i + 2]['name'] === 'b' && $tokens[$i + 2]['close']
                && !$this->inside('url') && !$this->inside('heading')) {
                $linked = ($this->boldLinker)($tokens[$i + 1]['raw']);
                if ($linked !== null) {
                    $this->inlineContext();
                    $this->out .= '<strong>' . $linked . '</strong>';
                    $i += 2;
                    continue;
                }
            }
            $t['close'] ? $this->close($t) : $this->open($t);
        }
        while (count($this->stack) > 1) {
            $this->pop();
        }

        return ['html' => $this->out, 'toc' => $this->toc];
    }

    /** Texte brut d'un BBCode (recherche, résumés) : balises retirées, espaces resserrés. */
    public static function plainText(string $bbcode): string
    {
        $text = preg_replace('/\[\/?(?:h[1-3]|b|i|u|s|strike|list|olist|\*|url(?:=[^\]]*)?|hr|quote(?:=[^\]]*)?|table|tr|th|td|code|noparse|spoiler)\]/i', ' ', $bbcode) ?? $bbcode;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** Ancre lisible : minuscules, sans accents, tirets. */
    public static function slug(string $text): string
    {
        $s = Search::fold($text);
        $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-');

        return $s !== '' ? $s : 'section';
    }

    /** Adresse autorisée : http(s) absolue, chemin du site ou ancre ; rien d'autre (javascript:, data:…). */
    public static function safeHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\x00-\x20"\'<>`\\\\]/', $href) === 1) {
            return null;
        }
        if (preg_match('#^https?://[a-z0-9.\-]+(?::\d+)?(?:[/?\#]|$)#i', $href) === 1) {
            return $href;
        }
        if ((str_starts_with($href, '/') && !str_starts_with($href, '//')) || str_starts_with($href, '#')) {
            return $href;
        }

        return null;
    }

    /** @return list<array{type: string, raw: string, name: string, close: bool, arg: ?string}> */
    private static function tokenize(string $s): array
    {
        $tokens = [];
        $re = '/\[(\/?)(h1|h2|h3|b|i|u|s|strike|list|olist|\*|url|hr|quote|table|tr|th|td|code|noparse|spoiler)(?:=([^\]\n]*))?\]/i';
        $pos = 0;
        if (preg_match_all($re, $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
            foreach ($m as $match) {
                $at = $match[0][1];
                if ($at > $pos) {
                    $tokens[] = ['type' => 'text', 'raw' => substr($s, $pos, $at - $pos), 'name' => '', 'close' => false, 'arg' => null];
                }
                $name = strtolower($match[2][0]);
                $close = $match[1][0] === '/';
                $arg = isset($match[3]) && $match[3][1] >= 0 ? $match[3][0] : null;
                $tokens[] = [
                    'type' => 'tag',
                    'raw' => $match[0][0],
                    'name' => match (true) {
                        $name === 'strike' => 's',
                        $name === '*' && $close => '/*',
                        default => $name,
                    },
                    'close' => $close,
                    'arg' => $arg,
                ];
                $pos = $at + strlen($match[0][0]);
            }
        }
        if ($pos < strlen($s)) {
            $tokens[] = ['type' => 'text', 'raw' => substr($s, $pos), 'name' => '', 'close' => false, 'arg' => null];
        }

        return $tokens;
    }

    /** @param array{type: string, name: string} $t */
    private static function isBlockToken(array $t): bool
    {
        return $t['type'] === 'tag' && (in_array($t['name'], self::BLOCK, true) || $t['name'] === '/*');
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function top(): string
    {
        return $this->stack[count($this->stack) - 1]['tag'];
    }

    private function inside(string $tag): bool
    {
        foreach ($this->stack as $el) {
            if ($el['tag'] === $tag) {
                return true;
            }
        }

        return false;
    }

    /** Vrai si une liste est ouverte sous le sommet, sans citation ni tableau entre les deux. */
    private function listBelow(): bool
    {
        for ($k = count($this->stack) - 1; $k > 0; $k--) {
            $tag = $this->stack[$k]['tag'];
            if ($tag === 'ul' || $tag === 'ol') {
                return true;
            }
            if (in_array($tag, ['quote', 'spoiler', 'table', 'heading'], true)) {
                return false;
            }
        }

        return false;
    }

    private function push(string $tag, string $html): void
    {
        $this->out .= $html;
        $this->stack[] = ['tag' => $tag, 'html' => $html, 'start' => strlen($this->out)];
    }

    private function pop(): void
    {
        if (count($this->stack) <= 1) {
            return;
        }
        /** @var array{tag: string, html: string, start: int} $el */
        $el = array_pop($this->stack);
        switch ($el['tag']) {
            case 'heading':
                $this->closeHeading($el);

                return;
            case 'url-bare':
                $this->closeBareUrl($el);

                return;
            case 'url-dead':
                return;
        }
        $this->out .= match ($el['tag']) {
            'spoiler' => '</div></details>',
            'table' => '</table></div>',
            'quote' => '</blockquote>',
            'url' => '</a>',
            'i' => '</em>',
            'b' => '</strong>',
            default => '</' . $el['tag'] . '>',
        };
    }

    /** Avant du contenu en ligne : paragraphe dans un conteneur de flux, élément dans une liste, cellule dans un tableau. */
    private function inlineContext(): void
    {
        $top = $this->top();
        if (in_array($top, self::FLOW, true)) {
            $this->push('p', '<p>');
        } elseif ($top === 'ul' || $top === 'ol') {
            $this->push('li', '<li>');
        } elseif ($top === 'table') {
            $this->push('tr', '<tr>');
            $this->push('td', '<td>');
        } elseif ($top === 'tr') {
            $this->push('td', '<td>');
        }
    }

    /** Ferme le paragraphe et les balises en ligne ouverts avant un bloc. */
    private function closeInline(): void
    {
        while (count($this->stack) > 1 && ($this->top() === 'p' || in_array($this->top(), self::INLINE, true))) {
            $this->pop();
        }
    }

    /** Vrai si un paragraphe est ouvert sous les balises en ligne du sommet. */
    private function paragraphOpen(): bool
    {
        for ($k = count($this->stack) - 1; $k > 0; $k--) {
            $tag = $this->stack[$k]['tag'];
            if ($tag === 'p') {
                return true;
            }
            if (!in_array($tag, self::INLINE, true)) {
                return false;
            }
        }

        return false;
    }

    private function text(string $raw, bool $prevBlock, bool $nextBlock): void
    {
        if ($prevBlock) {
            $raw = ltrim($raw, "\n");
        }
        if ($nextBlock) {
            $raw = rtrim($raw, "\n");
        }
        if ($raw === '') {
            return;
        }
        if (trim($raw) === '' && in_array($this->top(), ['ul', 'ol', 'table', 'tr', 'root', 'quote', 'spoiler'], true)) {
            return;
        }
        $chunks = preg_split('/\n{2,}/', $raw) ?: [$raw];
        foreach ($chunks as $k => $chunk) {
            if ($k > 0) {
                $this->paragraphBreak();
            }
            if ($chunk === '' || (trim($chunk) === '' && in_array($this->top(), self::FLOW, true))) {
                continue;
            }
            $this->inlineContext();
            $this->out .= str_replace("\n", "<br>\n", self::e($chunk));
        }
    }

    /** Ligne vide : nouveau paragraphe dans le flux (balises en ligne rouvertes), saut de ligne ailleurs. */
    private function paragraphBreak(): void
    {
        if (!$this->paragraphOpen()) {
            if (!in_array($this->top(), self::FLOW, true)) {
                $this->out .= "<br>\n";
            }

            return;
        }
        $reopen = [];
        while ($this->top() !== 'p') {
            $reopen[] = $this->stack[count($this->stack) - 1];
            $this->pop();
        }
        $this->pop();
        if ($reopen === []) {
            return;
        }
        $this->push('p', '<p>');
        foreach (array_reverse($reopen) as $el) {
            $this->push($el['tag'], $el['html']);
        }
    }

    /** @param array{name: string, arg: ?string, raw: string} $t */
    private function open(array $t): void
    {
        $name = $t['name'];
        switch ($name) {
            case 'b':
            case 'i':
            case 'u':
            case 's':
                $this->inlineContext();
                $this->push($name, match ($name) {
                    'b' => '<strong>',
                    'i' => '<em>',
                    default => '<' . $name . '>',
                });

                return;
            case 'url':
                $this->inlineContext();
                if ($t['arg'] === null) {
                    // [url]https://…[/url] : l'adresse est le texte, vérifiée à la fermeture.
                    $this->push('url-bare', '');

                    return;
                }
                $href = self::safeHref($t['arg']);
                if ($href === null) {
                    $this->push('url-dead', '');

                    return;
                }
                $this->push('url', self::anchor($href));

                return;
            case 'h1':
            case 'h2':
            case 'h3':
                $this->closeInline();
                while (count($this->stack) > 1 && !in_array($this->top(), self::FLOW, true)) {
                    $this->pop();
                }
                $this->push('heading', '');
                $this->stack[count($this->stack) - 1]['html'] = $this->headings[$name];

                return;
            case 'list':
            case 'olist':
                $this->closeInline();
                if ($this->top() === 'ul' || $this->top() === 'ol') {
                    $this->push('li', '<li>');
                }
                $this->push($name === 'list' ? 'ul' : 'ol', $name === 'list' ? '<ul>' : '<ol>');

                return;
            case '*':
                if ($this->listBelow()) {
                    while ($this->top() !== 'ul' && $this->top() !== 'ol') {
                        $this->pop();
                    }
                    $this->push('li', '<li>');
                } else {
                    $this->text('• ', false, false);
                }

                return;
            case 'hr':
                $this->closeInline();
                $this->out .= '<hr>';

                return;
            case 'quote':
                $this->closeInline();
                $this->push('quote', '<blockquote>');
                if ($t['arg'] !== null && trim($t['arg']) !== '') {
                    $this->out .= '<p class="atw-quote__by">' . self::e(trim($t['arg'])) . '</p>';
                }

                return;
            case 'spoiler':
                $this->closeInline();
                $this->push('spoiler', '<details class="atw-spoiler"><summary>' . self::e($this->spoilerLabel) . '</summary><div>');

                return;
            case 'table':
                $this->closeInline();
                $this->push('table', '<div class="atw-table"><table>');

                return;
            case 'tr':
            case 'th':
            case 'td':
                if (!$this->inside('table')) {
                    $this->text($t['raw'], false, false);

                    return;
                }
                $stop = $name === 'tr' ? ['table'] : ['tr', 'table'];
                while (!in_array($this->top(), $stop, true)) {
                    $this->pop();
                }
                if ($this->top() === 'table') {
                    $this->push('tr', '<tr>');
                }
                if ($name !== 'tr') {
                    $this->push($name, '<' . $name . '>');
                }

                return;
        }
        $this->text($t['raw'], false, false);
    }

    /** @param array{name: string, raw: string} $t */
    private function close(array $t): void
    {
        $name = $t['name'];
        if ($name === 'hr') {
            return; // [hr][/hr]
        }
        $want = match ($name) {
            // Comme Steam : [/list] ferme aussi un [olist] (et inversement).
            'list', 'olist' => ['ul', 'ol'],
            'h1', 'h2', 'h3' => ['heading'],
            'url' => ['url', 'url-bare', 'url-dead'],
            '/*' => ['li'],
            default => [$name],
        };
        $inline = in_array($name, ['b', 'i', 'u', 's', 'url'], true);
        $depth = null;
        for ($k = count($this->stack) - 1; $k > 0; $k--) {
            $tag = $this->stack[$k]['tag'];
            if (in_array($tag, $want, true)) {
                $depth = $k;
                break;
            }
            // Une balise en ligne ne se ferme pas à travers un bloc.
            if ($inline && !in_array($tag, self::INLINE, true)) {
                break;
            }
        }
        if ($depth === null) {
            $this->text($t['raw'], false, false);

            return;
        }
        while (count($this->stack) - 1 >= $depth) {
            $this->pop();
        }
    }

    private static function anchor(string $href): string
    {
        $external = preg_match('#^https?://#i', $href) === 1;

        return '<a href="' . self::e($href) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer nofollow"' : '') . '>';
    }

    /** @param array{tag: string, html: string, start: int} $el */
    private function closeHeading(array $el): void
    {
        $level = $el['html'];
        $inner = trim(substr($this->out, $el['start']));
        $this->out = substr($this->out, 0, $el['start']);
        $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return;
        }
        $id = self::slug($text);
        $n = $this->ids[$id] = ($this->ids[$id] ?? 0) + 1;
        if ($n > 1) {
            $id .= '-' . $n;
        }
        if ($level === 'h2') {
            $this->toc[] = ['id' => $id, 'text' => $text];
        }
        $this->out .= '<' . $level . ' id="' . self::e($id) . '">' . $inner . '</' . $level . '>';
    }

    /** @param array{tag: string, html: string, start: int} $el */
    private function closeBareUrl(array $el): void
    {
        $inner = substr($this->out, $el['start']);
        $href = self::safeHref(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href !== null) {
            $this->out = substr($this->out, 0, $el['start']) . self::anchor($href) . $inner . '</a>';
        }
    }

    /**
     * [code] et [noparse] : contenu brut échappé, sans interprétation. [code] multiligne = bloc.
     *
     * @param list<array{type: string, raw: string, name: string, close: bool, arg: ?string}> $tokens
     */
    private function raw(array $tokens, int $i, string $name): int
    {
        $raw = '';
        $count = count($tokens);
        for ($j = $i + 1; $j < $count && !($tokens[$j]['type'] === 'tag' && $tokens[$j]['name'] === $name && $tokens[$j]['close']); $j++) {
            $raw .= $tokens[$j]['raw'];
        }
        if ($name === 'noparse') {
            $this->text($raw, false, false);
        } elseif (str_contains($raw, "\n")) {
            $this->closeInline();
            $this->out .= '<pre><code>' . self::e(trim($raw, "\n")) . '</code></pre>';
        } else {
            $this->inlineContext();
            $this->out .= '<code>' . self::e($raw) . '</code>';
        }

        return $j;
    }
}
