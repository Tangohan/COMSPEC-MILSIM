<?php

declare(strict_types=1);

namespace App\Services\Academy;

/**
 * Texte riche des étapes Academy (cours intégrés et cours du Studio), rendu côté serveur.
 *
 * Syntaxe de type Markdown volontairement réduite (guide : docs/academy/format-texte-riche.md) :
 * titres, gras, italique, code, listes, séparateurs, citations, liens, tableaux, images, et des blocs
 * Academy « ::: » (encadrés, échange radio, étapes). Tout le texte est échappé avant la mise en forme :
 * aucune balise ni attribut saisi par l'auteur n'atteint la page. Liens http(s) uniquement, images limitées
 * aux fichiers envoyés depuis le Studio, aux visuels de l'Academy et à quelques hôtes autorisés.
 * Un texte sans syntaxe garde son rendu historique : paragraphes, retours à la ligne conservés.
 */
final class AcademyRichText
{
    /** Type d'encadré => classe canonique (alias français et anglais). */
    public const CALLOUTS = [
        'note' => 'note', 'info' => 'note',
        'astuce' => 'tip', 'tip' => 'tip',
        'attention' => 'warning', 'warning' => 'warning',
        'exemple' => 'example', 'example' => 'example',
        'analyse' => 'analysis', 'analysis' => 'analysis',
    ];

    private const RADIO = ['transmission', 'radio'];

    private const STEPS = ['etapes', 'étapes', 'steps'];

    /** Hôtes externes dont les images peuvent être affichées (HTTPS uniquement). */
    public const IMAGE_HOSTS = ['upload.wikimedia.org'];

    /** Fichiers envoyés depuis le Studio (TrainingCourseMediaUploadService) et visuels livrés avec l'Academy. */
    public const UPLOAD_PATTERN = '#\Auploads/training-course-media/\d{1,10}/academy-[a-f0-9]{16}\.(?:png|jpe?g|webp|gif)\z#';

    private const ASSET_PATTERN = '#\Aassets/images/academy/[A-Za-z0-9_-]{1,80}(?:/[A-Za-z0-9_-]{1,80}){0,2}\.(?:png|jpe?g|webp|gif)\z#';

    private const MAX_DEPTH = 3;

    /** @var array<string, string> */
    private array $labels;

    /** @var array<string, int> indicatifs d'un échange radio, dans l'ordre d'apparition */
    private array $speakers = [];

    /** @param array<string, string> $labels libellés traduits (clés rt_* de lang/{fr,en}/academy.php) */
    private function __construct(array $labels)
    {
        $this->labels = $labels + [
            'rt_note' => 'Note', 'rt_tip' => 'Tip', 'rt_warning' => 'Warning', 'rt_example' => 'Example',
            'rt_analysis' => 'Analysis', 'rt_radio' => 'Radio exchange', 'rt_steps' => 'Steps',
            'rt_image_blocked' => 'Image not shown: source not allowed.', 'rt_zoom' => 'Enlarge image',
        ];
    }

    /** @param array<string, string> $labels */
    public static function render(string $text, array $labels = []): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (trim($text) === '') {
            return '';
        }

        return (new self($labels))->blocks(explode("\n", $text), 0);
    }

    /** Source d'image acceptée (chemin d'envoi du Studio, visuel Academy, ou HTTPS d'un hôte autorisé). */
    public static function imageAllowed(string $src): bool
    {
        if (preg_match(self::UPLOAD_PATTERN, $src) || preg_match(self::ASSET_PATTERN, $src)) {
            return true;
        }

        return self::safeUrl($src, true) && in_array(strtolower((string) parse_url($src, PHP_URL_HOST)), self::IMAGE_HOSTS, true);
    }

    /** Lien autorisé : http(s), sans identifiants intégrés. */
    public static function safeUrl(string $url, bool $httpsOnly = false): bool
    {
        if ($url === '' || strlen($url) > 2000 || !filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\s<>"\'`]/', $url)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return ($httpsOnly ? $scheme === 'https' : in_array($scheme, ['http', 'https'], true))
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
            && (string) parse_url($url, PHP_URL_HOST) !== '';
    }

    private static function h(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function label(string $key): string
    {
        return self::h($this->labels[$key] ?? $key);
    }

    /** @param list<string> $lines */
    private function blocks(array $lines, int $depth): string
    {
        $html = [];
        $paragraph = [];
        $flush = function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . implode("<br>\n", array_map(fn (string $line): string => $this->inline($line), $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = rtrim($lines[$i]);
            $trim = trim($line);
            if ($trim === '') {
                $flush();
                continue;
            }
            // Bloc de code / journal : ```titre … ```
            if (str_starts_with($trim, '```')) {
                $flush();
                $title = trim(substr($trim, 3));
                $body = [];
                for ($i++; $i < $count && trim($lines[$i]) !== '```'; $i++) {
                    $body[] = rtrim($lines[$i]);
                }
                $pre = '<pre class="rt-code"><code>' . self::h(implode("\n", $body)) . '</code></pre>';
                $html[] = $title !== '' ? '<figure class="rt-log"><figcaption>' . $this->inline($title) . '</figcaption>' . $pre . '</figure>' : $pre;
                continue;
            }
            // Bloc Academy : :::type [titre] … :::
            if ($depth < self::MAX_DEPTH && preg_match('/\A:::\s*([\p{L}]+)\s*(.*)\z/u', $trim, $m)) {
                $type = mb_strtolower($m[1]);
                if (isset(self::CALLOUTS[$type]) || in_array($type, self::RADIO, true) || in_array($type, self::STEPS, true)) {
                    $flush();
                    $inner = [];
                    $nesting = 0;
                    for ($i++; $i < $count; $i++) {
                        $candidate = trim($lines[$i]);
                        if ($candidate === ':::') {
                            if ($nesting === 0) {
                                break;
                            }
                            --$nesting;
                        } elseif (preg_match('/\A:::\s*[\p{L}]+/u', $candidate)) {
                            ++$nesting;
                        }
                        $inner[] = $lines[$i];
                    }
                    $html[] = $this->academyBlock($type, trim($m[2]), $inner, $depth);
                    continue;
                }
            }
            if (preg_match('/\A(#{1,4})\s+(.+?)\s*#*\z/u', $trim, $m)) {
                $flush();
                $level = max(2, strlen($m[1]));
                $html[] = '<h' . $level . ' class="rt-h">' . $this->inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/\A(?:-{3,}|\*{3,}|_{3,})\z/', $trim)) {
                $flush();
                $html[] = '<hr class="rt-hr">';
                continue;
            }
            if (preg_match('/\A!\[([^\]\n]{0,300})\]\((\S{1,2000}?)\)\z/u', $trim, $m)) {
                $flush();
                $html[] = $this->image($m[2], $m[1]);
                continue;
            }
            if (str_starts_with($trim, '>')) {
                $flush();
                $quote = [];
                for (; $i < $count && str_starts_with(ltrim($lines[$i]), '>'); $i++) {
                    $quote[] = (string) preg_replace('/\A\s*>\s?/', '', rtrim($lines[$i]));
                }
                --$i;
                $html[] = '<blockquote class="rt-quote">' . ($depth < self::MAX_DEPTH ? $this->blocks($quote, $depth + 1) : '<p>' . implode('<br>', array_map(fn (string $q): string => $this->inline($q), $quote)) . '</p>') . '</blockquote>';
                continue;
            }
            if (preg_match('/\A\s*([-*•]|\d{1,3}[.)])\s+\S/u', $line, $m)) {
                $flush();
                $ordered = !in_array($m[1], ['-', '*', '•'], true);
                $start = $ordered ? (int) $m[1] : 1;
                $items = [];
                $pattern = $ordered ? '/\A\s*\d{1,3}[.)]\s+(.+)\z/u' : '/\A\s*[-*•]\s+(.+)\z/u';
                for (; $i < $count; $i++) {
                    $row = rtrim($lines[$i]);
                    if (preg_match($pattern, $row, $item)) {
                        $items[] = [$item[1]];
                    } elseif ($items !== [] && trim($row) !== '' && preg_match('/\A\s{2,}\S/', $row)) {
                        $items[count($items) - 1][] = trim($row);
                    } else {
                        break;
                    }
                }
                --$i;
                $lis = array_map(fn (array $parts): string => '<li>' . implode('<br>', array_map(fn (string $p): string => $this->inline($p), $parts)) . '</li>', $items);
                $html[] = $ordered
                    ? '<ol class="rt-list"' . ($start !== 1 ? ' start="' . $start . '"' : '') . '>' . implode('', $lis) . '</ol>'
                    : '<ul class="rt-list">' . implode('', $lis) . '</ul>';
                continue;
            }
            if (str_starts_with($trim, '|')) {
                $flush();
                $rows = [];
                for (; $i < $count && str_starts_with(trim($lines[$i]), '|'); $i++) {
                    $rows[] = trim($lines[$i]);
                }
                --$i;
                $html[] = $this->table($rows);
                continue;
            }
            $paragraph[] = $line;
        }
        $flush();

        return implode("\n", $html);
    }

    /** @param list<string> $inner */
    private function academyBlock(string $type, string $title, array $inner, int $depth): string
    {
        if (isset(self::CALLOUTS[$type])) {
            $kind = self::CALLOUTS[$type];
            $heading = $title !== '' ? $this->inline($title) : $this->label('rt_' . $kind);

            return '<aside class="rt-callout rt-callout--' . $kind . '"><p class="rt-callout__title"><span class="rt-ico" aria-hidden="true"></span>'
                . $heading . '</p><div class="rt-callout__body">' . $this->blocks($inner, $depth + 1) . '</div></aside>';
        }
        if (in_array($type, self::STEPS, true)) {
            $items = [];
            foreach ($inner as $row) {
                $row = trim((string) preg_replace('/\A\s*(?:\d{1,3}[.)]|[-*•])\s+/u', '', $row));
                if ($row !== '') {
                    $items[] = '<li><span>' . $this->inline($row) . '</span></li>';
                }
            }

            return ($title !== '' ? '<p class="rt-steps__title">' . $this->inline($title) . '</p>' : '')
                . '<ol class="rt-steps" aria-label="' . ($title !== '' ? self::h($title) : $this->label('rt_steps')) . '">' . implode('', $items) . '</ol>';
        }
        // Échange radio : « Indicatif : message » par ligne ; les autres lignes sont des indications de scène.
        $this->speakers = [];
        $rows = [];
        foreach ($inner as $row) {
            $row = trim($row);
            if ($row === '') {
                continue;
            }
            if (preg_match('/\A([^:«“"\[\]*`]{1,40}?)\s*:\s+(.+)\z/u', $row, $m)) {
                $who = trim($m[1]);
                $slot = $this->speakers[$who] ??= count($this->speakers);
                $rows[] = '<li class="rt-tx rt-tx--s' . ($slot % 4) . '"><b class="rt-tx__who">' . $this->inline($who) . '</b><span class="rt-tx__msg">' . $this->inline($m[2]) . '</span></li>';
            } else {
                $rows[] = '<li class="rt-tx-note">' . $this->inline($row) . '</li>';
            }
        }
        $heading = $title !== '' ? $this->inline($title) : $this->label('rt_radio');

        return '<figure class="rt-radio"><figcaption><span class="rt-ico" aria-hidden="true"></span>' . $heading . '</figcaption><ol class="rt-radio__list">' . implode('', $rows) . '</ol></figure>';
    }

    private function image(string $src, string $caption): string
    {
        if (!self::imageAllowed($src)) {
            return '<p class="rt-blocked">' . $this->label('rt_image_blocked') . '</p>';
        }
        $href = str_starts_with($src, 'https://') ? $src : (function_exists('url') ? url($src) : '/' . $src);
        $alt = trim($caption);

        return '<figure class="rt-figure"><a class="rt-figure__zoom" href="' . self::h($href) . '" target="_blank" rel="noopener noreferrer" data-rt-zoom title="' . $this->label('rt_zoom') . '">'
            . '<img src="' . self::h($href) . '" alt="' . self::h($alt) . '" loading="lazy" decoding="async" referrerpolicy="no-referrer"></a>'
            . ($alt !== '' ? '<figcaption>' . $this->inline($alt) . '</figcaption>' : '') . '</figure>';
    }

    /** @param list<string> $rows */
    private function table(array $rows): string
    {
        $cells = static function (string $row): array {
            $row = trim($row);
            $row = substr($row, 1);
            if (str_ends_with($row, '|')) {
                $row = substr($row, 0, -1);
            }

            return array_map('trim', explode('|', $row));
        };
        $head = null;
        $body = [];
        foreach ($rows as $index => $row) {
            if (preg_match('/\A\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\z/', $row)) {
                if ($index === 1 && $head === null && $body !== []) {
                    $head = array_pop($body);
                }
                continue;
            }
            $body[] = $cells($row);
        }
        $width = max(array_map('count', array_merge($head !== null ? [$head] : [], $body)) ?: [1]);
        $line = function (array $row, string $tag) use ($width): string {
            $out = '';
            for ($c = 0; $c < $width; $c++) {
                $out .= '<' . $tag . '>' . $this->inline((string) ($row[$c] ?? '')) . '</' . $tag . '>';
            }

            return '<tr>' . $out . '</tr>';
        };

        return '<div class="rt-table"><table>' . ($head !== null ? '<thead>' . $line($head, 'th') . '</thead>' : '')
            . '<tbody>' . implode('', array_map(static fn (array $row): string => $line($row, 'td'), $body)) . '</tbody></table></div>';
    }

    /** Mise en forme d'une ligne : le texte est échappé d'abord, seules des balises fixes sont ajoutées. */
    private function inline(string $text): string
    {
        $out = '';
        foreach (preg_split('/(`[^`\n]{1,500}`)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text] as $index => $part) {
            if ($index % 2 === 1) {
                $out .= '<code>' . self::h(substr($part, 1, -1)) . '</code>';
                continue;
            }
            foreach (preg_split('/(\[[^\]\n]{1,300}\]\([^)\s]{1,2000}\))/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$part] as $j => $piece) {
                if ($j % 2 === 1 && preg_match('/\A\[([^\]\n]{1,300})\]\(([^)\s]{1,2000})\)\z/u', $piece, $m)) {
                    $label = $this->emphasis(self::h($m[1]));
                    $out .= self::safeUrl($m[2])
                        ? '<a href="' . self::h($m[2]) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>'
                        : $label;
                    continue;
                }
                $out .= $this->emphasis(self::h($piece));
            }
        }

        return $out;
    }

    /** Gras et italique sur un texte déjà échappé (l'astérisque n'est pas modifié par l'échappement). */
    private function emphasis(string $escaped): string
    {
        $escaped = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $escaped);

        return (string) preg_replace('/(?<![*\p{L}\p{N}])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\p{L}\p{N}])/u', '<em>$1</em>', $escaped);
    }
}
