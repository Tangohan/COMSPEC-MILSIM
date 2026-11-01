<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Support\DevDispatchCatalog;
use App\Support\PackCodenameCatalog;

/**
 * Historique public des versions (page « Notre histoire ») : ATAK natif, COMSPEC Link, Overwatch, SSE et portail.
 *
 * Sources, fusionnées par produit + numéro de version :
 *  - CHANGELOG-ATAK.md : titres d'entrée (versions, parfois la date) et bloc « Résumé joueur » FR / EN ;
 *  - docs/dev/STEAM-CHANGELOG-<date>-<produit>-<version>.md : date de publication et texte joueur ;
 *  - bulletins UPDATE de DevDispatchCatalog : date, titre, résumé et numéro (versions lues dans la ligne « SIZE ») ;
 *  - storage/app_version.json : version actuelle du portail et son nom de ville.
 *
 * Seuls des textes destinés aux joueurs sortent d'ici ; les phrases d'atelier (noms de fichiers, outils internes)
 * sont écartées. Rien n'est calculé à chaque visite : le résultat est gardé dans storage/cache et recalculé
 * seulement quand une des sources change (taille ou date de modification).
 */
final class PublicReleaseHistoryService
{
    public const PRODUCTS = [
        'atak' => 'ATAK natif',
        'link' => 'COMSPEC Link',
        'overwatch' => 'Overwatch',
        'sse' => 'SSE',
        'portal' => 'Portail Athena',
    ];

    /** Libellé affiché sur l'étiquette de version (le module Athena en jeu est rangé avec Overwatch). */
    private const TAG_LABELS = [
        'atak' => 'ATAK natif',
        'link' => 'COMSPEC Link',
        'overwatch' => 'Overwatch',
        'athena' => 'Athena en jeu',
        'sse' => 'SSE',
        'portal' => 'Portail',
    ];

    /** Produit → filtre de la frise. */
    private const FILTER_OF = [
        'atak' => 'atak',
        'link' => 'link',
        'overwatch' => 'overwatch',
        'athena' => 'overwatch',
        'sse' => 'sse',
        'portal' => 'portal',
    ];

    private const CACHE_FORMAT = 3;

    private const SUMMARY_MAX = 220;

    private const POINTS_MAX = 4;

    /** Termes d'atelier : une phrase qui en contient n'est jamais affichée au joueur. */
    private const JARGON = '/`|\bapi\b|endpoint|\bjson\b|\bsql\b|\bsqf\b|\bpbo\b|\bdll\b|\bphp\b|\bc#|repository|handler|payload|polling|backend|\bhooks?\b|\bcfg[a-z]|\btables?\b|\bfix\b|rebuild|\bmigrations?\b|\bcron\b|webhook|\bTTRD\b|commit|\bPR\b|#\d{2,}|BIS_fnc|extAwait|\bOPT-\d|\.(?:sqf|php|hpp|cpp|cs|json|md)\b|\/api\//iu';

    /** Phrases de consigne d'installation, utiles dans un bulletin mais pas dans une frise. */
    private const INSTALL_HINT = '/^(?:relancez|relancer|quittez|quitter|mettez à jour|mettre à jour|déployez|déployer|rechargez|recharger|vérifiez le journal|non testé|à tester|demande de\s|retours? de\s)/iu';

    /** @var (callable(): list<array<string, mixed>>)|null */
    private $updatesProvider;

    /**
     * @param (callable(): list<array<string, mixed>>)|null $updatesProvider bulletins UPDATE (tests) ; défaut : DevDispatchCatalog
     */
    public function __construct(
        private ?string $rootDir = null,
        private ?string $cacheDir = null,
        ?callable $updatesProvider = null,
    ) {
        $this->updatesProvider = $updatesProvider;
    }

    /**
     * @return array{
     *   releases: list<array<string, mixed>>,
     *   current: list<array{product: string, label: string, version: string, date: ?string}>,
     *   counts: array<string, int>,
     *   bulletins: int,
     *   first_date: ?string
     * }
     */
    public function history(bool $fresh = false): array
    {
        $signature = $this->signature();
        $path = $this->cachePath();
        if (!$fresh && is_file($path)) {
            $cached = json_decode((string) @file_get_contents($path), true);
            if (is_array($cached) && ($cached['signature'] ?? null) === $signature && is_array($cached['data'] ?? null)) {
                /** @var array{releases: list<array<string, mixed>>, current: list<array{product: string, label: string, version: string, date: ?string}>, counts: array<string, int>, bulletins: int, first_date: ?string} $data */
                $data = $cached['data'];

                return $data;
            }
        }
        $data = $this->build();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, (string) json_encode(['signature' => $signature, 'data' => $data], JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $data;
    }

    /**
     * @return array{
     *   releases: list<array<string, mixed>>,
     *   current: list<array{product: string, label: string, version: string, date: ?string}>,
     *   counts: array<string, int>,
     *   bulletins: int,
     *   first_date: ?string
     * }
     */
    private function build(): array
    {
        $updates = $this->updates();
        $candidates = [
            ...$this->fromChangelog(),
            ...$this->fromSteam(),
            ...$this->fromUpdates($updates),
            ...$this->fromAppVersion(),
        ];

        // Fusion : une version = son étiquette principale (premier produit cité). Les étiquettes secondaires
        // (« COMSPEC Link 2.0.110 » livré avec un pack) restent des mentions, sans fusionner deux sorties.
        /** @var array<string, array<string, mixed>> $releases */
        $releases = [];
        foreach ($candidates as $c) {
            $tokens = $c['tokens'];
            if ($tokens === []) {
                continue;
            }
            $key = $tokens[0]['product'] . ':' . self::versionKey($tokens[0]['version']);
            if (!isset($releases[$key])) {
                $releases[$key] = [
                    'key' => $key,
                    'tokens' => [],
                    'date' => null,
                    'date_precision' => 'day',
                    'title' => ['p' => -1, 'fr' => '', 'en' => ''],
                    'summary' => ['p' => -1, 'fr' => ''],
                    'points' => ['p' => -1, 'fr' => [], 'en' => []],
                    'extras' => [],
                    'type' => ['p' => -1, 'v' => null],
                    'codename' => null,
                    'bulletin' => 0,
                    'order' => null,
                ];
            }
            $r = &$releases[$key];
            foreach ($tokens as $t) {
                // Une étiquette par produit : la première citée (celle de la sortie elle-même).
                $r['tokens'][$t['product']] ??= $t;
            }
            if ($c['date'] !== null && ($r['date'] === null || strcmp($c['date'], $r['date']) < 0)) {
                $r['date'] = $c['date'];
            }
            $p = $c['priority'];
            $titleFr = $c['title'];
            if ($titleFr !== '' && $p > $r['title']['p']) {
                $r['title'] = ['p' => $p, 'fr' => $titleFr, 'en' => $c['title_en']];
            } elseif ($titleFr !== '' && !empty($c['part']) && $titleFr !== $r['title']['fr']) {
                // Sous-entrée d'un regroupement : son titre devient une ligne de la version.
                $r['extras'][] = $titleFr;
            }
            if ($c['summary'] !== '' && $p > $r['summary']['p']) {
                $r['summary'] = ['p' => $p, 'fr' => $c['summary']];
            }
            if ($c['points'] !== [] && $p > $r['points']['p']) {
                $r['points'] = ['p' => $p, 'fr' => $c['points'], 'en' => $c['points_en']];
            }
            if ($c['type'] !== null && $p > $r['type']['p']) {
                $r['type'] = ['p' => $p, 'v' => $c['type']];
            }
            $r['codename'] ??= $c['codename'];
            $r['bulletin'] = max($r['bulletin'], $c['bulletin']);
            if ($c['order'] !== null) {
                $r['order'] = $r['order'] === null ? $c['order'] : min($r['order'], $c['order']);
            }
            unset($r);
        }

        // Entrées du journal sans date : au plus tôt la date de l'entrée datée qui la précède dans le journal
        // (journal rangé du plus récent au plus ancien) — affichée au mois, jamais au jour.
        $byOrder = array_filter($releases, static fn (array $r): bool => $r['order'] !== null);
        uasort($byOrder, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
        $orderKeys = array_keys($byOrder);
        foreach ($orderKeys as $i => $k) {
            if ($releases[$k]['date'] !== null) {
                continue;
            }
            for ($j = $i + 1, $n = count($orderKeys); $j < $n; $j++) {
                $older = $releases[$orderKeys[$j]]['date'];
                if ($older !== null) {
                    $releases[$k]['date'] = $older;
                    $releases[$k]['date_precision'] = 'month';
                    break;
                }
            }
        }

        $out = [];
        foreach ($releases as $r) {
            if ($r['date'] === null) {
                continue;
            }
            $tokens = array_values($r['tokens']);
            $primary = $tokens[0];
            $title = $r['title']['fr'] !== '' ? self::upperFirst($r['title']['fr']) : (self::TAG_LABELS[$primary['product']] . ' ' . $primary['version']);
            $points = $r['points']['fr'];
            foreach ($r['extras'] as $extra) {
                if (count($points) >= self::POINTS_MAX + 1) {
                    break;
                }
                if (!in_array($extra, $points, true)) {
                    $points[] = $extra;
                }
            }
            $filters = [];
            foreach ($tokens as $t) {
                $filters[self::FILTER_OF[$t['product']]] = true;
            }
            $out[] = [
                'id' => preg_replace('/[^a-z0-9]+/', '-', strtolower($r['key'])) ?? '',
                'date' => $r['date'],
                'date_precision' => $r['date_precision'],
                'product' => $primary['product'],
                'filters' => array_keys($filters),
                'tags' => array_map(static fn (array $t): array => [
                    'product' => $t['product'],
                    'label' => self::TAG_LABELS[$t['product']],
                    'version' => $t['version'],
                ], $tokens),
                'title' => $title,
                'title_en' => $r['title']['en'],
                'summary' => $r['summary']['fr'],
                'points' => array_values(array_slice($points, 0, self::POINTS_MAX + 1)),
                'points_en' => $r['points']['en'],
                'type' => $r['type']['v'],
                'codename' => $r['codename'],
                'bulletin' => $r['bulletin'],
                '_order' => $r['order'],
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $d = strcmp((string) $b['date'], (string) $a['date']);
            if ($d !== 0) {
                return $d;
            }
            if ($a['bulletin'] > 0 && $b['bulletin'] > 0 && $a['bulletin'] !== $b['bulletin']) {
                return $b['bulletin'] <=> $a['bulletin'];
            }
            if ($a['_order'] !== null && $b['_order'] !== null) {
                return $a['_order'] <=> $b['_order'];
            }
            if ($a['product'] === $b['product']) {
                return version_compare(self::versionKey($b['tags'][0]['version']), self::versionKey($a['tags'][0]['version']));
            }

            return 0;
        });

        $counts = array_fill_keys(array_keys(self::PRODUCTS), 0);
        $current = [];
        foreach ($out as &$rel) {
            unset($rel['_order']);
            foreach ($rel['filters'] as $f) {
                $counts[$f]++;
            }
            // Version en service = celle de la sortie la plus récente qui cite le produit (liste déjà triée).
            foreach ($rel['tags'] as $t) {
                $product = $t['product'];
                if ($product !== 'athena' && !isset($current[$product])) {
                    $current[$product] = ['product' => $product, 'label' => self::PRODUCTS[$product], 'version' => $t['version'], 'date' => $rel['date']];
                }
            }
        }
        unset($rel);

        $orderedCurrent = [];
        foreach (array_keys(self::PRODUCTS) as $product) {
            if (isset($current[$product])) {
                $orderedCurrent[] = $current[$product];
            }
        }
        $last = $out === [] ? null : $out[count($out) - 1]['date'];

        return [
            'releases' => $out,
            'current' => $orderedCurrent,
            'counts' => $counts,
            'bulletins' => count($updates),
            'first_date' => is_string($last) ? $last : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromChangelog(): array
    {
        $raw = $this->read('CHANGELOG-ATAK.md');
        if ($raw === '') {
            return [];
        }
        $raw = str_replace("\r\n", "\n", $raw);
        $parts = preg_split('/^## /m', $raw) ?: [];
        array_shift($parts);
        $out = [];
        foreach ($parts as $index => $part) {
            $nl = strpos($part, "\n");
            $heading = trim($nl === false ? $part : substr($part, 0, $nl));
            $body = $nl === false ? '' : substr($part, $nl + 1);
            $tokens = self::tokens($heading, true);
            if ($tokens === []) {
                continue;
            }
            $date = preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $heading, $m) === 1 ? $m[1] : null;
            $isPart = preg_match('/partie du regroupement|regroupé dans/iu', $heading) === 1;

            $resume = self::playerSummary($body);
            $type = $resume['type'];
            $title = $resume['fr'];
            $points = $resume['points'];
            if ($title === '') {
                $cleaned = self::headingTitle($heading);
                $sub = preg_match('/^### (?!Résumé|Détail)(.+)$/mu', $body, $sm) === 1 ? trim($sm[1]) : '';
                [$subType, $subTitle] = self::splitTypedTitle($sub);
                $type ??= $subType;
                $title = $cleaned !== '' ? $cleaned : $subTitle;
            }
            if (preg_match('/^correctif\b/iu', $heading) === 1) {
                $type ??= 'fix';
            }
            $summary = $resume['fr'] !== '' ? '' : self::firstParagraph($body);
            if ($points === []) {
                $points = self::listItems($body);
            }

            $out[] = [
                'tokens' => $tokens,
                'date' => $date,
                'priority' => $resume['fr'] !== '' ? 30 : 10,
                'title' => self::clean($title),
                'title_en' => $resume['en'],
                'summary' => $summary,
                'points' => $points,
                'points_en' => $resume['points_en'],
                'type' => $type,
                'codename' => preg_match('/\d+\.\d+\.\d+\.[A-Z]{1,3}\s*-\s*([A-Z][a-z]+)/', $heading, $cm) === 1 ? $cm[1] : null,
                'bulletin' => 0,
                'order' => $index,
                'part' => $isPart,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromSteam(): array
    {
        $dir = $this->root() . '/docs/dev';
        $out = [];
        foreach (glob($dir . '/STEAM-CHANGELOG-*.md') ?: [] as $file) {
            if (preg_match('/STEAM-CHANGELOG-(\d{4}-\d{2}-\d{2})-([a-z-]+)-(\d+\.\d+\.\d+)\.md$/', basename($file), $m) !== 1) {
                continue;
            }
            $product = match ($m[2]) {
                'atak' => 'atak',
                'overwatch' => 'overwatch',
                'sse' => 'sse',
                'link' => 'link',
                default => null, // Packs de communauté (ex. FN) : doublons d'une version déjà listée.
            };
            if ($product === null) {
                continue;
            }
            $text = str_replace("\r\n", "\n", (string) @file_get_contents($file));
            $type = null;
            $title = '';
            $summary = '';
            if (preg_match('/\[h2\](.+?)\[\/h2\]\s*\n(.*?)(?=\n\[|\z)/su', $text, $hm) === 1) {
                [$type, $title] = self::splitTypedTitle(self::stripBb($hm[1]));
                $summary = self::summarize(self::stripBb($hm[2]));
            }
            $points = [];
            if (preg_match('/\[list\](.*?)\[\/list\]/su', $text, $lm) === 1) {
                foreach (preg_split('/\[\*\]/', $lm[1]) ?: [] as $item) {
                    $item = self::point(self::stripBb($item));
                    if ($item !== '' && count($points) < self::POINTS_MAX) {
                        $points[] = $item;
                    }
                }
            }
            $out[] = [
                'tokens' => [['product' => $product, 'version' => $m[3]]],
                'date' => $m[1],
                'priority' => 20,
                'title' => self::clean($title),
                'title_en' => '',
                'summary' => $summary,
                'points' => $points,
                'points_en' => [],
                'type' => $type,
                'codename' => preg_match('/\d+\.\d+\.\d+\.[A-Z]{1,3}\s*-\s*([A-Z][a-z]+)/', $text, $cm) === 1 ? $cm[1] : null,
                'bulletin' => 0,
                'order' => null,
                'part' => false,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $updates
     * @return list<array<string, mixed>>
     */
    private function fromUpdates(array $updates): array
    {
        $out = [];
        foreach ($updates as $u) {
            $date = substr((string) ($u['date'] ?? ''), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                continue;
            }
            $title = trim((string) ($u['title'] ?? ''));
            // Versions : ligne « SIZE » du bulletin, sinon son titre (jamais le texte, qui cite d'anciennes versions).
            $tokens = self::tokens((string) ($u['size'] ?? ''), false);
            if ($tokens === []) {
                $tokens = self::tokens($title, false);
            }
            if ($tokens === []) {
                continue;
            }
            $points = [];
            foreach ($u['sections'] ?? [] as $section) {
                foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $item) {
                    if (($item['verb'] ?? '') === 'Added' && count($points) < self::POINTS_MAX) {
                        $p = self::point((string) ($item['text'] ?? ''));
                        if ($p !== '') {
                            $points[] = $p;
                        }
                    }
                }
            }
            $out[] = [
                'tokens' => $tokens,
                'date' => $date,
                'priority' => 15,
                'title' => self::clean($title),
                'title_en' => '',
                'summary' => self::summarize((string) ($u['activity'] ?? '')),
                'points' => $points,
                'points_en' => [],
                'type' => null,
                'codename' => null,
                'bulletin' => (int) ($u['number'] ?? 0),
                'order' => null,
                'part' => false,
            ];
        }

        return $out;
    }

    /**
     * Version actuelle du portail (storage/app_version.json), avec son nom de ville.
     *
     * @return list<array<string, mixed>>
     */
    private function fromAppVersion(): array
    {
        $raw = json_decode($this->read('storage/app_version.json'), true);
        if (!is_array($raw)) {
            return [];
        }
        $version = trim((string) ($raw['version'] ?? ''));
        $date = substr(trim((string) ($raw['updated_at'] ?? '')), 0, 10);
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return [];
        }
        $codename = trim((string) ($raw['codename'] ?? ''));
        if ($codename === '') {
            $codename = PackCodenameCatalog::nameForVersion($version);
        }

        return [[
            'tokens' => [['product' => 'portal', 'version' => $version]],
            'date' => $date,
            'priority' => 0,
            'title' => '',
            'title_en' => '',
            'summary' => '',
            'points' => [],
            'points_en' => [],
            'type' => null,
            'codename' => $codename !== '' ? $codename : null,
            'bulletin' => 0,
            'order' => null,
            'part' => false,
        ]];
    }

    /**
     * Étiquettes produit + version citées dans un texte, dans leur ordre d'apparition (un numéro par produit).
     *
     * @return list<array{product: string, version: string}>
     */
    public static function tokens(string $text, bool $changelogHeading): array
    {
        $patterns = [
            'atak' => ['/\bATAK natif\s+(\d+\.\d+\.\d+)/iu', '/\b(?:COMSPEC ATAK|pack(?: téléphone)?)\s+(\d+\.\d+\.\d+)/iu', '/partie du regroupement\s+(\d+\.\d+\.\d+)/iu'],
            'link' => ['/\b(?:COMSPEC Link|liaison|extension)\s+(\d+\.\d+\.\d+)/iu'],
            'overwatch' => ['/\bOverwatch(?:\s+(?:connect|main))?\s+(\d+\.\d+\.\d+)/iu'],
            'athena' => ['/\bAthena(?:\s+ATAK)?\s+(1\.0\.\d+)\b/u'],
            'sse' => ['/\bSSE\s+(\d+\.\d+\.\d+)/u'],
            'portal' => ['/\bPortail(?:\s+Athena)?\s+(\d+\.\d+(?:\.\d+)?)/iu'],
        ];
        if ($changelogHeading) {
            // Anciennes entrées « [1.5.48] / Overwatch … » : le numéro entre crochets est celui du portail.
            $patterns['portal'][] = '/^\[(\d+\.\d+\.\d+)\]/';
        }
        $found = [];
        foreach ($patterns as $product => $list) {
            foreach ($list as $re) {
                if (preg_match_all($re, $text, $mm, PREG_OFFSET_CAPTURE) > 0) {
                    foreach ($mm[1] as $hit) {
                        $found[] = ['product' => $product, 'version' => (string) $hit[0], 'at' => (int) $hit[1]];
                    }
                }
            }
        }
        usort($found, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        $out = [];
        $seen = [];
        foreach ($found as $f) {
            if (isset($seen[$f['product']])) {
                continue;
            }
            $seen[$f['product']] = true;
            $out[] = ['product' => $f['product'], 'version' => $f['version']];
        }

        return $out;
    }

    /** « 1.6.03 » et « 1.6.3 » désignent la même version. */
    public static function versionKey(string $version): string
    {
        return implode('.', array_map('intval', explode('.', $version)));
    }

    /**
     * Bloc « Résumé joueur » d'une entrée du journal.
     *
     * @return array{type: ?string, fr: string, en: string, points: list<string>, points_en: list<string>}
     */
    private static function playerSummary(string $body): array
    {
        $res = ['type' => null, 'fr' => '', 'en' => '', 'points' => [], 'points_en' => []];
        if (preg_match('/^### Résumé joueur\s*\n(.*?)(?=^### |^---|\z)/msu', $body, $m) !== 1) {
            return $res;
        }
        $lang = '';
        foreach (explode("\n", $m[1]) as $line) {
            $line = trim($line);
            if (preg_match('/^Type\s*:\s*(.+)$/iu', $line, $tm) === 1) {
                $res['type'] = self::typeOf($tm[1]);
            } elseif (preg_match('/^(FR|EN)\s*:\s*(.*)$/u', $line, $lm) === 1) {
                $lang = $lm[1];
                $res[$lang === 'FR' ? 'fr' : 'en'] = self::clean($lm[2]);
            } elseif (str_starts_with($line, '- ') && $lang !== '') {
                $p = self::point(substr($line, 2));
                $k = $lang === 'FR' ? 'points' : 'points_en';
                if ($p !== '' && count($res[$k]) < self::POINTS_MAX) {
                    $res[$k][] = $p;
                }
            }
        }
        if (preg_match(self::JARGON, $res['fr']) === 1) {
            $res['fr'] = '';
        }
        if (preg_match(self::JARGON, $res['en']) === 1) {
            $res['en'] = '';
        }

        return $res;
    }

    /** Titre d'entrée sans numéros de version, dates ni préfixes de vague. */
    private static function headingTitle(string $heading): string
    {
        $t = preg_replace('/\s*\((?:[^()]*\d+\.\d+[^()]*|[^()]*regroupement[^()]*|site|non testé en jeu)\)\s*/iu', ' ', $heading) ?? $heading;
        $t = preg_replace('/^\[[^\]]*\]\s*/u', '', $t) ?? $t;
        $t = preg_replace('/^(?:Pack\s+\d+\.\d+\.\d+\s*:\s*)/iu', '', $t) ?? $t;
        $t = preg_replace('/^(?:(?:Vague|Correctif|Traduction|Réalisme|Retours en jeu|Retours de test)\s+)?20\d{2}-\d{2}-\d{2}(?:\s*\([^)]*\))?\s*—\s*/u', '', $t) ?? $t;
        $t = preg_replace('/(?:^|\s*[—-])\s*20\d{2}-\d{2}-\d{2}\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/(?:\b(?:ATAK natif|COMSPEC Link|Overwatch(?: connect| main)?|Athena(?: ATAK)?|SSE|Pack SSE|Portail|liaison|extension)\s+\d+\.\d+(?:\.\d+)?(?:\.[A-Z]+)?|\s*\/\s*|·)/iu', ' ', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t, " \t-—:/");
        // Ce qui reste d'un titre fait seulement de noms de produits n'est pas un titre.
        if (preg_match('/^(?:(?:Athena|Overwatch|Overwatch Beta|Portail|Pack SSE|SSE|ATAK natif)[\s·\/,]*)*$/iu', $t) === 1) {
            return '';
        }
        if (preg_match(self::JARGON, $t) === 1) {
            return '';
        }

        return self::upperFirst(self::clean($t));
    }

    /**
     * « Correction — Ordres C2 » → ['fix', 'Ordres C2'].
     *
     * @return array{0: ?string, 1: string}
     */
    private static function splitTypedTitle(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^(Nouveau|Nouveauté|Ajouté|Amélioration|Amélioré|Correction|Corrigé|Correctif|Modifié|Performance)\s*[—–:-]\s*(.+)$/u', $text, $m) === 1) {
            $title = trim($m[2]);

            return [self::typeOf($m[1]), preg_match(self::JARGON, $title) === 1 ? '' : $title];
        }

        return [null, preg_match(self::JARGON, $text) === 1 ? '' : $text];
    }

    private static function typeOf(string $word): ?string
    {
        $w = mb_strtolower(trim($word));

        return match (true) {
            str_starts_with($w, 'nouv'), str_starts_with($w, 'ajout') => 'new',
            str_starts_with($w, 'amélior'), str_starts_with($w, 'modifi'), str_starts_with($w, 'rappel') => 'improvement',
            str_starts_with($w, 'correc'), str_starts_with($w, 'corrig') => 'fix',
            str_starts_with($w, 'perf') => 'performance',
            str_starts_with($w, 'patch') => 'fix',
            default => null,
        };
    }

    /** Premier paragraphe lisible d'une entrée (hors titres, listes et consignes d'installation). */
    private static function firstParagraph(string $body): string
    {
        foreach (preg_split('/\n\s*\n/u', $body) ?: [] as $block) {
            $block = trim($block);
            // Le paragraphe s'arrête à la première ligne de liste.
            $block = trim((string) preg_replace('/
\s*(?:-|\*|\d+\.)\s.*$/su', '', $block));
            if ($block === '' || preg_match('/^(?:#|-|\*|\||```|>|\d+\.)/u', $block) === 1) {
                continue;
            }
            $s = self::summarize($block);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    /** @return list<string> */
    private static function listItems(string $body): array
    {
        // Seulement la partie joueur : la liste d'un « Détail technique » ne sort jamais.
        $body = preg_split('/^### Détail technique/mu', $body)[0] ?? '';
        $out = [];
        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^- (.+)$/u', trim($line), $m) === 1) {
                $p = self::point($m[1]);
                if ($p !== '') {
                    $out[] = $p;
                }
                if (count($out) >= self::POINTS_MAX) {
                    break;
                }
            }
        }

        return $out;
    }

    /** Une à deux phrases lisibles, sans consigne d'installation ni terme d'atelier, coupées proprement. */
    private static function summarize(string $text): string
    {
        $text = self::clean($text);
        if ($text === '') {
            return '';
        }
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-ZÀ-ÖØ-Ý«])/u', $text) ?: [];
        $kept = [];
        foreach ($sentences as $s) {
            $s = trim($s);
            if ($s === '' || preg_match(self::JARGON, $s) === 1 || preg_match(self::INSTALL_HINT, $s) === 1) {
                continue;
            }
            $kept[] = $s;
            if (mb_strlen(implode(' ', $kept)) > 140) {
                break;
            }
        }

        return self::cut(implode(' ', $kept), self::SUMMARY_MAX);
    }

    private static function point(string $text): string
    {
        $text = preg_replace('/[\s.(]*non testé en jeu\)?/iu', '', self::clean($text)) ?? $text;
        $text = rtrim($text, " ;.,");
        if ($text === '' || mb_strlen($text) < 4 || preg_match(self::JARGON, $text) === 1 || preg_match(self::INSTALL_HINT, $text) === 1) {
            return '';
        }

        return self::upperFirst(self::cut($text, 140));
    }

    private static function stripBb(string $text): string
    {
        return trim(preg_replace('/\[\/?[a-z0-9*]+(?:=[^\]]*)?\]/iu', '', $text) ?? $text);
    }

    /** Retire la mise en forme (gras, liens, code) et normalise les espaces. */
    private static function clean(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text) ?? $text;
        $text = str_replace(['**', '__'], '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function cut(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $max * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " ,;:—-") . '…';
    }

    private static function upperFirst(string $text): string
    {
        return $text === '' ? '' : mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    /** @return list<array<string, mixed>> */
    private function updates(): array
    {
        if ($this->updatesProvider !== null) {
            return array_values(($this->updatesProvider)());
        }
        try {
            return array_values(array_filter(
                DevDispatchCatalog::all(),
                static fn (array $row): bool => ($row['kind'] ?? '') === DevDispatchCatalog::KIND_UPDATE
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Empreinte des sources : le cache est recalculé dès qu'une d'elles change. */
    private function signature(): string
    {
        $files = [
            $this->root() . '/CHANGELOG-ATAK.md',
            $this->root() . '/storage/app_version.json',
            ...(glob($this->root() . '/docs/dev/STEAM-CHANGELOG-*.md') ?: []),
        ];
        if ($this->updatesProvider === null) {
            // Chemin direct : pas de chargement du catalogue tant que le cache est valable.
            $files[] = $this->root() . '/app/Support/DevDispatchCatalog.php';
        }
        $bits = [self::CACHE_FORMAT];
        foreach ($files as $f) {
            $bits[] = basename($f) . ':' . (is_file($f) ? filesize($f) . '@' . filemtime($f) : '-');
        }
        if ($this->updatesProvider !== null) {
            $bits[] = md5((string) json_encode(($this->updatesProvider)()));
        }

        return md5(implode('|', $bits));
    }

    private function read(string $relative): string
    {
        $path = $this->root() . '/' . $relative;

        return is_file($path) ? (string) @file_get_contents($path) : '';
    }

    private function root(): string
    {
        return rtrim($this->rootDir ?? base_path(''), '/\\');
    }

    private function cachePath(): string
    {
        $dir = $this->cacheDir ?? base_path('storage/cache');

        return rtrim($dir, '/\\') . '/public_history_releases.json';
    }
}
