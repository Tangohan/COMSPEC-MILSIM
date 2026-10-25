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

    /** @var array<string, array{screen: string, label: string, context: string}>|null */
    private static ?array $contexts = null;

    /**
     * Écran lisible d’un catalogue à clés : « groupe » ou « groupe.préfixe ».
     * Les pages sans catalogue restent « Pages du portail ».
     *
     * @var array<string, string>
     */
    private const SCREENS = [
        'auth' => 'Connexion et compte',
        'auth.register' => 'Création de compte',
        'auth.step' => 'Création de compte',
        'auth.steps' => 'Création de compte',
        'auth.forgot' => 'Mot de passe oublié',
        'auth.reset' => 'Mot de passe oublié',
        'auth.otp' => 'Double vérification',
        'auth.flash' => 'Messages de connexion',
        'auth.header' => 'En-tête du portail',
        'auth.title' => 'Connexion et compte',
        'common' => 'Textes communs',
        'common.platform' => 'Avis sur Athena',
        'common.integration' => 'Liaisons du compte',
        'common.language' => 'Choix de la langue',
        'cookies' => 'Bandeau des cookies',
        'errors' => 'Pages d’erreur',
        'errors.404' => 'Page introuvable',
        'errors.403' => 'Accès refusé',
        'errors.500' => 'Incident du portail',
        'errors.personnel' => 'Fiche personnel',
        'home' => 'Page d’accueil',
        'home.demo' => 'Démonstration',
        'home.faq' => 'Questions fréquentes',
        'home.story' => 'Récit de la page d’accueil',
        'home.persona' => 'Profils de la page d’accueil',
        'home.units' => 'Unités',
        'legal' => 'Mentions et droits',
        'legal.gdpr' => 'Exercice des droits',
        'nav' => 'Navigation',
        'site' => 'Portail',
        'site.sitetr' => 'Traduction du site',
        'site.atakt' => 'Atelier du téléphone',
        'site.atakn' => 'Téléphone ATAK',
        'site.atak' => 'Téléphone ATAK',
        'site.atakw' => 'Atelier du téléphone',
        'site.sse' => 'Renseignement SSE',
        'site.contrib' => 'Contribuer',
        'site.about' => 'À propos',
        'site.contact' => 'Contact',
        'site.cl' => 'Journal des nouveautés',
    ];

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

    /** L’anglais livré avec le site existe et n’est pas une copie du français. */
    public static function hasShippedEnglish(string $fr, string $en): bool
    {
        $en = trim($en);

        return $en !== '' && mb_strtolower($en) !== mb_strtolower(trim($fr));
    }

    /** Textes du catalogue qui n’ont encore que le français. */
    public static function missingEnglishCount(?string $root = null): int
    {
        $n = 0;
        foreach (self::catalog($root) as [, $src, $en, $kind]) {
            $fr = $kind === 'key' ? self::frenchOf($src, $root) : $src;
            if (!self::hasShippedEnglish($fr, $en)) {
                $n++;
            }
        }

        return $n;
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

    /**
     * Où le texte apparaît, et la phrase autour quand le catalogue la contient déjà.
     * Une phrase complète ne reçoit que le nom de l’écran.
     *
     * @return array{screen: string, label: string, context: string}
     */
    public static function contextOf(string $hash, ?string $root = null): array
    {
        $all = self::contexts($root);

        return $all[$hash] ?? ['screen' => 'pages', 'label' => 'Pages du portail', 'context' => ''];
    }

    /**
     * @return array<string, array{screen: string, label: string, context: string}>
     */
    public static function contexts(?string $root = null): array
    {
        if (self::$contexts !== null && $root === null) {
            return self::$contexts;
        }
        $rows = [];
        foreach (self::catalog($root) as [$h, $src, , $kind]) {
            $fr = $kind === 'key' ? self::frenchOf($src, $root) : $src;
            [$screen, $label] = self::screenOf($kind, $src);
            $rows[] = ['h' => $h, 'fr' => $fr, 'kind' => $kind, 'screen' => $screen, 'label' => $label];
        }
        $byFrench = [];
        foreach ($rows as $row) {
            if ($row['kind'] === 'key' && !isset($byFrench[$row['fr']])) {
                $byFrench[$row['fr']] = [$row['screen'], $row['label']];
            }
        }
        foreach ($rows as $i => $row) {
            if ($row['kind'] === 'text' && isset($byFrench[$row['fr']])) {
                $rows[$i]['screen'] = $byFrench[$row['fr']][0];
                $rows[$i]['label'] = $byFrench[$row['fr']][1];
            }
        }
        /** @var array<string, list<int>> $groups */
        $groups = [];
        /** @var array<string, int> $keyByFrench */
        $keyByFrench = [];
        foreach ($rows as $i => $row) {
            if ($row['kind'] !== 'key') {
                continue;
            }
            $groups[$row['screen']][] = $i;
            $keyByFrench[$row['fr']] ??= $i;
        }
        $byIndex = [];
        foreach ($groups as $indexes) {
            foreach ($indexes as $i) {
                $byIndex[$i] = self::isWholeSentence($rows[$i]['fr'])
                    ? ''
                    : self::aroundText($rows, $indexes, $i);
            }
        }
        $out = [];
        foreach ($rows as $i => $row) {
            $context = $byIndex[$i] ?? '';
            if ($row['kind'] === 'text' && isset($keyByFrench[$row['fr']])) {
                $context = $byIndex[$keyByFrench[$row['fr']]] ?? '';
            }
            $out[$row['h']] = [
                'screen' => $row['screen'],
                'label' => $row['label'],
                'context' => $context,
            ];
        }
        if ($root === null) {
            self::$contexts = $out;
        }

        return $out;
    }

    /** Pour les tests. */
    public static function reset(): void
    {
        self::$dicts = [];
        self::$published = null;
        self::$catalog = null;
        self::$contexts = null;
    }

    /** @return array{0: string, 1: string} */
    private static function screenOf(string $kind, string $src): array
    {
        if ($kind !== 'key' || !str_starts_with($src, '@')) {
            return ['pages', 'Pages du portail'];
        }
        $id = substr($src, 1);
        $dot = strpos($id, '.');
        $group = $dot === false ? $id : substr($id, 0, $dot);
        $key = $dot === false ? $id : substr($id, $dot + 1);
        $head = explode('_', explode('.', $key)[0])[0];
        $specific = $group . '.' . $head;
        if (isset(self::SCREENS[$specific])) {
            return [self::SCREENS[$specific], self::SCREENS[$specific]];
        }
        if (isset(self::SCREENS[$group])) {
            return [self::SCREENS[$group], self::SCREENS[$group]];
        }

        return [$group !== '' ? $group : 'pages', 'Pages du portail'];
    }

    private static function isWholeSentence(string $fr): bool
    {
        $t = trim($fr);
        if ($t === '' || preg_match('/^["\'<]/u', $t) === 1 || str_contains($t, 'class=') || str_contains($t, 'aria-')) {
            return false;
        }
        $len = mb_strlen($t);
        $words = preg_match_all('/\p{L}{2,}/u', $t);
        if ($len >= 80) {
            return true;
        }
        if ($words >= 8 && $len >= 45) {
            return true;
        }

        return $words >= 6 && preg_match('/[.!?…:][»"\']?$/u', $t) === 1;
    }

    /**
     * @param list<array{h: string, fr: string, kind: string, screen: string, label: string}> $rows
     * @param list<int> $indexes
     */
    private static function aroundText(array $rows, array $indexes, int $i): string
    {
        $fr = trim($rows[$i]['fr']);
        if ($fr === '') {
            return '';
        }
        $pos = array_search($i, $indexes, true);
        if ($pos === false) {
            return '';
        }
        $sentence = self::containingSentence($rows, $indexes, $fr);
        if ($sentence !== '') {
            return $sentence;
        }
        $before = [];
        for ($d = $pos - 1; $d >= 0 && count($before) < 2 && $pos - $d <= 8; $d--) {
            $line = self::neighbourLine($rows[$indexes[$d]]['fr'], $fr);
            if ($line !== '') {
                array_unshift($before, $line);
            }
        }
        $after = [];
        $n = count($indexes);
        for ($d = $pos + 1; $d < $n && count($after) < 2 && $d - $pos <= 8; $d++) {
            $line = self::neighbourLine($rows[$indexes[$d]]['fr'], $fr);
            if ($line !== '') {
                $after[] = $line;
            }
        }

        $lines = [];
        foreach (array_merge($before, $after) as $line) {
            if (!in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<array{h: string, fr: string, kind: string, screen: string, label: string}> $rows
     * @param list<int> $indexes
     */
    private static function containingSentence(array $rows, array $indexes, string $fr): string
    {
        if (mb_strlen($fr) < 3 || mb_strlen($fr) > 60) {
            return '';
        }
        $best = '';
        $bestLen = PHP_INT_MAX;
        $found = 0;
        foreach ($indexes as $j) {
            $other = trim($rows[$j]['fr']);
            $len = mb_strlen($other);
            if ($other === $fr || $len > 320 || $len < mb_strlen($fr) + 8) {
                continue;
            }
            $pos = mb_stripos($other, $fr);
            if ($pos === false || $pos < 1) {
                continue;
            }
            $before = mb_substr($other, $pos - 1, 1);
            $afterAt = $pos + mb_strlen($fr);
            $after = $afterAt < $len ? mb_substr($other, $afterAt, 1) : ' ';
            if (self::isWordChar($before) || self::isWordChar($after)) {
                continue;
            }
            $found++;
            if ($found > 2) {
                return '';
            }
            if ($len < $bestLen) {
                $best = $other;
                $bestLen = $len;
            }
        }

        return $found > 0 ? $best : '';
    }

    private static function neighbourLine(string $other, string $self): string
    {
        $t = trim($other);
        if ($t === '' || $t === $self || preg_match('/^["\'<]/u', $t) === 1 || str_contains($t, 'class=')) {
            return '';
        }
        if (mb_strlen($t) > 160) {
            return '';
        }

        return $t;
    }

    private static function isWordChar(string $c): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $c) === 1;
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
