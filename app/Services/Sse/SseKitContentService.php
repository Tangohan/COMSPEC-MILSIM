<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdentityStoryRepository;
use App\Repositories\SseIdOperationRepository;
use App\Repositories\SseKitBatchRepository;
use App\Repositories\SseLoreRepository;
use App\Support\SseIdRealCountryCatalog;
use App\Support\SseKitCatalog;

/**
 * Portail SSE › Générer du contenu : peuple une communauté avec un réseau fictif tiré des kits (SseKitContentGenerator).
 *
 * Aperçu sans écriture, puis création en un LOT identifiable (étiquette, auteur, date, graine) dans les tables
 * existantes, par les repositories existants : identités « generated », papiers émis, fiche (signalement, alias,
 * adresses, téléphones, véhicules, personnes liées), récit, recherchés de l'opération, et lore de session si demandé.
 * Le lot se supprime en bloc (SseKitBatchRepository::purge) sans toucher au reste.
 *
 * Garde-fous : plafond par lot et par communauté, plafond de papiers du paquet de jeu, pas de doublon avec l'existant
 * (noms, téléphones, plaques, numéros de papiers), tout est marqué fictif et généré, tout est borné à la communauté.
 */
final class SseKitContentService
{
    public const MAX_PEOPLE = SseKitContentGenerator::MAX_PEOPLE;

    /** Identités de contenu généré gardées en même temps par une communauté. */
    public const MAX_COMMUNITY = 1500;

    public const PREVIEW_SAMPLE = 12;

    public const MARK = 'FICTIF · GÉNÉRÉ';

    public const DEFAULTS = [
        'people' => 40, 'civil_pct' => 70, 'link_density' => 40, 'forged_pct' => 15, 'alias_pct' => 30, 'wanted_pct' => 10,
        'vehicles_pp' => 4, 'docs_pp' => 2, 'lang' => 'fr',
    ];

    public function __construct(
        private ?SseKitContentGenerator $generator = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdOperationRepository $operations = null,
        private ?SseFileRecordRepository $records = null,
        private ?SseKitBatchRepository $batches = null,
    ) {
        $this->generator ??= new SseKitContentGenerator();
    }

    /**
     * Paramètres saisis ramenés aux valeurs permises.
     *
     * @param array<string, mixed> $in
     * @return array{kits: list<string>, themes: list<string>, people: int, civil_pct: int, link_density: int, forged_pct: int,
     *     alias_pct: int, wanted_pct: int, vehicles_pp: int, docs_pp: int, lang: string, label: string, op_key: string, lore_session_id: int}
     */
    public static function normalizeParams(array $in): array
    {
        $int = static fn (mixed $v, int $min, int $max, int $def): int => is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
        $list = static fn (mixed $v): array => array_values(array_unique(array_map(
            static fn (mixed $x): string => trim(is_scalar($x) ? (string) $x : ''),
            is_array($v) ? $v : []
        )));
        $kits = array_values(array_unique(array_intersect(array_map('strtoupper', $list($in['kits'] ?? [])), SseKitCatalog::codes())));
        if ($kits === []) {
            $kits = [SseKitCatalog::codes()[0]];
        }
        $themes = array_values(array_intersect($list($in['themes'] ?? []), SseKitCatalog::networkThemes()));
        $op = trim(is_scalar($in['op_key'] ?? null) ? (string) $in['op_key'] : '');
        $d = self::DEFAULTS;

        return [
            'kits' => array_slice($kits, 0, 6),
            'themes' => $themes,
            'people' => $int($in['people'] ?? null, 1, self::MAX_PEOPLE, $d['people']),
            // Population civile : tout le lot quand aucun réseau n'est choisi.
            'civil_pct' => $themes === [] ? 100 : $int($in['civil_pct'] ?? null, 0, 100, $d['civil_pct']),
            'link_density' => $int($in['link_density'] ?? null, 0, 100, $d['link_density']),
            'forged_pct' => $int($in['forged_pct'] ?? null, 0, 100, $d['forged_pct']),
            'alias_pct' => $int($in['alias_pct'] ?? null, 0, 100, $d['alias_pct']),
            'wanted_pct' => $int($in['wanted_pct'] ?? null, 0, 100, $d['wanted_pct']),
            'vehicles_pp' => $int($in['vehicles_pp'] ?? null, 0, 20, $d['vehicles_pp']),
            'docs_pp' => $int($in['docs_pp'] ?? null, 1, 4, $d['docs_pp']),
            'lang' => ($in['lang'] ?? '') === 'en' ? 'en' : 'fr',
            'label' => mb_substr(trim(is_scalar($in['label'] ?? null) ? (string) $in['label'] : ''), 0, 90),
            'op_key' => SseLoreRepository::validOp($op) ? $op : '',
            'lore_session_id' => $int($in['lore_session_id'] ?? null, 0, PHP_INT_MAX, 0),
        ];
    }

    public static function normalizeSeed(mixed $seed): int
    {
        return is_numeric($seed) && (int) $seed > 0 ? min(SseIdentityGeneratorService::MAX_SEED, (int) $seed) : SseIdentityGeneratorService::randomSeed();
    }

    /**
     * Jeu complet pour une communauté (aucune écriture) : mêmes paramètres, même graine, même date et même registre =
     * même résultat, à l'aperçu comme à la création.
     *
     * @param array<string, mixed> $params normalizeParams()
     * @return array<string, mixed>
     */
    public function generate(int $tenantId, array $params, int $seed, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $formats = [];
        foreach ($this->countries()->listForTenant($tenantId, false) as $c) {
            if (in_array($c['code'], $params['kits'], true)) {
                $formats[$c['code']] = ['number_format' => (string) $c['number_format']];
            }
        }
        foreach ($params['kits'] as $code) {
            if (!isset($formats[$code]) && SseIdRealCountryCatalog::has($code)) {
                $formats[$code] = ['number_format' => SseIdRealCountryCatalog::numberFormat($code)];
            }
        }

        return $this->generator->generate($params, $seed, $today, $this->batchRepo()->taken($tenantId), $formats);
    }

    /**
     * Aperçu avant création : compteurs, organisations, échantillon de fiches et ce qui bloquerait la création.
     *
     * @param array<string, mixed> $params normalizeParams()
     * @return array{seed: int, summary: array<string, mixed>, orgs: list<array<string, mixed>>, sample: list<array<string, mixed>>,
     *     blockers: list<string>, room: array{community: int, pack: int}}
     */
    public function preview(int $tenantId, array $params, int $seed, ?string $today = null): array
    {
        $set = $this->generate($tenantId, $params, $seed, $today);
        $people = $set['people'];
        // Échantillon : les chefs d'organisation d'abord, puis des fiches réparties sur le lot.
        $sample = array_values(array_filter($people, static fn (array $p): bool => $p['leader']));
        $step = max(1, intdiv(max(1, count($people)), self::PREVIEW_SAMPLE));
        for ($i = 0; $i < count($people) && count($sample) < self::PREVIEW_SAMPLE; $i += $step) {
            if (!$people[$i]['leader']) {
                $sample[] = $people[$i];
            }
        }

        return [
            'seed' => $seed,
            'summary' => $set['summary'],
            'orgs' => $set['orgs'],
            'sample' => array_slice($sample, 0, self::PREVIEW_SAMPLE),
            'blockers' => $this->blockers($tenantId, $params, $set['summary']),
            'room' => $this->room($tenantId, $params),
        ];
    }

    /**
     * Place restante : identités de contenu généré dans la communauté, papiers dans le paquet de jeu de l'opération.
     *
     * @param array<string, mixed> $params
     * @return array{community: int, pack: int}
     */
    public function room(int $tenantId, array $params): array
    {
        $op = $params['op_key'] !== '' ? (string) $params['op_key'] : null;
        $inPack = $this->documents()->listForPack($tenantId, 0, $op, null, 0, 1)['total'];

        return [
            'community' => max(0, self::MAX_COMMUNITY - $this->batchRepo()->identitiesCount($tenantId)),
            'pack' => max(0, SseIdDocumentRepository::PACK_MAX_PER_MAP - $inPack),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $summary
     * @return list<string>
     */
    private function blockers(int $tenantId, array $params, array $summary): array
    {
        $room = $this->room($tenantId, $params);
        $out = [];
        if ((int) $summary['people'] === 0) {
            $out[] = 'Rien à générer : choisissez au moins un kit et une personne.';
        }
        if ((int) $summary['people'] > $room['community']) {
            $out[] = 'Plafond de la communauté atteint : ' . self::MAX_COMMUNITY . ' personnes générées au plus en même temps (place restante : '
                . $room['community'] . '). Supprimez un ancien lot ou réduisez le nombre de personnes.';
        }
        if ((int) $summary['documents'] > $room['pack']) {
            $out[] = 'Trop de papiers pour le jeu : le serveur de jeu en charge ' . SseIdDocumentRepository::PACK_MAX_PER_MAP . ' au plus par opération (place restante : '
                . $room['pack'] . ', ' . (int) $summary['documents'] . ' demandés). Réduisez le lot, ou donnez-lui un code d’opération pour le sortir du paquet commun.';
        }
        if ((int) $params['lore_session_id'] > 0) {
            $session = (new SseLoreRepository())->find($tenantId, (int) $params['lore_session_id']);
            if ($session === null) {
                $out[] = 'Session de lore introuvable dans votre communauté.';
            } elseif (!empty($session['locked'])) {
                $out[] = 'Session de lore verrouillée : déverrouillez-la avant d’y ajouter des personnes.';
            } elseif ((new SseLoreRepository())->membersCount($tenantId, (int) $session['id']) + (int) $summary['people'] > SseLoreService::MAX_PERSONS) {
                $out[] = 'Session de lore pleine : ' . SseLoreService::MAX_PERSONS . ' personnes au plus.';
            }
        }

        return $out;
    }

    /**
     * Crée le lot dans la communauté.
     *
     * @param array<string, mixed> $params normalizeParams()
     * @return array{ok: bool, error?: string, batch_id?: int, identities?: int, documents?: int, links?: int, wanted?: int}
     */
    public function commit(int $tenantId, array $params, int $seed, ?int $actorId, string $actorLabel = '', ?string $today = null): array
    {
        if ($tenantId < 1) {
            return ['ok' => false, 'error' => 'Communauté inconnue.'];
        }
        $today ??= gmdate('Y-m-d');
        $set = $this->generate($tenantId, $params, $seed, $today);
        $blockers = $this->blockers($tenantId, $params, $set['summary']);
        if ($blockers !== []) {
            return ['ok' => false, 'error' => $blockers[0]];
        }
        $lang = $params['lang'];
        $opKey = $params['op_key'] !== '' ? (string) $params['op_key'] : null;
        $kitNames = array_map(static fn (string $c): string => SseKitCatalog::text(SseKitCatalog::kit($c)['name'], $lang), $params['kits']);
        $label = self::MARK . ' — ' . ($params['label'] !== '' ? $params['label'] : implode(', ', $kitNames));

        $pdo = Database::getPdo();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            // Les pays des kits entrent au registre de la communauté (pays réels stylisés du jeu), sans écraser un pays créé à la main.
            $this->countries()->importRealSet($tenantId, $params['kits'], $actorId);

            $batchId = $this->operations()->createBatch($tenantId, null, $opKey, $label, [
                'generator' => SseKitBatchRepository::GENERATOR, 'fictional' => true, 'kits' => $params['kits'], 'themes' => $params['themes'],
                'people' => $params['people'], 'civil_pct' => $params['civil_pct'], 'link_density' => $params['link_density'],
                'forged_pct' => $params['forged_pct'], 'alias_pct' => $params['alias_pct'], 'wanted_pct' => $params['wanted_pct'],
                'vehicles_pp' => $params['vehicles_pp'], 'docs_pp' => $params['docs_pp'], 'lang' => $lang, 'today' => $today,
                'author' => mb_substr($actorLabel, 0, 80), 'lore_session_id' => (int) $params['lore_session_id'],
            ], $seed, [], $actorId);

            $ids = [];
            $nDocs = $nLinks = $nWanted = 0;
            $stories = new SseIdentityStoryRepository();
            $loreId = (int) $params['lore_session_id'];
            $lore = $loreId > 0 ? new SseLoreRepository() : null;
            foreach ($set['people'] as $p) {
                $kit = SseKitCatalog::kit($p['kit']);
                $context = self::MARK . ' — kit ' . $p['kit'] . ' (' . SseKitCatalog::text($kit['name'], $lang) . '), ' . $p['theme_label']
                    . ($p['org'] !== '' ? ' : ' . $p['role_label'] . ', ' . $p['org'] : '') . '.';
                $identityId = $this->identities()->create($tenantId, [
                    'kind' => 'generated', 'last_name' => $p['last_name'], 'first_name' => $p['first_name'], 'sex' => $p['sex'],
                    'birth_date' => $p['birth_date'], 'birth_place' => $p['birth_place'], 'nationality' => $p['nationality'],
                    'profession' => $p['profession'], 'seed' => $p['seed'], 'op_key' => $opKey, 'batch_id' => $batchId,
                    'category' => $p['category'], 'side' => $p['side'], 'faction' => $p['org'], 'language' => (string) ($kit['langCode'] ?? ''),
                    'zone' => $p['zone'], 'alias' => $p['alias'], 'notes' => $context . "\n" . $p['story'],
                ], $actorId);
                $ids[$p['index']] = $identityId;

                foreach ($p['documents'] as $n => $d) {
                    $number = (string) $d['doc_number'];
                    for ($k = 0; $k < 25 && $this->documents()->numberExists($tenantId, $d['country_code'], $d['doc_type'], $number); $k++) {
                        $number = (new SseKitRng($seed, 'dup' . $p['index'] . ':' . $n . ':' . $k))->pattern(self::shape($number));
                    }
                    if ($this->documents()->numberExists($tenantId, $d['country_code'], $d['doc_type'], $number)) {
                        continue;
                    }
                    $docId = $this->documents()->create($tenantId, [
                        'identity_id' => $identityId, 'doc_number' => $number, 'op_key' => $opKey, 'batch_id' => $batchId,
                        'notes' => self::MARK . ($d['note'] !== '' ? ' — ' . $d['note'] : ''),
                    ] + $d, $actorId);
                    $this->documents()->transition($docId, $tenantId, 'validated', $actorId);
                    $this->documents()->transition($docId, $tenantId, 'issued', $actorId);
                    $nDocs++;
                }

                $network = $p['org'] !== '';
                $knowledge = array_filter([
                    self::MARK,
                    $network ? ($lang === 'en' ? 'Real role: ' : 'Rôle réel : ') . $p['role_label'] . ' — ' . $p['org'] : null,
                    $p['false_name'] !== '' ? ($lang === 'en' ? 'Also known under the false name ' : 'Aussi sous le faux nom ') . $p['false_name'] : null,
                    $p['pocket'] !== [] ? ($lang === 'en' ? 'Pocket items: ' : 'Pièces annexes : ') . implode(' ; ', $p['pocket']) : null,
                ]);
                $profile = [
                    'country' => $p['kit'], 'height_cm' => $p['height_cm'], 'weight_kg' => $p['weight_kg'], 'build' => $p['build'], 'hair' => $p['hair'],
                    'eyes' => $p['eyes'], 'marks' => $p['marks'], 'caution_codes' => $p['cautions'], 'in_files' => 1,
                    'affiliation' => $p['org'], 'cover_note' => $network ? ($lang === 'en' ? 'Cover: ' : 'Couverture : ') . $p['profession'] : '',
                    'knowledge' => implode("\n", $knowledge),
                    // Vérité de jeu : l'appartenance au réseau et la couverture restent au MJ tant qu'il ne les révèle pas.
                    'visibility' => $network ? ['affiliation' => ['v' => 'hidden'], 'cover' => ['v' => 'hidden'], 'cautions' => ['v' => 'hidden']] : [],
                ];
                if ($lore !== null) {
                    $profile['lore_session_id'] = $loreId;
                }
                $this->records()->saveProfile($tenantId, $identityId, $profile, $actorId);
                if ($p['alias'] !== '') {
                    $this->records()->addAlias($tenantId, $identityId, $p['alias'], 'nickname', 'known', 0, $actorId);
                }
                if ($p['false_name'] !== '') {
                    $this->records()->addAlias($tenantId, $identityId, $p['false_name'], 'aka', 'hidden', 0, $actorId);
                }
                $this->records()->addLink($tenantId, $identityId, ['kind' => 'address', 'value' => $p['address']], $actorId);
                foreach ($p['phones'] as $ph) {
                    $this->records()->addLink($tenantId, $identityId, ['kind' => 'phone', 'value' => $ph['number'], 'detail' => ['imsi' => $ph['imsi']]], $actorId);
                }
                foreach ($p['vehicles'] as $v) {
                    $this->records()->addLink($tenantId, $identityId, [
                        'kind' => 'vehicle', 'value' => $v['plate'], 'flag' => $v['flag'],
                        'detail' => ['make' => $v['make'], 'model' => $v['model'], 'color' => $v['color'], 'type' => $v['kind']],
                    ], $actorId);
                }
                $nLinks += 1 + count($p['phones']) + count($p['vehicles']);
                if ($p['wanted_level'] !== null) {
                    $this->operations()->addWanted($tenantId, null, $opKey, $identityId, (string) $p['wanted_level'], $p['alias'], [], self::MARK, $batchId, $actorId);
                    $nWanted++;
                }
                try {
                    $stories->save($tenantId, $identityId, $p['story'], $actorId);
                } catch (\Throwable) {
                    // Champ « histoire » absent (étape 4450 non passée) : le récit reste dans les notes de l'identité.
                }
                if ($lore !== null) {
                    $lore->addMember($tenantId, $loreId, $identityId, null, 'web');
                }
            }
            // Personnes liées : famille, hiérarchie, associés (un lien par relation, lisible des deux fiches).
            foreach ($set['links'] as $l) {
                if (!isset($ids[$l['from']], $ids[$l['to']])) {
                    continue;
                }
                $to = $set['people'][$l['to']];
                $this->records()->addLink($tenantId, $ids[$l['from']], [
                    'kind' => 'associate', 'value' => trim($to['first_name'] . ' ' . $to['last_name']), 'linked_identity_id' => $ids[$l['to']],
                    'relation' => $l['relation'], 'detail' => ['lien' => (string) $l['label']],
                    'visibility' => in_array($l['relation'], ['family', 'spouse'], true) ? 'known' : 'hidden',
                ], $actorId);
                $nLinks++;
            }
            if ($lore !== null) {
                $lore->touch($tenantId, $loreId);
            }
            $this->operations()->setBatchCounts($batchId, $tenantId, count($ids), $nDocs);
            if ($own) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        SseIdMassGeneratorService::audit($tenantId, $actorId, 'kit_batch.create', 'sse_id_batch', $batchId, [
            'generator' => SseKitBatchRepository::GENERATOR, 'kits' => $params['kits'], 'themes' => $params['themes'], 'seed' => $seed, 'op' => $opKey,
            'identities' => count($ids), 'documents' => $nDocs, 'links' => $nLinks, 'wanted' => $nWanted,
        ]);

        return ['ok' => true, 'batch_id' => $batchId, 'identities' => count($ids), 'documents' => $nDocs, 'links' => $nLinks, 'wanted' => $nWanted];
    }

    /**
     * Supprime en bloc un lot de contenu généré de la communauté.
     *
     * @return array{identities: int, documents: int, links: int}|null null : lot introuvable (ou d'un autre type)
     */
    public function delete(int $tenantId, int $batchId, ?int $actorId): ?array
    {
        $batch = $this->batchRepo()->find($tenantId, $batchId);
        if ($batch === null) {
            return null;
        }
        $done = $this->batchRepo()->purge($tenantId, $batchId);
        if ($done !== null) {
            SseIdMassGeneratorService::audit($tenantId, $actorId, 'kit_batch.delete', 'sse_id_batch', $batchId, $done + ['label' => $batch['label'], 'seed' => $batch['seed']]);
        }

        return $done;
    }

    /** @return list<array<string, mixed>> */
    public function listBatches(int $tenantId): array
    {
        return $this->batchRepo()->list($tenantId);
    }

    /** Motif d'un numéro déjà tiré (« IQK674034 » -> « IQ@###### ») pour en retirer un autre de même forme. */
    private static function shape(string $number): string
    {
        $prefix = mb_substr($number, 0, 2);
        $rest = (string) preg_replace(['/\d/', '/\p{L}/u'], ['#', '@'], mb_substr($number, 2));

        return $prefix . $rest;
    }

    private function identities(): SseIdentityRepository
    {
        return $this->identities ??= new SseIdentityRepository();
    }

    private function documents(): SseIdDocumentRepository
    {
        return $this->documents ??= new SseIdDocumentRepository();
    }

    private function countries(): SseIdCountryRepository
    {
        return $this->countries ??= new SseIdCountryRepository();
    }

    private function operations(): SseIdOperationRepository
    {
        return $this->operations ??= new SseIdOperationRepository();
    }

    private function records(): SseFileRecordRepository
    {
        return $this->records ??= new SseFileRecordRepository();
    }

    private function batchRepo(): SseKitBatchRepository
    {
        return $this->batches ??= new SseKitBatchRepository();
    }
}
