<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PilotQualificationRuleRepository;
use App\Repositories\PilotSyllabusStepRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog;
use App\Support\QualificationCriteriaCatalog as Cat;
use RuntimeException;

/**
 * Paliers du cursus pilote créés par la communauté. Chaque palier délivre une qualification du référentiel ; ses
 * conditions (heures au total, par appareil ou par famille, nombre de vols, heures de nuit / aux instruments / en
 * patrouille contresignées au carnet, palier précédent, autre qualification) sont la règle automatique de cette
 * qualification : le moteur commun (QualificationAwardRuleService, tâche planifiée) l'évalue, la délivre ou la propose
 * à la validation. Rien n'est évalué ici.
 */
final class PilotSyllabusStepService
{
    public const TRACKS = [PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE, PilotFleet::TRACK_OTHER];
    public const MAX_SCOPES = 4;

    /** Conditions de vol notées au carnet : champ du formulaire => clé du critère. */
    private const CONDS = ['night_hours' => 'night', 'ifr_hours' => 'ifr', 'formation_hours' => 'formation'];

    public function __construct(
        private PilotSyllabusStepRepository $steps,
        private QualificationDefinitionRepository $definitions,
        private QualificationAwardRuleService $rules,
        private PilotQualificationService $pilot,
        private PilotQualificationRuleRepository $pilotRules,
    ) {
    }

    public function schemaReady(): bool
    {
        return $this->steps->schemaReady() && $this->rules->schemaReady();
    }

    /**
     * Paliers de la communauté par filière, avec leurs conditions lues dans la règle et leur avancement.
     *
     * @return list<array<string, mixed>>
     */
    public function board(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $rows = $this->steps->listForTenant($tenantId);
        $stepDefs = array_map(static fn (array $r): int => (int) $r['definition_id'], $rows);
        $summaries = $this->rules->summaries($tenantId, $stepDefs);
        $out = [];
        foreach ($rows as $r) {
            $defId = (int) $r['definition_id'];
            $sum = $summaries[$defId] ?? ['held' => 0, 'eligible' => 0, 'pending' => [], 'near' => [], 'auto' => false, 'rule' => []];
            $settings = $this->rules->settings($tenantId, $defId);
            $out[] = $r + [
                'form' => self::parseRule($sum['rule'], (string) $r['track'], $stepDefs) + [
                    'name' => (string) $r['definition_name'],
                    'instructor' => ($settings['auto_mode'] ?? '') === Cat::AWARD_PROPOSE,
                ],
                'summary' => $sum,
                'archived' => $r['archived_at'] !== null,
            ];
        }

        return $out;
    }

    /** Qualifications qu'un palier peut délivrer : ni déjà un palier, ni un palier des cursus modèles. @return array<int, string> */
    public function freeDefinitions(int $tenantId): array
    {
        $taken = [];
        foreach ($this->steps->listForTenant($tenantId) as $s) {
            $taken[(int) $s['definition_id']] = true;
        }
        foreach ($this->pilotRules->listForTenant($tenantId) as $r) {
            $taken[(int) $r['definition_id']] = true;
        }
        $out = [];
        foreach ($this->definitions->listForTenant($tenantId) as $d) {
            if (!isset($taken[(int) $d['id']])) {
                $out[(int) $d['id']] = (string) $d['name'];
            }
        }
        asort($out);

        return $out;
    }

    /** Toutes les qualifications actives de la communauté (prérequis possibles). @return array<int, string> */
    public function definitionNames(int $tenantId): array
    {
        $out = [];
        foreach ($this->definitions->listForTenant($tenantId) as $d) {
            $out[(int) $d['id']] = (string) $d['name'];
        }
        asort($out);

        return $out;
    }

    /**
     * Crée ou modifie un palier. Retourne son identifiant.
     *
     * @param array<string, mixed> $in
     */
    public function save(int $tenantId, ?int $stepId, array $in, ?int $actorId): int
    {
        if (!$this->schemaReady()) {
            throw new RuntimeException('Les paliers de la communauté arrivent avec la prochaine mise à jour du site (migration à lancer).');
        }
        $form = self::normalizeForm($in);
        if ($form['name'] === '') {
            throw new RuntimeException('Donnez un nom au palier.');
        }
        $step = $stepId !== null ? $this->steps->find($tenantId, $stepId) : null;
        if ($stepId !== null && $step === null) {
            throw new RuntimeException('Palier introuvable.');
        }
        $stepDefs = array_map(static fn (array $r): int => (int) $r['definition_id'], $this->steps->listForTenant($tenantId));

        if ($step !== null) {
            $defId = (int) $step['definition_id'];
            $previous = self::parseRule($this->rules->settings($tenantId, $defId)['rule'], (string) $step['track'], $stepDefs);
            $form['extra_items'] = $previous['extra_items'];
            $form['extra_groups'] = $previous['extra_groups'];
        } else {
            $defId = (int) ($in['definition_id'] ?? 0);
            if ($defId > 0 && !isset($this->freeDefinitions($tenantId)[$defId])) {
                throw new RuntimeException('Cette qualification est déjà délivrée par un palier du cursus.');
            }
        }
        if ($form['prereq_step'] > 0 && !in_array($form['prereq_step'], $stepDefs, true)) {
            $form['prereq_step'] = 0;
        }
        if (($step !== null && $form['prereq_step'] === $defId) || ($step !== null && $form['prereq_qualification'] === $defId)) {
            throw new RuntimeException('Un palier ne peut pas se demander lui-même.');
        }
        $rule = self::buildRule($form);
        if ($rule === []) {
            throw new RuntimeException('Ajoutez au moins une condition : des heures de vol, un nombre de vols ou un palier précédent.');
        }

        if ($defId < 1) {
            $defId = $this->definitions->create($tenantId, [
                'code' => $this->freeCode($tenantId, $form['name']),
                'name' => $form['name'],
                'short_name' => $form['short'] !== '' ? $form['short'] : null,
                'description' => 'Palier du cursus pilote de la communauté. Conditions réglées sur la page Cursus pilote.',
                'category_id' => $this->pilot->categoryId($tenantId),
                'is_permanent' => 1,
                'requires_exam' => $form['instructor'] ? 1 : 0,
            ], $actorId);
        } else {
            $def = $this->definitions->find($tenantId, $defId);
            if ($def === null) {
                throw new RuntimeException('Qualification introuvable.');
            }
            if ($step !== null && (trim((string) $def['name']) !== $form['name'] || ($form['short'] !== '' && (string) ($def['short_name'] ?? '') !== $form['short']))) {
                $this->definitions->update($tenantId, $defId, array_merge($def, ['name' => $form['name'], 'short_name' => $form['short'] !== '' ? $form['short'] : ($def['short_name'] ?? null)]), $actorId);
            }
        }

        $archived = $step !== null && $step['archived_at'] !== null;
        $this->saveRule($tenantId, $defId, $rule, $form['instructor'], !$archived, $actorId);
        if ($step === null) {
            return $this->steps->create($tenantId, $defId, $form['track'], $this->steps->nextSort($tenantId, $form['track']), $actorId);
        }
        $sort = (string) $step['track'] === $form['track'] ? (int) $step['sort_order'] : $this->steps->nextSort($tenantId, $form['track']);
        $this->steps->update($tenantId, (int) $step['id'], $form['track'], $sort);

        return (int) $step['id'];
    }

    /** Copie d'un palier (nouvelle qualification, mêmes conditions), à la fin de sa filière. */
    public function duplicate(int $tenantId, int $stepId, ?int $actorId): int
    {
        $step = $this->steps->find($tenantId, $stepId) ?? throw new RuntimeException('Palier introuvable.');
        $settings = $this->rules->settings($tenantId, (int) $step['definition_id']);
        $def = $this->definitions->find($tenantId, (int) $step['definition_id']);
        $name = mb_substr(trim((string) ($def['name'] ?? 'Palier')) . ' (copie)', 0, 120);
        $newDef = $this->definitions->create($tenantId, [
            'code' => $this->freeCode($tenantId, $name),
            'name' => $name,
            'short_name' => $def['short_name'] ?? null,
            'description' => $def['description'] ?? null,
            'category_id' => $def['category_id'] ?? $this->pilot->categoryId($tenantId),
            'is_permanent' => 1,
            'requires_exam' => (int) ($def['requires_exam'] ?? 0),
        ], $actorId);
        $this->saveRule($tenantId, $newDef, $settings['rule'], ($settings['auto_mode'] ?? '') === Cat::AWARD_PROPOSE, $settings['rule'] !== [], $actorId);

        return $this->steps->create($tenantId, $newDef, (string) $step['track'], $this->steps->nextSort($tenantId, (string) $step['track']), $actorId);
    }

    /** Archive (plus délivré automatiquement ; les titulaires la gardent) ou réactive un palier. */
    public function setArchived(int $tenantId, int $stepId, bool $archived, ?int $actorId): void
    {
        $step = $this->steps->find($tenantId, $stepId) ?? throw new RuntimeException('Palier introuvable.');
        $settings = $this->rules->settings($tenantId, (int) $step['definition_id']);
        $this->saveRule($tenantId, (int) $step['definition_id'], $settings['rule'], ($settings['auto_mode'] ?? '') === Cat::AWARD_PROPOSE, !$archived && $settings['rule'] !== [], $actorId);
        $this->steps->setArchived($tenantId, $stepId, $archived);
    }

    /** Monte (-1) ou descend (+1) un palier dans l'ordre de sa filière. */
    public function move(int $tenantId, int $stepId, int $direction): void
    {
        $step = $this->steps->find($tenantId, $stepId) ?? throw new RuntimeException('Palier introuvable.');
        $same = array_values(array_filter($this->steps->listForTenant($tenantId), static fn (array $s): bool => (string) $s['track'] === (string) $step['track']));
        $ids = array_map(static fn (array $s): int => (int) $s['id'], $same);
        $pos = array_search($stepId, $ids, true);
        $to = $pos === false ? false : $pos + ($direction < 0 ? -1 : 1);
        if ($pos === false || $to < 0 || $to >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
        foreach ($ids as $i => $id) {
            $this->steps->update($tenantId, $id, (string) $step['track'], ($i + 1) * 10);
        }
    }

    /**
     * Périmètres d'heures proposés (filières, appareils, familles, classes vues), ceux du moteur commun.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function scopeOptions(int $tenantId): array
    {
        return $this->rules->aircraftOptions($tenantId);
    }

    /** Passage immédiat du moteur sur les paliers actifs. @return array{awarded: int, proposed: int, renewed: int} */
    public function evaluate(int $tenantId): array
    {
        $stats = ['awarded' => 0, 'proposed' => 0, 'renewed' => 0];
        if (!$this->schemaReady()) {
            return $stats;
        }
        foreach ($this->steps->listForTenant($tenantId, false) as $s) {
            $r = $this->rules->runAuto($tenantId, (int) $s['definition_id']);
            foreach ($stats as $k => $_) {
                $stats[$k] += (int) ($r[$k] ?? 0);
            }
        }

        return $stats;
    }

    /** Décision sur une proposition d'un palier de la communauté (moteur commun des qualifications). */
    public function decide(int $tenantId, int $awardId, bool $approve, int $actorId, int $definitionId): void
    {
        if ($this->steps->findByDefinition($tenantId, $definitionId) === null) {
            throw new RuntimeException('Cette qualification n’est pas un palier du cursus pilote.');
        }
        $this->rules->decide($tenantId, $awardId, $approve, $actorId);
    }

    /** @param array<string, mixed> $rule */
    private function saveRule(int $tenantId, int $defId, array $rule, bool $instructor, bool $auto, ?int $actorId): void
    {
        $s = $this->rules->settings($tenantId, $defId);
        $this->rules->saveFromInput($tenantId, $defId, [
            'allow_manual' => 1,
            'auto_enabled' => $auto ? 1 : 0,
            'auto_mode' => $instructor ? Cat::AWARD_PROPOSE : Cat::AWARD_DIRECT,
            'rule_json' => $rule !== [] ? json_encode($rule, JSON_UNESCAPED_UNICODE) : '',
            'prereq_grade_id' => $s['prereq_grade_id'] ?? null,
            'prereq_job_role_id' => $s['prereq_job_role_id'] ?? null,
            'show_on_profile' => $s['show_on_profile'] ?? 1,
            'show_on_orbat' => $s['show_on_orbat'] ?? 1,
            'min_holders_per_unit' => $s['min_holders_per_unit'] ?? null,
        ], $actorId);
    }

    private function freeCode(int $tenantId, string $name): string
    {
        $base = 'PIL-C-' . strtoupper(str_replace('_', '-', substr(PilotFleet::customKey($name), 3)));
        $code = $base;
        for ($i = 2; $this->definitions->findByCode($tenantId, $code) !== null; $i++) {
            $code = mb_substr($base, 0, 58) . '-' . $i;
        }

        return $code;
    }

    /** Périmètre « toute la filière » : filière du palier, ou tous les aéronefs pour « autre ». */
    public static function trackScope(string $track): string
    {
        return in_array($track, [PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE], true) ? 'kind:' . $track : '';
    }

    /**
     * Formulaire propre : nombres bornés, périmètres connus du moteur, au plus quatre lignes « heures sur ».
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public static function normalizeForm(array $in): array
    {
        $num = static fn (mixed $v): float => round(max(0.0, min(9999.0, (float) str_replace(',', '.', trim((string) $v)))), 2);
        $track = in_array((string) ($in['track'] ?? ''), self::TRACKS, true) ? (string) $in['track'] : PilotSyllabusCatalog::TRACK_HELI;
        $scopes = [];
        $scopeIn = is_array($in['scope'] ?? null) ? $in['scope'] : [];
        $hoursIn = is_array($in['scope_hours'] ?? null) ? $in['scope_hours'] : [];
        foreach (array_slice(array_keys($scopeIn), 0, self::MAX_SCOPES) as $i) {
            $scope = Cat::normalizeScope('flight_hours', (string) $scopeIn[$i]);
            $h = $num($hoursIn[$i] ?? 0);
            if ($scope !== '' && $h > 0) {
                $scopes[] = ['scope' => $scope, 'hours' => $h];
            }
        }
        $form = [
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 120),
            'short' => mb_substr(strtoupper(trim((string) ($in['short'] ?? ''))), 0, 30),
            'track' => $track,
            'total_hours' => $num($in['total_hours'] ?? 0),
            'scopes' => $scopes,
            'flights' => (int) min(9999, max(0, (int) ($in['flights'] ?? 0))),
            'flights_scope' => Cat::normalizeScope('flight_count', (string) ($in['flights_scope'] ?? '')),
            'prereq_step' => max(0, (int) ($in['prereq_step'] ?? 0)),
            'prereq_qualification' => max(0, (int) ($in['prereq_qualification'] ?? 0)),
            'instructor' => !empty($in['instructor']),
            'extra_items' => [],
            'extra_groups' => [],
        ];
        foreach (array_keys(self::CONDS) as $field) {
            $form[$field] = $num($in[$field] ?? 0);
        }

        return $form;
    }

    /**
     * Règle du moteur commun à partir du formulaire d'un palier (vide : aucune condition).
     *
     * @param array<string, mixed> $f normalizeForm()
     * @return array<string, mixed>
     */
    public static function buildRule(array $f): array
    {
        $track = (string) $f['track'];
        $items = [];
        if ((float) $f['total_hours'] > 0) {
            $items[] = ['type' => 'metric', 'metric' => 'flight_hours', 'scope' => self::trackScope($track), 'min' => (float) $f['total_hours']];
        }
        foreach ($f['scopes'] as $s) {
            $items[] = ['type' => 'metric', 'metric' => 'flight_hours', 'scope' => (string) $s['scope'], 'min' => (float) $s['hours']];
        }
        if ((int) $f['flights'] > 0) {
            $items[] = ['type' => 'metric', 'metric' => 'flight_count', 'scope' => (string) $f['flights_scope'], 'min' => (int) $f['flights']];
        }
        $groups = [];
        foreach (self::CONDS as $field => $cond) {
            $h = (float) ($f[$field] ?? 0);
            if ($h <= 0) {
                continue;
            }
            if ($track === PilotFleet::TRACK_OTHER) {
                // Filière « autre » : les heures du carnet comptent en hélicoptère ou en avion.
                $groups[] = ['op' => 'any', 'items' => [
                    ['type' => 'metric', 'metric' => 'flight_cond_hours', 'scope' => 'cond:' . $cond . ':heli', 'min' => $h],
                    ['type' => 'metric', 'metric' => 'flight_cond_hours', 'scope' => 'cond:' . $cond . ':plane', 'min' => $h],
                ]];
            } else {
                $items[] = ['type' => 'metric', 'metric' => 'flight_cond_hours', 'scope' => 'cond:' . $cond . ':' . $track, 'min' => $h];
            }
        }
        foreach (['prereq_step', 'prereq_qualification'] as $k) {
            if ((int) $f[$k] > 0) {
                $items[] = ['type' => 'qualification', 'definition_id' => (int) $f[$k]];
            }
        }
        foreach ($f['extra_items'] ?? [] as $c) {
            $items[] = $c;
        }
        $all = $items !== [] ? [['op' => 'all', 'items' => $items]] : [];

        return QualificationCriteriaEngine::normalize(['op' => 'all', 'groups' => array_merge($all, $groups, $f['extra_groups'] ?? [])]);
    }

    /**
     * Formulaire d'un palier relu dans sa règle. Ce que le formulaire ne sait pas montrer (critère ajouté sur la fiche
     * de la qualification) est gardé tel quel dans extra_items / extra_groups.
     *
     * @param array<string, mixed> $rule règle normalisée
     * @param list<int> $stepDefinitionIds qualifications délivrées par les paliers (pour reconnaître le palier précédent)
     * @return array<string, mixed>
     */
    public static function parseRule(array $rule, string $track, array $stepDefinitionIds = []): array
    {
        $f = [
            'track' => $track, 'total_hours' => 0.0, 'scopes' => [], 'flights' => 0, 'flights_scope' => '',
            'night_hours' => 0.0, 'ifr_hours' => 0.0, 'formation_hours' => 0.0,
            'prereq_step' => 0, 'prereq_qualification' => 0, 'extra_items' => [], 'extra_groups' => [],
        ];
        $condField = array_flip(self::CONDS);
        $rootAll = ($rule['op'] ?? 'all') === 'all';
        foreach ($rule['groups'] ?? [] as $g) {
            if ($rootAll && $g['op'] === 'any') {
                $conds = array_unique(array_map(static fn (array $c): string => ($c['metric'] ?? '') === 'flight_cond_hours' ? explode(':', (string) $c['scope'])[1] ?? '' : '', $g['items']));
                $mins = array_unique(array_map(static fn (array $c): float => (float) ($c['min'] ?? 0), $g['items']));
                if (count($conds) === 1 && count($mins) === 1 && isset($condField[(string) reset($conds)]) && (float) $f[$condField[(string) reset($conds)]] <= 0) {
                    $f[$condField[(string) reset($conds)]] = (float) reset($mins);
                    continue;
                }
                $f['extra_groups'][] = $g;
                continue;
            }
            if (!$rootAll || $g['op'] !== 'all') {
                $f['extra_groups'][] = $g;
                continue;
            }
            foreach ($g['items'] as $c) {
                $type = (string) ($c['type'] ?? 'metric');
                $metric = (string) ($c['metric'] ?? '');
                $scope = (string) ($c['scope'] ?? '');
                if ($type === 'metric' && $metric === 'flight_hours' && $scope === self::trackScope($track) && $f['total_hours'] <= 0) {
                    $f['total_hours'] = (float) $c['min'];
                } elseif ($type === 'metric' && $metric === 'flight_hours' && $scope !== '' && count($f['scopes']) < self::MAX_SCOPES) {
                    $f['scopes'][] = ['scope' => $scope, 'hours' => (float) $c['min']];
                } elseif ($type === 'metric' && $metric === 'flight_count' && $f['flights'] === 0) {
                    $f['flights'] = (int) $c['min'];
                    $f['flights_scope'] = $scope;
                } elseif ($type === 'metric' && $metric === 'flight_cond_hours' && isset($condField[explode(':', $scope)[1] ?? '']) && $f[$condField[explode(':', $scope)[1]]] <= 0) {
                    $f[$condField[explode(':', $scope)[1]]] = (float) $c['min'];
                } elseif ($type === 'qualification' && in_array((int) $c['definition_id'], $stepDefinitionIds, true) && $f['prereq_step'] === 0) {
                    $f['prereq_step'] = (int) $c['definition_id'];
                } elseif ($type === 'qualification' && $f['prereq_qualification'] === 0) {
                    $f['prereq_qualification'] = (int) $c['definition_id'];
                } else {
                    $f['extra_items'][] = $c;
                }
            }
        }

        return $f;
    }
}
