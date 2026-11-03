<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Container;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Services\OrganizationCatalog\RealUnitKitDefinitions;
use App\Support\JobCatalog\JobCatalog;
use App\Support\OrbatMaskMode;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationSuggestion\UnitNatureDetector;
use App\Support\VisibilityLevel;
use PDO;
use Throwable;

/**
 * « Recommandé pour votre communauté » (assistant d’importation du catalogue) : le moteur des qualifications
 * suggérées d’un membre (MemberQualificationSuggester, UnitNatureDetector, unités réelles du catalogue de modèles)
 * appliqué à toute la communauté.
 *
 * - Unités : nature de chaque unité de l’ORBAT (ses textes, ceux de ses parents, la lignée de l’unité réelle
 *   reconnue), agrégée avec l’effectif concerné ; pays des unités réelles reconnues.
 * - Fonctions : emplois du catalogue d’emplois reconnus dans chaque fonction (attribuée ou non), avec le nombre de
 *   membres qui l’occupent ; brevets cités par ces emplois (« qualification:<id> »).
 * - Prérequis : qualifications préalables (catalogue) des fiches déjà dans le référentiel, pas encore importées.
 *
 * Confidentialité : une unité masquée ou restreinte compte dans la détection mais n’est jamais citée par son nom à
 * qui n’est pas habilité à la voir.
 */
final class CommunityQualificationRecommender
{
    /** Éléments au plus par source (fonction, nature d’unité, fiche) et par groupe. */
    public const PER_SOURCE = 8;
    public const PER_GROUP = 30;

    /** Fonctions lues au plus (les plus occupées d’abord). */
    public const MAX_ROLES = 80;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Données de la communauté lues en quelques requêtes (unités, effectifs, fonctions et titulaires, fiches
     * importées du catalogue), puis recommend().
     *
     * @param array<string, true|int> $imported codes déjà dans le référentiel (majuscules)
     * @param array<string, int> $importedDefinitions code => fiche
     * @return array<string, mixed>
     */
    public function forTenant(int $tenantId, bool $staff, array $imported, array $importedDefinitions, string $lang): array
    {
        $units = [];
        try {
            $repo = new UnitRepository($this->pdo);
            $rows = $repo->allForTenant($tenantId);
            $members = $repo->countDistinctMembersByUnitForTenant($tenantId);
            $levels = OrgVisibilityService::effectiveUnitLevels($rows);
            foreach ($rows as $u) {
                $id = (int) ($u['id'] ?? 0);
                $units[] = $u + [
                    'members' => (int) ($members[$id] ?? 0),
                    'confidential' => ($levels[$id] ?? VisibilityLevel::NORMAL) !== VisibilityLevel::NORMAL
                        || OrbatMaskMode::normalize((string) ($u['orbat_mask_mode'] ?? '')) !== OrbatMaskMode::NONE,
                ];
            }
        } catch (Throwable $e) {
            error_log('Recommandations du catalogue (unités) : ' . $e->getMessage());
        }

        return self::recommend([
            'lang' => $lang,
            'staff' => $staff,
            'units' => $units,
            'roles' => $this->roles($tenantId),
            'imported' => $imported,
            'imported_definitions' => $importedDefinitions,
            'country' => $this->affiliationCountry($tenantId),
        ]);
    }

    /** @return list<array<string, mixed>> fonctions de la communauté avec le nombre de membres qui les occupent */
    private function roles(int $tenantId): array
    {
        try {
            $st = $this->pdo->prepare('SELECT * FROM personnel_job_roles WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $counts = [];
        try {
            $st = $this->pdo->prepare('SELECT personnel_job_role_id, COUNT(DISTINCT user_id) AS c FROM personnel_profile_job_roles WHERE tenant_id = ? GROUP BY personnel_job_role_id');
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $counts[(int) $r['personnel_job_role_id']] = (int) $r['c'];
            }
        } catch (Throwable) {
        }
        $deco = [];
        try {
            $deco = (new JobRoleEnrichmentService($this->pdo))->decorations($tenantId);
        } catch (Throwable) {
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            $out[] = [
                'role_id' => $id,
                'name' => trim((string) ($r['name'] ?? '')),
                'label_en' => trim((string) ($r['label_en'] ?? '')),
                'abbreviation' => (string) ($deco[$id]['abbreviation'] ?? ($r['abbreviation'] ?? '')),
                'members' => (int) ($counts[$id] ?? 0),
            ];
        }
        usort($out, static fn (array $a, array $b): int => [$b['members'], $a['name']] <=> [$a['members'], $b['name']]);

        return array_slice($out, 0, self::MAX_ROLES);
    }

    private function affiliationCountry(int $tenantId): string
    {
        try {
            $aff = Container::get(TenantRepository::class)->getSettings($tenantId)['community']['unit_affiliation'] ?? null;
            if (is_array($aff) && !empty($aff['is_real'])) {
                return strtoupper(trim((string) ($aff['country'] ?? '')));
            }
        } catch (Throwable) {
        }

        return '';
    }

    /**
     * État « déjà dans le référentiel » relu sur des recommandations gardées en cache (un import vient d’avoir lieu).
     *
     * @param array{detected: array<string, mixed>, groups: list<array<string, mixed>>} $reco
     * @param array<string, mixed> $imported codes déjà dans le référentiel (majuscules)
     * @return array{detected: array<string, mixed>, groups: list<array<string, mixed>>}
     */
    public static function markImported(array $reco, array $imported): array
    {
        foreach ($reco['groups'] as $g => $group) {
            $toImport = 0;
            foreach ($group['items'] as $i => $item) {
                $done = isset($imported[strtoupper((string) $item['code'])]);
                $reco['groups'][$g]['items'][$i]['done'] = $done;
                $toImport += $done ? 0 : 1;
            }
            $reco['groups'][$g]['to_import'] = $toImport;
        }

        return $reco;
    }

    /**
     * Calcul pur : tout vient de l’entrée.
     *
     * @param array{
     *   lang?: string,
     *   staff?: bool,
     *   units?: list<array<string, mixed>>,
     *   roles?: list<array<string, mixed>>,
     *   imported?: array<string, mixed>,
     *   imported_definitions?: array<string, int>,
     *   country?: string
     * } $in
     * @return array{detected: array<string, mixed>, groups: list<array<string, mixed>>}
     */
    public static function recommend(array $in): array
    {
        $lang = QualificationCatalog::lang((string) ($in['lang'] ?? 'fr'));
        $staff = !empty($in['staff']);
        $imported = is_array($in['imported'] ?? null) ? $in['imported'] : [];
        $catalog = [];
        $catalogByCode = [];
        foreach (QualificationCatalog::rawEntries() as $e) {
            $id = (string) ($e['id'] ?? '');
            if ($id === '' || QualificationCatalog::problems($e) !== []) {
                continue;
            }
            $catalog[$id] = $e;
            $code = strtoupper(trim((string) ($e['code'] ?? '')));
            if ($code !== '') {
                $catalogByCode[$code] = $id;
            }
        }
        $packCountry = static fn (array $e): string => (string) (QualificationCatalog::pack((string) ($e['pack'] ?? ''))['country'] ?? '');

        // 1. Unités : nature de chacune (ses textes et ceux de ses parents), effectif, pays des unités réelles.
        $units = [];
        foreach (is_array($in['units'] ?? null) ? $in['units'] : [] as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id > 0) {
                $units[$id] = $u;
            }
        }
        $own = [];
        $realCountry = [];
        foreach ($units as $id => $u) {
            $texts = [];
            foreach (['name', 'code', 'type', 'public_tags', 'echelon'] as $col) {
                $v = trim((string) ($u[$col] ?? ''));
                if ($v !== '') {
                    $texts[] = ['text' => $v, 'weight' => 1.0];
                }
            }
            $node = RealUnitKitDefinitions::findNode((string) ($u['slug'] ?? ''), (string) ($u['name'] ?? ''), (string) ($u['code'] ?? ''));
            if ($node !== null) {
                foreach (RealUnitKitDefinitions::lineage($node) as $i => $line) {
                    $texts[] = ['text' => $line, 'weight' => UnitNatureDetector::weight($i)];
                }
                $cc = RealUnitKitDefinitions::countryOf($node);
                if ($cc !== '') {
                    $realCountry[$cc] = ($realCountry[$cc] ?? 0) + 1 + (int) ($u['members'] ?? 0);
                }
            }
            $own[$id] = UnitNatureDetector::detect($texts);
        }
        /** @var array<string, array{score: float, members: int, units: int, sources: array<int, float>}> $natures */
        $natures = [];
        foreach ($units as $id => $u) {
            // Nature effective : la sienne, ou celle d’un parent (moins forte avec la distance).
            $eff = [];
            $src = [];
            $cur = $id;
            $seen = [];
            for ($depth = 0; $cur > 0 && isset($units[$cur]) && !isset($seen[$cur]) && $depth < 8; $depth++) {
                $seen[$cur] = true;
                foreach ($own[$cur] as $n => $s) {
                    $w = $s * UnitNatureDetector::weight($depth);
                    if ($w > ($eff[$n] ?? 0.0)) {
                        $eff[$n] = $w;
                        $src[$n] = $cur;
                    }
                }
                $cur = (int) ($units[$cur]['parent_id'] ?? 0);
            }
            foreach ($eff as $n => $s) {
                if ($s < UnitNatureDetector::MIN_SCORE) {
                    continue;
                }
                $natures[$n] ??= ['score' => 0.0, 'members' => 0, 'units' => 0, 'sources' => []];
                $natures[$n]['score'] = max($natures[$n]['score'], $s);
                $natures[$n]['members'] += (int) ($u['members'] ?? 0);
                $natures[$n]['units']++;
                $natures[$n]['sources'][$src[$n]] = max($natures[$n]['sources'][$src[$n]] ?? 0.0, (float) ($own[$src[$n]][$n] ?? 0.0));
            }
        }
        uasort($natures, static fn (array $a, array $b): int => [$b['score'], $b['members']] <=> [$a['score'], $a['members']]);

        // 2. Pays : unité réelle déclarée par la communauté, sinon unités réelles reconnues, nature propre à un pays,
        // puis emplois reconnus.
        $country = strtoupper(trim((string) ($in['country'] ?? '')));
        $countrySource = $country !== '' ? 'affiliation' : '';
        if ($country === '' && $realCountry !== []) {
            arsort($realCountry);
            $country = (string) array_key_first($realCountry);
            $countrySource = 'real_units';
        }
        if ($country === '') {
            foreach ($natures as $n => $data) {
                if ($data['score'] >= 0.6 && UnitNatureDetector::country($n) !== '') {
                    $country = UnitNatureDetector::country($n);
                    $countrySource = 'nature';
                    break;
                }
            }
        }

        // 3. Fonctions : emplois du catalogue reconnus.
        $roleMatches = [];
        foreach (is_array($in['roles'] ?? null) ? $in['roles'] : [] as $role) {
            $matches = MemberQualificationSuggester::matchJobs([$role], $country);
            if ($matches !== []) {
                $roleMatches[] = ['role' => $role, 'matches' => $matches];
            }
        }
        if ($country === '') {
            foreach ($roleMatches as $rm) {
                $cc = (string) ($rm['matches'][0]['entry']['country'] ?? '');
                if ($cc !== '' && (int) ($rm['role']['members'] ?? 0) > 0) {
                    $country = $cc;
                    $countrySource = 'jobs';
                    break;
                }
            }
        }
        $countryFactor = static function (array $e) use ($country, $packCountry): float {
            $cc = $packCountry($e);
            if ($country === '' || $cc === $country) {
                return 1.0;
            }

            return $cc === 'NATO' ? 0.8 : 0.5;
        };

        // Sources d’un groupe : libellé, membres, éléments (id => points) ; les éléments gardent leurs raisons.
        $groups = ['jobs' => [], 'units' => [], 'prereqs' => []];
        $addSource = static function (string $group, array $source, array $points) use (&$groups): void {
            arsort($points);
            $points = array_slice($points, 0, self::PER_SOURCE, true);
            if ($points !== []) {
                $groups[$group][] = $source + ['points' => $points];
            }
        };

        $detectedJobs = [];
        foreach ($roleMatches as $rm) {
            $members = (int) ($rm['role']['members'] ?? 0);
            $boost = $members > 0 ? 1.0 + min($members, 10) / 10 : 0.5;
            foreach ($rm['matches'] as $m) {
                $points = [];
                foreach (JobCatalog::qualificationRefs($m['entry']) as $ref) {
                    if (isset($catalog[$ref])) {
                        $points[$ref] = round(70.0 * $m['score'] * $boost * $countryFactor($catalog[$ref]), 2);
                    }
                }
                $detectedJobs[] = ['role' => (string) $rm['role']['name'], 'job' => $m['label'], 'country' => (string) ($m['entry']['country'] ?? ''),
                    'members' => $members, 'score' => $m['score'], 'qualifications' => count($points)];
                $addSource('jobs', ['label' => $m['label'], 'role' => (string) $rm['role']['name'], 'members' => $members], $points);
            }
        }

        $jobEntries = JobCatalog::rawEntries();
        foreach ($natures as $n => $data) {
            $s = $data['score'];
            $counts = [];
            foreach ($jobEntries as $job) {
                if ($country !== '' && (string) ($job['country'] ?? '') !== $country) {
                    continue;
                }
                $text = implode(' | ', array_filter([
                    is_array($job['unit'] ?? null) ? implode(' ', array_map('strval', $job['unit'])) : (string) ($job['unit'] ?? ''),
                    (string) ($job['formation'] ?? ''),
                ]));
                if ($text === '' || !UnitNatureDetector::matches($n, $text)) {
                    continue;
                }
                foreach (JobCatalog::qualificationRefs($job) as $ref) {
                    $counts[$ref] = ($counts[$ref] ?? 0) + 1;
                }
            }
            $points = [];
            foreach ($counts as $ref => $c) {
                if (isset($catalog[$ref])) {
                    $points[$ref] = $s * (12.0 + 6.0 * min($c, 4)) * $countryFactor($catalog[$ref]);
                }
            }
            $cats = UnitNatureDetector::categories($n);
            foreach ($catalog as $id => $e) {
                if (!in_array((string) ($e['category'] ?? ''), $cats, true)) {
                    continue;
                }
                if (isset($points[$id])) {
                    $points[$id] += 8.0 * $s;
                } elseif ($s >= 0.6 && ($country === '' || $packCountry($e) === $country)) {
                    $points[$id] = 10.0 * $s * ($country === '' && $packCountry($e) !== 'NATO' ? 0.6 : 1.0);
                }
            }
            $points = array_map(static fn (float $p): float => round($p, 2), $points);
            $addSource('units', ['label' => UnitNatureDetector::label($n), 'nature' => $n, 'members' => $data['members']], $points);
        }

        // 4. Prérequis des fiches déjà importées du catalogue.
        $importedDefs = is_array($in['imported_definitions'] ?? null) ? $in['imported_definitions'] : [];
        foreach ($importedDefs as $code => $_def) {
            $id = $catalogByCode[strtoupper((string) $code)] ?? null;
            if ($id === null) {
                continue;
            }
            $points = [];
            foreach (MemberQualificationSuggester::catalogPrerequisiteRefs($catalog[$id], $lang) as $ref) {
                if ($ref !== $id && isset($catalog[$ref]) && !isset($imported[strtoupper((string) ($catalog[$ref]['code'] ?? ''))])) {
                    $points[$ref] = 30.0;
                }
            }
            $parent = QualificationCatalog::pick($catalog[$id]['short_name'] ?? '', $lang) ?: QualificationCatalog::pick($catalog[$id]['name'] ?? '', $lang);
            $addSource('prereqs', ['label' => $parent, 'members' => null], $points);
        }

        // 5. Éléments de chaque groupe (les plus pertinents), état dans le référentiel.
        $out = [];
        foreach ($groups as $key => $sources) {
            $items = [];
            foreach ($sources as $src) {
                foreach ($src['points'] as $ref => $p) {
                    $items[$ref] ??= ['points' => 0.0, 'members' => 0];
                    $items[$ref]['points'] += $p;
                    $items[$ref]['members'] = max($items[$ref]['members'], (int) ($src['members'] ?? 0));
                }
            }
            uksort($items, static fn (string $a, string $b): int => [$items[$b]['points'], $a] <=> [$items[$a]['points'], $b]);
            $items = array_slice($items, 0, self::PER_GROUP, true);
            if ($items === []) {
                continue;
            }
            $rows = [];
            foreach ($items as $ref => $it) {
                $e = $catalog[$ref];
                $rows[] = [
                    'id' => (string) $ref,
                    'name' => QualificationCatalog::pick($e['name'] ?? '', $lang),
                    'code' => (string) ($e['code'] ?? ''),
                    'country' => $packCountry($e),
                    'done' => isset($imported[strtoupper((string) ($e['code'] ?? ''))]),
                    'members' => $it['members'],
                    'points' => (int) round($it['points']),
                ];
            }
            $out[] = [
                'key' => $key,
                'items' => $rows,
                'sources' => array_map(static function (array $s) use ($items): array {
                    $ids = array_values(array_filter(array_map('strval', array_keys($s['points'])), static fn (string $id): bool => isset($items[$id])));
                    unset($s['points']);

                    return $s + ['ids' => $ids];
                }, $sources),
                'to_import' => count(array_filter($rows, static fn (array $r): bool => !$r['done'])),
            ];
        }

        // 6. Ce qu’on a détecté (unités confidentielles jamais nommées à qui n’est pas habilité).
        $detectedNatures = [];
        foreach ($natures as $n => $data) {
            arsort($data['sources']);
            $sources = [];
            foreach (array_slice($data['sources'], 0, 3, true) as $uid => $_s) {
                $u = $units[$uid];
                $hidden = !empty($u['confidential']) && !$staff;
                $sources[] = ['name' => $hidden ? '' : (string) ($u['name'] ?? ''), 'confidential' => $hidden];
            }
            $detectedNatures[] = ['key' => $n, 'label' => UnitNatureDetector::label($n), 'score' => round($data['score'], 2),
                'units' => $data['units'], 'members' => $data['members'], 'sources' => $sources];
        }
        usort($detectedJobs, static fn (array $a, array $b): int => [$b['members'], $b['score']] <=> [$a['members'], $a['score']]);

        return [
            'detected' => [
                'natures' => $detectedNatures,
                'jobs' => array_slice($detectedJobs, 0, 40),
                'country' => $country,
                'country_label' => $country !== '' ? QualificationCatalog::countryLabel($country, $lang) : '',
                'country_source' => $countrySource,
                'units' => count($units),
                'roles' => count(is_array($in['roles'] ?? null) ? $in['roles'] : []),
            ],
            'groups' => $out,
        ];
    }
}
