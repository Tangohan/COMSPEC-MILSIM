<?php

declare(strict_types=1);

namespace App\Services\I18n;

/**
 * Langues du site traduites par la communauté (/traduire-site) et corrections de l'anglais.
 *
 * Le site est écrit en français. Les textes à traduire sont ceux que l'anglais traduit déjà : le dictionnaire des
 * pages (lang/en/site_auto.php, clé = texte français) et les catalogues à clés (lang/fr/*.php, clé « groupe.clé »).
 * Chaque texte a une empreinte (hash) : sha1 du texte français pour les pages, sha1 de « @groupe.clé » pour les
 * catalogues.
 *
 * Les traductions validées vivent en base (site_translations) ; à chaque validation, la langue est recompilée dans
 * storage/i18n/community/{code}.php ({t: {français: texte}, k: {groupe.clé: texte}}) et la liste des langues publiées
 * dans storage/i18n/community/languages.json. Le rendu des pages ne lit que ces fichiers (jamais la base) :
 * HtmlTranslator pose le dictionnaire de la langue par-dessus l'anglais, Translator fait de même pour les clés. Un
 * texte non traduit s'affiche en anglais. « en » est la langue des corrections de l'anglais (toujours active).
 */
final class CommunityLanguages
{
    public const DIR = 'storage/i18n/community';
    /** Langue des corrections de l'anglais : pas de publication, appliquée dès qu'une correction est validée. */
    public const ENGLISH = 'en';

    /** @var array<string, array{t: array<string, string>, k: array<string, string>}> */
    private static array $dicts = [];

    /** @var array<string, string>|null */
    private static ?array $published = null;

    /** @var list<array{0: string, 1: string, 2: string, 3: string}>|null */
    private static ?array $catalog = null;

    /** Code de langue accepté pour une langue communautaire : « es », « pt-br ». Jamais « fr ». */
    public static function validCode(string $code): bool
    {
        return $code !== 'fr' && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $code) === 1;
    }

    public static function hash(string $source): string
    {
        return substr(sha1($source), 0, 16);
    }

    /**
     * Langues publiées (code => nom dans la langue), dans l'ordre des codes. Ni « fr » ni « en ».
     *
     * @return array<string, string>
     */
    public static function published(): array
    {
        if (self::$published !== null) {
            return self::$published;
        }
        $path = self::path('languages.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $out = [];
        if (is_array($data)) {
            foreach ($data as $code => $name) {
                if (is_string($code) && $code !== self::ENGLISH && self::validCode($code) && is_string($name) && $name !== '') {
                    $out[$code] = $name;
                }
            }
        }
        ksort($out);

        return self::$published = $out;
    }

    public static function isPublished(string $code): bool
    {
        return isset(self::published()[$code]);
    }

    /**
     * Dictionnaire compilé d'une langue (vide si rien n'est validé).
     *
     * @return array{t: array<string, string>, k: array<string, string>}
     */
    public static function dictionary(string $code): array
    {
        if (isset(self::$dicts[$code])) {
            return self::$dicts[$code];
        }
        $empty = ['t' => [], 'k' => []];
        if (!self::validCode($code)) {
            return self::$dicts[$code] = $empty;
        }
        $path = self::path($code . '.php');
        if (!is_file($path)) {
            return self::$dicts[$code] = $empty;
        }
        /** @var mixed $data */
        $data = require $path;

        return self::$dicts[$code] = [
            't' => is_array($data['t'] ?? null) ? $data['t'] : [],
            'k' => is_array($data['k'] ?? null) ? $data['k'] : [],
        ];
    }

    /** Date de la dernière compilation d'une langue (version du dictionnaire JavaScript), 0 sans fichier. */
    public static function version(string $code): int
    {
        $path = self::path($code . '.php');

        return self::validCode($code) && is_file($path) ? (int) filemtime($path) : 0;
    }

    /**
     * Écrit le dictionnaire compilé d'une langue à partir des traductions validées ({empreinte: texte}).
     *
     * @param array<string, string> $byHash
     * @return int nombre de textes écrits
     */
    public static function compile(string $code, array $byHash): int
    {
        if (!self::validCode($code)) {
            return 0;
        }
        $t = [];
        $k = [];
        foreach (self::catalog() as [$h, $src, , $kind]) {
            $text = $byHash[$h] ?? '';
            if ($text === '' || self::check($src, $text) !== []) {
                continue;
            }
            if ($kind === 'key') {
                $k[substr($src, 1)] = $text;
            } else {
                $t[$src] = $text;
            }
        }
        self::writeFile($code . '.php', "<?php\n\n// Généré par App\\Services\\I18n\\CommunityLanguages::compile : ne pas modifier à la main.\n\nreturn "
            . var_export(['t' => $t, 'k' => $k], true) . ";\n");
        unset(self::$dicts[$code]);

        return count($t) + count($k);
    }

    /**
     * @param array<string, string> $published code => nom
     */
    public static function writePublished(array $published): void
    {
        $clean = [];
        foreach ($published as $code => $name) {
            if (self::validCode((string) $code) && $code !== self::ENGLISH) {
                $clean[(string) $code] = (string) $name;
            }
        }
        ksort($clean);
        self::writeFile('languages.json', (string) json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        self::$published = $clean;
    }

    /**
     * Tous les textes du site à traduire : [empreinte, source, anglais, type] ; type « text » (texte français d'une
     * page, la source est le texte) ou « key » (catalogue à clés, la source est « @groupe.clé » et le français est
     * donné par frenchOf()).
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function catalog(?string $root = null): array
    {
        if (self::$catalog !== null && $root === null) {
            return self::$catalog;
        }
        $base = rtrim($root ?? base_path(), '/');
        $out = [];
        $seen = [];
        $auto = $base . '/lang/en/site_auto.php';
        /** @var mixed $data */
        $data = is_file($auto) ? require $auto : [];
        foreach (is_array($data) ? $data : [] as $fr => $en) {
            if (!is_string($fr) || !is_string($en) || trim($fr) === '' || !preg_match('/\p{L}/u', $fr)) {
                continue;
            }
            $h = self::hash($fr);
            if (!isset($seen[$h])) {
                $seen[$h] = true;
                $out[] = [$h, $fr, $en, 'text'];
            }
        }
        foreach (self::keyedSources($base) as $id => [$fr, $en]) {
            $src = '@' . $id;
            $h = self::hash($src);
            if (!isset($seen[$h])) {
                $seen[$h] = true;
                $out[] = [$h, $src, $en, 'key'];
            }
        }
        if ($root === null) {
            self::$catalog = $out;
        }

        return $out;
    }

    /**
     * Textes français des catalogues à clés : « groupe.clé » => [français, anglais].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function keyedSources(?string $root = null): array
    {
        static $memo = [];
        $base = rtrim($root ?? base_path(), '/');
        if (isset($memo[$base])) {
            return $memo[$base];
        }
        $out = [];
        foreach (glob($base . '/lang/fr/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            /** @var mixed $fr */
            $fr = require $file;
            $enFile = $base . '/lang/en/' . $group . '.php';
            /** @var mixed $en */
            $en = is_file($enFile) ? require $enFile : [];
            $flatFr = self::flatten(is_array($fr) ? $fr : []);
            $flatEn = self::flatten(is_array($en) ? $en : []);
            foreach ($flatFr as $key => $text) {
                if (trim($text) !== '' && preg_match('/\p{L}/u', $text)) {
                    $out[$group . '.' . $key] = [$text, $flatEn[$key] ?? ''];
                }
            }
        }

        return $memo[$base] = $out;
    }

    /** Texte français à montrer au traducteur pour une source du catalogue. */
    public static function frenchOf(string $source, ?string $root = null): string
    {
        if (!str_starts_with($source, '@')) {
            return $source;
        }

        return self::keyedSources($root)[substr($source, 1)][0] ?? $source;
    }

    /**
     * Contrôle d'une traduction : mêmes valeurs %1, %2… (ou :nom pour les catalogues à clés), mêmes balises et
     * morceaux de code HTML, préfixe « !! » gardé. Renvoie la liste des erreurs (vide = valable).
     *
     * @return list<string>
     */
    public static function check(string $source, string $translation): array
    {
        $errors = [];
        if (trim($translation) === '') {
            return ['empty'];
        }
        if (mb_strlen($translation) > 8000) {
            $errors[] = 'too_long';
        }
        $src = str_starts_with($source, '@') ? self::frenchOf($source) : $source;
        // Catalogues à clés : valeurs « :nom » (Translator::get) ; pages : %1, %2…
        $pattern = str_starts_with($source, '@') ? '/%\d+|(?<![\w\/]):[a-z_]+\b/' : '/%\d+/';
        $ph = static function (string $s) use ($pattern): array {
            preg_match_all($pattern, $s, $m);
            $list = $m[0];
            sort($list);

            return $list;
        };
        if ($ph($src) !== $ph($translation)) {
            $errors[] = 'placeholders';
        }
        if (self::markup($src) !== self::markup($translation)) {
            $errors[] = 'markup';
        }
        if (str_starts_with($src, '!!') !== str_starts_with($translation, '!!')) {
            $errors[] = 'prefix';
        }

        return $errors;
    }

    /**
     * Code HTML d'un texte, qui doit rester identique dans la traduction : balises <…> et, pour un morceau de page
     * qui commence au milieu d'une balise (« " class="…">Retour »), tout ce qui précède le premier « > ».
     *
     * @return list<string>
     */
    public static function markup(string $s): array
    {
        $out = [];
        $gt = strpos($s, '>');
        $lt = strpos($s, '<');
        if ($gt !== false && ($lt === false || $gt < $lt)) {
            $out[] = substr($s, 0, $gt + 1);
            $s = substr($s, $gt + 1);
        }
        preg_match_all('/<[^<>]*>?/', $s, $m);

        return array_merge($out, $m[0]);
    }

    /** Pour les tests. */
    public static function reset(): void
    {
        self::$dicts = [];
        self::$published = null;
        self::$catalog = null;
    }

    /**
     * @param array<mixed, mixed> $a
     * @return array<string, string>
     */
    private static function flatten(array $a, string $prefix = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $key = $prefix . $k;
            if (is_array($v)) {
                $out += self::flatten($v, $key . '.');
            } elseif (is_string($v)) {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    private static function path(string $file): string
    {
        return base_path(self::DIR . '/' . $file);
    }

    private static function writeFile(string $file, string $data): void
    {
        $dir = base_path(self::DIR);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $file;
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $data, LOCK_EX) === false) {
            throw new \RuntimeException('Écriture impossible dans ' . self::DIR);
        }
        rename($tmp, $path);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
