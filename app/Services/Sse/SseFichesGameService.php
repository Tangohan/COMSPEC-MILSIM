<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseFileVisibility;
use App\Support\SseUsClassification;
use App\Support\SseWantedPhysical;
use App\Support\SseWantedCatalog;

/**
 * Fiches du portail SSE lues par le téléphone ATAK en jeu (ICD-SSE-FICHES-001, docs/technical/ICD-SSE-FICHES.md) : avis de
 * recherche actifs (type, menace, armé, motifs, mesures à prendre, zone, dernier lieu connu, validité) et fiches PDF émises
 * (identification, vigilance, renseignement, contrôle) avec leurs véhicules, adresses et personnes liées en libellés courts.
 *
 * Ce qui part au jeu est filtré, jamais seulement masqué à l'écran :
 *  - la classification : une fiche n'est donnée qu'à un porteur dont l'habilitation (plafond de lecture, voir
 *    SseClearanceService::resolve) atteint celle de la fiche. Niveau inconnu (clé de communauté seule, compte illisible) :
 *    le plus bas, celui des avis de recherche, qui sont des documents de diffusion ;
 *  - une identité de MEMBRE (sse_identities.user_id > 0) n'est jamais donnée : ni avis, ni fiche, ni lien ;
 *  - une donnée cachée ou pas encore révélée (visibilité des fichiers) devient une barre de caviardage ;
 *  - les liens suivent les catégories de la politique de caviardage (adresses : Encadrement, personnes liées : Confidentiel) ;
 *  - pas d'état civil : ni date ni lieu de naissance, ni pièce, ni photo, ni nature (couverte, falsifiée) de l'identité.
 * La communauté est celle de la clé, jamais celle de la requête (contrôleur).
 *
 * Deux formes pour le même contenu : JSON (GET /api/game/v1/sse/fiches) et pages parseSimpleArray de PAGE_BYTES au plus (kind
 * « sse_fiches » de POST /api/sse/identity/checks, la seule voie que COMSPEC Link sait déjà emprunter : aucune nouvelle DLL).
 */
final class SseFichesGameService
{
    public const VERSION = 1;
    public const KIND = 'sse_fiches';
    public const KIND_SHARE = 'sse_fiche_share';
    /** Résolution d'un numéro DSE par le canal du téléphone (même résultat que GET /api/sse/v1/dse/{dse}). */
    public const KIND_DSE = 'sse_dse';
    /** Une page, avec la marge pour « OK|id|ref » (8 000 octets au plus côté DLL). */
    public const PAGE_BYTES = 7400;
    /** Durée de validité conseillée au client, en secondes. */
    public const TTL = 300;
    public const MAX_FICHES = 80;
    public const MAX_PAGES = 14;
    public const MAX_LINKS = 3;
    public const LABEL_MAX = 60;
    /** Les avis de recherche sont des documents de diffusion : lisibles dès le niveau le plus bas. */
    public const NOTICE_LEVEL = SseCaseRepository::CLASS_INTERNAL;
    /** Modèles de fiches PDF repris (l'avis de recherche vient de son registre, pas de ses PDF). */
    public const MODELS = ['identification', 'vigilance', 'profile', 'control'];

    /** Colonnes d'une fiche, dans l'ordre des tableaux de la forme SQF. */
    public const COLUMNS = [
        'ref', 'kind', 'type', 'name', 'alias', 'threat', 'armed', 'level', 'mark', 'reason', 'measures', 'desc', 'remarks', 'note',
        'zone', 'place', 'seen', 'issued', 'until', 'updated', 'vehicles', 'addresses', 'people', 'auth', 'cut',
    ];

    /** Longueur maximale des textes dans la liste (le détail les donne en entier, dans la limite d'une page). */
    public const SHORT = ['reason' => 360, 'measures' => 260, 'desc' => 300, 'remarks' => 200, 'note' => 160];
    public const FULL = ['reason' => 800, 'measures' => 900, 'desc' => 1500, 'remarks' => 700, 'note' => 300];

    private Database $db;

    /** @var \Closure(int, int): string niveau de lecture d'un compte : (communauté, compte) */
    private \Closure $levelOf;

    /**
     * @param (\Closure(int, int): string)|null $levelOf plafond de lecture SSE du compte relié en jeu (règle de SseMemberClearance)
     */
    public function __construct(?Database $db = null, ?\Closure $levelOf = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->levelOf = $levelOf ?? static function (int $tenantId, int $userId): string {
            return (string) (new SseMemberClearance())->view($tenantId, $userId)['level'];
        };
    }

    /**
     * Niveau de lecture du porteur du téléphone. Compte inconnu, clé de communauté seule ou calcul impossible : le plus bas.
     */
    public function levelFor(int $tenantId, ?int $userId): string
    {
        if ($userId === null || $userId < 1 || $tenantId < 1) {
            return self::NOTICE_LEVEL;
        }
        try {
            $level = ($this->levelOf)($tenantId, $userId);
        } catch (\Throwable) {
            return self::NOTICE_LEVEL;
        }

        return isset(SseRedactionService::LEVELS[$level]) ? $level : self::NOTICE_LEVEL;
    }

    /**
     * Le paquet des fiches lisibles à ce niveau.
     *
     * @return array{version: int, revision: string, generated_at: string, ttl: int, level: string, mark: string,
     *               authority: array{0: string, 1: string}, types: list<array{0: string, 1: string, 2: string}>,
     *               fiches: list<array<string, mixed>>, total: int, truncated: bool}
     */
    public function build(int $tenantId, string $viewerLevel): array
    {
        $viewerLevel = isset(SseRedactionService::LEVELS[$viewerLevel]) ? $viewerLevel : self::NOTICE_LEVEL;
        $all = array_merge($this->notices($tenantId, $viewerLevel, false), $this->sheets($tenantId, $viewerLevel, false));
        $all = array_values(array_filter($all, static fn (array $f): bool => SseRedactionService::levelRank((string) $f['level']) <= SseRedactionService::levelRank($viewerLevel)));
        // Les plus graves d'abord, puis les plus récentes.
        usort($all, static function (array $a, array $b): int {
            return [SseWantedCatalog::threatRank((string) $b['threat']), (string) $b['updated'], (string) $a['ref']]
                <=> [SseWantedCatalog::threatRank((string) $a['threat']), (string) $a['updated'], (string) $b['ref']];
        });
        $total = count($all);
        $fiches = array_slice($all, 0, self::MAX_FICHES);
        $config = $this->settings($tenantId);
        $types = [];
        foreach ($fiches as $f) {
            if ($f['kind'] === 'avis' && $f['type'] !== '' && !isset($types[$f['type']])) {
                $types[$f['type']] = [
                    (string) $f['type'],
                    SseWantedCatalog::typeLabel((string) $f['type'], $config, 'fr'),
                    SseWantedCatalog::typeLabel((string) $f['type'], $config, 'en'),
                ];
            }
        }
        $types = array_values($types);
        $authority = [
            self::line($config['authority_fr'] ?? SseWantedCatalog::DEFAULTS['authority_fr'], 80),
            self::line($config['authority_en'] ?? SseWantedCatalog::DEFAULTS['authority_en'], 80),
        ];

        return [
            'version' => self::VERSION,
            'revision' => substr(sha1((string) json_encode([self::VERSION, $viewerLevel, $fiches, $authority, $types, $total], JSON_UNESCAPED_UNICODE)), 0, 16),
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'ttl' => self::TTL,
            'level' => $viewerLevel,
            'mark' => self::marking($viewerLevel),
            'authority' => $authority,
            'types' => $types,
            'fiches' => $fiches,
            'total' => $total,
            'truncated' => $total > count($fiches),
        ];
    }

    /**
     * Une fiche en entier (textes non raccourcis, dans la limite d'une page), ou null : inconnue, d'une autre communauté, ou
     * au-dessus de l'habilitation du porteur (même réponse : on ne dit pas qu'elle existe).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, string $viewerLevel, string $ref): ?array
    {
        $ref = self::line($ref, 40);
        if ($ref === '' || $tenantId < 1) {
            return null;
        }
        $viewerLevel = isset(SseRedactionService::LEVELS[$viewerLevel]) ? $viewerLevel : self::NOTICE_LEVEL;
        foreach (array_merge($this->notices($tenantId, $viewerLevel, true, $ref), $this->sheets($tenantId, $viewerLevel, true, $ref)) as $f) {
            if ((string) $f['ref'] === $ref && SseRedactionService::levelRank((string) $f['level']) <= SseRedactionService::levelRank($viewerLevel)) {
                return $f;
            }
        }

        return null;
    }

    /**
     * Avis actif d'une identité contrôlée (champ « wanted » du contrôle d'identité) : référence, type, menace, armé, mesure
     * courte. Jamais pour l'identité d'un membre. Sans avis : seulement « wanted: false ».
     *
     * Additif (même réponse, mêmes droits) : motifs et mesures codifiés (« CODE libellé »), service demandeur et signalement court.
     *
     * @return array{wanted: bool, ref?: string, type?: string, threat?: string, armed?: string, instruction?: string, reasons?: list<string>, measures?: list<string>, service?: string, physical?: string}
     */
    public function wantedFor(int $tenantId, ?int $identityId): array
    {
        if ($identityId === null || $identityId < 1 || $tenantId < 1) {
            return ['wanted' => false];
        }
        foreach ($this->noticeRows($tenantId, 5, $identityId) as $r) {
            if ($this->isMember($r)) {
                return ['wanted' => false];
            }

            return [
                'wanted' => true,
                'ref' => self::line($r['ref'] ?? '', 40),
                'type' => self::line($r['notice_type'] ?? '', 32),
                'threat' => self::pick(SseWantedCatalog::THREATS, $r['threat_level'] ?? '', 'medium'),
                'armed' => self::pick(SseWantedCatalog::ARMED, $r['armed'] ?? '', 'unknown'),
                'instruction' => self::line($r['instructions'] ?? '', 120),
            ] + $this->wantedDetail($tenantId, (int) $r['id']);
        }

        return ['wanted' => false];
    }

    /**
     * Motifs et mesures codifiés, service demandeur et signalement court d'un avis, pour le contrôle d'identité du jeu. Un avis sans
     * codes, sans service ou sans signalement (ou une étape de base absente) donne des listes et des textes vides.
     *
     * @return array{reasons: list<string>, measures: list<string>, service: string, physical: string}
     */
    private function wantedDetail(int $tenantId, int $noticeId): array
    {
        $out = ['reasons' => [], 'measures' => [], 'service' => '', 'physical' => ''];
        try {
            if (SseWantedExtrasRepository::ready()) {
                $x = (new SseWantedExtrasRepository($this->db))->forNotice($tenantId, $noticeId);
                $coded = static fn (array $list): array => array_slice(array_values(array_filter(array_map(
                    static fn (array $c): string => self::line(trim(($c['code'] ?? '') . ' ' . ($c['fr'] ?? '')), 90),
                    $list
                ), static fn (string $t): bool => $t !== '')), 0, 6);
                $out['reasons'] = $coded((array) ($x['codes']['reasons'] ?? []));
                $out['measures'] = $coded((array) ($x['codes']['measures'] ?? []));
                $out['physical'] = self::line(SseWantedPhysical::shortLine((array) ($x['physical'] ?? []), 'fr'), 160);
            }
            if (SseWantedMetaRepository::ready()) {
                $meta = (new SseWantedMetaRepository($this->db))->forNotice($tenantId, $noticeId);
                $snap = is_array($meta['service'] ?? null) ? $meta['service'] : [];
                $name = self::line($snap['fr'] ?? '', 90);
                $ac = self::line($snap['ac'] ?? '', 12);
                $out['service'] = $name !== '' && $ac !== '' ? $name . ' (' . $ac . ')' : $name;
            }
        } catch (\Throwable) {
            return $out;
        }

        return $out;
    }

    // ------------------------------------------------------------ Pages parseSimpleArray

    /**
     * Le paquet découpé en pages parseSimpleArray :
     * [version, "révision", "généré le", ttl, page, page suivante (0 = fin), [fiches…], [autorité fr, en, niveau, marquage, total, tronqué], [types…]].
     * Une fiche est le tableau des colonnes de COLUMNS, dans l'ordre.
     *
     * @param array<string, mixed> $pkg
     * @return list<string>
     */
    public static function pages(array $pkg): array
    {
        $meta = SseLoreService::sqf([
            (string) ($pkg['authority'][0] ?? ''), (string) ($pkg['authority'][1] ?? ''), (string) $pkg['level'], (string) $pkg['mark'],
            (int) $pkg['total'], (bool) $pkg['truncated'],
        ]);
        $types = SseLoreService::sqf($pkg['types'] ?? []);
        $headRoom = 96 + strlen($meta) + strlen($types);
        /** @var list<list<string>> $pages */
        $pages = [[]];
        $size = $headRoom;
        foreach ((array) ($pkg['fiches'] ?? []) as $f) {
            $text = SseLoreService::sqf(self::cells($f));
            $len = strlen($text) + 1;
            if ($len + $headRoom > self::PAGE_BYTES) {
                // Une fiche qui ne tient pas seule dans une page (liens très longs) : textes ramenés au plus court.
                $text = SseLoreService::sqf(self::cells(self::shrink($f)));
                $len = strlen($text) + 1;
                if ($len + $headRoom > self::PAGE_BYTES) {
                    continue;
                }
            }
            if ($size + $len > self::PAGE_BYTES) {
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
            $out[] = '[' . self::VERSION . ',' . SseLoreService::sqf((string) $pkg['revision']) . ',' . SseLoreService::sqf((string) $pkg['generated_at'])
                . ',' . (int) ($pkg['ttl'] ?? self::TTL) . ',' . $i . ',' . ($i + 1 < $count ? $i + 1 : 0)
                . ',[' . implode(',', $rows) . '],' . $meta . ',' . $types . ']';
        }

        return $out;
    }

    /**
     * Une seule fiche, en entier, dans la même forme qu'une page (le client la remplace dans sa liste).
     *
     * @param array<string, mixed> $pkg paquet de build() dont « fiches » ne contient que la fiche demandée
     */
    public static function detailPage(array $pkg, array $fiche): string
    {
        $pkg['fiches'] = [$fiche];
        $pkg['total'] = 1;
        $pkg['truncated'] = false;
        $pages = self::pages($pkg);

        return $pages[0];
    }

    /** Réponse « révision déjà connue » : même en-tête, aucune fiche, drapeau final à true. */
    public static function unchangedPage(string $revision): string
    {
        return '[' . self::VERSION . ',' . SseLoreService::sqf($revision) . ',' . SseLoreService::sqf(gmdate('Y-m-d\TH:i:s\Z'))
            . ',' . self::TTL . ',0,0,[],[],[],true]';
    }

    /** Identifiant numérique d'une révision (champ « id » attendu par COMSPEC Link dans « OK|id|ref »). */
    public static function revisionId(string $revision): int
    {
        return (int) hexdec(substr($revision, 0, 7)) + 1;
    }

    public static function marking(string $level): string
    {
        // Marquage de bannière de l'échelle américaine du portail (CUI, CONFIDENTIAL, SECRET//NOFORN, TOP SECRET//SCI//NOFORN) : une seule source.
        return SseUsClassification::banner($level);
    }

    /**
     * @param array<string, mixed> $f
     * @return list<mixed>
     */
    public static function cells(array $f): array
    {
        $out = [];
        foreach (self::COLUMNS as $c) {
            $v = $f[$c] ?? '';
            $out[] = $c === 'cut' ? (bool) $v : (is_array($v) ? array_values($v) : $v);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $f
     * @return array<string, mixed>
     */
    private static function shrink(array $f): array
    {
        foreach (self::SHORT as $k => $max) {
            $f[$k] = self::cut((string) ($f[$k] ?? ''), (int) ($max / 2));
        }
        foreach (['vehicles', 'addresses', 'people'] as $k) {
            $f[$k] = array_slice((array) ($f[$k] ?? []), 0, 1);
        }
        $f['cut'] = true;

        return $f;
    }

    // ------------------------------------------------------------ Avis de recherche

    /**
     * @return list<array<string, mixed>>
     */
    private function notices(int $tenantId, string $viewerLevel, bool $full, string $onlyRef = ''): array
    {
        $rows = $this->noticeRows($tenantId, 200, null, $onlyRef);
        $members = [];
        $ids = [];
        foreach ($rows as $r) {
            if ($this->isMember($r)) {
                $members[(int) $r['id']] = true;
                continue;
            }
            $ids[] = (int) $r['identity_id'];
        }
        $records = $ids !== [] ? $this->records($tenantId, $ids) : ['profiles' => [], 'links' => [], 'aliases' => []];
        // Une personne liée qui est l'identité d'un membre n'est jamais nommée.
        $linked = [];
        foreach ($records['links'] as $byIdentity) {
            foreach ($byIdentity as $l) {
                if ((int) ($l['linked_identity_id'] ?? 0) > 0) {
                    $linked[] = (int) $l['linked_identity_id'];
                }
            }
        }
        $memberIds = $this->memberIdentities($tenantId, $linked);
        $extras = $this->extras($tenantId, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $out = [];
        foreach ($rows as $r) {
            if (isset($members[(int) $r['id']])) {
                continue;
            }
            $iid = (int) $r['identity_id'];
            $profile = $records['profiles'][$iid] ?? null;
            $hidden = $profile !== null && !SseFileVisibility::field($profile, 'identity', SseFileVisibility::NO_SESSION_PHASE);
            $name = trim(mb_strtoupper(self::line($r['last_name'] ?? '', 60), 'UTF-8') . ' ' . self::line($r['first_name'] ?? '', 40));
            $aliases = [];
            if (!$hidden) {
                foreach ([$r['alias'] ?? null] as $a) {
                    if (is_string($a) && trim($a) !== '') {
                        $aliases[] = trim($a);
                    }
                }
                foreach ($records['aliases'][$iid] ?? [] as $a) {
                    if (!in_array((string) ($a['kind'] ?? ''), ['legal', 'registry'], true) && SseFileVisibility::row($a, SseFileVisibility::NO_SESSION_PHASE) && trim((string) ($a['alias'] ?? '')) !== '') {
                        $aliases[] = trim((string) $a['alias']);
                    }
                }
            }
            $links = $hidden ? ['vehicle' => [], 'address' => [], 'associate' => []] : $this->links($records['links'][$iid] ?? [], $viewerLevel, $memberIds);
            // Motifs et mesures codifiés (catalogue de la communauté) devant le texte libre ; signalement court.
            $x = $extras[(int) $r['id']] ?? ['reasons' => [], 'measures' => [], 'physical' => ''];
            $joinText = static fn (array $codes, mixed $text): string => trim(implode(', ', $codes) . ($codes !== [] && trim((string) $text) !== '' ? "
" : '') . trim((string) $text));
            $f = $this->fiche([
                'ref' => $r['ref'], 'kind' => 'avis', 'type' => $r['notice_type'], 'name' => $hidden ? SseRedactionService::bar($name) : $name,
                'alias' => implode(', ', array_slice(array_values(array_unique($aliases)), 0, 3)),
                'threat' => $r['threat_level'], 'armed' => $r['armed'], 'level' => self::NOTICE_LEVEL,
                'reason' => $joinText($x['reasons'], $r['reason'] ?? ''), 'measures' => $joinText($x['measures'], $r['instructions'] ?? ''), 'desc' => $hidden ? '' : $x['physical'],
                'zone' => $r['zone'] ?? '', 'place' => $r['last_seen_place'] ?? '', 'seen' => $r['last_seen_at'] ?? '',
                'issued' => $r['issued_at'] ?? '', 'until' => $r['expires_at'] ?? '', 'updated' => $r['updated_at'] ?: ($r['created_at'] ?? ''),
                'vehicles' => $links['vehicle'], 'addresses' => $links['address'], 'people' => $links['associate'],
                'auth' => $r['authority'] ?: ($r['contact_label'] ?? ''),
            ], $full);
            $out[] = $f;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function noticeRows(int $tenantId, int $limit, ?int $identityId = null, string $ref = ''): array
    {
        $sql = 'SELECT n.id, n.identity_id, n.ref, n.notice_type, n.threat_level, n.armed, n.reason, n.instructions, n.zone, n.last_seen_place,
                       n.last_seen_at, n.authority, n.contact_label, n.issued_at, n.expires_at, n.created_at, n.updated_at,
                       i.last_name, i.first_name, i.alias, i.user_id
                FROM sse_wanted_notices n
                LEFT JOIN sse_identities i ON i.id = n.identity_id AND i.tenant_id = n.tenant_id
                WHERE n.tenant_id = :t AND n.status = \'active\' AND (n.expires_at IS NULL OR n.expires_at > :now)';
        $params = ['t' => $tenantId, 'now' => gmdate('Y-m-d H:i:s')];
        if ($identityId !== null) {
            $sql .= ' AND n.identity_id = :i';
            $params['i'] = $identityId;
        }
        if ($ref !== '') {
            $sql .= ' AND n.ref = :ref';
            $params['ref'] = $ref;
        }

        return $this->safeFetch($sql . ' ORDER BY n.id DESC LIMIT ' . max(1, min(200, $limit)), $params);
    }

    // ------------------------------------------------------------ Fiches PDF émises

    /**
     * Dernière fiche émise de chaque modèle et de chaque sujet, au niveau de diffusion où elle a été imprimée (son texte a
     * déjà été caviardé à ce niveau : le porteur d'une habilitation au moins égale ne lit rien de plus que le PDF).
     *
     * @return list<array<string, mixed>>
     */
    private function sheets(int $tenantId, string $viewerLevel, bool $full, string $onlyRef = ''): array
    {
        $marks = [];
        $params = ['t' => $tenantId];
        foreach (self::MODELS as $i => $m) {
            $marks[] = ':m' . $i;
            $params['m' . $i] = $m;
        }
        $sql = 'SELECT d.id, d.person_id, d.identity_id, d.model, d.ref, d.classification, d.texts_json, d.snapshot_json, d.issued_at, i.user_id
                FROM sse_doc_issues d
                LEFT JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
                WHERE d.tenant_id = :t AND d.model IN (' . implode(', ', $marks) . ')';
        if ($onlyRef !== '') {
            $sql .= ' AND d.ref = :ref';
            $params['ref'] = $onlyRef;
        }
        $rows = $this->safeFetch($sql . ' ORDER BY d.id DESC LIMIT 400', $params);
        $today = gmdate('Y-m-d');
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            if ((int) ($r['user_id'] ?? 0) > 0) {
                continue;
            }
            $level = isset(SseRedactionService::LEVELS[(string) $r['classification']]) ? (string) $r['classification'] : SseCaseRepository::CLASS_RESTRICTED;
            if (SseRedactionService::levelRank($level) > SseRedactionService::levelRank($viewerLevel)) {
                continue;
            }
            $key = $r['model'] . '|' . (int) ($r['identity_id'] ?? 0) . '|' . (int) ($r['person_id'] ?? 0);
            if ($onlyRef === '' && isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $texts = json_decode((string) ($r['texts_json'] ?? ''), true);
            $snap = json_decode((string) ($r['snapshot_json'] ?? ''), true);
            $texts = is_array($texts) ? $texts : [];
            $data = is_array($snap['data'] ?? null) ? $snap['data'] : [];
            $facts = is_array($data['facts'] ?? null) ? $data['facts'] : [];
            $until = (string) ($data['valid_until'] ?? '');
            if ($until !== '' && $until < $today) {
                continue;
            }
            $model = (string) $r['model'];
            $t = static fn (string $k): string => is_string($texts[$k] ?? null) ? (string) $texts[$k] : '';
            [$reason, $measures, $desc, $remarks] = match ($model) {
                'vigilance' => [$t('reason'), $t('instructions'), $t('behaviour'), ''],
                'profile' => [$t('assessment'), $t('recommendations'), $t('summary'), ''],
                'control' => ['', '', $t('summary'), $t('remarks')],
                default => ['', '', $t('description'), $t('remarks')],
            };
            $links = is_array($facts['links'] ?? null) ? $facts['links'] : [];
            $label = static fn (string $k): array => array_values(array_map('strval', is_array($links[$k] ?? null) ? $links[$k] : []));
            $aliases = array_slice(array_values(array_filter(array_map('strval', is_array($facts['aliases'] ?? null) ? $facts['aliases'] : []), static fn (string $a): bool => trim($a) !== '')), 0, 3);
            $out[] = $this->fiche([
                'ref' => $r['ref'], 'kind' => $model, 'type' => $model, 'name' => $facts['display'] ?? '', 'alias' => implode(', ', $aliases),
                'threat' => $facts['threat'] ?? '', 'armed' => $facts['armed'] ?? '', 'level' => $level,
                'reason' => $reason, 'measures' => $measures, 'desc' => $desc, 'remarks' => $remarks, 'note' => $t('mention'),
                'zone' => $facts['zone'] ?? '', 'place' => $facts['last_seen_place'] ?? '', 'seen' => $facts['last_seen_at'] ?? '',
                'issued' => (string) ($data['issued_at'] ?? $r['issued_at']), 'until' => $until, 'updated' => $r['issued_at'],
                'vehicles' => $label('vehicle'), 'addresses' => $label('address'), 'people' => $label('associate'),
                'auth' => is_array($data['authority'] ?? null) ? (string) ($data['authority'][0] ?? '') : '',
            ], $full);
        }

        return $out;
    }

    // ------------------------------------------------------------ Outils

    /**
     * Fiche normalisée : valeurs de listes fermées contrôlées, textes d'une ligne ou à retours à la ligne, bornés.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    private function fiche(array $in, bool $full): array
    {
        $cut = false;
        $limits = $full ? self::FULL : self::SHORT;
        $text = static function (mixed $v, int $max) use (&$cut): string {
            $s = SseWantedCatalog::text($v, 4000);
            if (mb_strlen($s) > $max) {
                $cut = true;
                $s = rtrim(mb_substr($s, 0, $max - 1)) . '…';
            }

            return $s;
        };
        $list = static function (mixed $v) use (&$cut): array {
            $out = [];
            foreach (is_array($v) ? $v : [] as $item) {
                $s = self::line($item, self::LABEL_MAX);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
            if (count($out) > self::MAX_LINKS) {
                $cut = true;
            }

            return array_slice($out, 0, self::MAX_LINKS);
        };
        $level = isset(SseRedactionService::LEVELS[(string) $in['level']]) ? (string) $in['level'] : SseCaseRepository::CLASS_RESTRICTED;
        $f = [
            'ref' => self::line($in['ref'] ?? '', 40),
            'kind' => (string) $in['kind'],
            'type' => self::line($in['type'] ?? '', 32),
            'name' => self::line($in['name'] ?? '', self::LABEL_MAX),
            'alias' => self::line($in['alias'] ?? '', 100),
            'threat' => isset(SseWantedCatalog::THREATS[(string) ($in['threat'] ?? '')]) ? (string) $in['threat'] : '',
            'armed' => isset(SseWantedCatalog::ARMED[(string) ($in['armed'] ?? '')]) ? (string) $in['armed'] : '',
            'level' => $level,
            'mark' => self::marking($level),
            'zone' => self::line($in['zone'] ?? '', 100),
            'place' => self::line($in['place'] ?? '', 120),
            'seen' => self::day($in['seen'] ?? ''),
            'issued' => self::day($in['issued'] ?? ''),
            'until' => self::day($in['until'] ?? ''),
            'updated' => self::stamp($in['updated'] ?? ''),
            'vehicles' => $list($in['vehicles'] ?? []),
            'addresses' => $list($in['addresses'] ?? []),
            'people' => $list($in['people'] ?? []),
            'auth' => self::line($in['auth'] ?? '', 80),
        ];
        foreach (self::SHORT as $k => $_) {
            $f[$k] = $text($in[$k] ?? '', $limits[$k]);
        }
        $f['cut'] = $cut;

        return $f;
    }

    /**
     * Liens d'une identité en libellés, selon l'habilitation : véhicules toujours, adresses à partir d'Encadrement, personnes liées
     * à partir de Confidentiel (mêmes catégories que les fiches PDF). Un lien caché ou pas encore révélé n'est pas donné.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<int, true> $memberIds identités de membres, jamais nommées
     * @return array{vehicle: list<string>, address: list<string>, associate: list<string>}
     */
    private function links(array $rows, string $viewerLevel, array $memberIds = []): array
    {
        $out = ['vehicle' => [], 'address' => [], 'associate' => []];
        $need = ['address' => 'lieu', 'associate' => 'identite'];
        foreach ($rows as $l) {
            $k = (string) ($l['kind'] ?? '');
            if (!isset($out[$k]) || !SseFileVisibility::row($l, SseFileVisibility::NO_SESSION_PHASE) || trim((string) ($l['value'] ?? '')) === '') {
                continue;
            }
            if (isset($need[$k]) && !SseRedactionService::visibleAt($need[$k], $viewerLevel)) {
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
     * Motifs, mesures (libellés français) et signalement court des avis, depuis les tables de codification (absentes : rien).
     *
     * @param list<int> $noticeIds
     * @return array<int, array{reasons: list<string>, measures: list<string>, physical: string}>
     */
    private function extras(int $tenantId, array $noticeIds): array
    {
        $out = [];
        try {
            if ($noticeIds === [] || !SseWantedExtrasRepository::ready()) {
                return [];
            }
            foreach ((new SseWantedExtrasRepository($this->db))->forNotices($tenantId, $noticeIds) as $id => $x) {
                $label = static fn (array $list): array => array_values(array_filter(array_map(static fn (array $c): string => self::line(trim((string) ($c['code'] ?? '') . ' ' . ($c['fr'] ?? ($c['label'] ?? ''))), 90), $list), static fn (string $t): bool => $t !== ''));
                $out[(int) $id] = [
                    'reasons' => $label((array) ($x['codes']['reasons'] ?? [])),
                    'measures' => $label((array) ($x['codes']['measures'] ?? [])),
                    'physical' => self::line(SseWantedPhysical::shortLine((array) ($x['physical'] ?? []), 'fr'), 200),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Parmi ces identités, celles d'un membre de la communauté (rattachées à un compte).
     *
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

            return [
                'profiles' => $repo->profiles($tenantId, $ids),
                'links' => $repo->linksFor($tenantId, $ids),
                'aliases' => $repo->aliasesFor($tenantId, $ids),
            ];
        } catch (\Throwable) {
            // Fichiers pas encore migrés : avis seuls, sans alias ni lien (jamais d'erreur).
            return ['profiles' => [], 'links' => [], 'aliases' => []];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(int $tenantId): array
    {
        try {
            return (new SseWantedSettingsRepository($this->db))->get($tenantId);
        } catch (\Throwable) {
            return SseWantedCatalog::DEFAULTS;
        }
    }

    /** @param array<string, mixed> $row ligne d'avis jointe à son identité */
    private function isMember(array $row): bool
    {
        // Identité introuvable (jointure vide) : écartée aussi (fail-closed, comme pour les images de membres).
        if (($row['user_id'] ?? null) === null && ($row['last_name'] ?? null) === null && ($row['first_name'] ?? null) === null) {
            return true;
        }

        return (int) ($row['user_id'] ?? 0) > 0;
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

    /**
     * @param array<string, array<int, string>> $map
     */
    private static function pick(array $map, mixed $v, string $default): string
    {
        return is_string($v) && isset($map[$v]) ? $v : $default;
    }

    private static function line(mixed $v, int $max): string
    {
        return SseWantedCatalog::line($v, $max);
    }

    private static function cut(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, max(1, $max - 1))) . '…' : $s;
    }

    /** Jour « AAAA-MM-JJ » d'une date, ou vide. */
    private static function day(mixed $v): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $s) === 1 ? substr($s, 0, 10) : '';
    }

    /** Horodatage « AAAA-MM-JJ HH:MM » (UTC), ou vide. */
    private static function stamp(mixed $v): string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $s) === 1 ? str_replace('T', ' ', substr($s, 0, 16)) : self::day($s);
    }
}
