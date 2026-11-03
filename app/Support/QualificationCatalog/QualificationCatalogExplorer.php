<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

use App\Services\Academy\AcademyBuiltInCourses;
use App\Support\JobCatalog\JobCatalog;

/**
 * Liens du catalogue de qualifications de référence (QualificationCatalog), pour sa fiche détaillée (fenêtre
 * « Chercher dans le catalogue » des emplois et page Catalogue de référence du référentiel) et pour la page qui le
 * parcourt (filtres, tri). Rien n’est ajouté aux données des packs : tout est déduit de ce qu’ils contiennent.
 *
 * - Prérequis reliés : chaque prérequis (texte libre du pack) est rapproché des entrées du même pack (ou de l’OTAN)
 *   dont il cite l’abrégé (« CDQC », « JTAC »), l’intitulé officiel ou le nom (sans la parenthèse finale). Un
 *   prérequis ambigu ou sans correspondance reste du texte.
 * - Emplois qui l’exigent : emplois du catalogue d’emplois (JobCatalog) dont qualification_refs cite l’entrée.
 * - Formation Academy : cours intégré de l’Academy (AcademyBuiltInCourses) qui entraîne en jeu à la même procédure
 *   (JTAC, ACP 125, appel de feu JFO) ; correspondance fixée dans ACADEMY_COURSES.
 * - Niveau : échelle à cinq niveaux (LEVELS). Une entrée peut porter un champ « level » sourcé (clé de LEVELS) ;
 *   sinon le niveau est ESTIMÉ (estimated = true, affiché « niveau estimé ») : instructeur si l’intitulé désigne un
 *   instructeur ou un moniteur, expert si c’est un instructeur évaluateur, un niveau « master » ou si un prérequis
 *   relié est déjà un instructeur, confirmé pour un cours avancé, de chef ou de chef d’équipe ou dont un prérequis
 *   relié a lui-même des prérequis, qualifié s’il a des prérequis, initial sinon.
 */
final class QualificationCatalogExplorer
{
    /** Échelle des niveaux, du plus simple au plus élevé. */
    public const LEVELS = [
        'initial' => ['rank' => 1, 'fr' => 'Initial', 'en' => 'Initial'],
        'qualified' => ['rank' => 2, 'fr' => 'Qualifié', 'en' => 'Qualified'],
        'confirmed' => ['rank' => 3, 'fr' => 'Confirmé', 'en' => 'Advanced'],
        'instructor' => ['rank' => 4, 'fr' => 'Instructeur', 'en' => 'Instructor'],
        'expert' => ['rank' => 5, 'fr' => 'Expert', 'en' => 'Expert'],
    ];

    /** Entrée du catalogue => cours intégré de l’Academy qui entraîne à la même procédure (formation de jeu). */
    public const ACADEMY_COURSES = [
        'nato-jtac' => AcademyBuiltInCourses::JTAC_KEY,
        'us-jtac' => AcademyBuiltInCourses::JTAC_KEY,
        'fr-jtac' => AcademyBuiltInCourses::JTAC_KEY,
        'de-jtac' => AcademyBuiltInCourses::JTAC_KEY,
        'nato-acp125' => 'otan-procedures-radio-acp125',
        'nato-jfo' => 'otan-appel-de-feu-jfo',
        'us-jfo' => 'otan-appel-de-feu-jfo',
    ];

    public const SORTS = ['nom', 'pays', 'categorie', 'niveau', 'emplois'];

    /** @var array<string, list<string>>|null id de qualification => ids d’emplois */
    private static ?array $jobs = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $levels = null;

    /** @var array<string, list<array{text: string, refs: list<string>}>>|null clé id.lang */
    private static array $prereqs = [];

    /** Caches vidés (tests : autre dossier de packs). */
    public static function reset(): void
    {
        self::$jobs = null;
        self::$levels = null;
        self::$prereqs = [];
    }

    public static function levelLabel(string $key, string $lang): string
    {
        return self::LEVELS[$key][QualificationCatalog::lang($lang)] ?? $key;
    }

    private static function norm(string $text): string
    {
        return ' ' . trim((string) preg_replace('/\s+/u', ' ', JobCatalog::normalize($text))) . ' ';
    }

    /**
     * Formes reconnues d’une entrée dans un texte : [forme, sensible à la casse, sigle tiré d’une parenthèse].
     *
     * @param array<string, mixed> $entry
     * @return list<array{0: string, 1: bool, 2: bool}>
     */
    private static function keys(array $entry): array
    {
        $out = [];
        $short = trim((string) ($entry['short_name'] ?? ''));
        if (mb_strlen($short) >= 3 && preg_match('/\p{Lu}/u', $short) === 1) {
            $out[] = [$short, true, false];
        }
        $names = [(string) ($entry['official_name'] ?? ''), (string) ($entry['name']['fr'] ?? ''), (string) ($entry['name']['en'] ?? '')];
        foreach ($names as $name) {
            foreach ([$name, (string) preg_replace('/\s*\([^()]*\)\s*$/u', '', $name)] as $form) {
                $form = trim($form);
                if (mb_strlen($form) >= 8) {
                    $out[] = [$form, false, false];
                }
                // Sigle entre parenthèses (« … (MFFPC) ») : reconnu comme l’abrégé.
                if (preg_match('/\(([A-Z][A-Z0-9 \/-]{2,15})\)\s*$/u', $name, $m) === 1) {
                    $out[] = [trim($m[1]), true, true];
                }
            }
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }

    /**
     * Entrées citées par un texte, parmi les candidates (la forme la plus longue l’emporte ; une forme contenue dans
     * une autre forme trouvée est ignorée ; deux entrées pour la même forme = ambigu, ignorée).
     *
     * @param list<array<string, mixed>> $candidates entrées brutes
     * @return list<string> ids
     */
    public static function matchText(string $text, array $candidates, string $exceptId = ''): array
    {
        $flat = self::norm($text);
        $hits = [];
        $exact = [];
        $shorts = [];
        foreach ($candidates as $entry) {
            $shorts[(string) ($entry['id'] ?? '')] = trim((string) ($entry['short_name'] ?? ''));
        }
        foreach ($candidates as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($id === '' || $id === $exceptId) {
                continue;
            }
            foreach (self::keys($entry) as [$form, $caseSensitive, $acronym]) {
                $found = $caseSensitive
                    ? preg_match('/(?<![\p{L}\p{N}])' . preg_quote($form, '/') . '(?![\p{L}\p{N}])/u', $text) === 1
                    : str_contains($flat, self::norm($form));
                if ($found && !$acronym) {
                    $exact[$form][$id] = true;
                }
                if ($found) {
                    $hits[$form][$id] = true;
                    // Sigle tiré d’une parenthèse et repris par l’abrégé d’une autre entrée (« PFV » : « PFV KSK »,
                    // « PFV KSM ») : ambigu.
                    foreach ($acronym ? $shorts : [] as $otherId => $otherShort) {
                        if ($otherId !== $id && preg_match('/(?<![\p{L}\p{N}])' . preg_quote($form, '/') . '(?![\p{L}\p{N}])/u', $otherShort) === 1) {
                            $hits[$form][$otherId] = true;
                        }
                    }
                }
            }
        }
        // Une forme trouvée comme abrégé ou nom exact d’une entrée l’emporte sur le même sigle tiré d’une parenthèse.
        $hits = array_replace($hits, $exact);
        $forms = array_keys(array_filter($hits, static fn (array $ids): bool => count($ids) === 1));
        usort($forms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $kept = [];
        $out = [];
        foreach ($forms as $form) {
            $id = (string) array_key_first($hits[$form]);
            foreach ($kept as $longer) {
                if (str_contains(self::norm($longer), self::norm($form))) {
                    continue 2;
                }
            }
            $kept[] = $form;
            if (!in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return array_slice($out, 0, 3);
    }

    /** @return list<list<array<string, mixed>>> entrées où chercher les prérequis d’une entrée : son pack, puis l’OTAN */
    private static function neighbours(array $entry): array
    {
        $pack = (string) ($entry['pack'] ?? '');
        $own = QualificationCatalog::rawEntries($pack);

        return $pack === 'nato' ? [$own] : [$own, QualificationCatalog::rawEntries('nato')];
    }

    /**
     * Prérequis d’une entrée dans la langue du lecteur, chacun avec les entrées du catalogue qu’il cite.
     *
     * @param array<string, mixed> $entry entrée brute (avec son pack)
     * @return list<array{text: string, refs: list<string>}>
     */
    public static function prerequisites(array $entry, string $lang): array
    {
        $lang = QualificationCatalog::lang($lang);
        $key = (string) ($entry['pack'] ?? '') . ':' . (string) ($entry['id'] ?? '') . ':' . $lang;
        if (isset(self::$prereqs[$key])) {
            return self::$prereqs[$key];
        }
        $fr = is_array($entry['prerequisites']['fr'] ?? null) ? array_values($entry['prerequisites']['fr']) : [];
        $en = is_array($entry['prerequisites']['en'] ?? null) ? array_values($entry['prerequisites']['en']) : [];
        $shown = $lang === 'en' && $en !== [] ? $en : ($fr !== [] ? $fr : $en);
        $neighbours = self::neighbours($entry);
        $out = [];
        foreach ($shown as $i => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            // Même prérequis dans les deux langues : les noms officiels (souvent anglais ou allemands) s’y retrouvent.
            $both = trim((string) ($fr[$i] ?? '')) . ' | ' . trim((string) ($en[$i] ?? ''));
            $refs = [];
            foreach ($neighbours as $pool) {
                $refs = $refs !== [] ? $refs : self::matchText($both, $pool, (string) ($entry['id'] ?? ''));
            }
            $out[] = ['text' => $text, 'refs' => $refs];
        }

        return self::$prereqs[$key] = $out;
    }

    /**
     * Niveau d’une entrée (voir l’en-tête de la classe).
     *
     * @param array<string, mixed> $entry entrée brute
     * @return array{key: string, rank: int, estimated: bool}
     */
    public static function level(array $entry, int $depth = 0): array
    {
        $id = (string) ($entry['id'] ?? '');
        if ($depth === 0 && isset(self::$levels[$id])) {
            return self::$levels[$id];
        }
        $own = (string) ($entry['level'] ?? '');
        if (isset(self::LEVELS[$own])) {
            $level = ['key' => $own, 'rank' => self::LEVELS[$own]['rank'], 'estimated' => false];
        } else {
            $level = self::estimate($entry, $depth);
        }
        if ($depth === 0) {
            self::$levels[$id] = $level;
        }

        return $level;
    }

    /** @return array{key: string, rank: int, estimated: bool} */
    private static function estimate(array $entry, int $depth): array
    {
        $title = implode(' ', [(string) ($entry['name']['en'] ?? ''), (string) ($entry['official_name'] ?? ''), (string) ($entry['short_name'] ?? '')]);
        $instructor = preg_match('/\b(instructor|instructeur|moniteur|ausbilder|lehrer)/iu', $title) === 1;
        $expert = preg_match('/\b(evaluator|évaluateur|master)\b/iu', $title) === 1;
        $prereqs = is_array($entry['prerequisites']['fr'] ?? null) ? array_filter($entry['prerequisites']['fr'], static fn ($p): bool => trim((string) $p) !== '') : [];
        $refs = [];
        if ($depth < 3) {
            foreach (self::prerequisites($entry, 'fr') as $p) {
                foreach ($p['refs'] as $ref) {
                    $raw = QualificationCatalog::findRaw($ref);
                    if ($raw !== null) {
                        $refs[] = self::level($raw, $depth + 1)['rank'];
                    }
                }
            }
        }
        $key = 'initial';
        if ($instructor && ($expert || in_array(4, $refs, true) || in_array(5, $refs, true))) {
            $key = 'expert';
        } elseif ($expert && !$instructor) {
            $key = 'expert';
        } elseif ($instructor) {
            $key = 'instructor';
        } elseif (preg_match('/\b(advanced|senior|leader|chief|commander|supervisor|jumpmaster|tandem\w*|\w*führer|\w*fuehrer)\b/iu', $title) === 1
            || ($refs !== [] && max($refs) >= 2)) {
            $key = 'confirmed';
        } elseif ($prereqs !== []) {
            $key = 'qualified';
        }

        return ['key' => $key, 'rank' => self::LEVELS[$key]['rank'], 'estimated' => true];
    }

    /** @return array<string, list<string>> id de qualification => ids des emplois du catalogue qui la citent */
    public static function jobIndex(): array
    {
        if (self::$jobs !== null) {
            return self::$jobs;
        }
        $out = [];
        foreach (JobCatalog::rawEntries() as $job) {
            foreach (JobCatalog::qualificationRefs($job) as $ref) {
                $out[$ref][] = (string) ($job['id'] ?? '');
            }
        }

        return self::$jobs = $out;
    }

    /**
     * Emplois du catalogue qui exigent une qualification (nom dans la langue du lecteur, nom officiel, pays).
     *
     * @return list<array{id: string, name: string, official_name: string, country: string, country_label: string}>
     */
    public static function jobs(string $id, string $lang): array
    {
        $ids = array_flip(self::jobIndex()[$id] ?? []);
        $out = [];
        foreach (JobCatalog::rawEntries() as $job) {
            if (isset($ids[(string) ($job['id'] ?? '')])) {
                $l = JobCatalog::localize($job, $lang);
                $out[] = ['id' => $l['id'], 'name' => $l['name'], 'official_name' => $l['official_name'], 'country' => $l['country'], 'country_label' => $l['country_label']];
            }
        }

        return $out;
    }

    /**
     * Cours intégré de l’Academy correspondant (formation de jeu), ou null.
     *
     * @return array{key: string, code: string, title: string, qualification: string}|null
     */
    public static function academyCourse(string $id, string $lang): ?array
    {
        $key = self::ACADEMY_COURSES[$id] ?? '';
        $course = $key !== '' ? AcademyBuiltInCourses::find($key) : null;
        if ($course === null) {
            return null;
        }

        return [
            'key' => $key,
            'code' => (string) ($course['qualification']['code'] ?? ''),
            'title' => QualificationCatalog::pick($course['title'] ?? '', $lang),
            'qualification' => QualificationCatalog::pick($course['qualification']['name'] ?? '', $lang),
        ];
    }

    /**
     * Fiche détaillée d’une qualification du catalogue : intitulé officiel et traductions, émetteur réel, référence,
     * prérequis reliés, validité, sources, niveau, emplois qui l’exigent, cours Academy.
     *
     * @return array<string, mixed>|null
     */
    public static function detail(string $id, string $lang): ?array
    {
        $raw = QualificationCatalog::findRaw($id);
        if ($raw === null) {
            return null;
        }
        $lang = QualificationCatalog::lang($lang);
        $l = QualificationCatalog::localize($raw, $lang);
        $pack = QualificationCatalog::pack((string) $raw['pack']) ?? [];
        $issuer = QualificationCatalog::issuerOf($raw, $lang);
        $prerequisites = [];
        foreach (self::prerequisites($raw, $lang) as $p) {
            $links = [];
            foreach ($p['refs'] as $ref) {
                $r = QualificationCatalog::findRaw($ref);
                if ($r !== null) {
                    $links[] = ['id' => $ref, 'name' => QualificationCatalog::pick($r['name'] ?? '', $lang), 'code' => (string) ($r['code'] ?? '')];
                }
            }
            $prerequisites[] = ['text' => $p['text'], 'links' => $links];
        }
        $level = self::level($raw);
        // Entrées qui la citent comme prérequis (« Ouvre l’accès à »).
        $required = [];
        foreach (QualificationCatalog::rawEntries() as $other) {
            if ((string) $other['id'] === $id) {
                continue;
            }
            foreach (self::prerequisites($other, $lang) as $p) {
                if (in_array($id, $p['refs'], true)) {
                    $required[] = ['id' => (string) $other['id'], 'name' => QualificationCatalog::pick($other['name'] ?? '', $lang), 'code' => (string) ($other['code'] ?? '')];
                    break;
                }
            }
        }

        return $l + [
            'names' => array_filter([
                'official' => $l['official_name'],
                'fr' => trim((string) ($raw['name']['fr'] ?? '')),
                'en' => trim((string) ($raw['name']['en'] ?? '')),
            ], static fn (string $v): bool => $v !== ''),
            'official_lang' => (string) ($pack['official_lang'] ?? 'en'),
            'issuer_detail' => $issuer,
            'disclaimer' => QualificationCatalog::disclaimer($lang, (string) $raw['pack']),
            'prerequisite_links' => $prerequisites,
            'unlocks' => $required,
            'level' => $level + ['label' => self::levelLabel($level['key'], $lang)],
            'jobs' => self::jobs($id, $lang),
            'academy' => self::academyCourse($id, $lang),
        ];
    }

    /**
     * Lignes de la page Catalogue de référence (toutes les entrées, tous packs) avec niveau, nombre d’emplois liés,
     * cours Academy et texte cherché (FR, EN et intitulé officiel).
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(string $lang): array
    {
        $lang = QualificationCatalog::lang($lang);
        $jobs = self::jobIndex();
        $out = [];
        foreach (QualificationCatalog::rawEntries() as $raw) {
            $l = QualificationCatalog::localize($raw, $lang);
            $level = self::level($raw);
            $academy = self::academyCourse($l['id'], $lang);
            $hay = implode(' ', [$l['id'], $l['code'], $l['short_name'], $l['official_name'], (string) ($raw['name']['fr'] ?? ''), (string) ($raw['name']['en'] ?? ''),
                (string) ($raw['description']['fr'] ?? ''), (string) ($raw['description']['en'] ?? ''), $l['issuer'], $l['category_label'], $l['country_label']]);
            $out[] = $l + [
                'level' => $level + ['label' => self::levelLabel($level['key'], $lang)],
                'jobs_count' => count($jobs[$l['id']] ?? []),
                'academy' => $academy,
                '_hay' => self::norm($hay . ' ' . JobCatalog::transliterate($hay)),
            ];
        }

        return $out;
    }

    /**
     * Filtres lus dans l’adresse de la page (valeurs inconnues ignorées).
     *
     * @param array<string, mixed> $query
     * @return array{q: string, pays: string, categorie: string, niveau: string, emetteur: string, academy: string, etat: string, tri: string}
     */
    public static function filters(array $query): array
    {
        $s = static fn (string $k, int $max = 120): string => is_scalar($query[$k] ?? null) ? mb_substr(trim((string) $query[$k]), 0, $max) : '';
        $country = strtoupper($s('pays', 10));
        $category = $s('categorie', 40);
        $level = $s('niveau', 20);
        $tri = $s('tri', 20);

        return [
            'q' => $s('q'),
            'pays' => preg_match('/^[A-Z]{2,5}$/', $country) === 1 ? $country : '',
            'categorie' => isset(QualificationCatalog::CATEGORIES[$category]) ? $category : '',
            'niveau' => isset(self::LEVELS[$level]) ? $level : '',
            'emetteur' => $s('emetteur', 200),
            'academy' => $s('academy', 1) === '1' ? '1' : '',
            'etat' => in_array($s('etat', 20), ['importee', 'a-importer'], true) ? $s('etat', 20) : '',
            'tri' => in_array($tri, self::SORTS, true) ? $tri : 'pays',
        ];
    }

    /**
     * Lignes retenues par les filtres, triées.
     *
     * @param list<array<string, mixed>> $rows rows()
     * @param array<string, string> $f filters()
     * @param array<string, true> $imported codes déjà dans le référentiel (majuscules)
     * @return list<array<string, mixed>>
     */
    public static function filter(array $rows, array $f, array $imported): array
    {
        $words = array_values(array_filter(explode(' ', trim(self::norm((string) ($f['q'] ?? ''))))));
        $out = array_values(array_filter($rows, static function (array $r) use ($f, $imported, $words): bool {
            $done = isset($imported[strtoupper((string) $r['code'])]);
            foreach ($words as $w) {
                if (!str_contains((string) $r['_hay'], $w)) {
                    return false;
                }
            }

            return (($f['pays'] ?? '') === '' || $r['country'] === $f['pays'])
                && (($f['categorie'] ?? '') === '' || $r['category'] === $f['categorie'])
                && (($f['niveau'] ?? '') === '' || $r['level']['key'] === $f['niveau'])
                && (($f['emetteur'] ?? '') === '' || $r['issuer'] === $f['emetteur'])
                && (($f['academy'] ?? '') === '' || $r['academy'] !== null)
                && (($f['etat'] ?? '') === '' || ($f['etat'] === 'importee') === $done);
        }));
        $countries = array_flip(array_keys(QualificationCatalog::COUNTRIES));
        $categories = array_flip(array_keys(QualificationCatalog::CATEGORIES));
        $byName = static fn (array $a, array $b): int => strcasecmp(JobCatalog::normalize((string) $a['name']), JobCatalog::normalize((string) $b['name']));
        $byCountry = static fn (array $a, array $b): int => ($countries[$a['country']] ?? 99) <=> ($countries[$b['country']] ?? 99);
        $byCategory = static fn (array $a, array $b): int => ($categories[$a['category']] ?? 99) <=> ($categories[$b['category']] ?? 99);
        $sort = match ($f['tri'] ?? 'pays') {
            'nom' => $byName,
            'categorie' => static fn (array $a, array $b): int => $byCategory($a, $b) ?: $byCountry($a, $b) ?: $byName($a, $b),
            'niveau' => static fn (array $a, array $b): int => ($b['level']['rank'] <=> $a['level']['rank']) ?: $byCountry($a, $b) ?: $byName($a, $b),
            'emplois' => static fn (array $a, array $b): int => ($b['jobs_count'] <=> $a['jobs_count']) ?: $byName($a, $b),
            default => static fn (array $a, array $b): int => $byCountry($a, $b) ?: $byCategory($a, $b) ?: $byName($a, $b),
        };
        usort($out, $sort);

        return $out;
    }
}
