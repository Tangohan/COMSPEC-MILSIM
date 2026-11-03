<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\JobCatalog\JobCatalog;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCatalog\QualificationCatalogExplorer;
use App\Support\QualificationSuggestion\UnitNatureDetector;

/**
 * Qualifications et brevets suggérés à un membre (fiche membre, « Mes qualifications ») : calcul pur, testable seul,
 * tout vient de l’entrée et de deux fonctions de lecture.
 *
 * Sources croisées, chacune avec ses points et sa raison affichée :
 * - fonction attribuée : qualifications exigées par ses prérequis (« exigée par la fonction ») ;
 * - emploi du catalogue d’emplois reconnu dans la fonction (abrégé, nom) : brevets qu’il cite (qualification_refs,
 *   reference_keys « qualification:<id> ») — « recommandé pour l’emploi TACP » ;
 * - nature de l’unité d’affectation et de sa chaîne (UnitNatureDetector) : brevets cités par les emplois du catalogue
 *   servant dans ce type d’unité, catégories du catalogue de qualifications, fiches du référentiel de la communauté
 *   dont le nom s’y rapporte — « courant dans les unités Special Tactics » ;
 * - prérequis des suggestions les plus fortes (« prérequis de JTAC »).
 * Le pays de l’unité (unité réelle, nature propre à un pays, emploi reconnu) favorise le catalogue de ce pays.
 * Une qualification détenue, retirée ou échouée n’est jamais suggérée ; une suggestion ignorée pour ce membre part
 * dans la liste « ignorées ». État de chaque suggestion : en cours, en cours de formation Academy, prête à obtenir,
 * conditions manquantes (« il manque : 5 sauts »), ou pas encore dans le référentiel.
 */
final class MemberQualificationSuggester
{
    public const MAX = 12;
    public const MIN_POINTS = 8.0;

    public const STATE_PENDING = 'pending';
    public const STATE_ACADEMY = 'academy';
    public const STATE_READY = 'ready';
    public const STATE_MISSING = 'missing';
    public const STATE_NOT_IMPORTED = 'not_imported';

    /** Raisons affichées (texte fixe traduit, puis le sujet en clair). */
    public const REASONS = [
        'required' => 'Exigée par la fonction',
        'job' => 'Recommandée pour l’emploi',
        'nature' => 'Courante dans les',
        'prereq' => 'Prérequis de',
    ];

    /**
     * @param array{
     *   lang?: string,
     *   jobs?: list<array{role_id?: int, name?: string, abbreviation?: string, label_en?: string, detail?: string}>,
     *   job_requirements?: array<int, list<int>>,
     *   unit_texts?: list<array{text: string, weight: float}>,
     *   country?: string,
     *   definitions?: array<int, array<string, mixed>>,
     *   held?: array<int, string>,
     *   grade_nato?: string,
     *   dismissed?: array<string, mixed>,
     *   courses?: array<string, string>,
     *   enrolled?: array<string, string>
     * } $in
     * @param callable(int): ?array{state: string, missing: list<array<string, mixed>>, allow_manual: bool, course_keys: list<string>} $evaluate
     *        état d’une fiche du référentiel pour le membre (null : inconnue)
     * @param callable(array<string, mixed>): ?float $measure valeur du membre pour une condition chiffrée (null : inconnue)
     * @return array{items: list<array<string, mixed>>, dismissed: list<array<string, mixed>>, held: int, natures: array<string, float>, jobs: list<array{label: string, country: string}>, country: string}
     */
    public static function suggest(array $in, callable $evaluate, callable $measure): array
    {
        $lang = QualificationCatalog::lang((string) ($in['lang'] ?? 'fr'));
        $defs = is_array($in['definitions'] ?? null) ? $in['definitions'] : [];
        $byCode = [];
        foreach ($defs as $id => $d) {
            $code = strtoupper(trim((string) ($d['code'] ?? '')));
            if ($code !== '') {
                $byCode[$code] = (int) $id;
            }
        }
        $catalog = [];
        $catalogByCode = [];
        foreach (QualificationCatalog::rawEntries() as $e) {
            $id = (string) ($e['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $catalog[$id] = $e;
            $code = strtoupper(trim((string) ($e['code'] ?? '')));
            if ($code !== '') {
                $catalogByCode[$code] = $id;
            }
        }
        $packCountry = static fn (array $e): string => (string) (QualificationCatalog::pack((string) ($e['pack'] ?? ''))['country'] ?? '');

        $natures = UnitNatureDetector::detect(is_array($in['unit_texts'] ?? null) ? $in['unit_texts'] : []);
        $country = strtoupper(trim((string) ($in['country'] ?? '')));
        if ($country === '') {
            foreach ($natures as $n => $s) {
                if ($s >= 0.6 && UnitNatureDetector::country($n) !== '') {
                    $country = UnitNatureDetector::country($n);
                    break;
                }
            }
        }
        $jobs = self::matchJobs(is_array($in['jobs'] ?? null) ? $in['jobs'] : [], $country, (string) ($in['grade_nato'] ?? ''));
        if ($country === '' && $jobs !== []) {
            $country = (string) ($jobs[0]['entry']['country'] ?? '');
        }

        /** @var array<string, array{points: float, reasons: array<string, array{kind: string, subject: string, points: float}>}> $cand */
        $cand = [];
        $add = static function (string $key, float $points, string $kind, string $subject) use (&$cand): void {
            if ($points <= 0) {
                return;
            }
            $cand[$key] ??= ['points' => 0.0, 'reasons' => []];
            $cand[$key]['points'] += $points;
            $rk = $kind . '|' . $subject;
            if (!isset($cand[$key]['reasons'][$rk]) || $cand[$key]['reasons'][$rk]['points'] < $points) {
                $cand[$key]['reasons'][$rk] = ['kind' => $kind, 'subject' => $subject, 'points' => $points];
            }
        };
        $keyOfDefinition = static function (int $defId) use ($defs, $catalogByCode): string {
            $code = strtoupper(trim((string) ($defs[$defId]['code'] ?? '')));

            return isset($catalogByCode[$code]) ? 'q:' . $catalogByCode[$code] : 'd:' . $defId;
        };

        // 1. Prérequis des fonctions attribuées.
        $roleNames = [];
        foreach (is_array($in['jobs'] ?? null) ? $in['jobs'] : [] as $job) {
            $roleNames[(int) ($job['role_id'] ?? 0)] = trim((string) ($job['name'] ?? ''));
        }
        foreach (is_array($in['job_requirements'] ?? null) ? $in['job_requirements'] : [] as $roleId => $ids) {
            foreach ((array) $ids as $defId) {
                if (isset($defs[(int) $defId])) {
                    $add($keyOfDefinition((int) $defId), 100.0, 'required', $roleNames[(int) $roleId] ?? '');
                }
            }
        }

        // 2. Emplois du catalogue reconnus dans les fonctions.
        foreach ($jobs as $m) {
            foreach (JobCatalog::qualificationRefs($m['entry']) as $ref) {
                if (isset($catalog[$ref])) {
                    $add('q:' . $ref, 70.0 * $m['score'], 'job', $m['label']);
                }
            }
        }

        // 3. Nature de l’unité : emplois du catalogue servant dans ce type d’unité, catégories, fiches de la communauté.
        $jobEntries = JobCatalog::rawEntries();
        foreach ($natures as $nature => $s) {
            $label = UnitNatureDetector::label($nature);
            $counts = [];
            foreach ($jobEntries as $job) {
                if ($country !== '' && (string) ($job['country'] ?? '') !== $country) {
                    continue;
                }
                $text = implode(' | ', array_filter([
                    is_array($job['unit'] ?? null) ? implode(' ', array_map('strval', $job['unit'])) : (string) ($job['unit'] ?? ''),
                    (string) ($job['formation'] ?? ''),
                ]));
                if ($text === '' || !UnitNatureDetector::matches($nature, $text)) {
                    continue;
                }
                foreach (JobCatalog::qualificationRefs($job) as $ref) {
                    $counts[$ref] = ($counts[$ref] ?? 0) + 1;
                }
            }
            foreach ($counts as $ref => $c) {
                if (isset($catalog[$ref])) {
                    $add('q:' . $ref, $s * (12.0 + 6.0 * min($c, 4)), 'nature', $label);
                }
            }
            $cats = UnitNatureDetector::categories($nature);
            foreach ($catalog as $id => $e) {
                if (!in_array((string) ($e['category'] ?? ''), $cats, true)) {
                    continue;
                }
                if (isset($cand['q:' . $id])) {
                    $add('q:' . $id, 8.0 * $s, 'nature', $label);
                } elseif ($s >= 0.6 && ($country === '' || $packCountry($e) === $country)) {
                    // Pays inconnu : les standards OTAN d’abord.
                    $add('q:' . $id, 10.0 * $s * ($country === '' && $packCountry($e) !== 'NATO' ? 0.6 : 1.0), 'nature', $label);
                }
            }
            foreach ($defs as $defId => $d) {
                $text = trim((string) ($d['name'] ?? '') . ' ' . (string) ($d['short_name'] ?? '') . ' ' . (string) ($d['category_name'] ?? ''));
                if ($text !== '' && UnitNatureDetector::matches($nature, $text)) {
                    $add($keyOfDefinition((int) $defId), 35.0 * $s, 'nature', $label);
                }
            }
        }

        // 4. Prérequis des suggestions les plus fortes (un niveau).
        $ranked = $cand;
        uasort($ranked, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);
        foreach (array_slice($ranked, 0, 8, true) as $key => $c) {
            $id = str_starts_with($key, 'q:') ? substr($key, 2) : '';
            if ($id === '' || !isset($catalog[$id]) || $c['points'] < 25) {
                continue;
            }
            $parentName = QualificationCatalog::pick($catalog[$id]['short_name'] ?? '', $lang) ?: QualificationCatalog::pick($catalog[$id]['name'] ?? '', $lang);
            foreach (self::catalogPrerequisiteRefs($catalog[$id], $lang) as $ref) {
                if (isset($catalog[$ref]) && $ref !== $id) {
                    $add('q:' . $ref, min(40.0, 0.5 * $c['points']), 'prereq', $parentName);
                }
            }
        }

        // 5. État, pays, niveau ; détenues et ignorées à part.
        $held = is_array($in['held'] ?? null) ? $in['held'] : [];
        $dismissed = is_array($in['dismissed'] ?? null) ? $in['dismissed'] : [];
        $courses = is_array($in['courses'] ?? null) ? $in['courses'] : [];
        $enrolled = is_array($in['enrolled'] ?? null) ? $in['enrolled'] : [];
        $gradeNato = (string) ($in['grade_nato'] ?? '');
        $holdsCatalog = static function (string $ref) use ($catalog, $byCode, $held): bool {
            $defId = $byCode[strtoupper((string) ($catalog[$ref]['code'] ?? ''))] ?? 0;

            return $defId > 0 && ($held[$defId] ?? '') === 'held';
        };
        $items = [];
        $dismissedItems = [];
        $heldCount = 0;
        foreach ($cand as $key => $c) {
            $catalogId = str_starts_with($key, 'q:') ? substr($key, 2) : '';
            $entry = $catalogId !== '' ? ($catalog[$catalogId] ?? null) : null;
            $defId = $entry !== null
                ? ($byCode[strtoupper((string) ($entry['code'] ?? ''))] ?? 0)
                : (int) substr($key, 2);
            $status = $defId > 0 ? (string) ($held[$defId] ?? '') : '';
            if ($status === 'held') {
                $heldCount++;
                continue;
            }
            if ($status === 'blocked') {
                continue;
            }
            $points = $c['points'];
            $notes = [];
            $entryCountry = $entry !== null ? $packCountry($entry) : '';
            if ($entry !== null && $country !== '' && $entryCountry !== $country) {
                $points *= $entryCountry === 'NATO' ? 0.8 : 0.5;
            }
            if ($entry !== null && QualificationCatalogExplorer::level($entry)['rank'] >= 4) {
                $points *= 0.7;
                $notes[] = 'Niveau instructeur : à envisager après les qualifications de base.';
            }
            if ($points < self::MIN_POINTS) {
                continue;
            }
            $missing = [];
            $courseKeys = [];
            $allowManual = false;
            if ($status === 'pending') {
                $state = self::STATE_PENDING;
            } elseif ($defId > 0) {
                $ev = $evaluate($defId);
                $missing = $ev['missing'] ?? [];
                $courseKeys = $ev['course_keys'] ?? [];
                $allowManual = (bool) ($ev['allow_manual'] ?? true);
                $state = ($ev['state'] ?? '') === self::STATE_PENDING ? self::STATE_PENDING : ($missing === [] ? self::STATE_READY : self::STATE_MISSING);
            } else {
                $state = self::STATE_NOT_IMPORTED;
                $missing = $entry !== null ? self::catalogMissing($entry, $catalog, $holdsCatalog, $measure, $gradeNato, $lang) : [];
            }
            if ($catalogId !== '') {
                $builtin = QualificationCatalogExplorer::academyCourse($catalogId, $lang);
                if ($builtin !== null) {
                    $courseKeys[] = $builtin['key'];
                }
            }
            $course = null;
            foreach (array_values(array_unique($courseKeys)) as $ck) {
                if (isset($courses[$ck])) {
                    $course = ['key' => $ck, 'title' => $courses[$ck], 'enrolled' => ($enrolled[$ck] ?? '') !== '', 'completed' => ($enrolled[$ck] ?? '') === 'completed'];
                    break;
                }
            }
            if ($course !== null && $course['enrolled'] && !$course['completed'] && in_array($state, [self::STATE_MISSING, self::STATE_NOT_IMPORTED, self::STATE_READY], true)) {
                $state = self::STATE_ACADEMY;
            }
            $reasons = array_values($c['reasons']);
            usort($reasons, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);
            $name = $defId > 0 && isset($defs[$defId]) ? (string) ($defs[$defId]['name'] ?? '') : '';
            if ($name === '' && $entry !== null) {
                $name = QualificationCatalog::pick($entry['name'] ?? '', $lang);
            }
            $item = [
                'key' => $key,
                'catalog_id' => $catalogId,
                'pack' => (string) ($entry['pack'] ?? ''),
                'definition_id' => $defId,
                'name' => $name,
                'official_name' => $entry !== null ? trim((string) ($entry['official_name'] ?? '')) : '',
                'category' => $entry !== null ? QualificationCatalog::categoryLabel((string) ($entry['category'] ?? ''), $lang) : (string) ($defs[$defId]['category_name'] ?? ''),
                'country' => $entryCountry,
                'points' => (int) round($points),
                'reasons' => array_map(static fn (array $r): array => ['kind' => $r['kind'], 'text' => self::REASONS[$r['kind']] ?? '', 'subject' => $r['subject']], array_slice($reasons, 0, 3)),
                'state' => $state,
                'missing' => $missing,
                'notes' => $notes,
                'course' => $course,
                'allow_manual' => $allowManual,
            ];
            if (isset($dismissed[$key])) {
                $dismissedItems[] = $item;
                continue;
            }
            $items[] = $item;
        }
        usort($items, static fn (array $a, array $b): int => [$b['points'], $a['name']] <=> [$a['points'], $b['name']]);
        usort($dismissedItems, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return [
            'items' => array_slice($items, 0, self::MAX),
            'dismissed' => $dismissedItems,
            'held' => $heldCount,
            'natures' => $natures,
            'jobs' => array_map(static fn (array $m): array => ['label' => $m['label'], 'country' => (string) ($m['entry']['country'] ?? '')], $jobs),
            'country' => $country,
        ];
    }

    /**
     * Emplois du catalogue reconnus dans les fonctions du membre : abrégé identique ou présent comme mot
     * (« SOF TACP » → TACP), nom identique ou contenu. Le pays de l’unité est préféré, les autres comptent moitié.
     *
     * @param list<array<string, mixed>> $jobs
     * @return list<array{entry: array<string, mixed>, score: float, label: string}>
     */
    public static function matchJobs(array $jobs, string $country, string $gradeNato = ''): array
    {
        // Grade connu : un militaire du rang ou sous-officier n’est pas rapproché d’un emploi d’officier (et inversement).
        $rank = self::natoRank($gradeNato);
        $officer = $rank === 0 ? null : $rank > 100;
        $out = [];
        $entries = JobCatalog::rawEntries();
        foreach ($jobs as $job) {
            $texts = array_filter([(string) ($job['name'] ?? ''), (string) ($job['label_en'] ?? ''), (string) ($job['detail'] ?? '')], static fn (string $t): bool => trim($t) !== '');
            $abbr = JobCatalog::normalize((string) ($job['abbreviation'] ?? ''));
            $words = [];
            foreach ($texts as $t) {
                foreach (explode(' ', JobCatalog::normalize($t)) as $w) {
                    if ($w !== '') {
                        $words[$w] = true;
                    }
                }
            }
            $normTexts = array_map(static fn (string $t): string => trim(JobCatalog::normalize($t)), $texts);
            // Sigles de la fonction : mots tout en capitales de 3 lettres ou plus (« SOF TACP » → sof, tacp), et l’abrégé.
            $acronyms = [];
            foreach ($texts as $t) {
                if (preg_match_all('/\b\p{Lu}{3,}\b/u', $t, $mm) > 0) {
                    foreach ($mm[0] as $a) {
                        $acronyms[] = JobCatalog::normalize($a);
                    }
                }
            }
            if (mb_strlen(trim($abbr)) >= 3) {
                $acronyms[] = trim($abbr);
            }
            // Sigles trop généraux pour désigner un emploi.
            $acronyms = array_values(array_diff(array_unique(array_map('trim', $acronyms)), ['sof', 'cos', 'ops', 'nco', 'sgt', 'cpl', 'adj', 'chef', 'opex']));
            $best = [];
            foreach ($entries as $e) {
                $eAbbr = trim(JobCatalog::normalize((string) ($e['abbreviation'] ?? '')));
                $names = array_filter(array_map(static fn ($n): string => trim(JobCatalog::normalize(is_string($n) ? $n : '')), is_array($e['name'] ?? null) ? array_values($e['name']) : [(string) ($e['name'] ?? '')]));
                $score = 0.0;
                if ($eAbbr !== '' && $abbr !== '' && trim($abbr) === $eAbbr) {
                    $score = 0.9;
                } elseif ($eAbbr !== '' && mb_strlen($eAbbr) >= 3 && !str_contains($eAbbr, ' ') && isset($words[$eAbbr])) {
                    $score = 0.8;
                }
                foreach ($names as $n) {
                    foreach ($normTexts as $t) {
                        if ($t === $n) {
                            $score = max($score, 1.0);
                        } elseif (mb_strlen($t) >= 6 && mb_strlen($n) >= 6 && (str_contains($n, $t) || str_contains($t, $n))) {
                            $score = max($score, 0.6);
                        }
                    }
                }
                // Sigle de la fonction (« TACP ») cité dans le nom de l’emploi (« Officier TACP ») ou dans son unité
                // (« escadrons Special Tactics et unités TACP »).
                if ($score < 0.6 && $acronyms !== []) {
                    $nameWords = array_flip(explode(' ', implode(' ', $names)));
                    $unitText = is_array($e['unit'] ?? null) ? implode(' ', array_map('strval', $e['unit'])) : (string) ($e['unit'] ?? '');
                    $unitWords = array_flip(explode(' ', JobCatalog::normalize($unitText)));
                    foreach ($acronyms as $a) {
                        if (isset($nameWords[$a])) {
                            $score = max($score, 0.6);
                        } elseif (isset($unitWords[$a])) {
                            $score = max($score, 0.45);
                        }
                    }
                }
                if ($score > 0 && $officer !== null && preg_match('/\b(officer|officier)\b/u', implode(' ', $names)) === 1 && !$officer) {
                    $score *= 0.5;
                }
                if ($score <= 0) {
                    continue;
                }
                if ($country !== '' && (string) ($e['country'] ?? '') !== $country) {
                    $score *= 0.5;
                }
                if ($score >= 0.4) {
                    $best[] = ['entry' => $e, 'score' => round($score, 2), 'label' => trim((string) ($e['abbreviation'] ?? '')) !== '' ? (string) $e['abbreviation'] : QualificationCatalog::pick($e['name'] ?? '', 'en')];
                }
            }
            usort($best, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            // Trois emplois au plus par fonction, les mieux reconnus.
            foreach (array_slice($best, 0, 3) as $b) {
                $out[] = $b;
            }
        }
        usort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $out;
    }

    /** Qualifications du catalogue exigées par une entrée (prérequis structurés, puis prérequis reliés). @return list<string> */
    public static function catalogPrerequisiteRefs(array $entry, string $lang = 'fr'): array
    {
        $refs = [];
        foreach (is_array($entry['prerequisites_detected'] ?? null) ? $entry['prerequisites_detected'] : [] as $p) {
            if (is_array($p) && ($p['kind'] ?? '') === 'qualification' && is_string($p['ref'] ?? null)) {
                $refs[] = $p['ref'];
            }
        }
        foreach (QualificationCatalogExplorer::prerequisites($entry, $lang) as $p) {
            foreach ($p['refs'] as $r) {
                $refs[] = (string) $r;
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * Conditions d’une qualification pas encore importée, lues dans ses prérequis structurés : qualification à
     * détenir, condition chiffrée (sauts, heures…) comparée à la valeur du membre, grade OTAN minimum.
     *
     * @param array<string, array<string, mixed>> $catalog
     * @param callable(string): bool $holds
     * @param callable(array<string, mixed>): ?float $measure
     * @return list<array{text: string, subject: string, criterion: array<string, mixed>|null}>
     */
    private static function catalogMissing(array $entry, array $catalog, callable $holds, callable $measure, string $gradeNato, string $lang): array
    {
        $out = [];
        foreach (is_array($entry['prerequisites_detected'] ?? null) ? $entry['prerequisites_detected'] : [] as $p) {
            if (!is_array($p)) {
                continue;
            }
            $kind = (string) ($p['kind'] ?? '');
            if ($kind === 'qualification') {
                $ref = (string) ($p['ref'] ?? '');
                if (isset($catalog[$ref]) && !$holds($ref)) {
                    $out[] = ['text' => 'Qualification', 'subject' => QualificationCatalog::pick($catalog[$ref]['name'] ?? '', $lang), 'criterion' => null];
                }
            } elseif ($kind === 'metric' || $kind === 'attestation') {
                $c = $kind === 'metric'
                    ? ['type' => 'metric', 'metric' => (string) ($p['metric'] ?? ''), 'scope' => (string) ($p['scope'] ?? ''), 'min' => (float) ($p['min'] ?? 0), 'window_days' => (int) ($p['window_days'] ?? 0)]
                    : ['type' => 'attestation', 'key' => (string) ($p['key'] ?? 'other'), 'label' => (string) ($p['label'] ?? ''), 'min' => (float) ($p['min'] ?? 0)];
                $target = (float) $c['min'];
                $current = (float) ($measure($c) ?? 0.0);
                if ($target > 0 && $current < $target) {
                    $out[] = ['text' => 'Il manque', 'subject' => '', 'criterion' => ['min' => round($target - $current, 2)] + $c];
                } elseif ($target <= 0 && $measure($c) === null) {
                    $out[] = ['text' => 'À attester', 'subject' => '', 'criterion' => $c];
                }
            } elseif ($kind === 'grade') {
                $nato = strtoupper((string) ($p['nato'] ?? ''));
                if ($nato !== '' && self::natoRank($gradeNato) < self::natoRank($nato)) {
                    $out[] = ['text' => 'Grade minimum', 'subject' => $nato, 'criterion' => null];
                }
            }
        }

        return $out;
    }

    /** Rang comparable d’un code OTAN (OR-1 … OR-9, OF-1 … OF-10 au-dessus) ; 0 si inconnu. */
    public static function natoRank(string $code): int
    {
        if (preg_match('/^(OR|OF|WO)-?(\d{1,2})$/', strtoupper(str_replace(' ', '', $code)), $m) !== 1) {
            return 0;
        }

        return match ($m[1]) {
            'OF' => 100 + (int) $m[2],
            'WO' => 50 + (int) $m[2],
            default => (int) $m[2],
        };
    }
}
