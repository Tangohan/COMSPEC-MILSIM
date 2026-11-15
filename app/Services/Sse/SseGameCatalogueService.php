<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseDigitalLabRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseAcquisitionContexts;
use App\Support\SseDocumentTypes;
use App\Support\SseFileCatalog;
use App\Support\SseFileVisibility;
use App\Support\SseWantedCatalog;

/**
 * Catalogue du mod SSE (ICD-SSE-CATALOGUE-001, docs/technical/ICD-SSE-CATALOGUE.md) : les listes que les modules Zeus,
 * les attributs Eden et les écrans du joueur proposent au lieu d'une saisie libre ou d'une liste figée dans le mod.
 *
 * Un seul paquet, borné à la communauté de la clé : référentiels (contextes d'acquisition, statuts, types de pièces, de
 * supports, de sites et de papiers, mentions, niveaux) puis listes vivantes (dossiers et sites ouverts, campagnes, avis
 * de recherche actifs, personnes du lore de la session). Rien de plus que ce que les routes du jeu donnent déjà à cette
 * clé : des identifiants, des références et des noms affichés ; jamais d'état civil, de photo, de motif ni de consigne.
 *
 * Deux formes pour le même contenu : JSON (GET /api/game/v1/sse/catalogue) et pages parseSimpleArray de PAGE_BYTES au
 * plus (kind « sse_catalogue » de POST /api/sse/identity/checks, la seule voie que COMSPEC Link sait déjà emprunter).
 */
final class SseGameCatalogueService
{
    public const VERSION = 1;
    public const KIND = 'sse_catalogue';
    /** Une page, avec la marge pour « OK|id|ref » (8 000 octets au plus côté DLL). */
    public const PAGE_BYTES = 7400;
    /** Durée de validité conseillée au client, en secondes. */
    public const TTL = 600;
    public const LABEL_MAX = 80;
    public const MAX_PAGES = 12;

    /** Plafond de chaque liste vivante. */
    public const LIMITS = ['cases' => 80, 'sites' => 80, 'campaigns' => 40, 'wanted' => 80, 'lore' => 150];

    /** Sections, dans l'ordre du paquet, et colonnes de chaque ligne (ordre des tableaux de la forme SQF). */
    public const SECTIONS = [
        'contexts' => ['code', 'fr', 'en', 'group'],
        'statuses' => ['code', 'fr', 'en'],
        'pieces' => ['code', 'fr', 'en'],
        'supports' => ['code', 'fr', 'en'],
        'site_types' => ['code', 'fr', 'en'],
        'documents' => ['code', 'fr', 'en'],
        'mentions' => ['code', 'fr', 'en'],
        'threats' => ['code', 'fr', 'en'],
        'notice_types' => ['code', 'fr', 'en'],
        'cases' => ['id', 'ref', 'name'],
        'sites' => ['id', 'ref', 'name', 'type', 'case'],
        'campaigns' => ['key', 'name'],
        'wanted' => ['id', 'ref', 'name', 'level', 'type'],
        'lore' => ['uid', 'ref', 'name'],
    ];

    /** Libellés anglais des référentiels du site écrits en français (mêmes clés que la constante citée). */
    public const EN = [
        'statuses' => ['civil' => 'Civilian', 'combattant' => 'Fighter', 'detenu' => 'Detainee', 'prioritaire' => 'Priority person'],
        'pieces' => [
            'arme' => 'Weapons', 'munition' => 'Ammunition', 'document' => 'Documents', 'radio' => 'Signals equipment',
            'medical' => 'Medical equipment', 'numerique' => 'Digital media', 'valeur' => 'Valuables', 'autre' => 'Other',
        ],
        'supports' => [
            'ordinateur' => 'Computer', 'disque_dur' => 'Hard drive', 'ssd' => 'SSD', 'cle_usb' => 'USB stick',
            'carte_memoire' => 'Memory card', 'telephone' => 'Phone', 'tablette' => 'Tablet', 'appareil_photo' => 'Camera',
            'gps' => 'GPS', 'radio_numerique' => 'Digital radio', 'support_amovible' => 'Removable media',
            'image_disque' => 'Disk image (simulation)', 'compte_service' => 'Account or online service',
        ],
        'site_types' => [
            'habitation' => 'Dwelling', 'depot' => 'Depot', 'poste_ennemi' => 'Enemy command post', 'cache' => 'Cache',
            'vehicule' => 'Searched vehicle', 'autre' => 'Other',
        ],
        'mentions' => [
            'armed_dangerous' => 'Armed and dangerous', 'warrant' => 'Warrant', 'wanted' => 'Wanted',
            'missing' => 'Missing person', 'flagged' => 'Flagged', 'caution' => 'Caution',
        ],
    ];

    private Database $db;

    /** @var \Closure(int, int): bool */
    private \Closure $canSeeHidden;

    /**
     * @param (\Closure(int, int): bool)|null $canSeeHidden le compte relié en jeu peut-il lire le caché MJ ? (communauté, compte)
     */
    public function __construct(?Database $db = null, ?\Closure $canSeeHidden = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->canSeeHidden = $canSeeHidden
            ?? static fn (int $t, int $u): bool => (new \App\Services\Atak\AtakGameAdminService())->can($t, $u, ['atak.sse.lore.manage', 'admin.access']);
    }

    /**
     * Le catalogue de la communauté.
     *
     * @param string $op code d'opération de la mission (session de lore) ; vide : pas de section « lore »
     * @param int|null $gameUserId compte relié en jeu (jeton de jeu) ; une clé de communauté seule ne dit pas qui appelle
     * @return array{version: int, revision: string, generated_at: string, ttl: int, map: int, op: string,
     *               lore_session: array{id: int, op: string, title: string}|null,
     *               sections: array<string, list<array<string, int|string>>>, truncated: list<string>}
     */
    public function build(int $tenantId, int $mapId, string $op = '', ?int $gameUserId = null): array
    {
        $truncated = [];
        $cut = static function (string $section, array $rows) use (&$truncated): array {
            $max = self::LIMITS[$section];
            if (count($rows) > $max) {
                $truncated[] = $section;
                $rows = array_slice($rows, 0, $max);
            }

            return $rows;
        };

        $cases = $cut('cases', $this->cases($tenantId));
        $caseRefs = array_column($cases, 'ref', 'id');
        $session = null;
        $lore = [];
        $op = SseLoreRepository::validOp($op) ? $op : '';
        if ($op !== '') {
            [$session, $lore] = $this->lore($tenantId, $op, $gameUserId);
        }

        $sections = [
            'contexts' => $this->contexts($tenantId),
            'statuses' => self::pairs(SsePersonRepository::STATUS_LABELS, self::EN['statuses']),
            'pieces' => self::pairs(SseSiteRepository::SEIZURE_LABELS, self::EN['pieces']),
            'supports' => self::pairs(SseDigitalLabRepository::DEVICE_TYPES, self::EN['supports']),
            'site_types' => self::pairs(SseSiteRepository::TYPE_LABELS, self::EN['site_types']),
            'documents' => $this->documents(),
            'mentions' => self::pairs(SseFileCatalog::MENTIONS, self::EN['mentions']),
            'threats' => self::couples(SseWantedCatalog::THREATS),
            'notice_types' => self::couples($this->noticeTypes($tenantId)),
            'cases' => $cases,
            'sites' => $cut('sites', $this->sites($tenantId, $mapId, $caseRefs)),
            'campaigns' => $cut('campaigns', $this->campaigns($tenantId)),
            'wanted' => $cut('wanted', $this->wanted($tenantId)),
            'lore' => $cut('lore', $lore),
        ];

        return [
            'version' => self::VERSION,
            'revision' => substr(sha1((string) json_encode([self::VERSION, $sections, $session, $truncated], JSON_UNESCAPED_UNICODE)), 0, 16),
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'ttl' => self::TTL,
            'map' => $mapId,
            'op' => $op,
            'lore_session' => $session,
            'sections' => $sections,
            'truncated' => $truncated,
        ];
    }

    /**
     * Garde seulement les sections demandées (liste vide ou inconnue : tout). La révision reste celle du paquet entier.
     *
     * @param array<string, mixed> $catalogue
     * @param list<string> $only
     * @return array<string, mixed>
     */
    public static function only(array $catalogue, array $only): array
    {
        $keep = array_values(array_intersect(array_keys(self::SECTIONS), $only));
        if ($keep !== []) {
            $catalogue['sections'] = array_intersect_key((array) $catalogue['sections'], array_flip($keep));
        }

        return $catalogue;
    }

    /**
     * Le catalogue découpé en pages parseSimpleArray :
     * [version, "révision", "généré le", ttl, page, page suivante (0 = fin), [["section", [lignes…]], …], [sections tronquées], [session de lore]].
     * Une ligne est le tableau des colonnes de SECTIONS, dans l'ordre. Une section peut être répartie sur deux pages.
     *
     * @param array<string, mixed> $catalogue
     * @return list<string>
     */
    public static function pages(array $catalogue): array
    {
        $s = $catalogue['lore_session'] ?? null;
        $tail = SseLoreService::sqf(array_values((array) ($catalogue['truncated'] ?? [])))
            . ',' . SseLoreService::sqf(is_array($s) ? [(int) $s['id'], (string) $s['op'], (string) $s['title']] : []);
        $headRoom = 96 + strlen($tail);

        /** @var list<array<string, list<string>>> $pages */
        $pages = [[]];
        $size = $headRoom;
        foreach ((array) ($catalogue['sections'] ?? []) as $name => $rows) {
            $columns = self::SECTIONS[$name] ?? null;
            if ($columns === null) {
                continue;
            }
            // Section vide : annoncée quand même, pour que le client remplace sa liste (dossier clos, avis levé).
            $last = count($pages) - 1;
            $pages[$last][$name] ??= [];
            $size += strlen((string) $name) + 8;
            foreach ((array) $rows as $row) {
                $cells = [];
                foreach ($columns as $c) {
                    $cells[] = $row[$c] ?? '';
                }
                $text = SseLoreService::sqf($cells);
                $len = strlen($text) + 1;
                if ($len + $headRoom + strlen((string) $name) + 8 > self::PAGE_BYTES) {
                    continue;
                }
                if ($size + $len > self::PAGE_BYTES) {
                    if (count($pages) >= self::MAX_PAGES) {
                        break 2;
                    }
                    $pages[] = [$name => []];
                    $size = $headRoom + strlen((string) $name) + 8;
                }
                $pages[count($pages) - 1][$name][] = $text;
                $size += $len;
            }
        }

        $out = [];
        $count = count($pages);
        foreach ($pages as $i => $page) {
            $blocks = [];
            foreach ($page as $name => $texts) {
                $blocks[] = '[' . SseLoreService::sqf((string) $name) . ',[' . implode(',', $texts) . ']]';
            }
            $out[] = '[' . self::VERSION . ',' . SseLoreService::sqf((string) $catalogue['revision']) . ',' . SseLoreService::sqf((string) $catalogue['generated_at'])
                . ',' . (int) ($catalogue['ttl'] ?? self::TTL) . ',' . $i . ',' . ($i + 1 < $count ? $i + 1 : 0)
                . ',[' . implode(',', $blocks) . '],' . $tail . ']';
        }

        return $out;
    }

    /**
     * Réponse « révision déjà connue » de la forme SQF : même en-tête, aucune section, drapeau final à true.
     */
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

    // ------------------------------------------------------------ Référentiels

    /** @return list<array{code: string, fr: string, en: string, group: string}> */
    private function contexts(int $tenantId): array
    {
        $out = [];
        foreach (SseAcquisitionContexts::forTenant($tenantId)->entries(true) as $e) {
            $out[] = ['code' => (string) $e['code'], 'fr' => self::clean($e['fr']), 'en' => self::clean($e['en']), 'group' => (string) $e['group']];
        }

        return $out;
    }

    /** @return list<array{code: string, fr: string, en: string}> */
    private function documents(): array
    {
        $out = [];
        foreach (SseDocumentTypes::builtIn() as $key => $t) {
            $fr = self::clean($t['displayName'] ?? $key);
            $out[] = ['code' => (string) $key, 'fr' => $fr, 'en' => self::clean($t['displayNameEn'] ?? '') ?: $fr];
        }

        return $out;
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function noticeTypes(int $tenantId): array
    {
        try {
            return SseWantedCatalog::types((new SseWantedSettingsRepository($this->db))->get($tenantId));
        } catch (\Throwable) {
            return SseWantedCatalog::TYPES;
        }
    }

    /**
     * @param array<string, string> $fr
     * @param array<string, string> $en
     * @return list<array{code: string, fr: string, en: string}>
     */
    private static function pairs(array $fr, array $en): array
    {
        $out = [];
        foreach ($fr as $code => $label) {
            $out[] = ['code' => (string) $code, 'fr' => self::clean($label), 'en' => self::clean($en[$code] ?? $label)];
        }

        return $out;
    }

    /**
     * @param array<string, array{0: string, 1: string}> $map
     * @return list<array{code: string, fr: string, en: string}>
     */
    private static function couples(array $map): array
    {
        $out = [];
        foreach ($map as $code => $labels) {
            $out[] = ['code' => (string) $code, 'fr' => self::clean($labels[0]), 'en' => self::clean($labels[1])];
        }

        return $out;
    }

    // ------------------------------------------------------------ Listes vivantes

    /**
     * Dossiers ouverts au niveau de diffusion le plus bas (celui que le jeu lit), hors classeurs.
     *
     * @return list<array{id: int, ref: string, name: string}>
     */
    private function cases(int $tenantId): array
    {
        $sql = "SELECT id, reference_code, title FROM sse_cases
                WHERE tenant_id = :t AND classification = :c AND status IN ('ouvert', 'en_cours')%s
                ORDER BY id DESC LIMIT " . (self::LIMITS['cases'] + 1);
        $params = ['t' => $tenantId, 'c' => SseCaseRepository::CLASS_INTERNAL];
        try {
            $rows = $this->db->fetchAll(sprintf($sql, ' AND (is_folder = 0 OR is_folder IS NULL)'), $params);
        } catch (\Throwable) {
            $rows = $this->safeFetch(sprintf($sql, ''), $params);
        }
        $out = [];
        foreach ($rows as $r) {
            $ref = self::clean($r['reference_code'] ?? '');
            if ($ref !== '') {
                $out[] = ['id' => (int) $r['id'], 'ref' => $ref, 'name' => self::clean($r['title'] ?? '')];
            }
        }

        return $out;
    }

    /**
     * Sites ouverts de la carte. Le dossier d'un site n'est nommé que s'il figure dans la liste des dossiers.
     *
     * @param array<int, string> $caseRefs
     * @return list<array{id: int, ref: string, name: string, type: string, case: string}>
     */
    private function sites(int $tenantId, int $mapId, array $caseRefs): array
    {
        $out = [];
        foreach ($this->safeFetch(
            "SELECT id, reference_code, name, site_type, case_id FROM sse_sites
             WHERE tenant_id = :t AND context_id = :c AND status IN ('ouvert', 'en_cours')
             ORDER BY id DESC LIMIT " . (self::LIMITS['sites'] + 1),
            ['t' => $tenantId, 'c' => $mapId]
        ) as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'ref' => self::clean($r['reference_code'] ?? ''),
                'name' => self::clean($r['name'] ?? ''),
                'type' => SseSiteRepository::normalizeType((string) ($r['site_type'] ?? '')),
                'case' => $caseRefs[(int) ($r['case_id'] ?? 0)] ?? '',
            ];
        }

        return $out;
    }

    /** @return list<array{key: string, name: string}> */
    private function campaigns(int $tenantId): array
    {
        $out = [];
        foreach ($this->safeFetch(
            "SELECT campaign_key, name FROM sse_campaigns WHERE tenant_id = :t AND status = 'active'
             ORDER BY COALESCE(updated_at, created_at) DESC LIMIT " . (self::LIMITS['campaigns'] + 1),
            ['t' => $tenantId]
        ) as $r) {
            $out[] = ['key' => (string) $r['campaign_key'], 'name' => self::clean($r['name'] ?? '')];
        }

        return $out;
    }

    /**
     * Avis de recherche actifs : identifiant, référence, nom affiché, niveau de menace, type. Ni motif, ni consigne, ni photo.
     *
     * @return list<array{id: int, ref: string, name: string, level: string, type: string}>
     */
    private function wanted(int $tenantId): array
    {
        $out = [];
        try {
            if (!SseWantedNoticeRepository::ready()) {
                return [];
            }
            $rows = (new SseWantedNoticeRepository($this->db))->list($tenantId, ['status' => 'active', 'limit' => self::LIMITS['wanted'] + 1]);
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $n) {
            $out[] = [
                'id' => (int) $n['id'],
                'ref' => self::clean($n['ref'] ?? ''),
                'name' => self::clean($n['identity_name'] ?? ''),
                'level' => (string) ($n['threat_level'] ?? ''),
                'type' => (string) ($n['notice_type'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Personnes du lore de la session : identifiant d'IA, référence du registre et nom. Le nom suit la règle de la
     * redescente du lore (ICD-SSE-LORE-001) : donné s'il est « connu des fichiers » à la phase en cours, ou si le compte
     * relié peut lire le caché MJ ; sinon vide (la référence seule).
     *
     * @return array{0: array{id: int, op: string, title: string}|null, 1: list<array{uid: string, ref: string, name: string}>}
     */
    private function lore(int $tenantId, string $op, ?int $gameUserId): array
    {
        try {
            $repo = new SseLoreRepository($this->db);
            $session = $repo->findByOp($tenantId, $op);
            if ($session === null) {
                return [null, []];
            }
            $sid = (int) $session['id'];
            $phase = (int) $session['phase'];
            $hidden = $gameUserId !== null && $gameUserId > 0 && ($this->canSeeHidden)($tenantId, $gameUserId);
            $members = $repo->members($tenantId, $sid, self::LIMITS['lore'] + 1);
            $identities = new SseIdentityRepository($this->db);
            $profiles = (new SseFileRecordRepository($this->db))->profiles($tenantId, array_column($members, 'identity_id'));
            $out = [];
            foreach ($members as $m) {
                $iid = (int) $m['identity_id'];
                $identity = $identities->find($iid, $tenantId);
                if ($identity === null) {
                    continue;
                }
                $visible = $hidden || SseFileVisibility::field($profiles[$iid] ?? null, 'identity', $phase);
                $name = trim(mb_strtoupper((string) $identity['last_name']) . ' ' . (string) $identity['first_name']);
                $out[] = [
                    'uid' => self::clean($m['ai_uid'] ?? $identity['arma_key'] ?? ''),
                    'ref' => self::clean($identity['ref'] ?? ''),
                    'name' => $visible ? self::clean($name) : '',
                ];
            }

            return [['id' => $sid, 'op' => $op, 'title' => self::clean($session['title'] ?? '')], $out];
        } catch (\Throwable) {
            return [null, []];
        }
    }

    // ------------------------------------------------------------ Outils

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

    /** Texte d'une ligne : une seule ligne, sans caractère de contrôle, LABEL_MAX caractères au plus. */
    private static function clean(mixed $v): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        $s = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s));
        $s = (string) preg_replace('/\s{2,}/u', ' ', $s);

        return mb_strlen($s) > self::LABEL_MAX ? rtrim(mb_substr($s, 0, self::LABEL_MAX - 1)) . '…' : $s;
    }
}
