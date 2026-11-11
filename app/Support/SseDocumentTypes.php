<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Types de papiers d'identité : miroir du registre du mod SSE.
 *
 * Source unique : mod/@COMSPEC_SSE/addons/identity/CfgComspecDocuments.hpp (papiers intégrés), les extensions de
 * mod/@COMSPEC_SSE/extensions/<nom>/config.cpp, et mod/@COMSPEC_SSE/tools/countries/countries.json (papiers délivrés par
 * pays, pays complets d'abord). Le site lit config/sse_document_types.php, écrit par
 * `php tools/sse/sync_document_types.php` ; le test SseDocumentTypesMirrorTest échoue dès que les deux s'écartent.
 *
 * Papiers intégrés = SseIdentityCatalog::DOC_TYPES (types acceptés par le registre du site). Les types d'une extension
 * sont listés ici (clé « extension ») mais ne sont pas proposés : le site ne sait pas si l'extension est activée sur le
 * serveur de jeu.
 */
final class SseDocumentTypes
{
    public const CATEGORIES = [
        'identity' => 'Identité',
        'travel' => 'Voyage',
        'permit' => 'Permis et véhicule',
        'professional' => 'Professionnel',
        'pass' => 'Laissez-passer',
        'other' => 'Autres',
    ];

    private const PROPS = ['displayName', 'displayNameEn', 'category', 'sides', 'mrz', 'mrzSide', 'chip', 'extension'];

    /** @var array{types: array<string, array<string, mixed>>, countries: array<string, array{tier: string, types: list<string>}>}|null */
    private static ?array $data = null;

    /** @return array{types: array<string, array<string, mixed>>, countries: array<string, array{tier: string, types: list<string>}>} */
    public static function data(): array
    {
        if (self::$data === null) {
            $file = dirname(__DIR__, 2) . '/config/sse_document_types.php';
            $loaded = is_file($file) ? require $file : [];
            self::$data = ['types' => (array) ($loaded['types'] ?? []), 'countries' => (array) ($loaded['countries'] ?? [])];
        }

        return self::$data;
    }

    /**
     * Tous les types connus du mod (intégrés puis extensions), dans l'ordre des catégories.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::data()['types'];
    }

    /**
     * Papiers intégrés au SSE (hors extensions).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function builtIn(): array
    {
        return array_filter(self::all(), static fn (array $t): bool => ($t['extension'] ?? '') === '');
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Le type porte-t-il une bande de lecture automatique ? (inconnu : oui, comme une carte) */
    public static function hasMrz(string $key): bool
    {
        $t = self::get($key);

        return $t === null || ($t['mrz'] ?? '') !== '';
    }

    /** Nombre de faces (1 : recto seul, comme le passeport ; inconnu : 2, comme une carte). */
    public static function sides(string $key): int
    {
        return (int) (self::get($key)['sides'] ?? 2) >= 2 ? 2 : 1;
    }

    /**
     * Papiers que le mod délivre pour ce pays (countries.json), limités aux types intégrés ; [] si le pays n'est pas au
     * registre du mod (pays créé par la communauté : la fiche du pays décide).
     *
     * @return list<string>
     */
    public static function servedBy(string $countryCode): array
    {
        $c = self::data()['countries'][strtoupper($countryCode)] ?? null;
        if ($c === null) {
            return [];
        }
        $builtIn = self::builtIn();

        return array_values(array_filter($c['types'], static fn (string $t): bool => isset($builtIn[$t]) && self::servesCountry($builtIn[$t], $countryCode)));
    }

    /** @param array<string, mixed> $type */
    private static function servesCountry(array $type, string $countryCode): bool
    {
        $only = (array) ($type['countries'] ?? []);

        return $only === [] || in_array(strtoupper($countryCode), $only, true);
    }

    /** Rang d'un pays dans le registre du mod (pays complets d'abord : France, Allemagne, États-Unis…) ; inconnu : en fin. */
    public static function countryRank(string $countryCode): int
    {
        $rank = array_search(strtoupper($countryCode), array_keys(self::data()['countries']), true);

        return $rank === false ? PHP_INT_MAX : (int) $rank;
    }

    /**
     * Pays triés comme dans le mod : pays complets, puis les autres dans l'ordre du registre, puis les pays hors registre.
     *
     * @param list<array<string, mixed>> $countries lignes portant « code »
     * @return list<array<string, mixed>>
     */
    public static function sortCountries(array $countries): array
    {
        $tiers = self::data()['countries'];
        usort($countries, static function (array $a, array $b) use ($tiers): int {
            $ka = [($tiers[strtoupper((string) $a['code'])]['tier'] ?? '') === 'full' ? 0 : 1, self::countryRank((string) $a['code']), (string) ($a['name'] ?? '')];
            $kb = [($tiers[strtoupper((string) $b['code'])]['tier'] ?? '') === 'full' ? 0 : 1, self::countryRank((string) $b['code']), (string) ($b['name'] ?? '')];

            return $ka <=> $kb;
        });

        return $countries;
    }

    /**
     * Types rangés par catégorie pour une liste de choix : catégorie => [clé => libellé]. Clés inconnues ignorées.
     *
     * @param list<string>|null $keys null : tous les papiers intégrés
     * @return array<string, array{label: string, types: array<string, string>}>
     */
    public static function grouped(?array $keys = null): array
    {
        $out = [];
        foreach (self::builtIn() as $key => $t) {
            if ($keys !== null && !in_array($key, $keys, true)) {
                continue;
            }
            $cat = isset(self::CATEGORIES[$t['category']]) ? (string) $t['category'] : 'other';
            $out[$cat] ??= ['label' => self::CATEGORIES[$cat], 'types' => []];
            $out[$cat]['types'][$key] = (string) $t['name'];
        }

        return array_replace(array_intersect_key(array_fill_keys(array_keys(self::CATEGORIES), null), $out), $out);
    }

    // ------------------------------------------------------------------ Lecture du mod (outil de synchronisation, test)

    /**
     * Lit le registre du mod et rend le contenu attendu de config/sse_document_types.php.
     *
     * @return array{types: array<string, array<string, mixed>>, countries: array<string, array{tier: string, types: list<string>}>}
     */
    public static function fromMod(?string $root = null): array
    {
        $mod = ($root ?? dirname(__DIR__, 2)) . '/mod/@COMSPEC_SSE';
        $types = self::parseConfig((string) file_get_contents($mod . '/addons/identity/CfgComspecDocuments.hpp'));
        foreach (glob($mod . '/extensions/*/config.cpp') ?: [] as $file) {
            foreach (self::parseConfig((string) file_get_contents($file)) as $key => $t) {
                $types[$key] ??= $t;
            }
        }
        // Ordre du sélecteur du mod : par catégorie, puis ordre de déclaration.
        $order = array_flip(array_keys(self::CATEGORIES));
        $pos = array_flip(array_keys($types));
        uksort($types, static fn (string $a, string $b): int => [$order[$types[$a]['category']], $pos[$a]] <=> [$order[$types[$b]['category']], $pos[$b]]);

        $countries = [];
        $registry = json_decode((string) file_get_contents($mod . '/tools/countries/countries.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($registry as $code => $c) {
            $countries[strtoupper((string) $code)] = [
                'tier' => (string) ($c['tier'] ?? ''),
                'types' => array_values(array_map('strval', (array) ($c['docTypes'] ?? []))),
            ];
        }

        return ['types' => $types, 'countries' => $countries];
    }

    /**
     * Classes de premier niveau de « class CfgComspecDocuments { … } », héritage compris (class a: b).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function parseConfig(string $source): array
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
        $source = (string) preg_replace('#^\s*//.*$#m', '', $source);
        if (preg_match('/class\s+CfgComspecDocuments\s*\{/', $source, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }
        $block = self::body($source, $m[0][1] + strlen($m[0][0]));
        $raw = [];
        $offset = 0;
        while (preg_match('/class\s+(\w+)\s*(?::\s*(\w+)\s*)?\{/', $block, $c, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $c[0][1] + strlen($c[0][0]);
            $body = self::body($block, $start);
            $raw[strtolower($c[1][0])] = ['parent' => strtolower($c[2][0] ?? ''), 'body' => $body];
            $offset = $start + strlen($body) + 1;
        }
        $out = [];
        foreach (array_keys($raw) as $key) {
            $out[$key] = self::resolve($key, $raw);
        }

        return $out;
    }

    /**
     * @param array<string, array{parent: string, body: string}> $raw
     * @return array<string, mixed>
     */
    private static function resolve(string $key, array $raw): array
    {
        $chain = [];
        for ($k = $key; $k !== '' && isset($raw[$k]) && !in_array($k, $chain, true); $k = $raw[$k]['parent']) {
            array_unshift($chain, $k);
        }
        $props = [];
        $countries = [];
        $photo = false;
        foreach ($chain as $k) {
            $body = $raw[$k]['body'];
            // Propriétés du premier niveau seulement (les sous-classes recto / verso sont retirées).
            $flat = self::stripClasses($body);
            foreach (self::PROPS as $p) {
                if (preg_match('/\b' . $p . '\s*=\s*("([^"]*)"|[\d.]+)\s*;/', $flat, $v) === 1) {
                    $props[$p] = isset($v[2]) ? $v[2] : $v[1];
                }
            }
            if (preg_match('/\bcountries\s*\[\s*\]\s*=\s*\{([^}]*)\}/', $flat, $v) === 1) {
                $countries = array_values(array_filter(array_map(static fn (string $s): string => strtoupper(trim($s, " \t\n\r\"")), explode(',', $v[1])), static fn (string $s): bool => $s !== ''));
            }
            // Photo : classe recto redéfinie sans hériter du parent → sa présence décide ; « class recto: recto » hérite.
            if (preg_match('/class\s+recto\s*(:\s*recto\s*)?\{/', $body, $r, PREG_OFFSET_CAPTURE) === 1) {
                $recto = self::body($body, $r[0][1] + strlen($r[0][0]));
                $has = preg_match('/class\s+photo\s*\{/', $recto) === 1;
                $photo = isset($r[1]) && $r[1][0] !== '' ? ($photo || $has) : $has;
            }
        }
        $category = strtolower((string) ($props['category'] ?? 'other'));
        $apos = static fn (string $s): string => str_replace("'", '’', $s);
        $name = $apos((string) ($props['displayName'] ?? $key));
        $hasVerso = false;
        foreach ($chain as $k) {
            $hasVerso = $hasVerso || preg_match('/class\s+verso\s*(:\s*verso\s*)?\{/', $raw[$k]['body']) === 1;
        }

        return [
            'name' => $name,
            'name_en' => (string) ($props['displayNameEn'] ?? '') !== '' ? (string) $props['displayNameEn'] : $name,
            'category' => isset(self::CATEGORIES[$category]) ? $category : 'other',
            'sides' => (int) ($props['sides'] ?? 2) >= 2 && $hasVerso ? 2 : 1,
            'countries' => $countries,
            'mrz' => strtolower((string) ($props['mrz'] ?? '')),
            'mrz_side' => strtolower((string) ($props['mrzSide'] ?? 'verso')),
            'chip' => (int) ($props['chip'] ?? 0) === 1,
            'photo' => $photo,
            'extension' => (string) ($props['extension'] ?? ''),
        ];
    }

    /** Texte entre l'accolade ouverte juste avant $from et son accolade fermante. */
    private static function body(string $s, int $from): string
    {
        $depth = 1;
        $n = strlen($s);
        for ($i = $from; $i < $n; $i++) {
            if ($s[$i] === '{') {
                $depth++;
            } elseif ($s[$i] === '}' && --$depth === 0) {
                return substr($s, $from, $i - $from);
            }
        }

        return substr($s, $from);
    }

    /** Retire les sous-classes « class x { … } » d'un corps de classe. */
    private static function stripClasses(string $body): string
    {
        $out = '';
        $offset = 0;
        while (preg_match('/class\s+\w+\s*(?::\s*\w+\s*)?\{/', $body, $c, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $out .= substr($body, $offset, $c[0][1] - $offset);
            $start = $c[0][1] + strlen($c[0][0]);
            $offset = $start + strlen(self::body($body, $start)) + 1;
        }

        return $out . substr($body, $offset);
    }

    /** Texte PHP du fichier généré. */
    public static function export(array $data): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n/**\n * FICHIER GÉNÉRÉ : ne pas modifier à la main.\n *\n * Miroir du registre des papiers du mod SSE (CfgComspecDocuments.hpp, extensions, countries.json).\n"
            . " * Régénérer : php tools/sse/sync_document_types.php (voir App\\Support\\SseDocumentTypes).\n */\nreturn " . self::dump($data, 0) . ";\n";
    }

    private static function dump(mixed $v, int $depth): string
    {
        if (!is_array($v)) {
            return var_export($v, true);
        }
        if ($v === []) {
            return '[]';
        }
        $list = array_is_list($v);
        if ($list && array_filter($v, 'is_array') === []) {
            return '[' . implode(', ', array_map(static fn (mixed $x): string => var_export($x, true), $v)) . ']';
        }
        $pad = str_repeat('    ', $depth + 1);
        $out = "[\n";
        foreach ($v as $k => $x) {
            $out .= $pad . ($list ? '' : var_export($k, true) . ' => ') . self::dump($x, $depth + 1) . ",\n";
        }

        return $out . str_repeat('    ', $depth) . ']';
    }
}
