<?php

declare(strict_types=1);

/*
 * Rendu Markdown simple et sécurisé (documentation, contenus du portail).
 * Les liens externes passent par l’interstitiel signé /leave.
 */

if (!function_exists('markdown_resolve_href_for_http_url')) {
    /**
     * URL http(s) déjà validée : lien interne direct ou interstitiel /leave signé si externe
     *
     * @param list<string> $extraInternalHosts
     */
    function markdown_resolve_href_for_http_url(string $sanitizedUrl, array $extraInternalHosts = []): string
    {
        $svc = new \App\Services\Navigation\ExternalLeaveService();
        $href = $sanitizedUrl;
        if (!$svc->isInternalUrl($sanitizedUrl, $extraInternalHosts)) {
            $leave = $svc->buildSignedLeaveUrl($sanitizedUrl);
            if ($leave !== null) {
                $href = $leave;
            }
        }

        return $href;
    }
}

if (!function_exists('markdown_linkify_plain_http_urls')) {
    /**
     * Transforme les URL http(s) saisies en texte brut en liens (hors &lt;pre&gt;, &lt;code&gt;, &lt;a&gt;).
     * Réutilise le même interstitiel /leave que le Markdown [libellé](url).
     */
    function markdown_linkify_plain_http_urls(string $html): string
    {
        $placeholders = [];
        $n = 0;
        $stash = static function (array $m) use (&$placeholders, &$n): string {
            $chunk = $m[0] ?? '';
            $key = '@@FURLP' . ($n++) . '@@';
            $placeholders[$key] = $chunk;

            return $key;
        };

        $html = preg_replace_callback('#<pre\b[^>]*>.*?</pre>#is', $stash, $html) ?? $html;
        $html = preg_replace_callback('#<a\b[^>]*>.*?</a>#is', $stash, $html) ?? $html;
        $html = preg_replace_callback('#<code\b[^>]*>.*?</code>#is', $stash, $html) ?? $html;

        $svc = new \App\Services\Navigation\ExternalLeaveService();
        $extraHosts = [];

        $html = preg_replace_callback(
            '#\bhttps?://[^\s<>"\'\[\]]+#iu',
            static function (array $m) use ($svc, $extraHosts): string {
                $matched = $m[0];
                $trail = '';
                if (preg_match('#([.,;:!?]+)$#u', $matched, $tm)) {
                    $trail = $tm[1];
                    $matched = substr($matched, 0, -strlen($trail));
                }
                $decoded = html_entity_decode($matched, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $sanitized = $svc->sanitizeHttpUrl($decoded);
                if ($sanitized === null) {
                    return $m[0];
                }
                $href = markdown_resolve_href_for_http_url($sanitized, $extraHosts);
                $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $safeLabel = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $class = 'text-orange-400 hover:text-orange-300 underline break-all';

                return '<a href="' . $safeHref . '" rel="noopener noreferrer" class="' . $class . '">' . $safeLabel . '</a>' . $trail;
            },
            $html
        );

        foreach ($placeholders as $key => $chunk) {
            $html = str_replace($key, $chunk, $html);
        }

        return $html;
    }
}

if (!function_exists('markdown_process_tables')) {
    function markdown_process_tables(string $content): string
    {
        $lines = explode("\n", $content);
        $result = [];
        $i = 0;
        $n = count($lines);
        while ($i < $n) {
            $line = $lines[$i];
            if (preg_match('/^\|.*\|\s*$/', trim($line))) {
                $block = [];
                while ($i < $n && preg_match('/^\|.*\|\s*$/', trim($lines[$i]))) {
                    $block[] = $lines[$i];
                    ++$i;
                }
                if (count($block) >= 2) {
                    $sep = preg_replace('/\s+/', '', $block[1]);
                    if (preg_match('/^\|?[-:|]+\|?$/', $sep)) {
                        $parseRow = static function (string $row): array {
                            $t = trim($row);
                            if (str_starts_with($t, '|')) {
                                $t = substr($t, 1);
                            }
                            if (str_ends_with($t, '|')) {
                                $t = substr($t, 0, -1);
                            }

                            return array_map(static fn (string $c): string => trim($c), explode('|', $t));
                        };
                        $header = $parseRow($block[0]);
                        if ($header !== []) {
                            $html = '<div class="my-2 overflow-x-auto"><table class="min-w-full text-sm border border-white/10 rounded-lg overflow-hidden"><thead><tr>';
                            foreach ($header as $h) {
                                $html .= '<th class="border border-white/10 bg-white/5 px-2 py-1 text-left font-semibold">' . $h . '</th>';
                            }
                            $html .= '</tr></thead><tbody>';
                            for ($b = 2, $bc = count($block); $b < $bc; ++$b) {
                                $cells = $parseRow($block[$b]);
                                $html .= '<tr>';
                                $max = max(count($cells), count($header));
                                for ($c = 0; $c < $max; ++$c) {
                                    $html .= '<td class="border border-white/10 px-2 py-1">' . ($cells[$c] ?? '') . '</td>';
                                }
                                $html .= '</tr>';
                            }
                            $html .= '</tbody></table></div>';
                            $result[] = $html;

                            continue;
                        }
                    }
                }
                foreach ($block as $bl) {
                    $result[] = $bl;
                }
            } else {
                $result[] = $line;
                ++$i;
            }
        }

        return implode("\n", $result);
    }
}

if (!function_exists('markdown_to_html')) {
    /**
     * Convertit du Markdown simple en HTML (sécurisé).
     * Titres ## ###, séparateur ---, :::spoiler / :::info / :::warning, tableaux | |, citations, listes, code, liens.
     */
    function markdown_to_html(string $content): string
    {
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $codePlaceholders = [];
        $ci = 0;
        $content = preg_replace_callback('/```(\w*)\s*([\s\S]*?)```/', static function (array $m) use (&$codePlaceholders, &$ci): string {
            $key = '@@FCODE' . $ci . '@@';
            $codePlaceholders[$key] = '<pre class="my-2 p-3 bg-black/30 border border-white/10 rounded text-sm overflow-x-auto"><code>' . $m[2] . '</code></pre>';
            ++$ci;

            return $key;
        }, $content) ?? $content;

        $fencePlaceholders = [];
        $fi = 0;
        $content = preg_replace_callback(
            '/:::(spoiler|info|warning)\s*\n([\s\S]*?)\n:::/U',
            static function (array $m) use (&$fencePlaceholders, &$fi): string {
                $key = '@@FFENCE' . $fi . '@@';
                $inner = nl2br($m[2]);
                $fencePlaceholders[$key] = match ($m[1]) {
                    'spoiler' => '<details class="my-2 rounded-lg border border-white/10 bg-white/5 p-2"><summary class="cursor-pointer text-sm font-semibold">Afficher</summary><div class="mt-2 text-sm text-neutral-200">' . $inner . '</div></details>',
                    'info' => '<div class="my-2 rounded-lg border border-sky-500/30 bg-sky-500/10 p-3 text-sm text-sky-100">' . $inner . '</div>',
                    default => '<div class="my-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-100">' . $inner . '</div>',
                };
                ++$fi;

                return $key;
            },
            $content
        ) ?? $content;

        $content = markdown_process_tables($content);

        $out = [];
        $inBlockquote = false;
        $inUl = false;
        $inOl = false;
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^@@FCODE\d+@@$|^@@FFENCE\d+@@$/', $trimmed)) {
                if ($inBlockquote) {
                    $out[] = '</blockquote>';
                    $inBlockquote = false;
                }
                if ($inUl) {
                    $out[] = '</ul>';
                    $inUl = false;
                }
                if ($inOl) {
                    $out[] = '</ol>';
                    $inOl = false;
                }
                $out[] = $trimmed;
                continue;
            }
            if (preg_match('/^(\#{2,3})\s+(.+)$/', $trimmed, $hm)) {
                if ($inBlockquote) {
                    $out[] = '</blockquote>';
                    $inBlockquote = false;
                }
                if ($inUl) {
                    $out[] = '</ul>';
                    $inUl = false;
                }
                if ($inOl) {
                    $out[] = '</ol>';
                    $inOl = false;
                }
                $titleLine = $hm[2];
                $anchor = null;
                if (preg_match('/^(.+?)\s*\{#([a-zA-Z0-9_-]+)\}\s*$/', $titleLine, $am)) {
                    $titleLine = $am[1];
                    $anchor = $am[2];
                }
                $tag = $hm[1] === '##' ? 'h2' : 'h3';
                if ($anchor === null) {
                    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim(html_entity_decode($titleLine, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
                    $anchor = $slug !== '' ? trim($slug, '-') : 'section';
                }
                $safeId = htmlspecialchars($anchor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $out[] = '<' . $tag . ' id="' . $safeId . '" class="font-bold text-neutral-100 mt-3 mb-1">' . $titleLine . '</' . $tag . '>';
                continue;
            }
            if (preg_match('/^(?:\*{3}|-{3}|_{3})\s*$/', $trimmed)) {
                if ($inBlockquote) {
                    $out[] = '</blockquote>';
                    $inBlockquote = false;
                }
                if ($inUl) {
                    $out[] = '</ul>';
                    $inUl = false;
                }
                if ($inOl) {
                    $out[] = '</ol>';
                    $inOl = false;
                }
                $out[] = '<hr class="my-3 border-white/10">';
                continue;
            }
            if (preg_match('/^&gt;\s?(.*)$/', $trimmed, $qm)) {
                if ($inUl) {
                    $out[] = '</ul>';
                    $inUl = false;
                }
                if ($inOl) {
                    $out[] = '</ol>';
                    $inOl = false;
                }
                if (!$inBlockquote) {
                    $out[] = '<blockquote class="border-l-2 border-orange-500/40 pl-4 my-1.5 text-neutral-400">';
                    $inBlockquote = true;
                }
                $out[] = $qm[1] . '<br>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $um)) {
                if ($inBlockquote) {
                    $out[] = '</blockquote>';
                    $inBlockquote = false;
                }
                if ($inOl) {
                    $out[] = '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $out[] = '<ul class="list-disc list-inside space-y-0.5 my-2 text-neutral-300 pl-2">';
                    $inUl = true;
                }
                $out[] = '<li>' . $um[1] . '</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $om)) {
                if ($inBlockquote) {
                    $out[] = '</blockquote>';
                    $inBlockquote = false;
                }
                if ($inUl) {
                    $out[] = '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $out[] = '<ol class="list-decimal list-inside space-y-0.5 my-2 text-neutral-300 pl-2">';
                    $inOl = true;
                }
                $out[] = '<li>' . $om[1] . '</li>';
                continue;
            }
            if ($inBlockquote) {
                $out[] = '</blockquote>';
                $inBlockquote = false;
            }
            if ($inUl) {
                $out[] = '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $out[] = '</ol>';
                $inOl = false;
            }
            $out[] = $line . "\n";
        }
        if ($inBlockquote) {
            $out[] = '</blockquote>';
        }
        if ($inUl) {
            $out[] = '</ul>';
        }
        if ($inOl) {
            $out[] = '</ol>';
        }
        $content = implode('', $out);

        foreach ($codePlaceholders as $key => $html) {
            $content = str_replace($key, $html, $content);
        }
        foreach ($fencePlaceholders as $key => $html) {
            $content = str_replace($key, $html, $content);
        }

        $content = preg_replace('/`([^`\n]+)`/', '<code class="px-1 py-0.5 bg-white/10 rounded text-xs">$1</code>', $content);
        $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $content);
        $content = preg_replace('/_([^_]+)_/', '<em>$1</em>', $content);
        $content = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $content);
        $content = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $m): string {
            $label = $m[1];
            $rawUrl = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#^https?://#i', $rawUrl)) {
                $svc = new \App\Services\Navigation\ExternalLeaveService();
                $sanitized = $svc->sanitizeHttpUrl($rawUrl);
                if ($sanitized === null) {
                    return $label;
                }
                $extraHosts = [];
                $href = markdown_resolve_href_for_http_url($sanitized, $extraHosts);
                $safeHref = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $class = 'text-orange-400 hover:text-orange-300 underline';

                return '<a href="' . $safeHref . '" rel="noopener noreferrer" class="' . $class . '">' . $label . '</a>';
            }
            if (preg_match('#^/[^\s]+\s*$#', $rawUrl)) {
                $safeHref = htmlspecialchars($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return '<a href="' . $safeHref . '" class="text-emerald-400 hover:text-emerald-300 underline">' . $label . '</a>';
            }

            return $label;
        }, $content);
        $content = markdown_linkify_plain_http_urls($content);

        return nl2br($content);
    }
}
