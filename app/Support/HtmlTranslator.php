<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Traduction anglaise des pages à l'affichage.
 *
 * Les vues restent écrites en français. Quand la langue du visiteur est l'anglais, Response::view passe la page
 * rendue ici : chaque texte entre balises et les attributs title, placeholder, aria-label, alt, data-confirm et value
 * (boutons) sont cherchés dans lang/en/site_auto.php (tenu par tools/i18n/site_i18n.py). Ordre : texte exact,
 * « Libellé : valeur », modèle %1/%2 (les valeurs capturées sont traduites à leur tour), morceaux séparés par « · ».
 * Un texte inconnu reste en français. Jamais traduits : <script>, <style>, <textarea>, <pre>, <code> et tout élément
 * marqué translate="no" (contenu saisi par les membres).
 */
final class HtmlTranslator
{
    /** @var array<string, string>|null */
    private static ?array $exact = null;

    /** @var array<string, list<array{0: list<string>, 1: list<int>, 2: string}>> */
    private static array $templates = [];

    /** @var array<string, string> */
    private static array $memo = [];

    private const RAW_TAGS = ['script', 'style', 'textarea', 'pre', 'code'];

    private const ATTRS = '/\b(title|placeholder|aria-label|alt|data-confirm)\s*=\s*("([^"]*)"|\'([^\']*)\')/i';

    public static function translate(string $html): string
    {
        if ($html === '' || !self::load()) {
            return $html;
        }

        $out = '';
        $len = strlen($html);
        $pos = 0;
        $skipTag = null;
        $skipDepth = 0;
        while ($pos < $len) {
            $lt = strpos($html, '<', $pos);
            if ($lt === false) {
                $out .= $skipTag === null ? self::text(substr($html, $pos)) : substr($html, $pos);
                break;
            }
            if ($lt > $pos) {
                $chunk = substr($html, $pos, $lt - $pos);
                $out .= $skipTag === null ? self::text($chunk) : $chunk;
            }
            // Commentaire HTML : recopié tel quel.
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                $end = strpos($html, '-->', $lt + 4);
                $end = $end === false ? $len : $end + 3;
                $out .= substr($html, $lt, $end - $lt);
                $pos = $end;
                continue;
            }
            $gt = strpos($html, '>', $lt);
            if ($gt === false) {
                $out .= substr($html, $lt);
                break;
            }
            $tag = substr($html, $lt, $gt - $lt + 1);
            $pos = $gt + 1;
            if (!preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9-]*)~', $tag, $m)) {
                $out .= $tag;
                continue;
            }
            $closing = $m[1] === '/';
            $name = strtolower($m[2]);

            if ($skipTag !== null) {
                if ($name === $skipTag) {
                    if ($closing) {
                        $skipDepth--;
                        if ($skipDepth <= 0) {
                            $skipTag = null;
                        }
                    } elseif (!str_ends_with($tag, '/>')) {
                        $skipDepth++;
                    }
                }
                $out .= $tag;
                continue;
            }

            if (!$closing) {
                $voidish = str_ends_with($tag, '/>') || in_array($name, ['input', 'img', 'br', 'hr', 'meta', 'link', 'source', 'area', 'col', 'wbr'], true);
                if (in_array($name, self::RAW_TAGS, true) || preg_match('~\btranslate\s*=\s*["\']?no\b~i', $tag)) {
                    if (!$voidish) {
                        $skipTag = $name;
                        $skipDepth = 1;
                    }
                    $out .= $tag;
                    continue;
                }
                $tag = self::attributes($tag, $name);
            }
            $out .= $tag;
        }

        return $out;
    }

    /**
     * Texte brut (version texte d'un e-mail, objet) : chaque ligne est cherchée comme un texte de page ; une ligne
     * inconnue reste en français.
     */
    public static function translateText(string $text): string
    {
        if ($text === '' || !self::load()) {
            return $text;
        }
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (trim($line) === '' || !preg_match('/\p{L}/u', $line)) {
                continue;
            }
            $lead = strlen($line) - strlen(ltrim($line));
            $trail = strlen($line) - strlen(rtrim($line));
            $core = substr($line, $lead, strlen($line) - $lead - $trail);
            $en = self::lookup((string) preg_replace('/\s+/u', ' ', $core), 0);
            if ($en !== null) {
                $lines[$i] = substr($line, 0, $lead) . $en . substr($line, strlen($line) - $trail);
            }
        }

        return implode("\n", $lines);
    }

    private static function attributes(string $tag, string $name): string
    {
        $tag = (string) preg_replace_callback(self::ATTRS, static function (array $m): string {
            $quote = $m[2][0];
            $raw = $quote === '"' ? ($m[3] ?? '') : ($m[4] ?? '');
            $tr = self::text($raw, true);

            return $m[1] . '=' . $quote . $tr . $quote;
        }, $tag);
        // value des boutons (pas des champs saisis).
        if ($name === 'input' && preg_match('~\btype\s*=\s*["\']?(submit|button|reset)\b~i', $tag)) {
            $tag = (string) preg_replace_callback('~\bvalue\s*=\s*"([^"]*)"~i', static fn (array $m): string => 'value="' . self::text($m[1], true) . '"', $tag);
        }

        return $tag;
    }

    /** Texte HTML (entités comprises) : espaces autour gardés, cœur traduit puis ré-échappé. */
    private static function text(string $chunk, bool $attr = false): string
    {
        if (trim($chunk) === '' || !preg_match('/\p{L}/u', $chunk)) {
            return $chunk;
        }
        $lead = strlen($chunk) - strlen(ltrim($chunk));
        $trail = strlen($chunk) - strlen(rtrim($chunk));
        $core = substr($chunk, $lead, strlen($chunk) - $lead - $trail);
        $plain = html_entity_decode($core, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $key = (string) preg_replace('/\s+/u', ' ', $plain);
        $en = self::lookup($key, 0);
        if ($en === null) {
            return $chunk;
        }
        $escaped = htmlspecialchars($en, $attr ? ENT_QUOTES : ENT_NOQUOTES, 'UTF-8', false);

        return substr($chunk, 0, $lead) . $escaped . substr($chunk, strlen($chunk) - $trail);
    }

    private static function lookup(string $s, int $depth): ?string
    {
        if ($depth > 2 || $s === '') {
            return null;
        }
        if ($depth === 0 && array_key_exists($s, self::$memo)) {
            $hit = self::$memo[$s];

            return $hit === '' ? null : $hit;
        }
        $exact = self::$exact ?? [];
        $r = $exact[$s] ?? null;
        if ($r === null && ($k = strpos($s, ' : ')) !== false && $k > 0) {
            $label = $exact[substr($s, 0, $k)] ?? null;
            if ($label !== null) {
                $rest = substr($s, $k + 3);
                $r = $label . ': ' . (self::lookup($rest, $depth + 1) ?? $rest);
            }
        }
        if ($r === null) {
            $r = self::template($s, $depth);
        }
        if ($r === null && str_contains($s, ' · ')) {
            $parts = explode(' · ', $s);
            $changed = false;
            foreach ($parts as $i => $p) {
                $t = self::lookup(trim($p), $depth + 1);
                if ($t !== null) {
                    $parts[$i] = $t;
                    $changed = true;
                }
            }
            $r = $changed ? implode(' · ', $parts) : null;
        }
        if ($depth === 0) {
            if (count(self::$memo) > 20000) {
                self::$memo = [];
            }
            self::$memo[$s] = $r ?? '';
        }

        return $r;
    }

    private static function template(string $s, int $depth): ?string
    {
        $n = strlen($s);
        $candidates = array_merge(self::$templates[substr($s, 0, 3)] ?? [], self::$templates[''] ?? []);
        foreach ($candidates as [$parts, $ids, $en]) {
            $p0 = $parts[0];
            if ($p0 !== '' && !str_starts_with($s, $p0)) {
                continue;
            }
            $pos = strlen($p0);
            $caps = [];
            $last = count($parts) - 1;
            $ok = true;
            for ($i = 1; $i <= $last; $i++) {
                $pi = $parts[$i];
                if ($i === $last) {
                    $at = $n - strlen($pi);
                    if ($at <= $pos || ($pi !== '' && substr($s, $at) !== $pi)) {
                        $ok = false;
                        break;
                    }
                } else {
                    $f = strpos($s, $pi, $pos + 1);
                    if ($f === false) {
                        $ok = false;
                        break;
                    }
                    $at = $f;
                }
                $caps[] = substr($s, $pos, $at - $pos);
                $pos = $at + strlen($pi);
            }
            if (!$ok) {
                continue;
            }
            $repl = [];
            foreach ($ids as $k => $id) {
                $cap = $caps[$k];
                $repl['%' . $id] = self::lookup(trim($cap), $depth + 1) ?? $cap;
            }
            // Remplacement simultané (%1 ne doit pas toucher %10).
            krsort($repl, SORT_NATURAL);

            return strtr($en, $repl);
        }

        return null;
    }

    private static function load(): bool
    {
        if (self::$exact !== null) {
            return self::$exact !== [] || self::$templates !== [];
        }
        $path = base_path('lang/en/site_auto.php');
        /** @var mixed $data */
        $data = is_file($path) ? require $path : [];

        return self::useDictionary(is_array($data) ? $data : []);
    }

    /**
     * Dictionnaire à utiliser (chargé depuis lang/en/site_auto.php ; injecté par les tests).
     *
     * @param array<mixed, mixed> $data
     */
    public static function useDictionary(array $data): bool
    {
        self::$exact = [];
        self::$templates = [];
        self::$memo = [];
        foreach ($data as $fr => $en) {
            if (!is_string($fr) || !is_string($en)) {
                continue;
            }
            if (!preg_match('/%\d/', $fr)) {
                self::$exact[$fr] = $en;
                continue;
            }
            $parts = preg_split('/%(\d)/', $fr, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            $texts = [];
            $ids = [];
            foreach ($parts as $i => $p) {
                if ($i % 2 === 0) {
                    $texts[] = $p;
                } else {
                    $ids[] = (int) $p;
                }
            }
            // Deux valeurs collées (« %1%2 ») : impossible à séparer.
            if (in_array('', array_slice($texts, 1, -1), true)) {
                continue;
            }
            $bucket = strlen($texts[0]) >= 3 ? substr($texts[0], 0, 3) : '';
            self::$templates[$bucket][] = [$texts, $ids, $en];
        }
        foreach (self::$templates as &$list) {
            usort($list, static fn (array $a, array $b): int => strlen(implode('', $b[0])) <=> strlen(implode('', $a[0])));
        }
        unset($list);

        return self::$exact !== [] || self::$templates !== [];
    }

    /** Pour les tests. */
    public static function reset(): void
    {
        self::$exact = null;
        self::$templates = [];
        self::$memo = [];
    }
}
