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

    /**
     * Page complète : ajoute avant </head> le traducteur des textes posés par le JavaScript (assets/js/i18n_dom.js)
     * et son dictionnaire (assets/js/i18n_en_dict.js, généré par site_i18n.py js-bundle).
     */
    public static function withDomScripts(string $html): string
    {
        $at = stripos($html, '</head>');
        if ($at === false || str_contains($html, 'i18n_dom.js')) {
            return $html;
        }
        $tags = '';
        foreach (['i18n_en_dict.js', 'i18n_dom.js'] as $file) {
            $path = base_path('public/assets/js/' . $file);
            if (!is_file($path)) {
                return $html;
            }
            $src = (function_exists('url') ? url('assets/js/' . $file) : '/assets/js/' . $file) . '?v=' . (int) filemtime($path);
            $tags .= '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" defer></script>';
        }

        return substr($html, 0, $at) . $tags . substr($html, $at);
    }

    /** Champs d'une réponse JSON montrés tels quels par les pages (erreurs, confirmations, libellés). */
    private const JSON_KEYS = ['error', 'message', 'msg', 'warning', 'notice', 'hint', 'label', 'title', 'detail', 'reason', 'status_label'];

    /**
     * Réponse JSON des API appelées par les pages : seuls les champs de JSON_KEYS (à toute profondeur) sont cherchés
     * dans le dictionnaire, comme un texte de page ; les autres valeurs (données, identifiants) ne bougent pas.
     */
    public static function translateJson(string $json): string
    {
        if ($json === '' || !self::load()) {
            return $json;
        }
        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $json;
        }
        if (!is_array($data) && !is_object($data)) {
            return $json;
        }
        $changed = false;
        $data = self::jsonWalk($data, null, $changed);
        if (!$changed) {
            return $json;
        }
        $out = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        return $out === false ? $json : $out;
    }

    private static function jsonWalk(mixed $node, ?string $key, bool &$changed): mixed
    {
        if (is_array($node) || is_object($node)) {
            foreach ($node as $k => $v) {
                $name = is_string($k) ? $k : $key;
                $nv = self::jsonWalk($v, $name, $changed);
                if ($nv !== $v) {
                    if (is_object($node)) {
                        $node->{$k} = $nv;
                    } else {
                        $node[$k] = $nv;
                    }
                }
            }

            return $node;
        }
        if (!is_string($node) || $key === null || !in_array(strtolower($key), self::JSON_KEYS, true)
            || !preg_match('/\p{L}/u', $node)) {
            return $node;
        }
        $en = self::lookup((string) preg_replace('/\s+/u', ' ', trim($node)), 0);
        if ($en === null) {
            return $node;
        }
        $changed = true;

        return $en;
    }

    private static function attributes(string $tag, string $name): string
    {
        $tag = (string) preg_replace_callback(self::ATTRS, static function (array $m): string {
            $quote = $m[2][0];
            $raw = $quote === '"' ? ($m[3] ?? '') : ($m[4] ?? '');
            $tr = self::text($raw, true);

            return $m[1] . '=' . $quote . $tr . $quote;
        }, $tag);
        // Description et titres de partage (moteurs de recherche, Discord, réseaux) : même dictionnaire que la page.
        if ($name === 'meta' && preg_match('~\b(?:name|property)\s*=\s*["\'](?:description|og:title|og:description|og:image:alt|twitter:title|twitter:description)["\']~i', $tag)) {
            $tag = (string) preg_replace_callback('~\bcontent\s*=\s*"([^"]*)"~i', static fn (array $m): string => 'content="' . self::text($m[1], true) . '"', $tag);
        }
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
        try {
            $en = self::lookup($key, 0);
        } catch (\Throwable) {
            // Un texte qui fait échouer la recherche reste en français, sans faire perdre la traduction de la page.
            $en = null;
        }
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
                $tr = self::lookup($rest, $depth + 1);
                // « Libellé : phrase française » : pas de libellé anglais devant une phrase restée en français.
                if ($tr !== null || (!self::looksLikeSentence($rest) && substr_count($rest, ' ') < 3)) {
                    $r = $label . ': ' . ($tr ?? $rest);
                }
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
        // Préfixe décoratif (drapeau, puce, icône) : le texte qui suit est cherché seul.
        if ($r === null && $depth === 0 && preg_match('/^([^\p{L}\p{N}«"“(\[]+)(\p{L}.*)$/us', $s, $pm) === 1) {
            $inner = self::lookup($pm[2], 1);
            $r = $inner === null ? null : $pm[1] . $inner;
        }
        // Texte en capitales (« PRÉSENCE ») : cherché sous sa forme ordinaire, rendu en capitales.
        if ($r === null && $depth <= 1 && mb_strtoupper($s, 'UTF-8') === $s && preg_match('/\p{Lu}{3}/u', $s) === 1) {
            $lower = mb_strtolower($s, 'UTF-8');
            $inner = self::lookup(mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, null, 'UTF-8'), 2)
                ?? self::lookup($lower, 2);
            $r = $inner === null ? null : mb_strtoupper($inner, 'UTF-8');
        }
        if ($depth === 0) {
            if (count(self::$memo) > 20000) {
                self::$memo = [];
            }
            self::$memo[$s] = $r ?? '';
        }

        return $r;
    }

    /**
     * Valeur non traduite qui ressemble à un bout de phrase (long, ou commençant par un article ou une
     * préposition) : le modèle qui l'a capturée coupe une phrase et donnerait un texte mi-français mi-anglais.
     * $frenchWords : un mot français suffit (valeur en tête de texte, suivie d'une fin de phrase du modèle).
     */
    private static function looksLikeSentence(string $cap, bool $frenchWords = true): bool
    {
        // Mot français (accent, article, préposition) dans une valeur sans chiffre (les dates gardent leur mois).
        if ($frenchWords && preg_match('/\d/', $cap) !== 1
            && preg_match('/[àâçéèêëîïôûùœ]|\b(?:le|la|les|des|du|de|et|aux|au|pour|une|un|sur|avec|dans|par)\b/iu', $cap) === 1) {
            return true;
        }

        return substr_count($cap, ' ') >= 7
            || preg_match("/^(?:le|la|les|l’|l'|de|du|des|d’|d'|à|au|aux|un|une|en|et|pour|par|sur|avec)(?:\\s|(?<=[’'])\\p{L})/iu", $cap) === 1;
    }

    private static function template(string $s, int $depth): ?string
    {
        $n = strlen($s);
        $candidates = array_merge(self::$templates[substr($s, 0, 3)] ?? [], self::$templates[''] ?? []);
        foreach ($candidates as [$parts, $ids, $en, $weak, $glued]) {
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
                    if ($at < $pos || ($at === $pos && !($glued[$i - 1] ?? false)) || ($pi !== '' && substr($s, $at) !== $pi)) {
                        $ok = false;
                        break;
                    }
                } else {
                    $from = ($glued[$i - 1] ?? false) ? $pos : $pos + 1;
                    $f = $from < $n ? strpos($s, $pi, $from) : false;
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
            foreach ($caps as $k => $cap) {
                if (($glued[$k] ?? false) && preg_match('/^\p{L}{0,3}$/u', $cap) !== 1) {
                    continue 2;
                }
            }
            if ($weak) {
                foreach ($caps as $cap) {
                    if (substr_count(trim($cap), ' ') >= 3) {
                        continue 2;
                    }
                }
            }
            $repl = [];
            foreach ($ids as $k => $id) {
                $cap = $caps[$k];
                $tr = self::lookup(trim($cap), $depth + 1);
                // Dans une valeur déjà capturée, un modèle ne laisse pas de mots français autour de sa traduction.
                if ($tr === null && (self::looksLikeSentence(trim($cap), $k === 0 && $parts[0] === '') || (($depth > 0 || $weak) && preg_match('/\p{L}{2}/u', $cap) === 1 && preg_match('/\d/', $cap) !== 1))) {
                    // Morceau de phrase française, pas une valeur : mieux vaut tout laisser en français.
                    continue 2;
                }
                $repl['%' . $id] = $tr ?? $cap;
            }
            // Accord français (« affiché%2 ») : un participe anglais ne prend pas de « s ».
            foreach ($ids as $k => $id) {
                if ($glued[$k] ?? false) {
                    $en = preg_replace('/(?<=[a-z]ed|[a-z]wn)%' . $id . '(?!\d)/', '', $en) ?? $en;
                }
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
            // Modèle « faible » (« le %1 », « %1 sur %2 ») : presque aucun texte fixe, il ne doit s'appliquer qu'à
            // des valeurs courtes (date, nom, nombre), jamais au milieu d'une phrase.
            $weak = (int) preg_match_all('/\p{L}/u', implode('', $texts)) < 5;
            // Valeur collée à un mot (« %1 membre%2 » : marque du pluriel) : elle ne capture que quelques lettres.
            $glued = [];
            foreach ($ids as $k => $_id) {
                $glued[$k] = preg_match('/\p{L}$/u', $texts[$k]) === 1 || preg_match('/^\p{L}/u', $texts[$k + 1]) === 1;
            }
            self::$templates[$bucket][] = [$texts, $ids, $en, $weak, $glued];
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
