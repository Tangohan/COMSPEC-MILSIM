<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Services\Academy\AcademyBuiltInCourses;

/**
 * Génère un parcours de compétence ordonné à partir des données déjà saisies par la communauté
 * (référentiel des compétences, cours Academy, formations, modules ALPHA → DELTA, qualifications, emplois).
 *
 * Aucune IA ni service externe : même catalogue + même cible = même parcours. Le formateur ajuste ensuite
 * le résultat (ordre, étapes retirées ou ajoutées) avant de l’enregistrer.
 *
 * Catalogue attendu (construit par CompetencyCatalogService) : chaque élément porte au minimum
 * `ref` (chaîne) et `title`.
 *  - competencies : id, label, palier, palier_order, sort_order, acquisition_level, validation_mode, description, links[{type, ref}]
 *  - academy_courses / training_courses : ref, title, summary, duration_min, url
 *  - modules : ref, code, title, phase (ALPHA…DELTA), prereq_refs, duration_min, url
 *  - qualifications : ref, code, title, prereq_refs, requires_exam, url
 *  - job_roles / equipment : ref, title, url
 */
final class CompetencyPathwayGenerator
{
    /** Éléments auxquels une compétence peut être rattachée (type de lien => libellé). */
    public const LINK_TYPES = [
        'academy_course' => 'Cours Academy',
        'training_course' => 'Formation',
        'module' => 'Module de compétence',
        'qualification' => 'Qualification ou brevet',
        'job_role' => 'Emploi ou poste',
        'equipment' => 'Dotation',
    ];

    /** Comment la compétence est validée (clé enregistrée => libellé, explication). */
    public const VALIDATION_MODES = [
        'instructor' => ['label' => 'Validation par un instructeur', 'help' => 'Un instructeur observe le membre et valide la compétence.'],
        'academy' => ['label' => 'Licence Academy', 'help' => 'Le membre suit le cours Academy lié, réussit les évaluations, puis un instructeur valide la licence.'],
        'qualification' => ['label' => 'Attribution d’une qualification', 'help' => 'La compétence est acquise quand la qualification ou le brevet lié est attribué.'],
        'exercise' => ['label' => 'Évaluation en exercice', 'help' => 'Le membre démontre la compétence en exercice ou en opération, devant son encadrement.'],
        'module' => ['label' => 'Module de compétence validé', 'help' => 'La compétence est acquise quand le module de compétence lié est validé.'],
    ];

    /** Types d’étapes d’un parcours, dans l’ordre où elles apparaissent par défaut. */
    public const STEP_KINDS = [
        'prerequisite' => 'Prérequis',
        'course' => 'Cours',
        'module' => 'Module',
        'exercise' => 'Exercice',
        'qualification' => 'Qualification',
        'validation' => 'Validation',
        'custom' => 'Étape libre',
    ];

    private const STAGE = ['prerequisite' => 10, 'course' => 20, 'module' => 30, 'exercise' => 45, 'qualification' => 50, 'validation' => 70, 'custom' => 60];
    private const PHASE_STAGE = ['ALPHA' => 30, 'BRAVO' => 32, 'CHARLIE' => 40, 'DELTA' => 65];

    /** Modèles de parcours proposés (clé => définition). */
    public const TEMPLATES = [
        'fusilier_chef_equipe' => [
            'label' => 'Fusilier → Chef d’équipe',
            'summary' => 'Du fusilier qualifié au chef d’équipe : maîtrise individuelle, radio, navigation, puis commandement d’une équipe.',
            'keywords' => ['fusilier', 'chef d equipe', 'chef de groupe', 'commandement', 'leadership', 'combat', 'tir', 'radio', 'navigation', 'ordre initial'],
            'prerequisites' => [
                ['title' => 'Formation initiale du fusilier', 'detail' => 'Déplacements, tir, soins de base et discipline radio maîtrisés en mission.'],
            ],
            'exercises' => [
                ['title' => 'Exercice : conduite d’une équipe en patrouille', 'detail' => 'Le stagiaire commande une équipe de 4 sur une patrouille complète, encadré par un chef de groupe.'],
                ['title' => 'Exercice : ordre initial et compte rendu', 'detail' => 'Donner un ordre initial structuré puis rendre compte par radio au chef de groupe.'],
            ],
            'validation' => ['title' => 'Validation chef d’équipe', 'detail' => 'Un chef de groupe ou un instructeur valide le commandement de l’équipe en mission.'],
            'academy_keys' => [],
        ],
        'jtac' => [
            'label' => 'JTAC — contrôleur d’appui aérien',
            'summary' => 'Guidage de l’appui aérien rapproché selon la procédure de la communauté, jusqu’à la licence JTAC.',
            'keywords' => ['jtac', 'cas', 'appui aerien', 'appui feu', '9 line', 'guidage', 'fac', 'tacp'],
            'prerequisites' => [
                ['title' => 'Procédures radio maîtrisées', 'detail' => 'Discipline radio, indicatifs et vocabulaire de base.'],
                ['title' => 'Lecture de carte et coordonnées', 'detail' => 'Coordonnées, altitudes et orientation sur la carte du terrain.'],
            ],
            'exercises' => [
                ['title' => 'Exercice : guidage CAS en entraînement', 'detail' => 'Brief 9-line complet, attaque terminale et BDA avec un pilote de la communauté.'],
            ],
            'validation' => ['title' => 'Validation de la licence JTAC', 'detail' => 'Évaluations chronométrées réussies puis validation instructeur dans l’Academy.'],
            'academy_keys' => [AcademyBuiltInCourses::JTAC_KEY],
        ],
        'pilote' => [
            'label' => 'Pilote',
            'summary' => 'Du pilotage de base aux missions d’appui : vol, navigation, procédures et coordination avec le sol.',
            'keywords' => ['pilote', 'pilotage', 'vol', 'helico', 'aviation', 'aero', 'avion', 'aeronef', 'cursus pilote'],
            'prerequisites' => [
                ['title' => 'Procédures radio maîtrisées', 'detail' => 'Discipline radio et échanges sol-air.'],
            ],
            'exercises' => [
                ['title' => 'Exercice : vol de navigation', 'detail' => 'Vol planifié avec points de passage, contacts radio et posé sur zone.'],
                ['title' => 'Exercice : mission d’appui coordonnée', 'detail' => 'Insertion, extraction ou appui feu coordonné avec une équipe au sol.'],
            ],
            'validation' => ['title' => 'Lâcher pilote', 'detail' => 'Un instructeur pilote valide le vol en autonomie et la qualification de l’appareil.'],
            'academy_keys' => [],
        ],
        'medic' => [
            'label' => 'Médic',
            'summary' => 'Secours au combat jusqu’au médic d’équipe : soins sous le feu, triage et évacuation.',
            'keywords' => ['medic', 'medical', 'sante', 'secour', 'soin', 'sauvetage', 'evasan', 'medevac', 'triage', 'blesse'],
            'prerequisites' => [
                ['title' => 'Secourisme au combat de base', 'detail' => 'Garrot, mise en sécurité et bilan d’un blessé.'],
            ],
            'exercises' => [
                ['title' => 'Exercice : prise en charge de blessés multiples', 'detail' => 'Triage, soins prioritaires et demande d’évacuation (9-line MEDEVAC).'],
            ],
            'validation' => ['title' => 'Validation médic', 'detail' => 'Un instructeur santé valide la prise en charge complète en exercice.'],
            'academy_keys' => [],
        ],
    ];

    private const STOPWORDS = [
        'les', 'des', 'une', 'pour', 'avec', 'dans', 'sur', 'par', 'aux', 'du', 'de', 'la', 'le', 'et', 'en', 'au',
        'module', 'modules', 'formation', 'formations', 'cours', 'niveau', 'base', 'initiale', 'initial', 'courte', 'longue',
        'competence', 'competences', 'combat', 'avance', 'palier', 'partie', 'phase', 'stage', 'the', 'and', 'licence',
    ];

    /** @return array<string, array{label:string, summary:string}> */
    public static function templates(): array
    {
        $out = [];
        foreach (self::TEMPLATES as $key => $t) {
            $out[$key] = ['label' => $t['label'], 'summary' => $t['summary']];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $catalog
     * @return array{ok:bool, error:?string, title:string, summary:string,
     *   target:array{kind:string, ref:string, label:string},
     *   steps:list<array<string, mixed>>, stats:array{steps:int, duration_min:int, linked:int, suggested:int}}
     */
    public function generate(array $catalog, string $kind, string $ref): array
    {
        $catalog = self::withDefaults($catalog);
        $competencies = $catalog['competencies'];
        $seeds = [];
        $keywords = [];
        $template = null;
        $label = '';
        $summary = '';

        if ($kind === 'competency') {
            foreach ($competencies as $c) {
                if ((string) $c['id'] === $ref) {
                    $seeds[] = $c;
                }
            }
            if ($seeds === []) {
                return $this->failure($kind, $ref, 'Cette compétence n’existe plus dans le référentiel.');
            }
            $label = (string) $seeds[0]['label'];
            $keywords = self::tokens($label);
            $summary = 'Parcours vers la compétence « ' . $label . ' » (' . (string) $seeds[0]['palier'] . ').';
        } elseif ($kind === 'job_role') {
            $role = self::findItem($catalog['job_roles'], $ref);
            if ($role === null) {
                return $this->failure($kind, $ref, 'Cet emploi n’existe plus.');
            }
            $label = (string) $role['title'];
            $keywords = self::tokens($label);
            foreach ($competencies as $c) {
                if (self::hasLink($c, 'job_role', $ref)) {
                    $seeds[] = $c;
                }
            }
            if ($seeds === []) {
                foreach ($competencies as $c) {
                    if (self::matches((string) $c['label'], $keywords)) {
                        $seeds[] = $c;
                    }
                }
            }
            $summary = 'Parcours pour tenir l’emploi « ' . $label . ' ».';
        } elseif ($kind === 'template' && isset(self::TEMPLATES[$ref])) {
            $template = self::TEMPLATES[$ref];
            $label = $template['label'];
            $summary = $template['summary'];
            $keywords = $template['keywords'];
            foreach ($competencies as $c) {
                if (self::matches((string) $c['label'] . ' ' . (string) $c['palier'], $keywords)) {
                    $seeds[] = $c;
                }
            }
        } else {
            return $this->failure($kind, $ref, 'Choisissez une compétence, un emploi ou un modèle de parcours.');
        }

        $seeds = self::sortCompetencies($seeds);
        $seedIds = array_fill_keys(array_map(static fn (array $c): string => (string) $c['id'], $seeds), true);
        $steps = [];
        $linked = 0;
        $suggested = 0;

        // 1. Prérequis : modèle, puis compétences des paliers précédents (socle ou même domaine).
        foreach ($template['prerequisites'] ?? [] as $p) {
            $steps[] = self::step('prerequisite', $p['title'], $p['detail'], 'template', $ref, null, null, 'modele');
        }
        $minOrder = $seeds !== [] ? min(array_map(static fn (array $c): int => (int) $c['palier_order'], $seeds)) : null;
        if ($minOrder !== null) {
            foreach (self::sortCompetencies($competencies) as $c) {
                if (isset($seedIds[(string) $c['id']]) || (int) $c['palier_order'] >= $minOrder) {
                    continue;
                }
                $level = (string) ($c['acquisition_level'] ?? '');
                if ($level !== '' && $level !== 'Basique' && !self::matches((string) $c['label'], $keywords)) {
                    continue;
                }
                $steps[] = self::step(
                    'prerequisite',
                    (string) $c['label'],
                    'Palier « ' . (string) $c['palier'] . ' »' . ($level !== '' ? ' · niveau ' . $level : '') . ' — à maîtriser avant de commencer.',
                    'competency',
                    (string) $c['id'],
                    null,
                    null,
                    'referentiel'
                );
            }
        }

        // 2. Éléments liés aux compétences visées, puis éléments retrouvés par mots-clés.
        $picked = [];
        $pick = static function (string $type, array $item, string $source) use (&$picked, &$linked, &$suggested): void {
            $key = $type . ':' . (string) $item['ref'];
            if (isset($picked[$key])) {
                if ($source === 'lien' && $picked[$key]['source'] !== 'lien') {
                    $picked[$key]['source'] = 'lien';
                    $linked++;
                    $suggested--;
                }

                return;
            }
            $picked[$key] = ['type' => $type, 'item' => $item, 'source' => $source];
            if ($source === 'lien') {
                $linked++;
            } elseif ($source === 'suggestion') {
                $suggested++;
            }
        };
        $sources = ['academy_course' => 'academy_courses', 'training_course' => 'training_courses', 'module' => 'modules', 'qualification' => 'qualifications'];
        foreach ($seeds as $c) {
            foreach ($c['links'] ?? [] as $link) {
                $type = (string) ($link['type'] ?? '');
                if (!isset($sources[$type])) {
                    continue;
                }
                $item = self::findItem($catalog[$sources[$type]], (string) ($link['ref'] ?? ''));
                if ($item !== null) {
                    $pick($type, $item, 'lien');
                }
            }
        }
        foreach ($template['academy_keys'] ?? [] as $key) {
            $item = self::findItem($catalog['academy_courses'], $key);
            if ($item !== null) {
                $pick('academy_course', $item, 'modele');
            }
        }
        if ($keywords !== []) {
            foreach ($sources as $type => $list) {
                foreach ($catalog[$list] as $item) {
                    if (self::matches((string) $item['title'] . ' ' . (string) ($item['code'] ?? ''), $keywords)) {
                        $pick($type, $item, 'suggestion');
                    }
                }
            }
        }
        // Prérequis des modules et des qualifications retenus (fermeture transitive).
        foreach (['module' => 'modules', 'qualification' => 'qualifications'] as $type => $list) {
            $queue = array_values(array_filter($picked, static fn (array $p): bool => $p['type'] === $type));
            while ($queue !== []) {
                $current = array_shift($queue);
                foreach ($current['item']['prereq_refs'] ?? [] as $prereqRef) {
                    $key = $type . ':' . (string) $prereqRef;
                    $item = self::findItem($catalog[$list], (string) $prereqRef);
                    if ($item === null || isset($picked[$key])) {
                        continue;
                    }
                    $picked[$key] = ['type' => $type, 'item' => $item, 'source' => 'prerequis'];
                    $queue[] = $picked[$key];
                }
            }
        }
        $depth = [
            'module' => self::depths($catalog['modules']),
            'qualification' => self::depths($catalog['qualifications']),
        ];
        foreach ($picked as $p) {
            $item = $p['item'];
            $type = $p['type'];
            $why = match ($p['source']) {
                'lien' => 'Lié à la compétence dans le référentiel.',
                'modele' => 'Prévu par le modèle de parcours.',
                'prerequis' => 'Prérequis d’une étape suivante.',
                default => 'Proposé d’après son intitulé.',
            };
            if ($type === 'module') {
                $phase = strtoupper((string) ($item['phase'] ?? 'ALPHA'));
                $steps[] = self::step('module', (string) $item['title'], 'Phase ' . $phase . '. ' . $why, $type, (string) $item['ref'], $item['url'] ?? null, $item['duration_min'] ?? null, $p['source'])
                    + ['_stage' => (self::PHASE_STAGE[$phase] ?? 30), '_depth' => $depth['module'][(string) $item['ref']] ?? 0];
            } elseif ($type === 'qualification') {
                $detail = (!empty($item['requires_exam']) ? 'Examen requis. ' : '') . $why;
                $steps[] = self::step('qualification', (string) $item['title'], $detail, $type, (string) $item['ref'], $item['url'] ?? null, null, $p['source'])
                    + ['_depth' => $depth['qualification'][(string) $item['ref']] ?? 0];
            } else {
                $steps[] = self::step('course', (string) $item['title'], $why, $type, (string) $item['ref'], $item['url'] ?? null, $item['duration_min'] ?? null, $p['source']);
            }
        }

        // 3. Mises en situation.
        $hasExercise = false;
        foreach ($template['exercises'] ?? [] as $e) {
            $steps[] = self::step('exercise', $e['title'], $e['detail'], 'template', $ref, null, null, 'modele');
            $hasExercise = true;
        }
        foreach ($seeds as $c) {
            $level = (string) ($c['acquisition_level'] ?? '');
            if (in_array($level, ['Avancé', 'Spécifique'], true) || ($c['validation_mode'] ?? '') === 'exercise') {
                $steps[] = self::step('exercise', 'Mise en situation : ' . (string) $c['label'], 'Application en exercice encadré (niveau ' . ($level !== '' ? $level : 'visé') . ').', 'competency', (string) $c['id'], null, null, 'referentiel');
                $hasExercise = true;
            }
        }
        if (!$hasExercise) {
            $steps[] = self::step('exercise', 'Exercice d’application : ' . $label, 'Mise en pratique encadrée lors d’un entraînement de la communauté.', $kind, $ref, null, null, 'generique');
        }

        // 4. Validation finale.
        $hasValidation = false;
        foreach ($seeds as $c) {
            $mode = (string) ($c['validation_mode'] ?? '');
            if (!isset(self::VALIDATION_MODES[$mode])) {
                continue;
            }
            $steps[] = self::step('validation', self::VALIDATION_MODES[$mode]['label'] . ' — ' . (string) $c['label'], self::VALIDATION_MODES[$mode]['help'], 'competency', (string) $c['id'], null, null, 'referentiel');
            $hasValidation = true;
        }
        if ($template !== null) {
            $steps[] = self::step('validation', $template['validation']['title'], $template['validation']['detail'], 'template', $ref, null, null, 'modele');
            $hasValidation = true;
        }
        if (!$hasValidation) {
            $steps[] = self::step('validation', 'Validation par un instructeur', 'Un instructeur constate la compétence et l’enregistre dans le suivi du membre.', $kind, $ref, null, null, 'generique');
        }

        $steps = self::order(self::dedupe($steps));
        $duration = 0;
        foreach ($steps as $s) {
            $duration += (int) ($s['duration_min'] ?? 0);
        }

        return [
            'ok' => true,
            'error' => null,
            'title' => 'Parcours ' . $label,
            'summary' => $summary,
            'target' => ['kind' => $kind, 'ref' => $ref, 'label' => $label],
            'steps' => $steps,
            'stats' => ['steps' => count($steps), 'duration_min' => $duration, 'linked' => $linked, 'suggested' => $suggested],
        ];
    }

    /**
     * Étapes envoyées par le formulaire du formateur : nettoyées, ordonnées par position, sans étape vide.
     *
     * @param array<int|string, mixed> $posted
     * @return list<array{kind:string, title:string, detail:string, ref_type:string, ref:string}>
     */
    public static function normalizeSteps(array $posted): array
    {
        $rows = [];
        foreach ($posted as $i => $row) {
            if (!is_array($row) || empty($row['keep'])) {
                continue;
            }
            $title = trim(preg_replace('/\s+/u', ' ', (string) ($row['title'] ?? '')) ?? '');
            if ($title === '') {
                continue;
            }
            $kind = (string) ($row['kind'] ?? 'custom');
            $refType = (string) ($row['ref_type'] ?? '');
            $rows[] = [
                'pos' => (int) ($row['position'] ?? $i),
                'idx' => count($rows),
                'kind' => isset(self::STEP_KINDS[$kind]) ? $kind : 'custom',
                'title' => mb_substr($title, 0, 160),
                'detail' => mb_substr(trim((string) ($row['detail'] ?? '')), 0, 500),
                'ref_type' => isset(self::LINK_TYPES[$refType]) || in_array($refType, ['competency', 'job_role', 'template'], true) ? $refType : '',
                'ref' => mb_substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) ($row['ref'] ?? '')) ?? '', 0, 64),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['pos'], $a['idx']] <=> [$b['pos'], $b['idx']]);

        return array_slice(array_map(static function (array $r): array {
            unset($r['pos'], $r['idx']);
            if ($r['ref_type'] === '') {
                $r['ref'] = '';
            }

            return $r;
        }, $rows), 0, 60);
    }

    /**
     * Ajoute l’adresse de chaque étape enregistrée d’après le catalogue actuel (jamais stockée).
     *
     * @param list<array<string, mixed>> $steps
     * @param array<string, mixed> $catalog
     * @return list<array<string, mixed>>
     */
    public static function attachUrls(array $steps, array $catalog): array
    {
        $catalog = self::withDefaults($catalog);
        $lists = ['academy_course' => 'academy_courses', 'training_course' => 'training_courses', 'module' => 'modules', 'qualification' => 'qualifications', 'job_role' => 'job_roles', 'equipment' => 'equipment'];
        foreach ($steps as $i => $s) {
            $type = (string) ($s['ref_type'] ?? '');
            $item = isset($lists[$type]) ? self::findItem($catalog[$lists[$type]], (string) ($s['ref'] ?? '')) : null;
            $steps[$i]['url'] = $item['url'] ?? null;
            $steps[$i]['duration_min'] = $item['duration_min'] ?? null;
        }

        return $steps;
    }

    /**
     * Éléments du catalogue liés à une compétence, regroupés par type (liens dont la cible a disparu ignorés).
     *
     * @param array<string, mixed> $competency
     * @param array<string, mixed> $catalog
     * @return array<string, list<array<string, mixed>>>
     */
    public static function resolveLinks(array $competency, array $catalog): array
    {
        $catalog = self::withDefaults($catalog);
        $lists = ['academy_course' => 'academy_courses', 'training_course' => 'training_courses', 'module' => 'modules', 'qualification' => 'qualifications', 'job_role' => 'job_roles', 'equipment' => 'equipment'];
        $out = [];
        foreach ($competency['links'] ?? [] as $link) {
            $type = (string) ($link['type'] ?? '');
            if (!isset($lists[$type])) {
                continue;
            }
            $item = self::findItem($catalog[$lists[$type]], (string) ($link['ref'] ?? ''));
            if ($item !== null) {
                $out[$type][] = $item;
            }
        }

        return $out;
    }

    /**
     * Éléments non encore liés dont l’intitulé correspond à la compétence (aide à la saisie).
     *
     * @param array<string, mixed> $competency
     * @param array<string, mixed> $catalog
     * @return list<array{type:string, ref:string, title:string}>
     */
    public static function suggestLinks(array $competency, array $catalog, int $limit = 6): array
    {
        $catalog = self::withDefaults($catalog);
        $keywords = self::tokens((string) ($competency['label'] ?? ''));
        if ($keywords === []) {
            return [];
        }
        $lists = ['academy_course' => 'academy_courses', 'training_course' => 'training_courses', 'module' => 'modules', 'qualification' => 'qualifications', 'job_role' => 'job_roles'];
        $out = [];
        foreach ($lists as $type => $list) {
            foreach ($catalog[$list] as $item) {
                if (self::hasLink($competency, $type, (string) $item['ref'])) {
                    continue;
                }
                if (self::matches((string) $item['title'] . ' ' . (string) ($item['code'] ?? ''), $keywords)) {
                    $out[] = ['type' => $type, 'ref' => (string) $item['ref'], 'title' => (string) $item['title']];
                }
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** Texte en minuscules sans accents ni ponctuation, pour comparer des intitulés. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', '’' => ' ', "'" => ' ',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';

        return trim($text);
    }

    /** @return list<string> mots significatifs (3 lettres et plus, hors mots courants) */
    public static function tokens(string $text): array
    {
        $out = [];
        foreach (explode(' ', self::normalize($text)) as $w) {
            if ((strlen($w) >= 3 || in_array($w, ['cas', 'fac'], true)) && !in_array($w, self::STOPWORDS, true) && !ctype_digit($w)) {
                $out[$w] = true;
            }
        }

        return array_keys($out);
    }

    /** @param list<string> $keywords mots ou expressions déjà normalisés */
    public static function matches(string $text, array $keywords): bool
    {
        $hay = ' ' . self::normalize($text) . ' ';
        foreach ($keywords as $k) {
            $k = self::normalize((string) $k);
            if ($k === '') {
                continue;
            }
            // Mot entier (ou début de mot pour 5 lettres et plus : « secour » trouve « secourisme »).
            $pattern = strlen($k) >= 5 ? '/ ' . preg_quote($k, '/') . '/' : '/ ' . preg_quote($k, '/') . ' /';
            if (preg_match($pattern, $hay) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $catalog */
    private static function withDefaults(array $catalog): array
    {
        foreach (['competencies', 'academy_courses', 'training_courses', 'modules', 'qualifications', 'job_roles', 'equipment'] as $k) {
            $catalog[$k] = is_array($catalog[$k] ?? null) ? array_values($catalog[$k]) : [];
        }

        return $catalog;
    }

    /** @param list<array<string, mixed>> $items */
    private static function findItem(array $items, string $ref): ?array
    {
        if ($ref === '') {
            return null;
        }
        foreach ($items as $item) {
            if ((string) ($item['ref'] ?? '') === $ref) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $competency */
    private static function hasLink(array $competency, string $type, string $ref): bool
    {
        foreach ($competency['links'] ?? [] as $l) {
            if (($l['type'] ?? '') === $type && (string) ($l['ref'] ?? '') === $ref) {
                return true;
            }
        }

        return false;
    }

    /**
     * Profondeur de chaque élément dans son graphe de prérequis (0 = aucun prérequis). Les cycles sont coupés.
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, int>
     */
    private static function depths(array $items): array
    {
        $prereqs = [];
        foreach ($items as $item) {
            $prereqs[(string) $item['ref']] = array_map('strval', (array) ($item['prereq_refs'] ?? []));
        }
        $memo = [];
        $visit = static function (string $ref, array $stack) use (&$visit, &$memo, $prereqs): int {
            if (isset($memo[$ref])) {
                return $memo[$ref];
            }
            if (isset($stack[$ref])) {
                return 0;
            }
            $stack[$ref] = true;
            $d = 0;
            foreach ($prereqs[$ref] ?? [] as $p) {
                if (isset($prereqs[$p])) {
                    $d = max($d, $visit($p, $stack) + 1);
                }
            }

            return $memo[$ref] = $d;
        };
        foreach (array_keys($prereqs) as $ref) {
            $visit((string) $ref, []);
        }

        return $memo;
    }

    /**
     * @param list<array<string, mixed>> $competencies
     * @return list<array<string, mixed>>
     */
    private static function sortCompetencies(array $competencies): array
    {
        usort($competencies, static fn (array $a, array $b): int => [(int) $a['palier_order'], (string) $a['palier'], (int) ($a['sort_order'] ?? 0), (int) $a['id']]
            <=> [(int) $b['palier_order'], (string) $b['palier'], (int) ($b['sort_order'] ?? 0), (int) $b['id']]);

        return $competencies;
    }

    /** @return array<string, mixed> */
    private static function step(string $kind, string $title, string $detail, string $refType, string $ref, mixed $url, mixed $duration, string $source): array
    {
        return [
            'kind' => $kind,
            'title' => $title,
            'detail' => $detail,
            'ref_type' => $refType,
            'ref' => $ref,
            'url' => is_string($url) && $url !== '' ? $url : null,
            'duration_min' => is_numeric($duration) && (int) $duration > 0 ? (int) $duration : null,
            'source' => $source,
        ];
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    private static function dedupe(array $steps): array
    {
        $seen = [];
        $out = [];
        foreach ($steps as $s) {
            $key = $s['kind'] . '|' . self::normalize((string) $s['title']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $s;
        }

        return $out;
    }

    /**
     * Tri stable : étape (prérequis → cours → modules → exercices → qualifications → validation),
     * puis profondeur dans les prérequis, puis ordre d’apparition.
     *
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    private static function order(array $steps): array
    {
        foreach ($steps as $i => $s) {
            $steps[$i]['_i'] = $i;
            $steps[$i]['_stage'] ??= self::STAGE[$s['kind']] ?? 60;
            $steps[$i]['_depth'] ??= 0;
        }
        usort($steps, static fn (array $a, array $b): int => [$a['_stage'], $a['_depth'], $a['_i']] <=> [$b['_stage'], $b['_depth'], $b['_i']]);
        $out = [];
        foreach ($steps as $n => $s) {
            unset($s['_i'], $s['_stage'], $s['_depth']);
            $s['position'] = $n + 1;
            $out[] = $s;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function failure(string $kind, string $ref, string $message): array
    {
        return [
            'ok' => false,
            'error' => $message,
            'title' => '',
            'summary' => '',
            'target' => ['kind' => $kind, 'ref' => $ref, 'label' => ''],
            'steps' => [],
            'stats' => ['steps' => 0, 'duration_min' => 0, 'linked' => 0, 'suggested' => 0],
        ];
    }
}
