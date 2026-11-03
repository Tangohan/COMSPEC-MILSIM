<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeRepository;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCriteriaCatalog as Cat;
use App\Support\QualificationPrerequisiteExtractor as Extractor;
use App\Support\QualificationPrerequisiteResolver as Resolver;
use RuntimeException;
use Throwable;

/**
 * « Prérequis détectés » d'une qualification : ce que le texte de sa fiche (ou de son entrée du catalogue de
 * référence) laisse lire — « 5 sauts », « 150 sauts en chute libre », « grade de Feldwebel au minimum », « brevet
 * militaire de parachutiste » — traduit en conditions du site, avec leur confiance et leur phrase source.
 *
 * Rien n'est appliqué tout seul : l'écran propose, une personne coche et ajoute. Les conditions mesurées par le jeu
 * (sauts, heures de vol, missions, ancienneté) et les qualifications ou cours cités vont dans la règle d'obtention ;
 * le grade minimum dans les réglages ; une autre qualification à détenir dans les prérequis de la fiche. Ce que le jeu
 * ne mesure pas devient une condition attestée par un instructeur.
 */
final class QualificationPrerequisiteSuggester
{
    public const HIGH = Extractor::HIGH;
    public const MEDIUM = 0.6;

    public function __construct(
        private QualificationAwardRuleService $rules,
        private QualificationAwardRuleRepository $ruleRows,
        private QualificationDefinitionRepository $definitions,
        private QualificationReferentielRepository $referentiel,
        private ?GradeRepository $grades = null,
    ) {
    }

    /* ——————————————————— Lecture ——————————————————— */

    /** @return list<array<string, mixed>> suggestions d'une qualification de la communauté */
    public function forDefinition(int $tenantId, int $definitionId, string $lang = 'fr'): array
    {
        $def = $this->definitions->find($tenantId, $definitionId);
        if ($def === null) {
            return [];
        }
        $ctx = $this->context($tenantId, $lang);

        return self::suggest($def, $this->rules->settings($tenantId, $definitionId), $ctx['prereqs'][$definitionId] ?? [], $ctx);
    }

    /**
     * Suggestions de tout le référentiel (ou des fiches demandées) : seulement les fiches qui en ont.
     *
     * @param list<int>|null $onlyIds
     * @return list<array{definition: array<string, mixed>, suggestions: list<array<string, mixed>>}>
     */
    public function forTenant(int $tenantId, string $lang = 'fr', ?array $onlyIds = null): array
    {
        $ctx = $this->context($tenantId, $lang);
        $out = [];
        foreach ($this->definitions->listForTenant($tenantId) as $def) {
            $id = (int) $def['id'];
            if ($onlyIds !== null && !in_array($id, $onlyIds, true)) {
                continue;
            }
            $list = self::suggest($def, $this->rules->settings($tenantId, $id), $ctx['prereqs'][$id] ?? [], $ctx);
            if ($list !== []) {
                $out[] = ['definition' => $def, 'suggestions' => $list];
            }
        }

        return $out;
    }

    /**
     * Aperçu avant import (assistant d'importation) : prérequis détectés d'entrées du catalogue pas encore dans le
     * référentiel. Une qualification citée qui sera importée dans le même passage (planned) compte comme disponible.
     * « Sûr » = confiance haute et disponible : c'est ce que l'option « prérequis sûrs » ajoutera.
     *
     * @param list<string> $catalogIds
     * @param array<string, true> $planned ids du catalogue présents dans le référentiel après l'import
     * @return array<string, array{sure: list<string>, other: int}>
     */
    public function forCatalogEntries(int $tenantId, array $catalogIds, array $planned, string $lang = 'fr'): array
    {
        $ctx = $this->context($tenantId, $lang);
        $out = [];
        foreach ($catalogIds as $id) {
            $entry = QualificationCatalog::findRaw((string) $id);
            if ($entry === null) {
                continue;
            }
            $def = ['id' => 0, 'code' => (string) ($entry['code'] ?? ''), 'name' => QualificationCatalog::pick($entry['name'] ?? '', $lang), 'description' => ''];
            $sure = [];
            $other = 0;
            foreach (self::suggest($def, [], [], $ctx) as $s) {
                $available = $s['available'] || ($s['kind'] === 'qualification' && isset($planned[(string) ($s['catalog_id'] ?? '')]));
                if ($available && $s['level'] === 'high') {
                    $sure[] = (string) $s['label'];
                } else {
                    $other++;
                }
            }
            if ($sure !== [] || $other > 0) {
                $out[(string) $id] = ['sure' => $sure, 'other' => $other];
            }
        }

        return $out;
    }

    /**
     * Après un import groupé : ajoute aux fiches données leurs prérequis sûrs (cochés d'office à l'écran « Prérequis
     * détectés » : confiance haute, disponibles). Le reste se revoit ensuite sur cet écran.
     *
     * @param list<int> $definitionIds
     * @return array{added: int, definitions: list<int>, errors: int}
     */
    public function applySure(int $tenantId, array $definitionIds, ?int $actorId, string $lang = 'fr'): array
    {
        $out = ['added' => 0, 'definitions' => [], 'errors' => 0];
        $ids = array_values(array_unique(array_filter(array_map('intval', $definitionIds))));
        if ($ids === []) {
            return $out;
        }
        foreach ($this->forTenant($tenantId, $lang, $ids) as $row) {
            $selected = [];
            foreach ($row['suggestions'] as $s) {
                if (!empty($s['checked'])) {
                    $selected[(string) $s['id']] = null;
                }
            }
            if ($selected === []) {
                continue;
            }
            try {
                $stats = $this->apply($tenantId, (int) $row['definition']['id'], $selected, $actorId, $lang);
                if ($stats['added'] > 0) {
                    $out['added'] += $stats['added'];
                    $out['definitions'][] = (int) $row['definition']['id'];
                }
            } catch (Throwable $e) {
                error_log('Prérequis sûrs (import groupé) : ' . $e->getMessage());
                $out['errors']++;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> données de la communauté nécessaires au calcul */
    private function context(int $tenantId, string $lang): array
    {
        $names = $this->rules->names($tenantId);
        $byCode = [];
        $candidates = [];
        foreach ($this->definitions->listForTenant($tenantId) as $d) {
            $byCode[strtoupper((string) ($d['code'] ?? ''))] = (int) $d['id'];
            $candidates[] = ['type' => 'qualification', 'id' => (int) $d['id'], 'label' => (string) ($d['name'] ?? ''),
                'names' => [(string) ($d['name'] ?? '')], 'short' => [(string) ($d['short_name'] ?? ''), (string) ($d['code'] ?? '')]];
        }
        foreach ($names['courses'] ?? [] as $id => $title) {
            $candidates[] = ['type' => 'course', 'id' => (int) $id, 'label' => (string) $title, 'names' => [(string) $title]];
        }
        foreach ($names['builtin'] ?? [] as $key => $title) {
            $candidates[] = ['type' => 'course', 'id' => 'key:' . $key, 'label' => (string) $title, 'names' => [(string) $title]];
        }
        $grades = [];
        try {
            $grades = $this->grades?->listForTenant($tenantId) ?? [];
        } catch (Throwable) {
        }

        return [
            'lang' => QualificationCatalog::lang($lang),
            'resolver' => new Resolver($candidates),
            'catalog' => Resolver::fromCatalog($lang),
            'by_code' => $byCode,
            'grades' => $grades,
            'prereqs' => $this->ruleRows->prerequisitesByDefinition($tenantId),
            'names' => $names['definitions'] ?? [],
        ];
    }

    /**
     * Suggestions d'une fiche : pur (tout vient du contexte), testable seul.
     *
     * @param array<string, mixed> $def fiche du référentiel (id, code, name, description)
     * @param array<string, mixed> $settings réglages d'obtention (QualificationAwardRuleService::settings)
     * @param list<int> $requiredIds qualifications déjà exigées (prérequis d'obtention de la fiche)
     * @param array<string, mixed> $ctx lang, resolver (communauté), catalog (catalogue de référence), by_code, grades, names
     * @return list<array<string, mixed>>
     */
    public static function suggest(array $def, array $settings, array $requiredIds, array $ctx): array
    {
        $defId = (int) ($def['id'] ?? 0);
        $lang = (string) ($ctx['lang'] ?? 'fr');
        $entry = QualificationCatalog::findByCode((string) ($def['code'] ?? ''));
        $pack = (string) ($entry['pack'] ?? '');
        $found = [];
        $push = static function (array $s) use (&$found): void {
            $id = (string) $s['id'];
            if (!isset($found[$id]) || $s['confidence'] > $found[$id]['confidence']) {
                $found[$id] = $s;
            }
        };

        if ($entry !== null) {
            // Ajouts relus des catalogues (champ prerequisites_detected), puis tout ce que le texte laisse lire.
            foreach (is_array($entry['prerequisites_detected'] ?? null) ? $entry['prerequisites_detected'] : [] as $p) {
                $s = self::fromStored($p, $entry, $defId, $ctx);
                if ($s !== null) {
                    $push($s);
                }
            }
            $texts = [
                [array_values(array_filter((array) ($entry['prerequisites'][$lang] ?? []), 'is_string')), Extractor::ORIGIN_PREREQUISITE],
                [QualificationCatalog::pick($entry['description'] ?? '', $lang), Extractor::ORIGIN_DESCRIPTION],
            ];
        } else {
            $texts = self::splitDescription((string) ($def['description'] ?? ''));
        }
        foreach ($texts as [$text, $origin]) {
            if ($text === '' || $text === []) {
                continue;
            }
            foreach (Extractor::extract($text, $origin, $pack) as $d) {
                if ($d['kind'] === 'reference') {
                    foreach (self::references((string) $d['text'], $entry, $defId, $pack, $ctx) as $s) {
                        $push($s);
                    }
                    continue;
                }
                $s = self::fromDetection($d, $defId, $ctx);
                if ($s !== null) {
                    $push($s);
                }
            }
        }

        // Déjà en place : condition de la règle, grade réglé, qualification exigée.
        $have = [];
        foreach (QualificationCriteriaEngine::criteria($settings['rule'] ?? []) as $c) {
            $have[self::criterionId($c)] = true;
        }
        $out = [];
        foreach ($found as $s) {
            if ($s['kind'] === 'grade' && (int) ($settings['prereq_grade_id'] ?? 0) > 0) {
                continue;
            }
            if ($s['kind'] === 'qualification' && (in_array((int) $s['definition_id'], $requiredIds, true) || isset($have['q:' . (int) $s['definition_id']]))) {
                continue;
            }
            if ($s['kind'] === 'criterion' && isset($have[self::criterionId($s['criterion'])])) {
                continue;
            }
            $s['level'] = $s['confidence'] >= self::HIGH ? 'high' : ($s['confidence'] >= self::MEDIUM ? 'medium' : 'low');
            $s['checked'] = $s['available'] && $s['level'] === 'high';
            $out[] = $s;
        }
        usort($out, static fn (array $a, array $b): int => [$b['available'], $b['confidence']] <=> [$a['available'], $a['confidence']]);

        return $out;
    }

    /**
     * Description d'une fiche : la ligne « Prérequis : a ; b » (fiches importées du catalogue) se lit comme une liste
     * de prérequis, le reste comme une description.
     *
     * @return list<array{0: string|list<string>, 1: string}>
     */
    public static function splitDescription(string $description): array
    {
        $pre = [];
        $rest = [];
        foreach (preg_split('/\R/u', $description) ?: [] as $line) {
            if (preg_match('/^\s*(?:Prérequis|Prerequisites|Voraussetzungen)\s*:\s*(.+)$/iu', $line, $m) === 1) {
                foreach (preg_split('/\s*;\s*/u', $m[1]) ?: [] as $p) {
                    if (trim($p) !== '') {
                        $pre[] = trim($p);
                    }
                }
            } elseif (preg_match('/^\s*(?:Sources?|Validité|Validity|Référence publique|Public reference|Organisme de référence|Reference body|Intitulé officiel|Official title)\s*:/iu', $line) !== 1) {
                $rest[] = $line;
            }
        }
        $out = [];
        if ($pre !== []) {
            $out[] = [$pre, Extractor::ORIGIN_PREREQUISITE];
        }
        $text = trim(implode("\n", $rest));
        if ($text !== '') {
            $out[] = [$text, Extractor::ORIGIN_DESCRIPTION];
        }

        return $out;
    }

    /** Détection chiffrée ou grade → suggestion. @param array<string, mixed> $d @param array<string, mixed> $ctx @return array<string, mixed>|null */
    private static function fromDetection(array $d, int $defId, array $ctx): ?array
    {
        $base = ['confidence' => (float) $d['confidence'], 'source' => (string) ($d['source'] ?? ''), 'available' => true, 'note' => '',
            'criterion' => null, 'grade_id' => null, 'definition_id' => null, 'value' => null, 'unit' => '', 'measured' => false];
        if ($d['kind'] === 'grade') {
            return self::gradeSuggestion((string) $d['nato'], (string) ($d['rank'] ?? ''), $base, $ctx);
        }
        $raw = $d['kind'] === 'attestation'
            ? ['type' => 'attestation', 'key' => $d['key'], 'min' => $d['min'] ?? 0, 'label' => $d['key'] === 'freefall_jumps' || $d['key'] === 'dive_hours' ? Cat::attestationLabel((string) $d['key']) : ($d['label'] ?? '')]
            : ['type' => 'metric', 'metric' => $d['metric'], 'scope' => $d['scope'] ?? '', 'min' => $d['min'], 'window_days' => $d['window_days'] ?? 0];
        $c = QualificationCriteriaEngine::normalizeCriterion($raw);
        if ($c === null) {
            return null;
        }
        $isMetric = $c['type'] === 'metric';
        $note = '';
        if ($isMetric && $c['metric'] === 'halo_jumps' && !empty($d['static_line'])) {
            $note = 'Le mod compte tous les sauts, sans distinguer l’ouverture automatique.';
        } elseif ($isMetric && $c['metric'] === 'seniority_days') {
            $note = 'Ancienneté dans la communauté (date d’engagement du dossier, sinon arrivée).';
        } elseif (!$isMetric) {
            $note = 'Pas de donnée du jeu : un instructeur l’atteste pour chaque membre.';
        }

        return [
            'id' => self::criterionId($c),
            'kind' => 'criterion',
            'criterion' => $c,
            'label' => Cat::describe($c),
            'value' => ($isMetric || Cat::attestationCounts((string) ($c['key'] ?? ''))) ? (float) $c['min'] : null,
            'unit' => $isMetric ? Cat::unit((string) $c['metric']) : Cat::attestationUnit((string) ($c['key'] ?? '')),
            'measured' => $isMetric,
            'note' => $note,
        ] + $base;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $ctx @return array<string, mixed> */
    private static function gradeSuggestion(string $nato, string $rank, array $base, array $ctx): array
    {
        $gradeId = 0;
        $label = '';
        foreach ($ctx['grades'] ?? [] as $g) {
            if (preg_replace('/^(OR|OF)-?(\d{1,2})$/', '$1-$2', strtoupper(str_replace(' ', '', (string) ($g['label_otan'] ?? '')))) === $nato) {
                $gradeId = (int) $g['id'];
                $label = trim((string) ($g['label_short'] ?? '')) . ' — ' . trim((string) ($g['label_long'] ?? ''));
                break;
            }
        }
        if ($gradeId === 0 && $rank !== '') {
            foreach ($ctx['grades'] ?? [] as $g) {
                if (Extractor::fold(trim((string) ($g['label_long'] ?? ''))) === Extractor::fold($rank)) {
                    $gradeId = (int) $g['id'];
                    $label = trim((string) ($g['label_short'] ?? '')) . ' — ' . trim((string) ($g['label_long'] ?? ''));
                    break;
                }
            }
        }

        return [
            'id' => 'g',
            'kind' => 'grade',
            'grade_id' => $gradeId > 0 ? $gradeId : null,
            'nato' => $nato,
            'label' => 'Grade minimum ' . ($label !== '' ? '« ' . $label . ' »' : $nato) . ' (' . $nato . ')',
            'target' => ($label !== '' ? $label . ' · ' : '') . $nato,
            'measured' => true,
            'available' => $gradeId > 0,
            'note' => $gradeId > 0 ? 'Grade du dossier du membre.' : 'Aucun grade de ce code OTAN dans le système de grades de la communauté.',
        ] + $base;
    }

    /**
     * Qualifications et cours cités par une phrase de prérequis.
     *
     * @param array<string, mixed>|null $entry entrée du catalogue de la fiche (null : fiche créée par la communauté)
     * @param array<string, mixed> $ctx
     * @return list<array<string, mixed>>
     */
    private static function references(string $sentence, ?array $entry, int $defId, string $pack, array $ctx): array
    {
        $out = [];
        $base = ['source' => $sentence, 'criterion' => null, 'grade_id' => null, 'value' => null, 'unit' => '', 'measured' => true, 'note' => ''];
        if ($entry !== null && isset($ctx['catalog'])) {
            foreach ($ctx['catalog']->resolve($sentence, (string) $entry['id'], $pack) as $hit) {
                $s = self::catalogQualification((string) $hit['id'], (float) $hit['confidence'], $base, $defId, $ctx);
                if ($s !== null) {
                    $out[] = $s;
                }
            }
        }
        if (isset($ctx['resolver'])) {
            foreach ($ctx['resolver']->resolve($sentence, $defId) as $hit) {
                if ($hit['type'] === 'qualification') {
                    $out[] = ['id' => 'q:' . (int) $hit['id'], 'kind' => 'qualification', 'definition_id' => (int) $hit['id'], 'label' => 'Détenir la qualification « ' . $hit['label'] . ' »', 'target' => (string) $hit['label'],
                        'confidence' => (float) $hit['confidence'], 'available' => true] + $base;
                    continue;
                }
                $c = str_starts_with((string) $hit['id'], 'key:')
                    ? ['type' => 'academy', 'course_key' => substr((string) $hit['id'], 4)]
                    : ['type' => 'academy', 'course_id' => (int) $hit['id']];
                $c = QualificationCriteriaEngine::normalizeCriterion($c);
                if ($c !== null) {
                    $out[] = ['id' => self::criterionId($c), 'kind' => 'criterion', 'criterion' => $c, 'label' => 'Réussir la formation « ' . $hit['label'] . ' »',
                        'confidence' => (float) $hit['confidence'] - 0.05, 'available' => true] + $base;
                }
            }
        }

        return $out;
    }

    /**
     * Qualification du catalogue citée : la fiche de la communauté qui en vient (même code), sinon à importer d'abord.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    private static function catalogQualification(string $catalogId, float $confidence, array $base, int $defId, array $ctx): ?array
    {
        $ref = QualificationCatalog::findRaw($catalogId);
        if ($ref === null) {
            return null;
        }
        $target = (int) ($ctx['by_code'][strtoupper((string) ($ref['code'] ?? ''))] ?? 0);
        if ($target === $defId && $target > 0) {
            return null;
        }
        $name = QualificationCatalog::pick($ref['name'] ?? [], (string) ($ctx['lang'] ?? 'fr'));

        return [
            'id' => $target > 0 ? 'q:' . $target : 'qc:' . $catalogId,
            'kind' => 'qualification',
            'definition_id' => $target > 0 ? $target : null,
            'catalog_id' => $catalogId,
            'label' => 'Détenir la qualification « ' . ($target > 0 ? (string) ($ctx['names'][$target] ?? $name) : $name) . ' »',
            'target' => $target > 0 ? (string) ($ctx['names'][$target] ?? $name) : $name,
            'confidence' => $confidence,
            'available' => $target > 0,
            'note' => $target > 0 ? '' : 'Qualification du catalogue pas encore importée dans votre référentiel : importez-la d’abord.',
        ] + $base;
    }

    /**
     * Ajout relu d'un catalogue (champ prerequisites_detected) → suggestion.
     *
     * @param mixed $p
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    private static function fromStored(mixed $p, array $entry, int $defId, array $ctx): ?array
    {
        if (!is_array($p) || !isset($p['kind'])) {
            return null;
        }
        $lang = (string) ($ctx['lang'] ?? 'fr');
        $source = '';
        if (is_int($p['source_index'] ?? null)) {
            $source = (string) ($entry['prerequisites'][$lang][$p['source_index']] ?? '');
        } elseif (($p['source'] ?? '') === 'description') {
            $source = QualificationCatalog::pick($entry['description'] ?? '', $lang);
        }
        $conf = (float) ($p['confidence'] ?? Extractor::HIGH);
        if ($p['kind'] === 'qualification') {
            return self::catalogQualification((string) ($p['ref'] ?? ''), $conf, ['source' => $source, 'criterion' => null, 'grade_id' => null, 'value' => null, 'unit' => '', 'measured' => true, 'note' => ''], $defId, $ctx);
        }
        $d = $p + ['confidence' => $conf, 'source' => $source];
        if ($p['kind'] === 'attestation') {
            $d['label'] = Cat::attestationLabel((string) ($p['key'] ?? 'other'));
        }

        return in_array($p['kind'], ['metric', 'attestation', 'grade'], true) ? self::fromDetection($d, $defId, $ctx) : null;
    }

    /** Identifiant stable d'une condition (la valeur seuil n'en fait pas partie). @param array<string, mixed> $c */
    public static function criterionId(array $c): string
    {
        return match ((string) ($c['type'] ?? 'metric')) {
            'academy' => 'c:' . (isset($c['course_key']) ? 'key:' . $c['course_key'] : (int) ($c['course_id'] ?? 0)),
            'qualification' => 'q:' . (int) ($c['definition_id'] ?? 0),
            'attestation' => 'a:' . ($c['key'] ?? 'other') . (Cat::attestationCounts((string) ($c['key'] ?? '')) ? '' : ':' . substr(sha1(mb_strtolower((string) ($c['label'] ?? ''))), 0, 8)),
            default => 'm:' . ($c['metric'] ?? '') . ':' . ($c['scope'] ?? '') . ':' . (int) ($c['window_days'] ?? 0),
        };
    }

    /* ——————————————————— Écriture ——————————————————— */

    /**
     * Ajoute les suggestions choisies (case cochée, valeur éventuellement corrigée) : conditions dans un bloc « toutes
     * ces conditions » de la règle, grade minimum, qualification à détenir. Une qualification sans règle automatique
     * passe en « proposée, un responsable valide » (elle reste délivrable à la main).
     *
     * @param array<string, float|int|string|null> $selected identifiant de suggestion => valeur saisie (vide : celle détectée)
     * @return array{added: int, skipped: int}
     */
    public function apply(int $tenantId, int $definitionId, array $selected, ?int $actorId, string $lang = 'fr'): array
    {
        $stats = ['added' => 0, 'skipped' => 0];
        if ($selected === []) {
            return $stats;
        }
        if (!$this->rules->schemaReady()) {
            throw new RuntimeException('Les modes d’obtention arrivent avec la prochaine mise à jour du site (migration à lancer).');
        }
        $byId = [];
        foreach ($this->forDefinition($tenantId, $definitionId, $lang) as $s) {
            $byId[(string) $s['id']] = $s;
        }
        $settings = $this->rules->settings($tenantId, $definitionId);
        $rule = $settings['rule'];
        $newItems = [];
        $gradeId = (int) ($settings['prereq_grade_id'] ?? 0);
        foreach ($selected as $id => $value) {
            $s = $byId[(string) $id] ?? null;
            if ($s === null || !$s['available']) {
                $stats['skipped']++;
                continue;
            }
            if ($s['kind'] === 'grade') {
                $gradeId = (int) $s['grade_id'];
                $stats['added']++;
            } elseif ($s['kind'] === 'qualification') {
                $this->referentiel->addPrerequisite($tenantId, $definitionId, (int) $s['definition_id'], null, 'obtention');
                $stats['added']++;
            } else {
                $c = $s['criterion'];
                $v = is_scalar($value) ? (float) str_replace(',', '.', trim((string) $value)) : 0.0;
                if ($v > 0 && $s['value'] !== null) {
                    $c['min'] = $v;
                }
                $c = QualificationCriteriaEngine::normalizeCriterion($c);
                if ($c === null) {
                    $stats['skipped']++;
                    continue;
                }
                $newItems[] = $c;
            }
        }
        $wasAuto = !empty($settings['auto_enabled']) && $rule !== [];
        if ($newItems !== []) {
            $merged = self::mergeIntoRule($rule, $newItems);
            if ($merged === null) {
                $stats['skipped'] += count($newItems);
            } else {
                $rule = $merged;
                $stats['added'] += count($newItems);
            }
        }
        if ($stats['added'] === 0) {
            return $stats;
        }
        $auto = $rule !== [];
        $this->rules->saveFromInput($tenantId, $definitionId, [
            'allow_manual' => $wasAuto ? $settings['allow_manual'] : 1,
            'auto_enabled' => $auto ? 1 : 0,
            'auto_mode' => $wasAuto ? $settings['auto_mode'] : Cat::AWARD_PROPOSE,
            'rule_json' => $rule !== [] ? json_encode($rule, JSON_UNESCAPED_UNICODE) : '',
            'prereq_grade_id' => $gradeId > 0 ? $gradeId : null,
            'prereq_job_role_id' => $settings['prereq_job_role_id'],
            'show_on_profile' => $settings['show_on_profile'],
            'show_on_orbat' => $settings['show_on_orbat'],
            'min_holders_per_unit' => $settings['min_holders_per_unit'],
        ], $actorId);

        return $stats;
    }

    /**
     * Règle complétée par des conditions à remplir toutes : ajoutées au bloc « toutes ces conditions » de la règle (ou
     * dans un nouveau bloc) ; null si la règle est faite de plusieurs alternatives (« au moins un bloc ») où l'ajout
     * changerait le sens : à faire à la main.
     *
     * @param array<string, mixed> $rule
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    public static function mergeIntoRule(array $rule, array $items): ?array
    {
        if ($rule === []) {
            return QualificationCriteriaEngine::normalize(['op' => 'all', 'groups' => [['op' => 'all', 'items' => $items]]]);
        }
        $groups = $rule['groups'];
        if (($rule['op'] ?? 'all') === 'any' && count($groups) > 1) {
            return null;
        }
        if (count($groups) === 1 && ($groups[0]['op'] ?? 'all') === 'any') {
            // « Une de ces conditions » : elle devient un bloc parmi d'autres, tous exigés.
            $groups[] = ['op' => 'all', 'items' => $items];
        } else {
            $target = null;
            foreach ($groups as $i => $g) {
                if (($g['op'] ?? 'all') === 'all' && count($g['items']) + count($items) <= Cat::MAX_ITEMS) {
                    $target = $i;
                    break;
                }
            }
            if ($target !== null) {
                $groups[$target]['items'] = array_merge($groups[$target]['items'], $items);
            } elseif (count($groups) < Cat::MAX_GROUPS) {
                $groups[] = ['op' => 'all', 'items' => $items];
            } else {
                return null;
            }
        }

        return QualificationCriteriaEngine::normalize(['op' => 'all', 'groups' => $groups]);
    }
}
