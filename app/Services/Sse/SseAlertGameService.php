<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAlertRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseFileVisibility;
use App\Support\SseSheetText;
use App\Support\SseUsClassification;
use App\Support\SseWantedCatalog as C;
use App\Support\SseSheetCatalog;
use App\Support\SseWantedCodes;
use App\Support\SseWantedPhysical;
use App\Support\SseWantedServices;

/**
 * Alertes SSE lues par le téléphone ATAK en jeu (ICD-SSE-ALERTES-001, docs/technical/ICD-SSE-ALERTES.md) : catalogue (niveaux,
 * types, couleurs de notice, motifs, mesures, services, délais), liste des avis de recherche actifs et des fiches de vigilance en
 * cours, détail d'une alerte (lisible 30 jours après sa levée ou son échéance).
 *
 * Ce qui part au jeu est filtré comme pour « sse_fiches » (SseFichesGameService), jamais seulement masqué à l'écran :
 *  - une fiche de vigilance n'est donnée qu'à un porteur dont l'habilitation atteint sa classification d'émission ; un avis de
 *    recherche est un document de diffusion (niveau le plus bas), jamais donné tant qu'il attend sa validation ;
 *  - une identité de MEMBRE (sse_identities.user_id > 0) n'est jamais donnée ;
 *  - une identité cachée devient une barre de caviardage ; les liens suivent l'habilitation ;
 *  - ni image, ni état civil.
 * Une alerte que le porteur ne peut pas lire n'existe pas pour lui : find() rend null, comme pour une référence inconnue.
 *
 * Deux formes pour le même contenu : JSON (routes /api/game/v1/sse/alertes) et pages parseSimpleArray de
 * SseFichesGameService::PAGE_BYTES au plus (kind « sse_alertes » de POST /api/sse/identity/checks).
 */
final class SseAlertGameService
{
    public const VERSION = 1;
    public const KIND = 'sse_alertes';
    public const TTL = 300;
    public const MAX_ALERTS = 80;
    public const MAX_PAGES = 14;
    public const MAX_CATALOG_PAGES = 40;
    public const MAX_LINKS = 3;
    /** Une alerte levée ou expirée reste lisible en détail pendant ce nombre de jours. */
    public const CLOSED_DAYS = 30;

    /** Colonnes d'une alerte, dans l'ordre des tableaux de la forme SQF (ICD § 3.2). */
    public const COLUMNS = [
        'ref', 'doc', 'type', 'color', 'rank', 'level', 'order', 'name', 'alias', 'threat', 'armed', 'conduct', 'reason', 'zone', 'place',
        'issued', 'until', 'soon', 'service', 'svc', 'channel', 'delay', 'url', 'token', 'state', 'acked', 'updated', 'cls', 'mark', 'cut',
    ];

    /** Colonnes booléennes. */
    private const FLAGS = ['soon', 'acked', 'cut'];

    /** Liste : motif court, conduite à tenir en 6 lignes de 90 caractères. Détail : textes longs. */
    public const SHORT = ['reason' => 260, 'lines' => 6, 'line' => 90];
    public const FULL = ['reason' => 800, 'lines' => 10, 'line' => 200];

    /** Sections du catalogue et leurs colonnes (après le nom de la section). */
    public const CATALOG = [
        'level' => ['key', 'fr', 'en', 'rank', 'hex', 'shape'],
        'type' => ['key', 'fr', 'en', 'color', 'title_fr', 'title_en', 'subtitle_fr', 'subtitle_en'],
        'color' => ['key', 'fr', 'en', 'hex'],
        'reason' => ['code', 'fr', 'en', 'family', 'threat'],
        'measure' => ['code', 'fr', 'en', 'family', 'threat'],
        'service' => ['code', 'fr', 'en', 'ac', 'hex', 'shape', 'emblem_ref', 'default', 'channel'],
        'delay' => ['key', 'fr', 'en'],
        'validity' => ['key', 'fr', 'en', 'days', 'hours'],
        'sure' => ['key', 'fr', 'en'],
        'threat' => ['key', 'fr', 'en'],
        'armed' => ['key', 'fr', 'en'],
    ];

    private Database $db;

    private SseFichesGameService $fiches;

    private SseAlertService $alerts;

    /**
     * @param (\Closure(int, int): string)|null $levelOf plafond de lecture SSE du compte relié en jeu (règle de SseMemberClearance)
     */
    public function __construct(?Database $db = null, ?\Closure $levelOf = null, ?SseAlertService $alerts = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->fiches = new SseFichesGameService($this->db, $levelOf);
        $this->alerts = $alerts ?? new SseAlertService();
    }

    /** Niveau de lecture du porteur : celui de son compte ; sans compte, le plus bas (règle de « sse_fiches »). */
    public function levelFor(int $tenantId, ?int $userId): string
    {
        return $this->fiches->levelFor($tenantId, $userId);
    }

    public function alerts(): SseAlertService
    {
        return $this->alerts;
    }

    // ------------------------------------------------------------------ Catalogue

    /**
     * Catalogue de la communauté : lignes par section (CATALOG) et révision (empreinte du contenu, sans les droits du compte).
     *
     * @return array{version: int, revision: string, rows: list<list<mixed>>}
     */
    public function catalog(int $tenantId): array
    {
        $config = $this->settings($tenantId);
        $rows = [];
        foreach (SseAlertLevel::LEVELS as $key => $l) {
            $rows[] = ['level', $key, $l['title'][0], $l['title'][1], $l['rank'], SseAlertLevel::hex($key), $l['shape']];
        }
        foreach (C::types($config) as $key => $labels) {
            $color = SseAlertLevel::noticeColor((string) $key, $config);
            $banner = C::BANNERS[(string) ($config['banner'] ?? 'red')] ?? C::BANNERS['wanted'];
            $title = $color !== null ? $color['title'] : $banner;
            $subtitle = $color !== null ? $color['subtitle'] : [mb_strtoupper((string) $labels[0], 'UTF-8'), mb_strtoupper((string) $labels[1], 'UTF-8')];
            $rows[] = ['type', (string) $key, (string) $labels[0], (string) $labels[1], $color['key'] ?? '', $title[0], $title[1], $subtitle[0], $subtitle[1]];
        }
        foreach (SseAlertLevel::NOTICE_COLORS as $key => $c) {
            $rows[] = ['color', $key, $c['label'][0], $c['label'][1], SseAlertLevel::noticeHex($key)];
        }
        foreach ([SseWantedCodes::REASONS => 'reason', SseWantedCodes::MEASURES => 'measure'] as $kind => $section) {
            foreach (SseWantedCodes::active($config, $kind) as $code => $c) {
                $rows[] = [$section, (string) $code, self::line($c['fr'] ?? '', 90), self::line($c['en'] ?? '', 90), self::line($c['family'] ?? '', 12), isset(C::THREATS[(string) ($c['threat'] ?? '')]) ? (string) $c['threat'] : ''];
            }
        }
        $default = SseWantedServices::defaultCode($config);
        foreach (SseWantedServices::available($config) as $code => $svc) {
            $r = SseAlertReportTo::resolve($config, ['service' => (string) $code])['report'];
            $rows[] = ['service', (string) $code, self::line($r['fr'], 120), self::line($r['en'], 120), self::line($r['ac'], 16), (string) $r['color'], self::line($r['emblem'], 12), self::line($r['emblem_ref'] ?? '', 60), (string) $code === $default, self::line($r['channel'], 200)];
        }
        // Durées de validité (additif) : jours, durées courtes en heures et « jusqu'à levée » (sans échéance).
        foreach (SseSheetCatalog::VALIDITY_HOURS as $h) {
            $rows[] = ['validity', 'h' . $h, $h . ' h', $h . ' h', 0, $h];
        }
        foreach (SseSheetCatalog::VALIDITY_DAYS as $d) {
            $rows[] = ['validity', 'd' . $d, $d . ' jours', $d . ' days', $d, 0];
        }
        $rows[] = ['validity', 'until_lifted', 'Jusqu’à levée', 'Until lifted', 0, 0];
        foreach (['delay' => SseAlertLevel::DELAYS, 'sure' => SseAlertLevel::CERTAINTY, 'threat' => C::THREATS, 'armed' => C::ARMED] as $section => $map) {
            foreach ($map as $key => $labels) {
                $rows[] = [$section, (string) $key, (string) $labels[0], (string) $labels[1]];
            }
        }

        return [
            'version' => self::VERSION,
            'revision' => substr(sha1((string) json_encode([self::VERSION, 'catalog', $rows], JSON_UNESCAPED_UNICODE)), 0, 16),
            'rows' => $rows,
        ];
    }

    /**
     * Le catalogue en objet JSON : une liste d'objets par section.
     *
     * @param array{version: int, revision: string, rows: list<list<mixed>>} $catalog
     * @return array<string, list<array<string, mixed>>>
     */
    public static function catalogSections(array $catalog): array
    {
        $out = array_fill_keys(array_keys(self::CATALOG), []);
        foreach ($catalog['rows'] as $row) {
            $section = (string) ($row[0] ?? '');
            if (isset(self::CATALOG[$section])) {
                $out[$section][] = array_combine(self::CATALOG[$section], array_slice($row, 1, count(self::CATALOG[$section])));
            }
        }

        return $out;
    }

    /**
     * Pages parseSimpleArray du catalogue : [1, "catalog", "révision", page, page suivante (0 = fin), [ligne…], [droits…]].
     *
     * @param array{version: int, revision: string, rows: list<list<mixed>>} $catalog
     * @param array{0: bool, 1: bool, 2: bool, 3: bool} $rights peut émettre une vigilance, placer un avis, lever, signaler
     * @return list<string>
     */
    public static function catalogPages(array $catalog, array $rights): array
    {
        $tail = SseLoreService::sqf(array_map('boolval', array_values($rights)));
        $head = 64 + strlen($tail);
        $pages = [[]];
        $size = $head;
        foreach ($catalog['rows'] as $row) {
            $text = SseLoreService::sqf($row);
            $len = strlen($text) + 1;
            if ($len + $head > SseFichesGameService::PAGE_BYTES) {
                continue;
            }
            if ($size + $len > SseFichesGameService::PAGE_BYTES) {
                if (count($pages) >= self::MAX_CATALOG_PAGES) {
                    break;
                }
                $pages[] = [];
                $size = $head;
            }
            $pages[count($pages) - 1][] = $text;
            $size += $len;
        }
        $out = [];
        $count = count($pages);
        foreach ($pages as $i => $rows) {
            $out[] = '[' . self::VERSION . ',"catalog",' . SseLoreService::sqf((string) $catalog['revision']) . ',' . $i . ',' . ($i + 1 < $count ? $i + 1 : 0)
                . ',[' . implode(',', $rows) . '],' . $tail . ']';
        }

        return $out;
    }

    /** @param array{0: bool, 1: bool, 2: bool, 3: bool} $rights */
    public static function catalogUnchangedPage(string $revision, array $rights): string
    {
        return '[' . self::VERSION . ',"catalog",' . SseLoreService::sqf($revision) . ',0,0,[],' . SseLoreService::sqf(array_map('boolval', array_values($rights))) . ',true]';
    }

    // ---------------------------------------------------------------------- Liste

    /**
     * Le paquet des alertes en cours lisibles à ce niveau : avis de recherche actifs et diffusés, fiches de vigilance ni levées ni
     * expirées (toutes celles d'un même sujet). Les plus graves d'abord, puis les plus récentes ; MAX_ALERTS au plus.
     *
     * La révision est celle de CE lecteur : elle porte ses accusés de lecture (colonne « acked »).
     *
     * @param string $doc « avis », « vigilance » ou vide (les deux)
     * @return array{version: int, revision: string, generated_at: string, ttl: int, level: string, mark: string, lang: string,
     *               alerts: list<array<string, mixed>>, total: int, truncated: bool}
     */
    public function build(int $tenantId, string $viewerLevel, ?int $userId = null, string $lang = 'fr', string $doc = ''): array
    {
        $viewerLevel = self::level($viewerLevel);
        $lang = $lang === 'en' ? 'en' : 'fr';
        $doc = in_array($doc, SseAlertRepository::DOCS, true) ? $doc : '';
        $all = [];
        if ($doc !== SseAlertRepository::DOC_VIGILANCE) {
            $all = array_merge($all, $this->notices($tenantId, $viewerLevel, $lang, false));
        }
        if ($doc !== SseAlertRepository::DOC_NOTICE) {
            $all = array_merge($all, $this->vigilances($tenantId, $viewerLevel, $lang, false));
        }
        $all = array_values(array_filter($all, static fn (array $a): bool => $a['state'] === 'active'));
        usort($all, static function (array $a, array $b): int {
            return [(int) $b['rank'], (string) $b['updated'], (string) $a['ref']] <=> [(int) $a['rank'], (string) $a['updated'], (string) $b['ref']];
        });
        $total = count($all);
        $alerts = $this->withLinks($tenantId, array_slice($all, 0, self::MAX_ALERTS), $userId, true);
        $cells = array_map([self::class, 'cells'], $alerts);

        return [
            'version' => self::VERSION,
            'revision' => substr(sha1((string) json_encode([self::VERSION, 'list', $viewerLevel, $lang, $doc, $cells, $total], JSON_UNESCAPED_UNICODE)), 0, 16),
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'ttl' => self::TTL,
            'level' => $viewerLevel,
            'mark' => SseUsClassification::banner($viewerLevel),
            'lang' => $lang,
            'alerts' => $alerts,
            'total' => $total,
            'truncated' => $total > count($alerts),
        ];
    }

    /**
     * Une alerte en entier (textes longs, motifs, liens, signalement, levée), ou null : inconnue, d'une autre communauté, au-dessus
     * de l'habilitation du porteur, en attente de validation, close depuis plus de CLOSED_DAYS jours, ou celle d'un membre.
     *
     * Les clés qui commencent par « _ » sont internes (identifiant, lien, émetteur) : elles ne partent jamais au jeu.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, string $viewerLevel, ?int $userId, string $ref, string $lang = 'fr'): ?array
    {
        $ref = self::line($ref, 40);
        if ($ref === '' || $tenantId < 1) {
            return null;
        }

        return $this->one($tenantId, $viewerLevel, $userId, $lang, ['ref' => $ref]);
    }

    /** La même alerte, retrouvée par son jeton de signalement (jeton d'une autre communauté ou révoqué : null). */
    public function findByToken(int $tenantId, string $viewerLevel, ?int $userId, string $token, string $lang = 'fr'): ?array
    {
        if ($tenantId < 1 || !SseAlertRepository::ready()) {
            return null;
        }
        try {
            $link = $this->alerts->alerts()->linkByToken($tenantId, $token);
        } catch (\Throwable) {
            $link = null;
        }
        if ($link === null || $link['revoked_at'] !== null) {
            return null;
        }

        return $this->findByTarget($tenantId, $viewerLevel, $userId, (string) $link['doc'], (int) $link['target_id'], $lang);
    }

    /** La même alerte, retrouvée par son genre et son identifiant (réponse rejouée d'une création ou d'un renouvellement). */
    public function findByTarget(int $tenantId, string $viewerLevel, ?int $userId, string $doc, int $targetId, string $lang = 'fr'): ?array
    {
        if ($tenantId < 1 || $targetId < 1 || !in_array($doc, SseAlertRepository::DOCS, true)) {
            return null;
        }

        return $this->one($tenantId, $viewerLevel, $userId, $lang, ['doc' => $doc, 'id' => $targetId]);
    }

    /**
     * Lien de signalement de l'alerte, créé au premier besoin (accusé de lecture, signalement). Null sans l'étape de base 4830.
     *
     * @param array<string, mixed> $alert alerte de find()
     * @return array<string, mixed>|null
     */
    public function linkOf(int $tenantId, array $alert): ?array
    {
        if (!SseAlertRepository::ready()) {
            return null;
        }
        if (is_array($alert['_link'] ?? null)) {
            return $alert['_link'];
        }
        try {
            if ($alert['doc'] === SseAlertRepository::DOC_NOTICE) {
                return is_array($alert['_notice'] ?? null) ? $this->alerts->noticeLink($tenantId, $alert['_notice']) : null;
            }
            $view = is_array($alert['_view'] ?? null) ? $alert['_view'] : [];

            // Jeton déjà imprimé sur le PDF : repris tel quel.
            return $this->alerts->alerts()->ensureLink($tenantId, SseAlertRepository::DOC_VIGILANCE, (int) $alert['_id'], [
                'token' => (string) ($view['token'] ?? ''), 'valid_until' => (string) ($view['until'] ?? ''), 'report' => $view['report'] ?? null,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{ref?: string, doc?: string, id?: int} $by
     * @return array<string, mixed>|null
     */
    private function one(int $tenantId, string $viewerLevel, ?int $userId, string $lang, array $by): ?array
    {
        $viewerLevel = self::level($viewerLevel);
        $lang = $lang === 'en' ? 'en' : 'fr';
        $doc = (string) ($by['doc'] ?? '');
        $found = [];
        if ($doc !== SseAlertRepository::DOC_VIGILANCE) {
            $found = array_merge($found, $this->notices($tenantId, $viewerLevel, $lang, true, $by));
        }
        if ($doc !== SseAlertRepository::DOC_NOTICE) {
            $found = array_merge($found, $this->vigilances($tenantId, $viewerLevel, $lang, true, $by));
        }
        $limit = gmdate('Y-m-d H:i:s', time() - self::CLOSED_DAYS * 86400);
        foreach ($found as $a) {
            if (isset($by['ref']) && (string) $a['ref'] !== $by['ref']) {
                continue;
            }
            if ($a['state'] !== 'active' && (string) $a['_closed'] < $limit) {
                continue;
            }

            return $this->withLinks($tenantId, [$a], $userId, $a['state'] === 'active')[0];
        }

        return null;
    }

    // ------------------------------------------------------------ Pages parseSimpleArray

    /**
     * Le paquet découpé en pages parseSimpleArray :
     * [1, "list", "révision", "généré le", ttl, page, page suivante (0 = fin), [alerte…], [niveau de lecture, marquage, total, tronqué]].
     * Une alerte est le tableau des colonnes de COLUMNS, dans l'ordre.
     *
     * @param array<string, mixed> $pkg
     * @return list<string>
     */
    public static function pages(array $pkg): array
    {
        $meta = SseLoreService::sqf([(string) $pkg['level'], (string) $pkg['mark'], (int) $pkg['total'], (bool) $pkg['truncated']]);
        $headRoom = 104 + strlen($meta);
        /** @var list<list<string>> $pages */
        $pages = [[]];
        $size = $headRoom;
        foreach ((array) ($pkg['alerts'] ?? []) as $a) {
            $text = SseLoreService::sqf(self::cells($a));
            $len = strlen($text) + 1;
            if ($len + $headRoom > SseFichesGameService::PAGE_BYTES) {
                $text = SseLoreService::sqf(self::cells(self::shrink($a)));
                $len = strlen($text) + 1;
                if ($len + $headRoom > SseFichesGameService::PAGE_BYTES) {
                    continue;
                }
            }
            if ($size + $len > SseFichesGameService::PAGE_BYTES) {
                if (count($pages) >= self::MAX_PAGES) {
                    break;
                }
                $pages[] = [];
                $size = $headRoom;
            }
            $pages[count($pages) - 1][] = $text;
            $size += $len;
        }
        $out = [];
        $count = count($pages);
        foreach ($pages as $i => $rows) {
            $out[] = '[' . self::VERSION . ',"list",' . SseLoreService::sqf((string) $pkg['revision']) . ',' . SseLoreService::sqf((string) $pkg['generated_at'])
                . ',' . (int) ($pkg['ttl'] ?? self::TTL) . ',' . $i . ',' . ($i + 1 < $count ? $i + 1 : 0)
                . ',[' . implode(',', $rows) . '],' . $meta . ']';
        }

        return $out;
    }

    /** Réponse « révision déjà connue » : même en-tête, aucune alerte, drapeau final à true. */
    public static function unchangedPage(string $revision): string
    {
        return '[' . self::VERSION . ',"list",' . SseLoreService::sqf($revision) . ',' . SseLoreService::sqf(gmdate('Y-m-d\TH:i:s\Z'))
            . ',' . self::TTL . ',0,0,[],[],true]';
    }

    /**
     * Détail : [1, "detail", [alerte…], [motifs…], [véhicules…], [personnes liées…], signalement, nombre de signalements, levée le,
     * motif de levée, [à ne pas faire…]]. Toujours une seule page (textes ramenés au plus court s'il le faut).
     *
     * @param array<string, mixed> $alert alerte de find()
     */
    public static function detailPage(array $alert): string
    {
        $page = '';
        foreach ([$alert, self::shrink($alert), self::shrink($alert, true)] as $a) {
            $d = self::detail($a);
            $page = SseLoreService::sqf([
                self::VERSION, 'detail', self::cells($a), $d['reasons'], $d['vehicles'], $d['people'], $d['physical'], $d['reports'],
                $d['lifted_at'], $d['lift_reason'], $d['donts'],
            ]);
            if (strlen($page) <= SseFichesGameService::PAGE_BYTES) {
                break;
            }
        }

        return $page;
    }

    /**
     * Ce que le détail ajoute aux colonnes : motifs codifiés, liens, signalement physique, nombre de signalements, levée.
     *
     * @param array<string, mixed> $alert
     * @return array{reasons: list<string>, vehicles: list<string>, people: list<string>, physical: string, reports: int, lifted_at: string, lift_reason: string, donts: list<string>}
     */
    public static function detail(array $alert): array
    {
        $x = is_array($alert['_detail'] ?? null) ? $alert['_detail'] : [];
        $list = static fn (string $k): array => array_values(array_map('strval', is_array($x[$k] ?? null) ? $x[$k] : []));

        return [
            'reasons' => $list('reasons'), 'vehicles' => $list('vehicles'), 'people' => $list('people'), 'physical' => (string) ($x['physical'] ?? ''),
            'reports' => (int) ($x['reports'] ?? 0), 'lifted_at' => (string) ($x['lifted_at'] ?? ''), 'lift_reason' => (string) ($x['lift_reason'] ?? ''),
            'donts' => $list('donts'),
        ];
    }

    /** Identifiant numérique d'une révision (champ « id » attendu par COMSPEC Link dans « OK|id|ref »). */
    public static function revisionId(string $revision): int
    {
        return SseFichesGameService::revisionId($revision);
    }

    /**
     * @param array<string, mixed> $a
     * @return list<mixed>
     */
    public static function cells(array $a): array
    {
        $out = [];
        foreach (self::COLUMNS as $c) {
            $v = $a[$c] ?? '';
            $out[] = match (true) {
                in_array($c, self::FLAGS, true) => (bool) $v,
                $c === 'rank' => (int) $v,
                is_array($v) => array_values($v),
                default => (string) $v,
            };
        }

        return $out;
    }

    /**
     * L'alerte telle qu'elle part en JSON : ses colonnes seulement (aucune clé interne).
     *
     * @param array<string, mixed> $a
     * @return array<string, mixed>
     */
    public static function publicAlert(array $a): array
    {
        return array_combine(self::COLUMNS, self::cells($a));
    }

    /**
     * @param array<string, mixed> $a
     * @return array<string, mixed>
     */
    private static function shrink(array $a, bool $hard = false): array
    {
        $a['reason'] = self::cut((string) ($a['reason'] ?? ''), $hard ? 120 : (int) (self::SHORT['reason'] / 2));
        $a['conduct'] = array_map(static fn (string $l): string => self::cut($l, self::SHORT['line']), array_slice(array_values((array) ($a['conduct'] ?? [])), 0, $hard ? 3 : self::SHORT['lines']));
        if (is_array($a['_detail'] ?? null)) {
            foreach (['reasons', 'vehicles', 'people', 'donts'] as $k) {
                $a['_detail'][$k] = array_slice((array) ($a['_detail'][$k] ?? []), 0, $hard ? 1 : 3);
            }
            $a['_detail']['physical'] = self::cut((string) ($a['_detail']['physical'] ?? ''), $hard ? 60 : 120);
            $a['_detail']['lift_reason'] = self::cut((string) ($a['_detail']['lift_reason'] ?? ''), $hard ? 80 : 200);
        }
        $a['cut'] = true;

        return $a;
    }

    // ------------------------------------------------------------ Avis de recherche

    /**
     * @param array{ref?: string, doc?: string, id?: int} $by vide : les avis actifs ; sinon l'avis de cette référence ou de cet identifiant
     * @return list<array<string, mixed>>
     */
    private function notices(int $tenantId, string $viewerLevel, string $lang, bool $full, array $by = []): array
    {
        $sql = 'SELECT n.*, i.last_name AS identity_last_name, i.first_name AS identity_first_name, i.alias AS identity_alias, i.user_id AS identity_user_id
                FROM sse_wanted_notices n
                LEFT JOIN sse_identities i ON i.id = n.identity_id AND i.tenant_id = n.tenant_id
                WHERE n.tenant_id = :t';
        $params = ['t' => $tenantId];
        if (isset($by['ref'])) {
            $sql .= ' AND n.ref = :ref';
            $params['ref'] = $by['ref'];
        } elseif (isset($by['id'])) {
            $sql .= ' AND n.id = :id';
            $params['id'] = $by['id'];
        } else {
            $sql .= ' AND n.status = \'active\' AND (n.expires_at IS NULL OR n.expires_at > :now)';
            $params['now'] = gmdate('Y-m-d H:i:s');
        }
        $rows = [];
        foreach ($this->safeFetch($sql . ' ORDER BY n.id DESC LIMIT 200', $params) as $r) {
            // Identité de membre, ou identité introuvable (jointure vide) : écartée (fail-closed).
            $orphan = ($r['identity_user_id'] ?? null) === null && ($r['identity_last_name'] ?? null) === null && ($r['identity_first_name'] ?? null) === null;
            if ($orphan || (int) ($r['identity_user_id'] ?? 0) > 0) {
                continue;
            }
            $rows[] = $r;
        }
        if ($rows === []) {
            return [];
        }
        $noticeIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        // Un avis proposé (circuit de validation) ou refusé n'a jamais été diffusé : il n'existe pas pour le jeu.
        $meta = [];
        try {
            $meta = SseWantedMetaRepository::ready() ? (new SseWantedMetaRepository($this->db))->forNotices($tenantId, $noticeIds) : [];
        } catch (\Throwable) {
            $meta = [];
        }
        $codes = [];
        try {
            $codes = SseWantedExtrasRepository::ready() ? (new SseWantedExtrasRepository($this->db))->forNotices($tenantId, $noticeIds) : [];
        } catch (\Throwable) {
            $codes = [];
        }
        $identityIds = array_map(static fn (array $r): int => (int) $r['identity_id'], $rows);
        $records = $this->records($tenantId, $identityIds);
        $linked = [];
        foreach ($records['links'] as $byIdentity) {
            foreach ($byIdentity as $l) {
                if ((int) ($l['linked_identity_id'] ?? 0) > 0) {
                    $linked[] = (int) $l['linked_identity_id'];
                }
            }
        }
        $memberIds = $this->memberIdentities($tenantId, $linked);
        $config = $this->settings($tenantId);
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (($meta[$id]['state'] ?? SseWantedMetaRepository::LIVE) !== SseWantedMetaRepository::LIVE) {
                continue;
            }
            $n = SseWantedNoticeRepository::hydrate($r);
            $iid = (int) $n['identity_id'];
            $profile = $records['profiles'][$iid] ?? null;
            $hidden = $profile !== null && !SseFileVisibility::field($profile, 'identity', SseFileVisibility::NO_SESSION_PHASE);
            $name = (string) $n['identity_name'];
            $aliases = [];
            if (!$hidden) {
                if (is_string($r['identity_alias'] ?? null) && trim($r['identity_alias']) !== '') {
                    $aliases[] = trim($r['identity_alias']);
                }
                foreach ($records['aliases'][$iid] ?? [] as $a) {
                    if (!in_array((string) ($a['kind'] ?? ''), ['legal', 'registry'], true) && SseFileVisibility::row($a, SseFileVisibility::NO_SESSION_PHASE) && trim((string) ($a['alias'] ?? '')) !== '') {
                        $aliases[] = trim((string) $a['alias']);
                    }
                }
            }
            $links = $hidden ? ['vehicle' => [], 'associate' => []] : $this->links($records['links'][$iid] ?? [], $viewerLevel, $memberIds);
            $x = $codes[$id] ?? ['codes' => ['reasons' => [], 'measures' => []], 'physical' => []];
            $reasons = (array) ($x['codes']['reasons'] ?? []);
            $measures = (array) ($x['codes']['measures'] ?? []);
            $measureCodes = array_values(array_map('strval', array_column($measures, 'code')));
            $status = (string) $n['status'];
            $state = $status === 'active' ? 'active' : ($status === 'expired' ? 'expired' : 'lifted');
            $closed = $state === 'active' ? '' : (string) ($n['closed_at'] ?? ($n['expires_at'] ?? ''));
            $color = SseAlertLevel::noticeColor((string) $n['notice_type'], $config);
            $a = $this->alert([
                'ref' => $n['ref'], 'doc' => SseAlertRepository::DOC_NOTICE, 'type' => $n['notice_type'], 'color' => $color['key'] ?? '',
                'threat' => $n['threat_level'], 'armed' => $n['armed'], 'order' => SseAlertLevel::order($measureCodes),
                'name' => $hidden ? SseRedactionService::bar($name) : $name, 'alias' => implode(', ', array_slice(array_values(array_unique($aliases)), 0, 3)),
                'codes' => self::coded($measures, $lang), 'instructions' => self::free((string) ($n['instructions'] ?? ''), $measureCodes, SseWantedCodes::MEASURES, $config),
                'reasons' => self::coded($reasons, $lang), 'reason' => self::free((string) ($n['reason'] ?? ''), array_values(array_map('strval', array_column($reasons, 'code'))), SseWantedCodes::REASONS, $config),
                'zone' => $n['zone'] ?? '', 'place' => $n['last_seen_place'] ?? '', 'issued' => $n['issued_at'], 'until' => $n['expires_at'] ?? '',
                'soon' => $state === 'active' && SseAlertLevel::perishable($n['expires_at']), 'state' => $state,
                'updated' => ($r['updated_at'] ?? '') ?: ($r['created_at'] ?? ''), 'cls' => SseFichesGameService::NOTICE_LEVEL,
            ], $full, $lang);
            $a['_id'] = $id;
            $a['_identity'] = $iid;
            $a['_closed'] = $closed;
            $a['_issuer'] = $n['created_by_user_id'];
            $a['_notice'] = $n;
            $a['_measures'] = $measureCodes;
            $a['_detail'] = [
                'reasons' => $a['_reasons'], 'vehicles' => self::labels($links['vehicle']), 'people' => self::labels($links['associate']),
                'physical' => $hidden ? '' : self::line(SseWantedPhysical::shortLine((array) ($x['physical'] ?? []), $lang), 200),
                'reports' => 0, 'lifted_at' => $state === 'lifted' ? self::stamp($n['closed_at'] ?? '') : '',
                'lift_reason' => $state === 'active' ? '' : self::line($n['closed_reason'] ?? '', 500), 'donts' => SseAlertLevel::donts($measureCodes, $lang),
            ];
            $out[] = $a;
        }

        return $out;
    }

    // ---------------------------------------------------------- Fiches de vigilance

    /**
     * Fiches de vigilance émises, à leur classification d'émission (leur texte a été caviardé à ce niveau). Toutes celles d'un même
     * sujet sont gardées : deux vigilances en cours valent deux alertes.
     *
     * @param array{ref?: string, doc?: string, id?: int} $by
     * @return list<array<string, mixed>>
     */
    private function vigilances(int $tenantId, string $viewerLevel, string $lang, bool $full, array $by = []): array
    {
        $sql = 'SELECT d.*, i.user_id AS identity_user_id
                FROM sse_doc_issues d
                LEFT JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
                WHERE d.tenant_id = :t AND d.model = \'vigilance\'';
        $params = ['t' => $tenantId];
        if (isset($by['ref'])) {
            $sql .= ' AND d.ref = :ref';
            $params['ref'] = $by['ref'];
        } elseif (isset($by['id'])) {
            $sql .= ' AND d.id = :id';
            $params['id'] = $by['id'];
        }
        $rows = [];
        foreach ($this->safeFetch($sql . ' ORDER BY d.id DESC LIMIT 400', $params) as $r) {
            if ((int) ($r['identity_user_id'] ?? 0) > 0) {
                continue;
            }
            $cls = isset(SseRedactionService::LEVELS[(string) $r['classification']]) ? (string) $r['classification'] : SseCaseRepository::CLASS_RESTRICTED;
            if (SseRedactionService::levelRank($cls) > SseRedactionService::levelRank($viewerLevel)) {
                continue;
            }
            $r['classification'] = $cls;
            $rows[] = $r;
        }
        if ($rows === []) {
            return [];
        }
        $links = [];
        try {
            $links = $this->alerts->alerts()->linksFor($tenantId, SseAlertRepository::DOC_VIGILANCE, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        } catch (\Throwable) {
            $links = [];
        }
        $today = gmdate('Y-m-d');
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $texts = json_decode((string) ($r['texts_json'] ?? ''), true);
            $snap = json_decode((string) ($r['snapshot_json'] ?? ''), true);
            $issue = [
                'id' => $id, 'ref' => (string) $r['ref'], 'lang' => (string) ($r['lang'] ?? 'fr'), 'classification' => (string) $r['classification'],
                'person_id' => ($r['person_id'] ?? null) !== null ? (int) $r['person_id'] : null, 'identity_id' => ($r['identity_id'] ?? null) !== null ? (int) $r['identity_id'] : null,
                'texts' => is_array($texts) ? $texts : [], 'snapshot' => is_array($snap) ? $snap : [],
                'issued_by_user_id' => ($r['issued_by_user_id'] ?? null) !== null ? (int) $r['issued_by_user_id'] : null,
                'issued_by_label' => (string) ($r['issued_by_label'] ?? ''), 'issued_at' => (string) $r['issued_at'],
            ];
            $link = $links[$id] ?? null;
            $v = SseAlertService::vigilanceView($issue, $link, $today);
            $data = is_array($issue['snapshot']['data'] ?? null) ? $issue['snapshot']['data'] : [];
            $facts = is_array($data['facts'] ?? null) ? $data['facts'] : [];
            $factLinks = is_array($facts['links'] ?? null) ? $facts['links'] : [];
            $measureCodes = array_values(array_map('strval', array_column($v['measures'], 'code')));
            $text = static fn (string $k): string => is_string($issue['texts'][$k] ?? null) ? SseSheetText::plain((string) $issue['texts'][$k]) : '';
            $aliases = array_slice(array_values(array_filter(array_map('strval', is_array($facts['aliases'] ?? null) ? $facts['aliases'] : []), static fn (string $s): bool => trim($s) !== '')), 0, 3);
            $state = (string) $v['state'];
            $donts = is_array($v['donts'][$lang === 'en' ? 1 : 0] ?? null) ? array_values(array_map('strval', $v['donts'][$lang === 'en' ? 1 : 0])) : [];
            $a = $this->alert([
                'ref' => $v['ref'], 'doc' => SseAlertRepository::DOC_VIGILANCE, 'type' => 'vigilance', 'color' => '',
                'threat' => $v['threat'], 'armed' => $v['armed'], 'order' => $v['order'], 'name' => $v['name'], 'alias' => implode(', ', $aliases),
                'codes' => self::coded($v['measures'], $lang), 'instructions' => $text('instructions'),
                'reasons' => self::coded($v['reasons'], $lang), 'reason' => $text('reason'),
                'zone' => $v['zone'], 'place' => $v['place'], 'issued' => (string) ($data['issued_at'] ?? $issue['issued_at']), 'until' => $v['until'],
                'soon' => (bool) $v['perishable'], 'state' => $state, 'updated' => $issue['issued_at'], 'cls' => $issue['classification'],
            ], $full, $lang);
            $physical = [];
            foreach (['height_cm' => ' cm', 'build' => '', 'hair' => '', 'eyes' => '', 'marks' => ''] as $k => $unit) {
                $s = self::line($facts[$k] ?? '', 120);
                if ($s !== '' && $s !== '0') {
                    $physical[] = $s . $unit;
                }
            }
            $a['_id'] = $id;
            $a['_identity'] = (int) ($issue['identity_id'] ?? 0);
            $a['_closed'] = $state === 'lifted' ? (string) $v['lifted_at'] : ($state === 'expired' ? (string) $v['until'] . ' 23:59:59' : '');
            $a['_issuer'] = $issue['issued_by_user_id'];
            $a['_view'] = $v;
            $a['_link'] = $link;
            $a['_measures'] = $measureCodes;
            $a['_detail'] = [
                'reasons' => $a['_reasons'], 'vehicles' => self::labels($factLinks['vehicle'] ?? []), 'people' => self::labels($factLinks['associate'] ?? []),
                'physical' => self::line(implode(' · ', $physical), 200), 'reports' => 0,
                'lifted_at' => $state === 'lifted' ? self::stamp($v['lifted_at']) : '', 'lift_reason' => self::line($v['lift_reason'], 500),
                'donts' => $donts !== [] ? array_slice($donts, 0, 3) : SseAlertLevel::donts($measureCodes, $lang),
            ];
            $out[] = $a;
        }

        return $out;
    }

    // --------------------------------------------------------------------- Outils

    /**
     * Service à informer, adresse de signalement, accusé de lecture et nombre de signalements des alertes retenues. Le lien d'un avis
     * en cours est créé au premier besoin (jeton tiré une fois) ; sans l'étape de base 4830 : ni adresse ni jeton.
     *
     * @param list<array<string, mixed>> $alerts
     * @return list<array<string, mixed>>
     */
    private function withLinks(int $tenantId, array $alerts, ?int $userId, bool $create): array
    {
        $ready = SseAlertRepository::ready();
        $repo = $this->alerts->alerts();
        $noticeLinks = [];
        $acked = [];
        $counts = [];
        if ($ready) {
            try {
                $noticeLinks = $repo->linksFor($tenantId, SseAlertRepository::DOC_NOTICE, array_values(array_map(static fn (array $a): int => (int) $a['_id'], array_filter($alerts, static fn (array $a): bool => $a['doc'] === SseAlertRepository::DOC_NOTICE))));
                $acked = $userId !== null && $userId > 0 ? $repo->ackedBy($tenantId, $userId) : [];
            } catch (\Throwable) {
                $noticeLinks = [];
            }
        }
        foreach ($alerts as $k => $a) {
            $link = null;
            $report = [];
            if ($a['doc'] === SseAlertRepository::DOC_NOTICE) {
                $link = $noticeLinks[(int) $a['_id']] ?? null;
                try {
                    if ($link === null && $ready && $create) {
                        $link = $this->alerts->noticeLink($tenantId, $a['_notice']);
                    }
                    $report = SseAlertReportTo::read(is_array($link['report'] ?? null) && $link['report'] !== [] ? $link['report'] : $this->alerts->noticeReport($tenantId, $a['_notice']));
                } catch (\Throwable) {
                    $report = SseAlertReportTo::read($link['report'] ?? []);
                }
                $usable = $link !== null && $link['revoked_at'] === null;
                $a['token'] = $usable ? (string) $link['token'] : '';
                $a['url'] = $usable ? SseAlertReportTo::url((string) $link['token']) : '';
            } else {
                $link = $a['_link'] ?? null;
                $report = is_array($a['_view']['report'] ?? null) ? $a['_view']['report'] : SseAlertReportTo::read([]);
                $a['token'] = $ready ? (string) ($a['_view']['token'] ?? '') : '';
                $a['url'] = $ready ? (string) ($a['_view']['url'] ?? '') : '';
            }
            $a['_link'] = $link;
            $a['service'] = self::line(SseAlertReportTo::label($report, (string) $a['_lang']), 140);
            $a['svc'] = self::line($report['code'] ?? '', 30);
            $a['channel'] = self::line($report['channel'] ?? '', 200);
            $a['delay'] = (string) ($report['delay'] ?? SseAlertReportTo::DEFAULT_DELAY);
            $a['acked'] = $link !== null && isset($acked[(int) $link['id']]);
            $alerts[$k] = $a;
        }
        if ($ready) {
            try {
                $counts = $repo->reportCounts($tenantId, array_values(array_filter(array_map(static fn (array $a): int => (int) ($a['_link']['id'] ?? 0), $alerts))));
            } catch (\Throwable) {
                $counts = [];
            }
        }
        foreach ($alerts as $k => $a) {
            $alerts[$k]['_detail']['reports'] = (int) ($counts[(int) ($a['_link']['id'] ?? 0)] ?? 0);
        }

        return array_values($alerts);
    }

    /**
     * Alerte normalisée : valeurs de listes fermées contrôlées, textes bornés, niveau déduit de la menace et de l'armement.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    private function alert(array $in, bool $full, string $lang): array
    {
        $cut = false;
        $limits = $full ? self::FULL : self::SHORT;
        $threat = isset(C::THREATS[(string) ($in['threat'] ?? '')]) ? (string) $in['threat'] : '';
        $armed = isset(C::ARMED[(string) ($in['armed'] ?? '')]) ? (string) $in['armed'] : 'unknown';
        $level = SseAlertLevel::of($threat, $armed);
        // Conduite à tenir : mesures codifiées (6 au plus), puis les lignes libres.
        $codes = array_slice(array_values((array) ($in['codes'] ?? [])), 0, 6);
        $lines = $codes;
        foreach (preg_split('/\n+/', C::text($in['instructions'] ?? '', 4000)) ?: [] as $l) {
            $l = self::line($l, 400);
            if ($l !== '') {
                $lines[] = $l;
            }
        }
        if (count($lines) > $limits['lines']) {
            $cut = true;
            $lines = array_slice($lines, 0, $limits['lines']);
        }
        foreach ($lines as $k => $l) {
            if (mb_strlen($l) > $limits['line']) {
                $cut = true;
                $lines[$k] = self::cut($l, $limits['line']);
            }
        }
        $reasons = array_slice(array_values((array) ($in['reasons'] ?? [])), 0, 6);
        $free = C::text($in['reason'] ?? '', 4000);
        $reason = trim(implode(', ', $reasons) . ($reasons !== [] && $free !== '' ? "\n" : '') . $free);
        if (mb_strlen($reason) > $limits['reason']) {
            $cut = true;
            $reason = self::cut($reason, $limits['reason']);
        }
        $cls = self::level((string) ($in['cls'] ?? ''));

        return [
            'ref' => self::line($in['ref'] ?? '', 40),
            'doc' => (string) $in['doc'],
            'type' => self::line($in['type'] ?? '', 32),
            'color' => isset(SseAlertLevel::NOTICE_COLORS[(string) ($in['color'] ?? '')]) ? (string) $in['color'] : '',
            'rank' => (int) $level['rank'],
            'level' => (string) $level['key'],
            'order' => in_array($in['order'] ?? '', ['hold', 'order'], true) ? (string) $in['order'] : '',
            'name' => self::line($in['name'] ?? '', SseFichesGameService::LABEL_MAX),
            'alias' => self::line($in['alias'] ?? '', 100),
            'threat' => $threat,
            'armed' => $armed,
            'conduct' => array_values($lines),
            'reason' => $reason,
            'zone' => self::line($in['zone'] ?? '', 100),
            'place' => self::line($in['place'] ?? '', 120),
            'issued' => self::day($in['issued'] ?? ''),
            'until' => self::day($in['until'] ?? ''),
            'soon' => (bool) ($in['soon'] ?? false),
            'service' => '', 'svc' => '', 'channel' => '', 'delay' => SseAlertReportTo::DEFAULT_DELAY, 'url' => '', 'token' => '',
            'state' => in_array($in['state'] ?? '', ['active', 'lifted', 'expired'], true) ? (string) $in['state'] : 'active',
            'acked' => false,
            'updated' => self::stamp($in['updated'] ?? ''),
            'cls' => $cls,
            'mark' => SseUsClassification::banner($cls),
            'cut' => $cut,
            '_reasons' => $reasons,
            '_lang' => $lang,
        ];
    }

    /**
     * « CODE libellé » dans la langue demandée.
     *
     * @param list<array<string, mixed>> $codes
     * @return list<string>
     */
    private static function coded(array $codes, string $lang): array
    {
        $out = [];
        foreach ($codes as $c) {
            if (!is_array($c)) {
                continue;
            }
            $label = (string) ($c[$lang === 'en' ? 'en' : 'fr'] ?? '');
            $s = self::cut(self::line(trim((string) ($c['code'] ?? '') . ' ' . ($label !== '' ? $label : (string) ($c['fr'] ?? ''))), 200), 90);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * Texte libre d'un avis : vide quand il n'est que la phrase composée à partir des codes (déjà donnés en « CODE libellé »).
     *
     * @param list<string> $codes
     * @param array<string, mixed> $config
     */
    private static function free(string $text, array $codes, string $kind, array $config): string
    {
        $text = trim($text);
        if ($text === '' || $codes === []) {
            return $text;
        }
        foreach (['fr', 'en'] as $lang) {
            if ($text === SseWantedCodes::compose($codes, $kind, $lang, $config)) {
                return '';
            }
        }

        return $text;
    }

    /**
     * @return list<string> libellés courts, MAX_LINKS au plus
     */
    private static function labels(mixed $list): array
    {
        $out = [];
        foreach (is_array($list) ? $list : [] as $item) {
            $s = self::line($item, SseFichesGameService::LABEL_MAX);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_slice($out, 0, self::MAX_LINKS);
    }

    /**
     * Liens d'une identité selon l'habilitation (règles de « sse_fiches ») : véhicules toujours, personnes liées à partir de
     * Confidentiel. Un lien caché, pas encore révélé ou qui nomme un membre n'est pas donné.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<int, true> $memberIds
     * @return array{vehicle: list<string>, associate: list<string>}
     */
    private function links(array $rows, string $viewerLevel, array $memberIds): array
    {
        $out = ['vehicle' => [], 'associate' => []];
        foreach ($rows as $l) {
            $k = (string) ($l['kind'] ?? '');
            if (!isset($out[$k]) || !SseFileVisibility::row($l, SseFileVisibility::NO_SESSION_PHASE) || trim((string) ($l['value'] ?? '')) === '') {
                continue;
            }
            if ($k === 'associate' && !SseRedactionService::visibleAt('identite', $viewerLevel)) {
                continue;
            }
            if (isset($memberIds[(int) ($l['linked_identity_id'] ?? 0)])) {
                continue;
            }
            $rel = trim((string) ($l['relation_label'] ?? ''));
            $out[$k][] = (string) $l['value'] . ($k === 'associate' && $rel !== '' && $rel !== '—' ? ' (' . $rel . ')' : '');
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array<int, true>
     */
    private function memberIdentities(int $tenantId, array $ids): array
    {
        $ids = array_slice(array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0))), 0, 500);
        if ($ids === []) {
            return [];
        }
        $params = ['t' => $tenantId];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $id;
        }
        $out = [];
        foreach ($this->safeFetch('SELECT id FROM sse_identities WHERE tenant_id = :t AND user_id > 0 AND id IN (' . implode(', ', $marks) . ')', $params) as $r) {
            $out[(int) $r['id']] = true;
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array{profiles: array<int, array<string, mixed>>, links: array<int, list<array<string, mixed>>>, aliases: array<int, list<array<string, mixed>>>}
     */
    private function records(int $tenantId, array $ids): array
    {
        try {
            $repo = new SseFileRecordRepository($this->db);

            return ['profiles' => $repo->profiles($tenantId, $ids), 'links' => $repo->linksFor($tenantId, $ids), 'aliases' => $repo->aliasesFor($tenantId, $ids)];
        } catch (\Throwable) {
            return ['profiles' => [], 'links' => [], 'aliases' => []];
        }
    }

    /** @return array<string, mixed> */
    private function settings(int $tenantId): array
    {
        try {
            return (new SseWantedSettingsRepository($this->db))->get($tenantId);
        } catch (\Throwable) {
            return SseWantedSettingsRepository::normalize([]);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function safeFetch(string $sql, array $params): array
    {
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function level(string $level): string
    {
        return isset(SseRedactionService::LEVELS[$level]) ? $level : SseFichesGameService::NOTICE_LEVEL;
    }

    private static function line(mixed $v, int $max): string
    {
        return C::line($v, $max);
    }

    private static function cut(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, max(1, $max - 1))) . '…' : $s;
    }

    private static function day(mixed $v): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $s) === 1 ? substr($s, 0, 10) : '';
    }

    private static function stamp(mixed $v): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $s) === 1 ? str_replace('T', ' ', substr($s, 0, 16)) : self::day($s);
    }
}
