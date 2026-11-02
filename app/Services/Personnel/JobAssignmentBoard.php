<?php

declare(strict_types=1);

namespace App\Services\Personnel;

/**
 * Tableau « Qui tient quel emploi » : filtres de l’URL, recherche, tri, indicateurs et export.
 *
 * Calcul pur, sans base de données : JobAssignmentBoardLoader fournit les emplois et les membres
 * déjà passés par la confidentialité (membres masqués absents, anonymisés sans identité, unités
 * restreintes remplacées par leur mention). Rien ici ne doit réintroduire une donnée retirée.
 */
final class JobAssignmentBoard
{
    public const VIEWS = ['matrice', 'liste', 'membres'];
    public const STATES = ['', 'vacants', 'sans_titulaire', 'critiques', 'sans_emploi', 'multi'];
    public const ORIGINS = ['', 'site', 'community', 'orbat', 'masquees'];
    public const JOB_SORTS = ['categorie', 'nom', 'titulaires', 'vacants'];
    public const MEMBER_SORTS = ['nom', 'grade', 'unite', 'emplois'];

    /**
     * Filtres lus dans l’URL, bornés et normalisés (une valeur inconnue revient au défaut).
     *
     * @param array<string, mixed> $query
     * @return array{q: string, categorie: int, etiquette: int, unite: int, sous_unites: bool, grade: string, statut: string,
     *   etat: string, origine: string, tri: string, affichage: string}
     */
    public static function filters(array $query): array
    {
        $str = static fn (string $key, int $max = 120): string => mb_substr(trim(is_scalar($query[$key] ?? null) ? (string) $query[$key] : ''), 0, $max, 'UTF-8');
        $view = $str('affichage', 20);
        $view = in_array($view, self::VIEWS, true) ? $view : 'matrice';
        $state = $str('etat', 20);
        $origin = $str('origine', 20);
        $sort = $str('tri', 20);
        $sorts = $view === 'membres' ? self::MEMBER_SORTS : self::JOB_SORTS;

        return [
            'q' => $str('q'),
            'categorie' => max(0, (int) ($query['categorie'] ?? 0)),
            'etiquette' => max(0, (int) ($query['etiquette'] ?? 0)),
            'unite' => max(0, (int) ($query['unite'] ?? 0)),
            // Les sous-unités sont incluses par défaut : « 1er GTIA » couvre ses compagnies.
            'sous_unites' => !array_key_exists('sous_unites', $query) || (string) $query['sous_unites'] !== '0',
            'grade' => $str('grade', 60),
            'statut' => $str('statut', 80),
            'etat' => in_array($state, self::STATES, true) ? $state : '',
            'origine' => in_array($origin, self::ORIGINS, true) ? $origin : '',
            'tri' => in_array($sort, $sorts, true) ? $sort : $sorts[0],
            'affichage' => $view,
        ];
    }

    /**
     * Paramètres d’URL à conserver (valeurs par défaut omises).
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $override
     * @return array<string, string>
     */
    public static function query(array $filters, array $override = []): array
    {
        $f = array_merge($filters, $override);
        $defaults = self::filters([]);
        $out = ['vue' => 'attributions'];
        foreach (['affichage', 'q', 'categorie', 'etiquette', 'unite', 'grade', 'statut', 'etat', 'origine', 'tri'] as $key) {
            $value = $f[$key] ?? '';
            if ($key === 'tri') {
                $sorts = ($f['affichage'] ?? 'matrice') === 'membres' ? self::MEMBER_SORTS : self::JOB_SORTS;
                if (!in_array($value, $sorts, true) || $value === $sorts[0]) {
                    continue;
                }
            } elseif ($value === '' || $value === 0 || $value === null || $value === ($defaults[$key] ?? null)) {
                continue;
            }
            $out[$key] = (string) $value;
        }
        if (!empty($f['unite']) && empty($f['sous_unites'])) {
            $out['sous_unites'] = '0';
        }

        return $out;
    }

    /** Minuscules sans accents, pour une recherche tolérante (« medecin » trouve « Médecin »). */
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $text = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
            }
        } else {
            // Sans l’extension intl : accents usuels des langues latines.
            $text = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
                'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
                'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae']);
        }

        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    /**
     * @param list<array<string, mixed>> $categories lignes {id, parent_id, name}
     * @return array<int, list<int>> catégorie => elle-même et toutes ses sous-catégories
     */
    public static function categoryDescendants(array $categories): array
    {
        $children = [];
        foreach ($categories as $c) {
            $children[(int) ($c['parent_id'] ?? 0)][] = (int) ($c['id'] ?? 0);
        }
        $out = [];
        foreach ($categories as $c) {
            $id = (int) ($c['id'] ?? 0);
            $seen = [$id => true];
            $stack = [$id];
            while ($stack !== []) {
                foreach ($children[array_pop($stack)] ?? [] as $child) {
                    if ($child > 0 && !isset($seen[$child])) {
                        $seen[$child] = true;
                        $stack[] = $child;
                    }
                }
            }
            $out[$id] = array_keys($seen);
        }

        return $out;
    }

    /**
     * Construit le tableau filtré.
     *
     * Emploi attendu : {id, name, category_id, category, abbreviation?, tags: list<{id,name,color}>, origin, hidden,
     *   places, linked_unit_ids: list<int>, holder_keys: list<string>, …}.
     * Membre attendu : {key, id, name, callsign, unit_label, unit_chain: list<int>, grade, grade_order, account_status,
     *   situation, jobs: list<int>, primary_job, selectable, …}.
     *
     * @param list<array<string, mixed>> $jobs
     * @param list<array<string, mixed>> $members
     * @param array<string, mixed> $filters résultat de filters()
     * @param list<array<string, mixed>> $categories
     * @return array<string, mixed>
     */
    public static function build(array $jobs, array $members, array $filters, array $categories = []): array
    {
        $membersByKey = [];
        foreach ($members as $m) {
            $membersByKey[(string) $m['key']] = $m;
        }
        // Les titulaires connus d’un emploi : seulement les membres affichables (les masqués ont déjà disparu).
        foreach ($jobs as &$job) {
            $job['holder_keys'] = array_values(array_filter(
                array_map('strval', $job['holder_keys'] ?? []),
                static fn (string $k): bool => isset($membersByKey[$k])
            ));
            $job['holders_total'] = count($job['holder_keys']);
            $places = max(0, (int) ($job['places'] ?? 0));
            $job['places'] = $places;
            $job['vacancies'] = $places > 0 ? max(0, $places - $job['holders_total']) : ($job['holders_total'] === 0 ? 1 : 0);
            $job['critical'] = $places > 0 && $job['holders_total'] === 0;
            $job['state'] = $job['holders_total'] === 0 ? 'vacant' : ($places > $job['holders_total'] ? 'partial' : 'covered');
        }
        unset($job);

        $descendants = self::categoryDescendants($categories);
        $query = self::fold((string) ($filters['q'] ?? ''));
        $tag = (int) ($filters['etiquette'] ?? 0);
        $category = (int) ($filters['categorie'] ?? 0);
        $categoryIds = $category > 0 ? array_flip($descendants[$category] ?? [$category]) : [];
        $origin = (string) ($filters['origine'] ?? '');
        $state = (string) ($filters['etat'] ?? '');
        $unit = (int) ($filters['unite'] ?? 0);
        $withSub = !empty($filters['sous_unites']);
        $grade = (string) ($filters['grade'] ?? '');
        $status = (string) ($filters['statut'] ?? '');

        $memberText = static fn (array $m): string => self::fold(implode(' ', [
            (string) ($m['name'] ?? ''), (string) ($m['callsign'] ?? ''), (string) ($m['unit_label'] ?? ''),
            (string) ($m['grade'] ?? ''), (string) ($m['grade_long'] ?? ''),
        ]));
        $jobText = static fn (array $j): string => self::fold(implode(' ', array_merge(
            [(string) ($j['name'] ?? ''), (string) ($j['abbreviation'] ?? ''), (string) ($j['category'] ?? ''), (string) ($j['code'] ?? '')],
            array_map(static fn (array $t): string => (string) ($t['name'] ?? ''), $j['tags'] ?? [])
        )));
        $unitMatch = static function (array $m) use ($unit, $withSub): bool {
            if ($unit < 1) {
                return true;
            }
            $chain = array_map('intval', $m['unit_chain'] ?? []);
            if ($chain === []) {
                return false;
            }

            return $withSub ? in_array($unit, $chain, true) : (int) end($chain) === $unit;
        };
        // Filtres « personne » : unité, grade, statut. Ils restreignent les titulaires affichés.
        $memberFilter = static function (array $m) use ($unitMatch, $grade, $status): bool {
            if (!$unitMatch($m)) {
                return false;
            }
            if ($grade !== '' && (string) ($m['grade'] ?? '') !== $grade) {
                return false;
            }
            if ($status !== '') {
                [$kind, $value] = array_pad(explode(':', $status, 2), 2, '');
                $actual = $kind === 'compte' ? (string) ($m['account_status'] ?? '') : self::fold((string) ($m['situation'] ?? ''));
                if ($actual !== ($kind === 'compte' ? $value : self::fold($value))) {
                    return false;
                }
            }

            return true;
        };
        $personFiltered = $unit > 0 || $grade !== '' || $status !== '';

        $jobFilter = static function (array $j) use ($tag, $categoryIds, $origin): bool {
            if ($categoryIds !== [] && !isset($categoryIds[(int) ($j['category_id'] ?? 0)])) {
                return false;
            }
            if ($tag > 0 && !in_array($tag, array_map(static fn (array $t): int => (int) ($t['id'] ?? 0), $j['tags'] ?? []), true)) {
                return false;
            }
            $hidden = !empty($j['hidden']);
            if ($origin === 'masquees') {
                return $hidden;
            }
            // Une fonction du site masquée n’est plus proposée : on ne la montre que si quelqu’un la tient encore.
            if ($hidden && (int) ($j['holders_total'] ?? 0) === 0) {
                return false;
            }

            return $origin === '' || (string) ($j['origin'] ?? '') === $origin;
        };

        // Emplois retenus par les filtres d’emploi (catégorie, étiquette, origine) : base des compteurs.
        $jobsInScope = array_values(array_filter($jobs, $jobFilter));
        $scopeJobIds = array_flip(array_map(static fn (array $j): int => (int) $j['id'], $jobsInScope));

        $shownJobs = [];
        foreach ($jobsInScope as $j) {
            $holders = [];
            foreach ($j['holder_keys'] as $k) {
                $m = $membersByKey[$k];
                if ($memberFilter($m)) {
                    $holders[] = $m;
                }
            }
            // Un emploi rattaché à l’unité (ou à un poste ORBAT de l’unité) reste visible même sans titulaire.
            $linkedToUnit = $unit > 0 && in_array($unit, array_map('intval', $withSub ? ($j['linked_unit_chain'] ?? []) : ($j['linked_unit_ids'] ?? [])), true);
            if ($personFiltered && $holders === [] && !$linkedToUnit) {
                continue;
            }
            if ($state === 'vacants' && (int) $j['vacancies'] === 0) {
                continue;
            }
            if ($state === 'sans_titulaire' && (int) $j['holders_total'] > 0) {
                continue;
            }
            if ($state === 'critiques' && empty($j['critical'])) {
                continue;
            }
            if ($state === 'multi') {
                $holders = array_values(array_filter($holders, static fn (array $m): bool => count($m['jobs'] ?? []) > 1));
                if ($holders === []) {
                    continue;
                }
            }
            if ($state === 'sans_emploi') {
                continue;
            }
            if ($query !== '') {
                if (str_contains($jobText($j), $query)) {
                    $j['match'] = 'job';
                } else {
                    $matching = array_values(array_filter($holders, static fn (array $m): bool => str_contains($memberText($m), $query)));
                    if ($matching === []) {
                        continue;
                    }
                    $j['match'] = 'holders';
                    $j['matching_keys'] = array_map(static fn (array $m): string => (string) $m['key'], $matching);
                }
            }
            $j['holders'] = $holders;
            $shownJobs[] = $j;
        }
        $sort = (string) ($filters['tri'] ?? 'categorie');
        usort($shownJobs, static function (array $a, array $b) use ($sort): int {
            $name = strnatcasecmp(self::fold((string) $a['name']), self::fold((string) $b['name']));
            return match ($sort) {
                'nom' => $name,
                'titulaires' => [count($b['holders']), $name] <=> [count($a['holders']), $name],
                'vacants' => [(int) $b['critical'], (int) $b['vacancies'], -count($b['holders'])] <=> [(int) $a['critical'], (int) $a['vacancies'], -count($a['holders'])] ?: $name,
                default => strnatcasecmp(self::fold((string) ($a['category'] ?? '')), self::fold((string) ($b['category'] ?? ''))) ?: $name,
            };
        });

        // Membres : chaque emploi tenu, restreint aux emplois retenus par les filtres d’emploi.
        $jobFiltersActive = $categoryIds !== [] || $tag > 0 || $origin !== '';
        $jobsById = [];
        foreach ($jobs as $j) {
            $jobsById[(int) $j['id']] = $j;
        }
        $shownMembers = [];
        foreach ($members as $m) {
            if (!$memberFilter($m)) {
                continue;
            }
            $held = array_values(array_filter(array_map('intval', $m['jobs'] ?? []), static fn (int $id): bool => isset($jobsById[$id])));
            $inScope = array_values(array_filter($held, static fn (int $id): bool => isset($scopeJobIds[$id])));
            if ($jobFiltersActive && $inScope === [] && $state !== 'sans_emploi') {
                continue;
            }
            if ($state === 'sans_emploi' && $held !== []) {
                continue;
            }
            if ($state === 'multi' && count($held) < 2) {
                continue;
            }
            if (in_array($state, ['vacants', 'sans_titulaire', 'critiques'], true)) {
                continue;
            }
            if ($query !== '' && !str_contains($memberText($m), $query)) {
                $hit = false;
                foreach ($held as $id) {
                    if (str_contains($jobText($jobsById[$id]), $query)) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            $m['job_items'] = array_map(static fn (int $id): array => $jobsById[$id], $held);
            $shownMembers[] = $m;
        }
        $msort = (string) ($filters['tri'] ?? 'nom');
        usort($shownMembers, static function (array $a, array $b) use ($msort): int {
            $name = strnatcasecmp(self::fold((string) $a['name']), self::fold((string) $b['name']));
            return match ($msort) {
                'grade' => [(int) ($a['grade_order'] ?? 999), $name] <=> [(int) ($b['grade_order'] ?? 999), $name],
                'unite' => strnatcasecmp(self::fold((string) ($a['unit_path'] ?? $a['unit_label'] ?? 'zzz')), self::fold((string) ($b['unit_path'] ?? $b['unit_label'] ?? 'zzz'))) ?: $name,
                'emplois' => [count($b['job_items']), $name] <=> [count($a['job_items']), $name],
                default => $name,
            };
        });

        return [
            'filters' => $filters,
            'jobs' => $shownJobs,
            'members' => $shownMembers,
            'indicators' => self::indicators($jobs, $members, $jobFilter),
            'facets' => self::facets($jobs, $members),
        ];
    }

    /**
     * Indicateurs de couverture sur les emplois proposés (les fonctions masquées sans titulaire n’en font pas partie).
     *
     * @param list<array<string, mixed>> $jobs
     * @param list<array<string, mixed>> $members
     * @return array<string, int>
     */
    public static function indicators(array $jobs, array $members, ?callable $jobFilter = null): array
    {
        $active = array_values(array_filter($jobs, static fn (array $j): bool => empty($j['hidden']) || (int) ($j['holders_total'] ?? 0) > 0));
        $held = count(array_filter($active, static fn (array $j): bool => (int) ($j['holders_total'] ?? 0) > 0));
        $places = 0;
        $placesFilled = 0;
        foreach ($active as $j) {
            $p = (int) ($j['places'] ?? 0);
            $places += $p;
            $placesFilled += min($p, (int) ($j['holders_total'] ?? 0));
        }
        $withJob = count(array_filter($members, static fn (array $m): bool => ($m['jobs'] ?? []) !== []));
        $links = 0;
        foreach ($members as $m) {
            $links += count($m['jobs'] ?? []);
        }

        return [
            'jobs' => count($active),
            'jobs_held' => $held,
            'coverage' => $active === [] ? 0 : (int) round(100 * $held / count($active)),
            'vacant' => count($active) - $held,
            'critical' => count(array_filter($active, static fn (array $j): bool => !empty($j['critical']))),
            'places' => $places,
            'places_filled' => $placesFilled,
            'members' => count($members),
            'with_job' => $withJob,
            'without_job' => count($members) - $withJob,
            'multi' => count(array_filter($members, static fn (array $m): bool => count($m['jobs'] ?? []) > 1)),
            'links' => $links,
        ];
    }

    /**
     * Nombre d’éléments par valeur de filtre (affiché dans les listes déroulantes).
     *
     * @param list<array<string, mixed>> $jobs
     * @param list<array<string, mixed>> $members
     * @return array{categories: array<int,int>, tags: array<int,int>, origins: array<string,int>, units: array<int,int>,
     *   grades: array<string,int>, statuses: array<string,int>}
     */
    public static function facets(array $jobs, array $members): array
    {
        $out = ['categories' => [], 'tags' => [], 'origins' => [], 'units' => [], 'grades' => [], 'statuses' => []];
        foreach ($jobs as $j) {
            $c = (int) ($j['category_id'] ?? 0);
            $out['categories'][$c] = ($out['categories'][$c] ?? 0) + 1;
            foreach ($j['tags'] ?? [] as $t) {
                $tid = (int) ($t['id'] ?? 0);
                $out['tags'][$tid] = ($out['tags'][$tid] ?? 0) + 1;
            }
            $o = !empty($j['hidden']) ? 'masquees' : (string) ($j['origin'] ?? '');
            $out['origins'][$o] = ($out['origins'][$o] ?? 0) + 1;
        }
        foreach ($members as $m) {
            foreach (array_map('intval', $m['unit_chain'] ?? []) as $u) {
                $out['units'][$u] = ($out['units'][$u] ?? 0) + 1;
            }
            $g = (string) ($m['grade'] ?? '');
            if ($g !== '') {
                $out['grades'][$g] = ($out['grades'][$g] ?? 0) + 1;
            }
            $a = 'compte:' . (string) ($m['account_status'] ?? '');
            $out['statuses'][$a] = ($out['statuses'][$a] ?? 0) + 1;
            $s = trim((string) ($m['situation'] ?? ''));
            if ($s !== '') {
                $key = 'situation:' . self::fold($s);
                $out['statuses'][$key] = ($out['statuses'][$key] ?? 0) + 1;
            }
        }

        return $out;
    }

    /** Neutralise une cellule qu’un tableur interpréterait comme une formule. */
    public static function csvCell(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t"], true)) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Export CSV (séparateur « ; », BOM UTF-8 pour Excel) du tableau tel qu’il est filtré.
     *
     * @param array<string, mixed> $board résultat de build()
     * @param array<string, string> $labels libellés des en-têtes et valeurs, déjà dans la langue du lecteur
     */
    public static function csv(array $board, array $labels): string
    {
        $l = static fn (string $key): string => $labels[$key] ?? $key;
        $origin = static fn (array $j): string => !empty($j['hidden']) ? $l('origin_hidden') : $l('origin_' . (string) ($j['origin'] ?? ''));
        $lines = [];
        $line = static function (array $cells) use (&$lines): void {
            $lines[] = implode(';', array_map(static fn (mixed $c): string => self::csvCell((string) $c), $cells));
        };
        if (($board['filters']['affichage'] ?? '') === 'membres') {
            $line([$l('member'), $l('callsign'), $l('grade'), $l('unit'), $l('status'), $l('jobs'), $l('primary')]);
            foreach ($board['members'] as $m) {
                $primary = '';
                $names = [];
                foreach ($m['job_items'] as $j) {
                    $names[] = (string) $j['name'];
                    if ((int) $j['id'] === (int) ($m['primary_job'] ?? 0)) {
                        $primary = (string) $j['name'];
                    }
                }
                $line([$m['name'] ?? '', $m['callsign'] ?? '', $m['grade'] ?? '', $m['unit_label'] ?? '', $m['status_label'] ?? '', implode(', ', $names), $primary]);
            }
        } else {
            $line([$l('job'), $l('abbreviation'), $l('category'), $l('tags'), $l('origin'), $l('places'), $l('holders'), $l('member'), $l('callsign'), $l('grade'), $l('unit'), $l('primary')]);
            foreach ($board['jobs'] as $j) {
                $base = [$j['name'] ?? '', $j['abbreviation'] ?? '', $j['category'] ?? '',
                    implode(', ', array_map(static fn (array $t): string => (string) ($t['name'] ?? ''), $j['tags'] ?? [])),
                    $origin($j), (int) ($j['places'] ?? 0) > 0 ? (string) $j['places'] : '', (string) count($j['holders'] ?? [])];
                if (($j['holders'] ?? []) === []) {
                    $line(array_merge($base, [$l('vacant'), '', '', '', '']));
                    continue;
                }
                foreach ($j['holders'] as $m) {
                    $line(array_merge($base, [$m['name'] ?? '', $m['callsign'] ?? '', $m['grade'] ?? '', $m['unit_label'] ?? '',
                        (int) ($m['primary_job'] ?? 0) === (int) $j['id'] ? $l('yes') : '']));
                }
            }
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }
}
