<?php

declare(strict_types=1);

namespace App\Support\Wiki;

/**
 * Wiki ATAK public (/wiki/atak) : contenu lu à la volée dans les fichiers versionnés du dépôt.
 *
 * - docs/steam/discussions/NN_<slug>.txt : un sujet Steam par app (BBCode, français puis anglais) → une page
 *   /wiki/atak/<slug> ; 00_INDEX.txt donne les catégories, les noms anglais, les noms de classe et les résumés
 *   (page /wiki/atak/apps) ; 90 et 91 sont les pages d'aide.
 * - docs/steam/STEAM_DESCRIPTION_LONG_{fr,en}.md : présentation de l'accueil du wiki.
 * - CHANGELOG-ATAK.md : page Nouveautés (bloc « Résumé joueur » de chaque version).
 *
 * Aucune base ni étape de génération : modifier un fichier suffit. Le catalogue (titres, résumés, texte de
 * recherche, nouveautés) est mis en cache dans storage/cache, reconstruit dès qu'un fichier source change.
 * Voir docs/wiki-atak.md.
 */
final class AtakWiki
{
    public const WORKSHOP_ID = '3684656708';
    public const WORKSHOP_URL = 'https://steamcommunity.com/sharedfiles/filedetails/?id=' . self::WORKSHOP_ID;
    public const DISCUSSIONS_DIR = 'docs/steam/discussions';
    public const CHANGELOG = 'CHANGELOG-ATAK.md';
    /** Slug de la page générée depuis 00_INDEX.txt (sommaire des apps). */
    public const INDEX_SLUG = 'apps';
    /** Adresses du wiki qui ne sont pas des sujets (un fichier ne peut pas les prendre). */
    public const RESERVED = ['apps', 'nouveautes', 'recherche'];
    /** Sujets d'aide (90_…, 91_…), cités par les autres pages. */
    public const HELP_INSTALL = 'aide-installation-serveur-logs';
    public const HELP_CBA = 'aide-reglages-serveur-cba';
    /** Change la clé du cache quand le format du catalogue évolue. */
    private const FORMAT = 3;

    private const REPLY = '===== RÉPONSE =====';

    /** @var array<string, mixed>|null */
    private static ?array $memo = null;
    private static ?string $rootOverride = null;

    /** Racine du dépôt (tests). null : base_path(). */
    public static function useRoot(?string $root): void
    {
        self::$rootOverride = $root;
        self::$memo = null;
    }

    public static function lang(): string
    {
        return function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
    }

    public static function url(string $slug = ''): string
    {
        return url('wiki/atak' . ($slug !== '' ? '/' . rawurlencode($slug) : ''));
    }

    // ------------------------------------------------------------------ catalogue

    /**
     * @return array{
     *   version: string,
     *   categories: list<array{key: string, fr: string, en: string, items: list<array<string, mixed>>}>,
     *   pages: array<string, array<string, mixed>>,
     *   releases: list<array<string, mixed>>,
     *   intro: array{fr: list<array{title: string, html: string}>, en: list<array{title: string, html: string}>}
     * }
     */
    public static function catalog(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        $sources = self::sources();
        $sig = [self::FORMAT];
        foreach ($sources as $path) {
            $sig[] = basename($path) . ':' . (int) @filemtime($path) . ':' . (int) @filesize($path);
        }
        $key = substr(md5(implode('|', $sig)), 0, 16);
        $dir = self::root() . '/storage/cache';
        $file = $dir . '/atak-wiki-' . $key . '.ser';
        if (self::$rootOverride === null && is_file($file)) {
            $data = @unserialize((string) @file_get_contents($file), ['allowed_classes' => false]);
            if (is_array($data) && isset($data['pages'], $data['categories'])) {
                return self::$memo = $data;
            }
        }
        $data = self::build();
        if (self::$rootOverride === null && is_dir($dir) && is_writable($dir)) {
            foreach (glob($dir . '/atak-wiki-*.ser') ?: [] as $old) {
                @unlink($old);
            }
            @file_put_contents($file . '.tmp', serialize($data));
            @rename($file . '.tmp', $file);
        }

        return self::$memo = $data;
    }

    /** @return array<string, mixed>|null */
    public static function page(string $slug): ?array
    {
        $pages = self::catalog()['pages'];

        return $pages[$slug] ?? null;
    }

    /**
     * Corps HTML d'un sujet dans la langue demandée, liens vers les autres apps compris.
     *
     * @return array{html: string, toc: list<array{id: string, text: string}>}
     */
    public static function render(string $slug, string $lang): array
    {
        $page = self::page($slug);
        if ($page === null || $page['file'] === '') {
            return ['html' => '', 'toc' => []];
        }
        $parts = self::parts(self::read(self::root() . '/' . self::DISCUSSIONS_DIR . '/' . $page['file']));
        $bb = $parts[$lang]['body'];
        $hasTop = preg_match('/\[h[12]\]/i', $bb) === 1;
        $renderer = new BbcodeRenderer(
            $hasTop ? ['h1' => 'h2', 'h2' => 'h2', 'h3' => 'h3'] : ['h1' => 'h2', 'h2' => 'h2', 'h3' => 'h2'],
            self::linker($lang, $slug),
            $lang === 'en' ? 'Show' : 'Afficher'
        );

        return $renderer->render($bb);
    }

    /**
     * @return list<array{slug: string, title: string, summary: string, snippet: string, score: int}>
     */
    public static function search(string $query, string $lang): array
    {
        $docs = [];
        foreach (self::catalog()['pages'] as $slug => $page) {
            $docs[] = [
                'slug' => (string) $slug,
                'title' => (string) $page[$lang]['title'],
                'summary' => (string) $page[$lang]['summary'],
                'text' => (string) $page[$lang]['text'],
            ];
        }

        return Search::run($docs, $query, 40);
    }

    // ------------------------------------------------------------------ construction

    /** @return list<string> */
    private static function sources(): array
    {
        $root = self::root();
        $files = glob($root . '/' . self::DISCUSSIONS_DIR . '/*.txt') ?: [];
        sort($files);
        foreach (['docs/steam/STEAM_DESCRIPTION_LONG_fr.md', 'docs/steam/STEAM_DESCRIPTION_LONG_en.md', self::CHANGELOG] as $rel) {
            $files[] = $root . '/' . $rel;
        }

        return $files;
    }

    private static function root(): string
    {
        return rtrim(str_replace('\\', '/', self::$rootOverride ?? base_path()), '/');
    }

    private static function read(string $path): string
    {
        $raw = is_file($path) ? (string) @file_get_contents($path) : '';
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        }

        return str_replace(["\r\n", "\r"], "\n", $raw);
    }

    /** @return array<string, mixed> */
    private static function build(): array
    {
        $root = self::root();
        $dir = $root . '/' . self::DISCUSSIONS_DIR;
        $files = glob($dir . '/*.txt') ?: [];
        sort($files);

        $index = ['version' => '', 'fr' => [], 'en' => []];
        $topics = [];
        foreach ($files as $path) {
            $base = basename($path);
            if (preg_match('/^(\d+)_([a-z0-9\-]+)\.txt$/i', $base, $m) !== 1) {
                continue;
            }
            $raw = self::read($path);
            if ((int) $m[1] === 0) {
                $index = self::parseIndex($raw);
                continue;
            }
            $slug = strtolower($m[2]);
            if (in_array($slug, self::RESERVED, true)) {
                $slug .= '-' . $m[1];
            }
            $topics[] = ['num' => (int) $m[1], 'slug' => $slug, 'file' => $base, 'raw' => $raw, 'mtime' => (int) @filemtime($path)];
        }

        // Chaque sujet retrouve sa ligne de l'index par son nom français, sinon par l'ordre.
        $items = [];
        foreach ($index['fr'] as $c => $cat) {
            foreach ($cat['items'] as $k => $it) {
                $items[] = ['c' => $c, 'k' => $k, 'fold' => Search::fold(self::bare($it['name'])), 'soon' => $it['soon'], 'used' => false];
            }
        }
        $pages = [];
        $byCategory = [];
        foreach ($topics as $topic) {
            $parts = self::parts($topic['raw']);
            $fold = Search::fold(self::bare($parts['fr']['title']));
            $hit = null;
            foreach ($items as $n => $it) {
                if (!$it['used'] && !$it['soon'] && $it['fold'] === $fold) {
                    $hit = $n;
                    break;
                }
            }
            if ($hit === null) {
                foreach ($items as $n => $it) {
                    if (!$it['used'] && !$it['soon']) {
                        $hit = $n;
                        break;
                    }
                }
            }
            $fr = $hit !== null ? $index['fr'][$items[$hit]['c']]['items'][$items[$hit]['k']] : null;
            $en = $hit !== null ? ($index['en'][$items[$hit]['c']]['items'][$items[$hit]['k']] ?? null) : null;
            if ($hit !== null) {
                $items[$hit]['used'] = true;
                $byCategory[$items[$hit]['c']][$items[$hit]['k']] = $topic['slug'];
            }
            $titleFr = $parts['fr']['title'] !== '' ? $parts['fr']['title'] : self::bare((string) ($fr['name'] ?? $topic['slug']));
            $titleEn = $parts['en']['title'] !== '' ? $parts['en']['title'] : self::bare((string) ($en['name'] ?? $titleFr));
            $subjectSummary = preg_match('/\s—\s(.+)$/u', $parts['subject'], $sm) === 1 ? trim($sm[1]) : '';
            $pages[$topic['slug']] = [
                'slug' => $topic['slug'],
                'num' => $topic['num'],
                'file' => $topic['file'],
                'kind' => $topic['num'] >= 90 ? 'help' : 'app',
                'class' => (string) ($fr['class'] ?? ''),
                'category' => $hit !== null ? $items[$hit]['c'] : null,
                'mtime' => $topic['mtime'],
                'fr' => [
                    'title' => $titleFr,
                    'summary' => $fr !== null && $fr['desc'] !== '' ? $fr['desc'] : $subjectSummary,
                    'lead' => $subjectSummary,
                    'subtitle' => $parts['fr']['subtitle'],
                    'text' => BbcodeRenderer::plainText($parts['fr']['body']),
                ],
                'en' => [
                    'title' => $titleEn,
                    'summary' => $en !== null && $en['desc'] !== '' ? $en['desc'] : '',
                    'lead' => $en !== null && $en['desc'] !== '' ? $en['desc'] : '',
                    'subtitle' => $parts['en']['subtitle'],
                    'text' => BbcodeRenderer::plainText($parts['en']['body']),
                ],
            ];
        }

        $categories = [];
        foreach ($index['fr'] as $c => $cat) {
            $list = [];
            foreach ($cat['items'] as $k => $it) {
                $enIt = $index['en'][$c]['items'][$k] ?? $it;
                $list[] = [
                    'slug' => $byCategory[$c][$k] ?? null,
                    'class' => $it['class'],
                    'soon' => $it['soon'],
                    'fr' => ['name' => self::bare($it['name']), 'desc' => $it['desc']],
                    'en' => ['name' => self::bare($enIt['name']), 'desc' => $enIt['desc']],
                ];
            }
            $categories[] = [
                'key' => BbcodeRenderer::slug($cat['title']),
                'fr' => $cat['title'],
                'en' => $index['en'][$c]['title'] ?? $cat['title'],
                'items' => $list,
            ];
        }
        // Un sujet absent de l'index reste accessible : catégorie « Autres ».
        $orphans = array_filter($pages, static fn (array $p): bool => $p['category'] === null);
        if ($orphans !== []) {
            $categories[] = [
                'key' => 'autres',
                'fr' => 'Autres',
                'en' => 'Other',
                'items' => array_values(array_map(static fn (array $p): array => [
                    'slug' => $p['slug'], 'class' => '', 'soon' => false,
                    'fr' => ['name' => $p['fr']['title'], 'desc' => $p['fr']['summary']],
                    'en' => ['name' => $p['en']['title'], 'desc' => $p['en']['summary']],
                ], $orphans)),
            ];
        }
        foreach ($categories as $c => $cat) {
            foreach ($pages as $slug => $p) {
                if ($p['category'] === $c) {
                    $pages[$slug]['category'] = $cat['key'];
                }
            }
        }
        foreach ($pages as $slug => $p) {
            if ($p['category'] === null) {
                $pages[$slug]['category'] = 'autres';
            }
        }

        return [
            'version' => $index['version'],
            'categories' => $categories,
            'pages' => $pages,
            'releases' => self::parseChangelog(self::read($root . '/' . self::CHANGELOG)),
            'intro' => [
                'fr' => self::parseIntro(self::read($root . '/docs/steam/STEAM_DESCRIPTION_LONG_fr.md')),
                'en' => self::parseIntro(self::read($root . '/docs/steam/STEAM_DESCRIPTION_LONG_en.md')),
            ],
        ];
    }

    /** « [AIDE] Installation… » → « Installation… ». */
    private static function bare(string $name): string
    {
        return trim(preg_replace('/^\[(?:AIDE|HELP|APP|GUIDE)\]\s*/i', '', strip_tags($name)) ?? $name);
    }

    /**
     * Découpe un sujet : ligne 1 = titre Steam, puis français ([h2]FRANÇAIS[/h2]) et anglais ([h2]ENGLISH[/h2]).
     * Les lignes « ===== RÉPONSE ===== », « Message n/m » et le séparateur [hr] final ne sont pas repris.
     *
     * @return array{subject: string, fr: array{title: string, subtitle: string, body: string}, en: array{title: string, subtitle: string, body: string}}
     */
    public static function parts(string $raw): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));
        $subject = trim((string) array_shift($lines));
        $frAt = null;
        $enAt = null;
        foreach ($lines as $n => $line) {
            $t = trim($line);
            if ($frAt === null && preg_match('/^\[h2\]\s*FRAN[CÇ]AIS\s*\[\/h2\]$/iu', $t) === 1) {
                $frAt = $n;
            } elseif ($enAt === null && preg_match('/^\[h2\]\s*ENGLISH\s*\[\/h2\]$/i', $t) === 1) {
                $enAt = $n;
            }
        }
        $meta = ['fr' => ['title' => '', 'subtitle' => ''], 'en' => ['title' => '', 'subtitle' => '']];
        $grab = static function (array $slice, string $lang) use (&$meta): void {
            foreach ($slice as $line) {
                $t = trim($line);
                if ($meta[$lang]['title'] === '' && preg_match('/^\[h1\](.+)\[\/h1\]$/u', $t, $m) === 1) {
                    $meta[$lang]['title'] = trim(BbcodeRenderer::plainText($m[1]));
                } elseif ($meta[$lang]['subtitle'] === '' && preg_match('/^\[i\](.+)\[\/i\]$/u', $t, $m) === 1 && preg_match('/^Message \d/u', $m[1]) !== 1) {
                    $meta[$lang]['subtitle'] = trim(BbcodeRenderer::plainText($m[1]));
                }
            }
        };
        if ($frAt === null) {
            $body = self::clean($lines);

            return ['subject' => $subject, 'fr' => ['title' => '', 'subtitle' => '', 'body' => $body], 'en' => ['title' => '', 'subtitle' => '', 'body' => $body]];
        }
        $grab(array_slice($lines, 0, $frAt), 'fr');
        $frEnd = $enAt ?? count($lines);
        for ($n = $frAt + 1; $n < $frEnd; $n++) {
            if (trim($lines[$n]) === self::REPLY) {
                $grab(array_slice($lines, $n + 1, $frEnd - $n - 1), 'en');
                $frEnd = $n;
                break;
            }
        }
        $fr = self::clean(array_slice($lines, $frAt + 1, $frEnd - $frAt - 1));
        $en = $enAt !== null ? self::clean(array_slice($lines, $enAt + 1)) : $fr;

        return [
            'subject' => $subject,
            'fr' => $meta['fr'] + ['body' => $fr],
            'en' => $meta['en'] + ['body' => $en],
        ];
    }

    /** @param list<string> $lines */
    private static function clean(array $lines): string
    {
        $keep = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === self::REPLY || preg_match('/^\[i\]Message \d+\/\d+.*\[\/i\]$/u', $t) === 1) {
                continue;
            }
            $keep[] = rtrim($line);
        }
        $body = trim(implode("\n", $keep));
        // Séparateur avant la partie anglaise.
        $body = preg_replace('/(?:\s*\[hr\]\s*(?:\[\/hr\])?)+$/i', '', $body) ?? $body;

        return trim($body);
    }

    /**
     * 00_INDEX.txt : catégories ([h3]) et lignes « [*][b]Nom[/b] (Classe) : résumé. Voir le sujet dédié. ».
     *
     * @return array{version: string, fr: list<array{title: string, items: list<array{name: string, class: string, desc: string, soon: bool}>}>, en: list<array{title: string, items: list<array{name: string, class: string, desc: string, soon: bool}>}>}
     */
    public static function parseIndex(string $raw): array
    {
        $parts = self::parts($raw);
        $version = preg_match('/ATAK\s+(\d+(?:\.\d+)+)/', $raw, $v) === 1 ? $v[1] : '';

        return ['version' => $version, 'fr' => self::indexLang($parts['fr']['body']), 'en' => self::indexLang($parts['en']['body'])];
    }

    /** @return list<array{title: string, items: list<array{name: string, class: string, desc: string, soon: bool}>}> */
    private static function indexLang(string $bb): array
    {
        $cats = [];
        foreach (explode("\n", $bb) as $line) {
            $t = trim($line);
            if (preg_match('/^\[h3\](.+)\[\/h3\]$/u', $t, $m) === 1) {
                $cats[] = ['title' => trim(BbcodeRenderer::plainText($m[1])), 'items' => []];
                continue;
            }
            if ($cats === [] || preg_match('/^\[\*\]\s*\[b\](.+?)\[\/b\]\s*(?:\(([A-Za-z0-9_]+)\))?\s*[:.]?\s*(.*)$/u', $t, $m) !== 1) {
                continue;
            }
            $desc = trim($m[3]);
            $soon = preg_match('/(Pas encore de sujet|No thread yet)\.?$/iu', $desc) === 1;
            $desc = trim(preg_replace('/\s*(Voir le sujet dédié|See the dedicated thread|Pas encore de sujet|No thread yet)\.?$/iu', '', $desc) ?? $desc);
            $cats[count($cats) - 1]['items'][] = [
                'name' => trim($m[1]),
                'class' => $m[2] ?? '',
                'desc' => rtrim(BbcodeRenderer::plainText($desc), ' .') . ($desc !== '' ? '.' : ''),
                'soon' => $soon,
            ];
        }

        return array_values(array_filter($cats, static fn (array $c): bool => $c['items'] !== []));
    }

    /**
     * Lien automatique : un [b]Nom d'app[/b] (ou « A, B et C ») mène à la page de l'app.
     *
     * @return callable(string): ?string
     */
    private static function linker(string $lang, string $self): callable
    {
        $names = [];
        foreach (self::catalog()['pages'] as $slug => $p) {
            if ($slug !== $self) {
                $names[(string) $p[$lang]['title']] = (string) $slug;
            }
        }
        $a = static fn (string $name, string $slug): string => '<a class="atw-applink" href="' . htmlspecialchars(self::url($slug), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';

        return static function (string $text) use ($names, $a): ?string {
            $text = trim($text);
            if (isset($names[$text])) {
                return $a($text, $names[$text]);
            }
            $bits = preg_split('/(\s*,\s*|\s+(?:et|and|ou|or)\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            if (count($bits) < 3) {
                return null;
            }
            $html = '';
            foreach ($bits as $n => $bit) {
                if ($n % 2 === 1) {
                    $html .= htmlspecialchars($bit, ENT_QUOTES, 'UTF-8');
                    continue;
                }
                if (!isset($names[$bit])) {
                    return null;
                }
                $html .= $a($bit, $names[$bit]);
            }

            return $html;
        };
    }

    /**
     * CHANGELOG-ATAK.md : chaque entrée « ## … » qui a un « ### Résumé joueur ».
     *
     * @return list<array{id: string, label: string, version: string, type: string, heading: string, fr: array{title: string, bullets: list<string>}, en: array{title: string, bullets: list<string>}}>
     */
    public static function parseChangelog(string $md): array
    {
        $out = [];
        $ids = [];
        // Les exemples entre ``` (règle d'écriture en tête du fichier) ne sont pas des versions.
        $md = preg_replace('/^```.*?^```[ \t]*$/ms', '', $md) ?? $md;
        foreach (preg_split('/^## /m', $md) ?: [] as $n => $chunk) {
            if ($n === 0) {
                continue;
            }
            $heading = trim(strtok($chunk, "\n") ?: '');
            if (preg_match('/^### Résumé joueur[ \t]*\n(.*?)(?=^### |\z)/msu', $chunk, $b) !== 1) {
                continue;
            }
            $entry = ['type' => '', 'fr' => ['title' => '', 'bullets' => []], 'en' => ['title' => '', 'bullets' => []]];
            $lang = '';
            foreach (explode("\n", $b[1]) as $line) {
                $line = trim($line);
                if (preg_match('/^Type\s*:\s*(.+)$/u', $line, $l) === 1) {
                    $entry['type'] = mb_strtolower(trim($l[1]), 'UTF-8');
                } elseif (preg_match('/^(FR|EN)\s*:\s*(.+)$/u', $line, $l) === 1) {
                    $lang = strtolower($l[1]);
                    $entry[$lang]['title'] = self::cleanMd($l[2]);
                } elseif ($lang !== '' && str_starts_with($line, '- ')) {
                    $entry[$lang]['bullets'][] = self::cleanMd(substr($line, 2));
                }
            }
            if ($entry['fr']['title'] === '' && $entry['en']['title'] === '') {
                continue;
            }
            $label = preg_match_all('/\(([^()]*)\)/u', $heading, $pm) > 0 ? trim((string) end($pm[1])) : '';
            $version = preg_match('/ATAK natif (\d+(?:\.\d+)+)/u', $heading, $vm) === 1 ? $vm[1]
                : (preg_match('/(\d+\.\d+\.\d+)/', $label, $vm) === 1 ? $vm[1] : '');
            $id = 'v-' . BbcodeRenderer::slug($version !== '' ? $version : ($label !== '' ? $label : $heading));
            $ids[$id] = ($ids[$id] ?? 0) + 1;
            if ($ids[$id] > 1) {
                $id .= '-' . $ids[$id];
            }
            if ($entry['en']['title'] === '') {
                $entry['en'] = $entry['fr'];
            }
            if ($entry['fr']['title'] === '') {
                $entry['fr'] = $entry['en'];
            }
            $out[] = ['id' => $id, 'label' => $label, 'version' => $version, 'heading' => $heading] + $entry;
        }

        return $out;
    }

    private static function cleanMd(string $s): string
    {
        return trim(str_replace(['`', '**', '|'], '', $s));
    }

    /**
     * Description longue (Markdown simple) : sections « ## » de l'accueil du wiki, la liste des apps en moins
     * (remplacée par le sommaire), le titre et la note d'en-tête en moins.
     *
     * @return list<array{title: string, html: string}>
     */
    public static function parseIntro(string $md): array
    {
        $sections = [];
        foreach (preg_split('/^## /m', $md) ?: [] as $n => $chunk) {
            if ($n === 0) {
                continue;
            }
            $title = trim(strtok($chunk, "\n") ?: '');
            if (preg_match('/^(Les apps|The apps)\b/iu', $title) === 1) {
                continue;
            }
            $body = (string) substr($chunk, strlen((string) strtok($chunk, "\n")));
            $sections[] = ['title' => $title, 'html' => self::markdown($body)];
        }

        return $sections;
    }

    /** Markdown minimal et sûr : paragraphes, listes (imbriquées sur deux espaces), listes numérotées, `code`, adresses. */
    private static function markdown(string $md): string
    {
        $html = '';
        $para = [];
        $listStack = [];
        $flushPara = static function () use (&$para, &$html): void {
            if ($para !== []) {
                $html .= '<p>' . self::inlineMd(implode(' ', $para)) . '</p>';
                $para = [];
            }
        };
        $closeLists = static function (int $depth) use (&$listStack, &$html): void {
            while (count($listStack) > $depth) {
                $html .= '</li></' . array_pop($listStack) . '>';
            }
        };
        foreach (explode("\n", $md) as $line) {
            if (trim($line) === '') {
                $flushPara();
                continue;
            }
            if (preg_match('/^(\s*)(-|\d+\.)\s+(.*)$/u', $line, $m) === 1) {
                $flushPara();
                $depth = intdiv(strlen($m[1]), 2) + 1;
                $type = $m[2] === '-' ? 'ul' : 'ol';
                if (count($listStack) > $depth) {
                    $closeLists($depth);
                }
                if (count($listStack) === $depth) {
                    $html .= '</li><li>';
                } else {
                    while (count($listStack) < $depth) {
                        $listStack[] = $type;
                        $html .= '<' . $type . '><li>';
                    }
                }
                $html .= self::inlineMd(trim($m[3]));
                continue;
            }
            if ($listStack !== [] && preg_match('/^\s+\S/u', $line) === 1) {
                $html .= ' ' . self::inlineMd(trim($line));
                continue;
            }
            $closeLists(0);
            $para[] = trim($line);
        }
        $flushPara();
        $closeLists(0);

        return $html;
    }

    private static function inlineMd(string $s): string
    {
        $e = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $e = preg_replace('/`([^`]+)`/', '<code>$1</code>', $e) ?? $e;
        $e = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $e) ?? $e;

        return preg_replace_callback('#https?://[a-z0-9.\-]+(?:/[^\s<)]*)?#i', static function (array $m): string {
            $url = rtrim($m[0], '.,;');
            $tail = substr($m[0], strlen($url));

            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $tail;
        }, $e) ?? $e;
    }
}
