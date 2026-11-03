<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Services\Academy\AcademyOffer;
use App\Support\JobCatalog\JobCatalog;
use App\Support\QualificationCatalog\CatalogCountry;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationCatalogExplorer;
use App\Support\QualificationCatalog\QualificationIssuerCatalog;
use Throwable;

/**
 * Assistant d'importation du catalogue de référence (Academy › Catalogue) : choisir une portée (tout, pays, catégorie,
 * domaine, niveau, émetteur, sélection à la main ; combinables), voir l'aperçu (déjà présents, émetteurs réels,
 * qualifications préalables manquantes, cours Academy liés), puis importer par lots.
 *
 * L'import lui-même reste celui de QualificationCatalogImporter (sans doublon, émetteur réel, plafond de la formule) :
 * cette classe ne fait que choisir, ordonner (les qualifications préalables avant celles qui les demandent) et
 * découper en lots.
 */
final class QualificationCatalogImportWizard
{
    /** Qualifications traitées par appel (chaque appel reste court : pas de délai dépassé). */
    public const BATCH = 10;

    /** Profondeur maximale des qualifications préalables suivies (préalable d'un préalable…). */
    public const MAX_DEPTH = 3;

    /** Dimensions de la portée, combinables (et entre elles, ou dans une même dimension). */
    public const DIMENSIONS = ['pays', 'nature', 'categorie', 'niveau', 'emetteur'];

    private QualificationCatalogImporter $importer;

    public function __construct(private QualificationReferenceWriter $writer, ?QualificationCatalogImporter $importer = null)
    {
        $this->importer = $importer ?? new QualificationCatalogImporter($writer);
    }

    public function importer(): QualificationCatalogImporter
    {
        return $this->importer;
    }

    /**
     * Lignes compactes du catalogue pour l'assistant (recherche et compteurs en direct dans la page).
     *
     * @param array<string, true|int> $imported codes déjà dans le référentiel (majuscules)
     * @return list<array<string, mixed>>
     */
    public static function rows(string $lang, array $imported = []): array
    {
        $out = [];
        foreach (QualificationCatalogExplorer::rows($lang) as $r) {
            $name = (string) $r['name'];
            $official = (string) $r['official_name'];
            // Texte cherché : nom, intitulé officiel, code, abrégé, émetteur (pays, domaine… se choisissent à part).
            $hay = implode(' ', [$r['code'], $r['short_name'], $official, $name, $r['issuer']]);
            $translit = JobCatalog::transliterate($hay);
            $out[] = [
                'id' => (string) $r['id'],
                'name' => $name,
                'official' => $official !== $name ? $official : '',
                'code' => (string) $r['code'],
                'pays' => (string) $r['country'],
                'nature' => AcademyOffer::nature($name . ' ' . $official, false, (string) $r['category']),
                'categorie' => (string) $r['category'],
                'niveau' => (string) ($r['level']['key'] ?? 'initial'),
                'emetteur' => (string) $r['issuer'],
                'academy' => is_array($r['academy'] ?? null) ? (string) ($r['academy']['title'] ?? '') : '',
                'done' => isset($imported[strtoupper((string) $r['code'])]),
                'search' => trim(JobCatalog::normalize($hay . ($translit !== $hay ? ' ' . $translit : ''))),
            ];
        }

        return $out;
    }

    /**
     * Libellés des choix de la portée : pays (drapeau, nom, nom dans la langue du pays), catégories, domaines,
     * niveaux, émetteurs.
     *
     * @param list<array<string, mixed>> $rows rows()
     * @return array<string, list<array<string, string>>>
     */
    public static function options(array $rows, string $lang): array
    {
        $lang = QualificationCatalog::lang($lang);
        $countries = [];
        foreach (array_keys(QualificationCatalog::COUNTRIES) as $cc) {
            $countries[] = ['value' => $cc, 'label' => QualificationCatalog::countryLabel($cc, $lang), 'native' => CatalogCountry::native($cc), 'flag' => CatalogCountry::flag($cc)];
        }
        $natures = $lang === 'en'
            ? ['brevet' => 'Badge', 'qualification' => 'Qualification', 'cursus' => 'Course and selection', 'licence' => 'Licence']
            : ['brevet' => 'Brevet', 'qualification' => 'Qualification', 'cursus' => 'Cursus et sélection', 'licence' => 'Licence'];
        $issuers = array_values(array_unique(array_map(static fn (array $r): string => (string) $r['emetteur'], $rows)));
        sort($issuers, SORT_NATURAL | SORT_FLAG_CASE);
        $levels = [];
        foreach (['initial', 'qualified', 'confirmed', 'instructor', 'expert'] as $lv) {
            $levels[] = ['value' => $lv, 'label' => QualificationCatalogExplorer::levelLabel($lv, $lang)];
        }

        return [
            'pays' => $countries,
            'nature' => array_map(static fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys($natures), $natures),
            'categorie' => array_map(static fn (string $k): array => ['value' => $k, 'label' => QualificationCatalog::categoryLabel($k, $lang)], array_keys(QualificationCatalog::CATEGORIES)),
            'niveau' => $levels,
            'emetteur' => array_map(static fn (string $i): array => ['value' => $i, 'label' => $i], $issuers),
        ];
    }

    /**
     * Portée lue dans une demande : listes de valeurs par dimension, recherche, sélection à la main (identifiants).
     *
     * @param array<string, mixed> $input
     * @return array{pays: list<string>, nature: list<string>, categorie: list<string>, niveau: list<string>, emetteur: list<string>, q: string, ids: list<string>}
     */
    public static function scope(array $input): array
    {
        $list = static function (mixed $v, int $max = 200): array {
            $v = is_array($v) ? $v : (is_string($v) && $v !== '' ? explode(',', $v) : []);
            $out = [];
            foreach ($v as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    $out[] = mb_substr(trim((string) $item), 0, $max);
                }
            }

            return array_values(array_unique($out));
        };
        $out = ['q' => is_scalar($input['q'] ?? null) ? mb_substr(trim((string) $input['q']), 0, 120) : ''];
        foreach (self::DIMENSIONS as $dim) {
            $out[$dim] = $list($input[$dim] ?? []);
        }
        $out['pays'] = array_values(array_filter(array_map('strtoupper', $out['pays']), static fn (string $c): bool => isset(QualificationCatalog::COUNTRIES[$c])));
        $out['nature'] = array_values(array_intersect($out['nature'], AcademyOffer::NATURES));
        $out['categorie'] = array_values(array_filter($out['categorie'], static fn (string $c): bool => isset(QualificationCatalog::CATEGORIES[$c])));
        $out['ids'] = array_values(array_filter($list($input['ids'] ?? [], 80), static fn (string $id): bool => preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,60}$/', $id) === 1));

        return ['pays' => $out['pays'], 'nature' => $out['nature'], 'categorie' => $out['categorie'], 'niveau' => $out['niveau'], 'emetteur' => $out['emetteur'], 'q' => $out['q'], 'ids' => $out['ids']];
    }

    /**
     * Lignes retenues par la portée : chaque dimension renseignée doit correspondre (une des valeurs choisies), la
     * recherche doit trouver tous ses mots ; une sélection à la main restreint encore aux éléments cochés.
     *
     * @param list<array<string, mixed>> $rows rows()
     * @param array<string, mixed> $scope scope()
     * @param string|null $except dimension ignorée (compteurs d'une dimension avec les autres filtres)
     * @return list<array<string, mixed>>
     */
    public static function select(array $rows, array $scope, ?string $except = null): array
    {
        $words = array_values(array_filter(explode(' ', JobCatalog::normalize((string) ($scope['q'] ?? ''))), static fn (string $w): bool => $w !== ''));
        $ids = $except === 'ids' ? [] : array_flip((array) ($scope['ids'] ?? []));

        return array_values(array_filter($rows, static function (array $r) use ($scope, $words, $ids, $except): bool {
            foreach (self::DIMENSIONS as $dim) {
                $wanted = (array) ($scope[$dim] ?? []);
                if ($dim !== $except && $wanted !== [] && !in_array((string) $r[$dim], $wanted, true)) {
                    return false;
                }
            }
            foreach ($words as $w) {
                if (!str_contains((string) $r['search'], $w)) {
                    return false;
                }
            }

            return $ids === [] || isset($ids[(string) $r['id']]);
        }));
    }

    /**
     * Compteurs de la portée : éléments retenus, déjà dans le référentiel, à importer ; et pour chaque dimension, le
     * nombre d'éléments par valeur avec les autres choix appliqués.
     *
     * @param list<array<string, mixed>> $rows rows()
     * @param array<string, mixed> $scope scope()
     * @return array{total: int, already: int, to_import: int, facets: array<string, array<string, int>>}
     */
    public static function summary(array $rows, array $scope): array
    {
        $picked = self::select($rows, $scope);
        $already = count(array_filter($picked, static fn (array $r): bool => !empty($r['done'])));
        $facets = [];
        foreach (self::DIMENSIONS as $dim) {
            $facets[$dim] = [];
            foreach (self::select($rows, $scope, $dim) as $r) {
                $facets[$dim][(string) $r[$dim]] = ($facets[$dim][(string) $r[$dim]] ?? 0) + 1;
            }
        }

        return ['total' => count($picked), 'already' => $already, 'to_import' => count($picked) - $already, 'facets' => $facets];
    }

    /**
     * Packs (pays, OTAN) entièrement couverts par une sélection : « importer un pays entier en un clic », réservé
     * aux formules qui l'incluent.
     *
     * @param list<string> $ids
     * @return list<string>
     */
    public static function wholePacks(array $ids): array
    {
        $set = array_flip($ids);
        $out = [];
        foreach (QualificationCatalog::packs() as $name => $data) {
            $entries = $data['entries'] ?? [];
            if (count($entries) < 2) {
                continue;
            }
            $all = true;
            foreach ($entries as $e) {
                if (!isset($set[(string) ($e['id'] ?? '')])) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                $out[] = (string) $name;
            }
        }

        return $out;
    }

    /**
     * Plan d'import : éléments choisis et, si demandé, leurs qualifications préalables manquantes (citées par leurs
     * prérequis, jusqu'à MAX_DEPTH niveaux), dans l'ordre d'import (préalables d'abord) ; émetteurs réels qui seront
     * créés ou réutilisés ; cours Academy liés.
     *
     * @param list<string> $ids éléments choisis
     * @return array{order: list<string>, items: array<string, array<string, mixed>>, missing: list<array{id: string, name: string, code: string}>, issuers: list<array<string, mixed>>, courses: list<array<string, string>>, to_create: int, already: int, dependencies: int, unknown: int}
     */
    public function plan(int $tenantId, array $ids, bool $withDependencies, string $lang): array
    {
        $lang = QualificationCatalog::lang($lang);
        $imported = $this->importer->importedCodes($tenantId);
        $selected = [];
        $unknown = 0;
        foreach (array_values(array_unique(array_map('strval', $ids))) as $id) {
            $raw = QualificationCatalog::findRaw($id);
            if ($raw !== null && QualificationCatalog::problems($raw) === []) {
                $selected[$id] = true;
            } else {
                $unknown++;
            }
        }
        $items = [];
        $order = [];
        $missing = [];
        $visit = function (string $id, int $depth, ?string $requiredBy) use (&$visit, &$items, &$order, &$missing, $selected, $imported, $withDependencies, $lang): void {
            if (isset($items[$id])) {
                if ($requiredBy !== null && !in_array($requiredBy, $items[$id]['required_by'], true)) {
                    $items[$id]['required_by'][] = $requiredBy;
                }

                return;
            }
            $raw = QualificationCatalog::findRaw($id);
            if ($raw === null || QualificationCatalog::problems($raw) !== []) {
                return;
            }
            $done = isset($imported[strtoupper((string) $raw['code'])]);
            $items[$id] = [
                'id' => $id,
                'name' => QualificationCatalog::pick($raw['name'] ?? '', $lang),
                'code' => (string) $raw['code'],
                'country' => (string) (QualificationCatalog::pack((string) $raw['pack'])['country'] ?? ''),
                'already' => $done,
                'dependency' => !isset($selected[$id]),
                'required_by' => $requiredBy !== null ? [$requiredBy] : [],
            ];
            if (!$done && $depth < self::MAX_DEPTH) {
                foreach (QualificationCatalogExplorer::prerequisites($raw, $lang) as $p) {
                    foreach ($p['refs'] as $ref) {
                        $ref = (string) $ref;
                        if ($ref === $id) {
                            continue;
                        }
                        // Préalable choisi lui aussi : importé avant (pas compté comme dépendance).
                        if (isset($selected[$ref])) {
                            $visit($ref, $depth + 1, $id);
                            continue;
                        }
                        $refRaw = QualificationCatalog::findRaw($ref);
                        if ($refRaw === null || isset($imported[strtoupper((string) ($refRaw['code'] ?? ''))])) {
                            continue;
                        }
                        if (!in_array($ref, $missing, true)) {
                            $missing[] = $ref;
                        }
                        if ($withDependencies) {
                            $visit($ref, $depth + 1, $id);
                        }
                    }
                }
            }
            // Ordre d'import : les préalables (visités ci-dessus) passent avant.
            $order[] = $id;
        };
        // Préalables choisis eux aussi : visités d'abord s'ils sont cités, l'ordre reste correct.
        foreach (array_keys($selected) as $id) {
            $visit((string) $id, 0, null);
        }
        $missing = array_values(array_filter($missing, static fn (string $m): bool => !isset($selected[$m])));
        $missingItems = [];
        foreach ($missing as $m) {
            $raw = QualificationCatalog::findRaw($m);
            $missingItems[] = ['id' => $m, 'name' => QualificationCatalog::pick($raw['name'] ?? '', $lang), 'code' => (string) ($raw['code'] ?? '')];
        }

        $toCreate = 0;
        $already = 0;
        $dependencies = 0;
        foreach ($order as $id) {
            if ($items[$id]['already']) {
                $already++;
                continue;
            }
            $toCreate++;
            $dependencies += $items[$id]['dependency'] ? 1 : 0;
        }

        return [
            'order' => $order,
            'items' => $items,
            'missing' => $missingItems,
            'issuers' => $this->issuersOf($tenantId, $order, $items, $lang),
            'courses' => self::coursesOf($order, $lang),
            'to_create' => $toCreate,
            'already' => $already,
            'dependencies' => $dependencies,
            'unknown' => $unknown,
        ];
    }

    /**
     * Émetteurs des qualifications à créer : déjà dans le référentiel ou créés à l'import (émetteur réel du catalogue
     * des émetteurs, sinon école citée, sinon émetteur de référence du pack).
     *
     * @param list<string> $order
     * @param array<string, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function issuersOf(int $tenantId, array $order, array $items, string $lang): array
    {
        $rows = [];
        try {
            $st = $this->writer->pdo()->prepare('SELECT id, name, short_name FROM qualification_issuers WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }
        $names = array_flip(array_map(static fn (array $r): string => (string) $r['name'], $rows));
        $out = [];
        foreach ($order as $id) {
            if ($items[$id]['already']) {
                continue;
            }
            $raw = QualificationCatalog::findRaw($id);
            if ($raw === null) {
                continue;
            }
            $issuer = QualificationCatalog::issuerOf($raw, $lang);
            $key = $issuer['name'];
            if (!isset($out[$key])) {
                $real = isset($issuer['key']) ? QualificationIssuerCatalog::find((string) $issuer['key']) : null;
                $exists = $real !== null ? QualificationIssuerCatalog::matchTenant($real, $rows) > 0 : isset($names[$issuer['name']]);
                $out[$key] = ['name' => $issuer['name'], 'short_name' => $issuer['short_name'], 'real' => $real !== null,
                    'country' => (string) ($real['country'] ?? $items[$id]['country']), 'new' => !$exists, 'count' => 0];
            }
            $out[$key]['count']++;
        }

        return array_values($out);
    }

    /**
     * @param list<string> $order
     * @return list<array<string, string>>
     */
    private static function coursesOf(array $order, string $lang): array
    {
        $out = [];
        foreach ($order as $id) {
            $course = QualificationCatalogExplorer::academyCourse($id, $lang);
            if ($course !== null && !isset($out[$course['key']])) {
                $out[$course['key']] = ['key' => (string) $course['key'], 'title' => (string) $course['title']];
            }
        }

        return array_values($out);
    }

    /**
     * Un lot de l'import : les BATCH éléments suivants du plan, importés par le service d'import (sans doublon, dans
     * la limite de places restantes de la formule ; null = sans limite).
     *
     * Chaque élément du lot ressort avec son sort : créé (fiche du référentiel), déjà présent (fiche existante),
     * bloqué par le plafond de la formule, ou introuvable.
     *
     * @param list<string> $order
     * @return array{ids: list<string>, next: int, created: array<string, int>, skipped: array<string, int>, blocked: list<string>, unknown: list<string>, issuer_ids: list<int>}
     */
    public function batch(int $tenantId, array $order, int $from, string $lang, ?int $actorId, ?int $remaining, int $size = self::BATCH): array
    {
        $from = max(0, $from);
        $ids = array_values(array_slice($order, $from, max(1, $size)));
        $out = ['ids' => $ids, 'next' => $from + count($ids), 'created' => [], 'skipped' => [], 'blocked' => [], 'unknown' => [], 'issuer_ids' => []];
        if ($ids === []) {
            return $out;
        }
        $before = $this->importer->importedCodes($tenantId);
        $result = $this->importer->import($tenantId, $ids, $lang, $actorId, $remaining);
        foreach ($ids as $id) {
            $raw = QualificationCatalog::findRaw($id);
            $def = (int) ($result['definitions'][$id] ?? 0);
            if ($raw === null || QualificationCatalog::problems($raw) !== []) {
                $out['unknown'][] = $id;
            } elseif (isset($before[strtoupper((string) $raw['code'])])) {
                $out['skipped'][$id] = $def;
            } elseif ($def > 0) {
                $out['created'][$id] = $def;
            } else {
                $out['blocked'][] = $id;
            }
        }
        $out['issuer_ids'] = $result['issuer_ids'];

        return $out;
    }
}
