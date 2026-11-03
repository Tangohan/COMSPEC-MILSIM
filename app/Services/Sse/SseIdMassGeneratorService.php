<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdOperationRepository;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdWorkshopCatalog;

/**
 * Préparation de mission › Identités et couvertures (COMSPEC IDENTITY, Lot 6) : population d'une opération générée en
 * un coup (civils, militaires, suspects, HVT), avec leurs papiers (part de faux, d'expirés, de volés).
 *
 * Déterministe : une graine de lot et les mêmes paramètres donnent toujours la même population (même tirage que le
 * générateur du mod, SseIdentityGeneratorService). L'aperçu ne touche pas la base ; la validation enregistre le lot,
 * les identités « generated », leurs papiers émis et les recherchés / HVT de l'opération.
 */
final class SseIdMassGeneratorService
{
    public const MAX_PER_CATEGORY = 100;
    public const MAX_TOTAL = 250;

    /** Camp par défaut de chaque catégorie. */
    private const DEFAULT_SIDES = ['civilian' => 'civ', 'military' => 'east', 'suspect' => 'guer', 'hvt' => 'guer'];

    private const MILITARY_RANKS = ['Soldat', 'Caporal', 'Caporal-chef', 'Sergent', 'Sergent-chef', 'Adjudant', 'Lieutenant', 'Capitaine'];
    private const HVT_PROFESSIONS = ['Homme d’affaires', 'Transporteur', 'Négociant', 'Entrepreneur', 'Notable local', 'Armateur'];
    private const HVT_ALIASES = ['Le Faucon', 'Le Renard', 'Scorpion', 'L’Ingénieur', 'Le Docteur', 'Cobra', 'Le Banquier', 'Vautour', 'Le Professeur', 'Loup gris', 'Le Comptable', 'Corbeau'];
    private const FORGERY_KINDS = ['counterfeit', 'photo_swap', 'data_alteration', 'stolen_blank'];

    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdOperationRepository $operations = null,
        private ?SseIdentityGeneratorService $generator = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->operations ??= new SseIdOperationRepository();
        $this->generator ??= new SseIdentityGeneratorService();
    }

    /**
     * Paramètres saisis ramenés aux valeurs permises. Sans habilitation Confidentiel, aucun faux (vérité de jeu).
     *
     * @param array<string, mixed> $in
     * @param list<string> $countryCodes pays actifs de la communauté
     * @return array<string, mixed>
     */
    public static function normalizeParams(array $in, array $countryCodes, bool $truth): array
    {
        $int = static fn (mixed $v, int $min, int $max, int $def): int => is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
        $text = static fn (mixed $v, int $max): string => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $countries = array_values(array_intersect(array_map(static fn (mixed $c): string => strtoupper(trim(is_scalar($c) ? (string) $c : '')), (array) ($in['countries'] ?? [])), $countryCodes));
        if ($countries === [] && $countryCodes !== []) {
            $countries = [$countryCodes[0]];
        }
        $types = array_values(array_filter(array_keys(SseIdentityCatalog::DOC_TYPES), static fn (string $t): bool => in_array($t, (array) ($in['doc_types'] ?? ['id_card']), true)));
        $cats = [];
        $total = 0;
        foreach (array_keys(SseIdWorkshopCatalog::POP_CATEGORIES) as $cat) {
            $row = is_array($in['categories'][$cat] ?? null) ? $in['categories'][$cat] : [];
            $count = $int($row['count'] ?? 0, 0, self::MAX_PER_CATEGORY, 0);
            $count = min($count, self::MAX_TOTAL - $total);
            $total += $count;
            $forged = $truth ? $int($row['forged'] ?? 0, 0, 100, 0) : 0;
            $expired = $int($row['expired'] ?? 0, 0, 100 - $forged, 0);
            $stolen = $int($row['stolen'] ?? 0, 0, 100 - $forged - $expired, 0);
            $side = (string) ($row['side'] ?? '');
            $cats[$cat] = [
                'count' => $count,
                'side' => isset(SseIdWorkshopCatalog::SIDES[$side]) ? $side : self::DEFAULT_SIDES[$cat],
                'faction' => $text($row['faction'] ?? '', 64),
                'profession' => $text($row['profession'] ?? '', 120),
                'forged' => $forged,
                'expired' => $expired,
                'stolen' => $stolen,
            ];
        }
        $ageMin = $int($in['age_min'] ?? 18, 16, 90, 18);
        $ageMax = $int($in['age_max'] ?? 65, $ageMin, 95, max($ageMin, 65));

        return [
            'label' => $text($in['label'] ?? '', 120),
            'countries' => $countries,
            'doc_types' => $types !== [] ? $types : ['id_card'],
            'language' => strtolower($text($in['language'] ?? '', 8)),
            'zone' => $text($in['zone'] ?? '', 64),
            'age_min' => $ageMin,
            'age_max' => $ageMax,
            'female_pct' => $int($in['female_pct'] ?? 50, 0, 100, 50),
            'visible_sides' => SseIdWorkshopCatalog::sides($in['visible_sides'] ?? []),
            'categories' => $cats,
        ];
    }

    /** Nombre de personnes demandées. */
    public static function total(array $params): int
    {
        return array_sum(array_map(static fn (array $c): int => (int) $c['count'], $params['categories'] ?? []));
    }

    /**
     * Population tirée (sans écrire en base). Même graine et mêmes paramètres = même résultat.
     *
     * @param array<string, mixed> $params normalizeParams()
     * @param array<string, array<string, mixed>> $countriesByCode pays hydratés, par code
     * @return list<array<string, mixed>>
     */
    public function preview(array $params, array $countriesByCode, int $seed, ?string $today = null): array
    {
        $today ??= gmdate('Y-m-d');
        $year = (int) substr($today, 0, 4);
        $codes = array_values(array_filter($params['countries'] ?? [], static fn (string $c): bool => isset($countriesByCode[$c])));
        if ($codes === []) {
            return [];
        }
        $out = [];
        foreach ($params['categories'] as $cat => $c) {
            for ($i = 0; $i < (int) $c['count']; $i++) {
                $s = SseIdentityGeneratorService::hash($seed, $cat . ':' . $i) % (SseIdentityGeneratorService::MAX_SEED + 1);
                $country = $countriesByCode[$codes[SseIdentityGeneratorService::hash($s, 'cty') % count($codes)]];
                $sex = SseIdentityGeneratorService::hash($s, 'sx2') % 100 < (int) $params['female_pct'] ? 'F' : 'M';
                $p = $this->generator->generate($country, $s, $sex);
                $span = (int) $params['age_max'] - (int) $params['age_min'] + 1;
                $age = (int) $params['age_min'] + SseIdentityGeneratorService::hash($s, 'age2') % max(1, $span);
                $p['birth_date'] = sprintf('%04d', $year - $age) . substr($p['birth_date'], 4);
                $p['age'] = $age;
                $p['profession'] = $c['profession'] !== '' ? $c['profession'] : match ($cat) {
                    'military' => 'Militaire',
                    'hvt' => SseIdentityGeneratorService::pick($s, 'hjob', self::HVT_PROFESSIONS),
                    default => $p['profession'],
                };
                $p += [
                    'category' => $cat,
                    'side' => $c['side'],
                    'faction' => $c['faction'],
                    'zone' => (string) $params['zone'],
                    'language' => (string) ($params['language'] !== '' ? $params['language'] : ($country['languages'][0] ?? '')),
                    'alias' => $cat === 'hvt' ? SseIdentityGeneratorService::pick($s, 'alias', self::HVT_ALIASES) : '',
                    'wanted_level' => match ($cat) {
                        'hvt' => 'hvt',
                        'suspect' => 'recherche',
                        default => null,
                    },
                    'rank' => $cat === 'military' ? SseIdentityGeneratorService::pick($s, 'rank', self::MILITARY_RANKS) : '',
                    'country_name' => (string) $country['name'],
                    'documents' => $this->documentsFor($p, $country, $c, $params['doc_types'], $s, $today),
                ];
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $p
     * @param array<string, mixed> $country
     * @param array<string, mixed> $c ligne de catégorie
     * @param list<string> $types
     * @return list<array<string, mixed>>
     */
    private function documentsFor(array $p, array $country, array $c, array $types, int $s, string $today): array
    {
        $docs = [];
        foreach ($types as $type) {
            if (!in_array($type, $country['doc_types'], true)) {
                continue;
            }
            if ($type === 'driving_license' && SseIdentityGeneratorService::hash($s, 'dl') % 2 === 1) {
                continue;
            }
            $validity = SseIdCountryRepository::validityFor($country, $type);
            $roll = SseIdentityGeneratorService::hash($s, 'flag' . $type) % 100;
            $flag = match (true) {
                $roll < (int) $c['forged'] => 'forged',
                $roll < (int) $c['forged'] + (int) $c['expired'] => 'expired',
                $roll < (int) $c['forged'] + (int) $c['expired'] + (int) $c['stolen'] => 'stolen',
                default => 'genuine',
            };
            $base = new \DateTimeImmutable($today, new \DateTimeZone('UTC'));
            if ($flag === 'expired') {
                $issued = $base->modify('-' . $validity . ' years')->modify('-' . (30 + SseIdentityGeneratorService::hash($s, 'exp' . $type) % 700) . ' days');
            } else {
                $issued = $base->modify('-' . (SseIdentityGeneratorService::hash($s, 'iss' . $type) % max(1, $validity * 365 - 30)) . ' days');
            }
            $docs[] = [
                'doc_type' => $type,
                'country_code' => (string) $country['code'],
                'doc_number' => SseIdentityGeneratorService::documentNumber((string) $country['number_format'], $s, $type),
                'issued_on' => $issued->format('Y-m-d'),
                'expires_on' => $issued->modify('+' . $validity . ' years')->format('Y-m-d'),
                'authority' => (string) $country['authority'],
                'flag' => $flag,
                'st_origin' => $flag === 'forged' ? 'forged' : 'generated',
                'st_validity' => $flag === 'expired' ? 'expired' : 'valid',
                'st_report' => $flag === 'stolen' ? 'stolen' : 'none',
                'forgery_kind' => $flag === 'forged' ? self::FORGERY_KINDS[SseIdentityGeneratorService::hash($s, 'fk' . $type) % count(self::FORGERY_KINDS)] : null,
                'forgery_quality' => $flag === 'forged' ? 30 + SseIdentityGeneratorService::hash($s, 'fq' . $type) % 61 : null,
                'chip_status' => $flag === 'forged' ? (SseIdentityGeneratorService::hash($s, 'chip' . $type) % 2 === 0 ? 'absent' : 'mismatch') : 'ok',
            ];
        }

        return $docs;
    }

    /**
     * Synthèse d'un aperçu : effectifs par catégorie et par camp, papiers par état.
     *
     * @param list<array<string, mixed>> $profiles
     * @return array{people: int, documents: int, by_category: array<string, int>, by_side: array<string, int>, by_flag: array<string, int>}
     */
    public static function summary(array $profiles): array
    {
        $sum = ['people' => count($profiles), 'documents' => 0, 'by_category' => [], 'by_side' => [], 'by_flag' => ['genuine' => 0, 'forged' => 0, 'expired' => 0, 'stolen' => 0]];
        foreach ($profiles as $p) {
            $sum['by_category'][$p['category']] = ($sum['by_category'][$p['category']] ?? 0) + 1;
            $sum['by_side'][$p['side']] = ($sum['by_side'][$p['side']] ?? 0) + 1;
            foreach ($p['documents'] as $d) {
                $sum['documents']++;
                $sum['by_flag'][$d['flag']]++;
            }
        }

        return $sum;
    }

    /**
     * Enregistre la population : lot, identités « generated », papiers émis, recherchés et HVT de l'opération.
     *
     * @param array<string, mixed> $params normalizeParams()
     * @return array{ok: bool, error?: string, batch_id?: int, identities?: int, documents?: int, wanted?: int}
     */
    public function commit(int $tenantId, ?int $contextId, ?string $opKey, array $params, int $seed, ?int $actorId, ?string $today = null): array
    {
        $byCode = [];
        foreach ($this->countries->listForTenant($tenantId) as $c) {
            $byCode[$c['code']] = $c;
        }
        $profiles = $this->preview($params, $byCode, $seed, $today);
        if ($profiles === []) {
            return ['ok' => false, 'error' => 'Rien à générer : indiquez au moins une personne et un pays actif.'];
        }
        $summary = self::summary($profiles);
        $already = $this->documents->listForPack($tenantId, $contextId ?? 0, $opKey, null, 0, 1)['total'];
        if ($already + $summary['documents'] > SseIdDocumentRepository::PACK_MAX_PER_MAP) {
            return ['ok' => false, 'error' => 'Trop de papiers pour cette carte : le serveur de jeu en charge 500 au plus (déjà ' . $already . ', ' . $summary['documents'] . ' de plus demandés). Réduisez le nombre de personnes ou de types de papiers.'];
        }
        $label = $params['label'] !== '' ? (string) $params['label'] : 'Population ' . ($opKey ?? 'hors opération');
        $batchId = $this->operations->createBatch($tenantId, $contextId, $opKey, $label, $params, $seed, $params['visible_sides'], $actorId);
        $nIdentities = $nDocs = $nWanted = 0;
        foreach ($profiles as $p) {
            $identityId = $this->identities->create($tenantId, [
                'kind' => 'generated',
                'last_name' => $p['last_name'],
                'first_name' => $p['first_name'],
                'sex' => $p['sex'],
                'birth_date' => $p['birth_date'],
                'birth_place' => $p['birth_place'],
                'nationality' => $p['nationality'],
                'profession' => $p['profession'],
                'seed' => $p['seed'],
                'context_id' => $contextId,
                'op_key' => $opKey,
                'batch_id' => $batchId,
                'category' => $p['category'],
                'side' => $p['side'],
                'faction' => $p['faction'],
                'language' => $p['language'],
                'zone' => $p['zone'],
                'alias' => $p['alias'],
            ], $actorId);
            $nIdentities++;
            $docIds = [];
            foreach ($p['documents'] as $d) {
                $country = $byCode[$d['country_code']];
                $number = $d['doc_number'];
                for ($k = 0; $k < 20 && $this->documents->numberExists($tenantId, $d['country_code'], $d['doc_type'], $number); $k++) {
                    $number = SseIdentityGeneratorService::documentNumber((string) $country['number_format'], (int) $p['seed'], $d['doc_type'] . 'b' . $batchId . $k);
                }
                if ($this->documents->numberExists($tenantId, $d['country_code'], $d['doc_type'], $number)) {
                    continue;
                }
                $docId = $this->documents->create($tenantId, [
                    'identity_id' => $identityId,
                    'doc_number' => $number,
                    'context_id' => $contextId,
                    'op_key' => $opKey,
                    'batch_id' => $batchId,
                    'print' => $p['rank'] !== '' ? ['rank' => $p['rank'], 'service' => $p['faction']] : null,
                ] + $d, $actorId);
                $this->documents->transition($docId, $tenantId, 'validated', $actorId);
                $this->documents->transition($docId, $tenantId, 'issued', $actorId);
                $docIds[] = $docId;
                $nDocs++;
            }
            if ($p['wanted_level'] !== null) {
                $this->operations->addWanted($tenantId, $contextId, $opKey, $identityId, (string) $p['wanted_level'], (string) $p['alias'], $params['visible_sides'], '', $batchId, $actorId);
                $nWanted++;
            }
        }
        $this->operations->setBatchCounts($batchId, $tenantId, $nIdentities, $nDocs);
        self::audit($tenantId, $actorId, 'id_batch.create', 'sse_id_batch', $batchId, [
            'map' => $contextId, 'op' => $opKey, 'seed' => $seed, 'identities' => $nIdentities, 'documents' => $nDocs, 'wanted' => $nWanted,
        ]);

        return ['ok' => true, 'batch_id' => $batchId, 'identities' => $nIdentities, 'documents' => $nDocs, 'wanted' => $nWanted];
    }

    /**
     * Retire un lot : ses papiers émis sont révoqués, ses recherchés retirés, il sort du pack (population).
     *
     * @return int papiers révoqués
     */
    public function archive(int $tenantId, int $batchId, ?int $actorId): int
    {
        if (!$this->operations->archiveBatch($batchId, $tenantId)) {
            return 0;
        }
        $n = 0;
        foreach ($this->operations->batchIdentities($tenantId, $batchId, 1000) as $i) {
            foreach ($this->documents->list($tenantId, ['identity_id' => (int) $i['id'], 'workflow' => 'issued']) as $d) {
                $n += $this->documents->transition((int) $d['id'], $tenantId, 'revoked', $actorId, 'Lot de population retiré') ? 1 : 0;
            }
        }
        $this->operations->removeWantedOfBatch($batchId, $tenantId);
        self::audit($tenantId, $actorId, 'id_batch.archive', 'sse_id_batch', $batchId, ['revoked' => $n]);

        return $n;
    }

    /** @var (callable(array<string, mixed>): void)|null journal remplacé (tests) */
    private static $auditSink = null;

    /** Remplace le journal d'audit (tests) ; null = journal d'audit SSE. */
    public static function useAuditSink(?callable $sink): void
    {
        self::$auditSink = $sink;
    }

    /**
     * Journal d'audit SSE des écritures de l'atelier, des kits types, de la préparation de mission et des briefs.
     *
     * @param array<string, mixed> $after
     */
    public static function audit(int $tenantId, ?int $actorId, string $action, string $type, int $objectId, array $after): void
    {
        $entry = [
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'actor_label' => 'Atelier des papiers',
            'action' => $action,
            'object_type' => $type,
            'object_id' => $objectId,
            'after' => $after,
        ];
        if (self::$auditSink !== null) {
            (self::$auditSink)($entry);

            return;
        }
        try {
            (new SseAuditLogRepository())->write($entry);
        } catch (\Throwable $e) {
            error_log('Atelier des papiers : audit indisponible : ' . $e->getMessage());
        }
    }
}
