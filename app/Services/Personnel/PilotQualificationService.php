<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AtakDistanceRepository;
use App\Repositories\PilotQualificationRuleRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Support\PilotSyllabusCatalog;
use App\Support\QualificationAdminStatus;
use RuntimeException;
use Throwable;

/**
 * Cursus pilote : installe les paliers dans le référentiel des qualifications, évalue les heures de vol du mod,
 * attribue (ou propose à un instructeur) les qualifications atteintes, et tient le maintien de qualification
 * (« currency ») à jour d'après le dernier vol sur le type.
 */
final class PilotQualificationService
{
    public const FEATURE = 'pilot_qualifications';
    public const SOURCE = 'auto_pilot';
    public const CATEGORY_NAME = 'Pilotage';

    public function __construct(
        private PilotQualificationRuleRepository $rules,
        private QualificationDefinitionRepository $definitions,
        private QualificationReferentielRepository $referentiel,
        private QualificationAwardRepository $awards,
        private QualificationStatusTransitionService $transitions,
        private ?QualificationCurrencyService $currency = null,
    ) {
    }

    public function schemaReady(): bool
    {
        return $this->rules->schemaReady();
    }

    /**
     * Tableau du cursus : règles, et pour chacune les membres éligibles, en attente de validation, titulaires et
     * en progression (avec leurs heures), plus les types d'appareils déjà pilotés dans la communauté.
     *
     * @return array{rules: list<array<string, mixed>>, members: array<int, array{display_name: string, callsign: string}>, flown_families: array<string, list<array{key: string, label: string, hours: float, pilots: int}>>, pending_total: int, eligible_total: int}
     */
    public function board(int $tenantId): array
    {
        $rules = $this->rules->listForTenant($tenantId);
        $rowsByUser = $this->rules->pilotRowsByUser($tenantId);
        $awardsByUser = $this->rules->awardsByUser($tenantId, array_merge(
            array_map(static fn (array $r): int => (int) $r['definition_id'], $rules),
            array_map(static fn (array $r): int => (int) ($r['prereq_definition_id'] ?? 0), $rules)
        ));
        $members = $this->rules->memberNames($tenantId, array_merge(array_keys($rowsByUser), array_keys($awardsByUser)));

        $out = [];
        $pending = 0;
        $eligible = 0;
        foreach ($rules as $rule) {
            $groups = [PilotQualificationEngine::ELIGIBLE => [], PilotQualificationEngine::PENDING => [], PilotQualificationEngine::HELD => [], PilotQualificationEngine::PROGRESS => [], PilotQualificationEngine::BLOCKED => []];
            foreach ($members as $uid => $_m) {
                $totals = PilotQualificationEngine::scopeTotals($rowsByUser[$uid] ?? []);
                $awards = PilotQualificationEngine::awardsByDefinition($awardsByUser[$uid] ?? []);
                $ev = PilotQualificationEngine::evaluate($rule, $totals, $awards);
                if ($ev['state'] === PilotQualificationEngine::PROGRESS && $ev['scope_hours'] <= 0) {
                    continue;
                }
                $groups[$ev['state']][] = ['user_id' => $uid] + $ev;
            }
            usort($groups[PilotQualificationEngine::PROGRESS], static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);
            $pending += count($groups[PilotQualificationEngine::PENDING]);
            $eligible += count($groups[PilotQualificationEngine::ELIGIBLE]);
            $out[] = $rule + [
                'groups' => $groups,
                'validators' => $this->validatorIds($rule, $rules, $awardsByUser),
            ];
        }

        return [
            'rules' => $out,
            'members' => $members,
            'flown_families' => $this->flownFamilies($rowsByUser),
            'pending_total' => $pending,
            'eligible_total' => $eligible,
        ];
    }

    /**
     * Progression d'un membre (sa page « Mes qualifications ») : un palier par ligne, du plus avancé au moins avancé,
     * sans les paliers qu'il n'a pas commencés.
     *
     * @return list<array<string, mixed>>
     */
    public function memberProgress(int $tenantId, int $userId): array
    {
        $rules = $this->rules->listForTenant($tenantId, true);
        if ($rules === []) {
            return [];
        }
        $rows = $this->rules->pilotRowsByUser($tenantId)[$userId] ?? [];
        $awardRows = $this->rules->awardsByUser($tenantId, array_merge(
            array_map(static fn (array $r): int => (int) $r['definition_id'], $rules),
            array_map(static fn (array $r): int => (int) ($r['prereq_definition_id'] ?? 0), $rules)
        ))[$userId] ?? [];
        $totals = PilotQualificationEngine::scopeTotals($rows);
        $awards = PilotQualificationEngine::awardsByDefinition($awardRows);
        $out = [];
        foreach ($rules as $rule) {
            $ev = PilotQualificationEngine::evaluate($rule, $totals, $awards);
            if ($ev['state'] === PilotQualificationEngine::PROGRESS && $ev['scope_hours'] <= 0) {
                continue;
            }
            $out[] = $rule + $ev;
        }
        usort($out, static function (array $a, array $b): int {
            $order = [PilotQualificationEngine::ELIGIBLE => 0, PilotQualificationEngine::PENDING => 1, PilotQualificationEngine::PROGRESS => 2, PilotQualificationEngine::HELD => 3, PilotQualificationEngine::BLOCKED => 4];

            return [$order[$a['state']] ?? 9, -$a['pct']] <=> [$order[$b['state']] ?? 9, -$b['pct']];
        });

        return $out;
    }

    /**
     * Installe un cursus : catégorie « Pilotage », une qualification par palier (et par type d'appareil pour les
     * paliers de type), prérequis et règles. Les qualifications déjà installées (même code) sont reprises sans doublon.
     *
     * @param list<string> $familyKeys types d'appareils (clés AtakDistanceRepository, ou « class:… » relevées par le mod)
     * @param list<string> $tierKeys paliers voulus (vide : tous)
     * @param array<string, string> $familyLabels libellés des types relevés par le mod
     * @return array{created: int, reused: int}
     */
    public function install(int $tenantId, string $track, array $familyKeys, array $tierKeys, array $familyLabels, ?int $actorId): array
    {
        if (!$this->schemaReady()) {
            throw new RuntimeException('Le cursus pilote n’est pas encore installé sur le serveur (migration pilot_qualification_rules à lancer).');
        }
        $track = PilotSyllabusCatalog::normalizeTrack($track);
        $tiers = PilotSyllabusCatalog::tiers();
        $wanted = $tierKeys === [] ? array_keys($tiers) : array_values(array_intersect(array_keys($tiers), $tierKeys));
        // Un palier n'a de sens qu'avec ceux qui le précèdent : on ajoute les prérequis manquants.
        foreach ($wanted as $key) {
            for ($p = $tiers[$key]['prereq']; $p !== null; $p = $tiers[$p]['prereq']) {
                if (!in_array($p, $wanted, true)) {
                    $wanted[] = $p;
                }
            }
        }
        $wanted = array_values(array_intersect(array_keys($tiers), $wanted));
        $known = PilotSyllabusCatalog::knownFamilies($track);
        $families = [];
        foreach ($familyKeys as $key) {
            $key = trim((string) $key);
            if ($key === '' || !preg_match('/^(class:)?[A-Za-z0-9_]{1,80}$/', $key)) {
                continue;
            }
            $families[$key] = $known[$key] ?? ($familyLabels[$key] ?? substr($key, 6));
        }
        $needsFamily = (bool) array_filter($wanted, static fn (string $k): bool => $tiers[$k]['per'] === PilotSyllabusCatalog::PER_FAMILY);
        if ($needsFamily && $families === []) {
            throw new RuntimeException('Choisissez au moins un type d’appareil pour les qualifications de type.');
        }

        $categoryId = $this->pilotCategoryId($tenantId);
        $created = 0;
        $reused = 0;
        $sort = 0;
        $trackDefs = [];
        foreach ($wanted as $key) {
            if ($tiers[$key]['per'] === PilotSyllabusCatalog::PER_TRACK) {
                $trackDefs[$key] = $this->ensureTier($tenantId, $track, $key, null, null, $categoryId, $trackDefs, [], $sort++, $actorId, $created, $reused);
            }
        }
        foreach ($families as $fKey => $fLabel) {
            $familyDefs = [];
            foreach ($wanted as $key) {
                if ($tiers[$key]['per'] === PilotSyllabusCatalog::PER_FAMILY) {
                    $familyDefs[$key] = $this->ensureTier($tenantId, $track, $key, $fKey, $fLabel, $categoryId, $trackDefs, $familyDefs, $sort++, $actorId, $created, $reused);
                }
            }
        }

        return ['created' => $created, 'reused' => $reused];
    }

    /**
     * @param array<string, int> $trackDefs qualifications de filière déjà prêtes (palier => id)
     * @param array<string, int> $familyDefs qualifications du type déjà prêtes (palier => id)
     */
    private function ensureTier(
        int $tenantId,
        string $track,
        string $tierKey,
        ?string $familyKey,
        ?string $familyLabel,
        ?int $categoryId,
        array $trackDefs,
        array $familyDefs,
        int $sort,
        ?int $actorId,
        int &$created,
        int &$reused
    ): int {
        $tier = PilotSyllabusCatalog::tier($tierKey) ?? [];
        $code = PilotSyllabusCatalog::definitionCode($track, $tierKey, $familyKey);
        $existing = $this->definitions->findByCode($tenantId, $code);
        $prereqKey = $tier['prereq'] ?? null;
        $prereqId = $prereqKey !== null ? ($familyDefs[$prereqKey] ?? $trackDefs[$prereqKey] ?? null) : null;

        if ($existing !== null) {
            $defId = (int) $existing['id'];
            $reused++;
        } else {
            $defId = $this->definitions->create($tenantId, [
                'code' => $code,
                'name' => PilotSyllabusCatalog::definitionName($track, $tierKey, $familyLabel),
                'short_name' => mb_substr(($tier['short'] ?? '') . ($familyKey !== null ? ' ' . strtoupper((string) preg_replace('/^class:/', '', $familyKey)) : ''), 0, 30),
                'description' => ($tier['summary'] ?? '') . "\n\nRéférence réelle : " . ($tier['real'][$track] ?? ''),
                'category_id' => $categoryId,
                'is_permanent' => 1,
                'currency_days' => $tier['currency_days'] ?? null,
                'requires_exam' => ($tier['mode'] ?? '') === PilotSyllabusCatalog::MODE_PROPOSE ? 1 : 0,
            ], $actorId);
            $created++;
            if ($prereqId !== null) {
                try {
                    $this->referentiel->addPrerequisite($tenantId, $defId, $prereqId, null, 'obtention');
                } catch (Throwable) {
                }
            }
        }

        if ($this->rules->findByDefinition($tenantId, $defId) === null) {
            $this->rules->create($tenantId, [
                'definition_id' => $defId,
                'track' => $track,
                'tier' => $tierKey,
                'scope' => PilotSyllabusCatalog::scopeFor($track, $familyKey),
                'scope_label' => $familyLabel ?? PilotSyllabusCatalog::trackLabel($track),
                'min_scope_hours' => $tier['min_scope_hours'] ?? 0,
                'min_track_hours' => $tier['min_track_hours'] ?? 0,
                'prereq_definition_id' => $prereqId,
                'award_mode' => $tier['mode'] ?? PilotSyllabusCatalog::MODE_PROPOSE,
                'validator_tier' => $tier['validator'] ?? null,
                'sort_order' => $sort,
            ]);
        }

        return $defId;
    }

    private function pilotCategoryId(int $tenantId): ?int
    {
        try {
            foreach ($this->referentiel->listCategories($tenantId) as $cat) {
                if (mb_strtolower(trim((string) ($cat['name'] ?? ''))) === mb_strtolower(self::CATEGORY_NAME)) {
                    return (int) $cat['id'];
                }
            }

            return $this->referentiel->createCategory($tenantId, self::CATEGORY_NAME);
        } catch (Throwable) {
            return null;
        }
    }

    public function updateRule(int $tenantId, int $ruleId, float $minScope, float $minTrack, string $mode, bool $enabled): void
    {
        if ($this->rules->find($tenantId, $ruleId) === null) {
            throw new RuntimeException('Palier introuvable.');
        }
        $mode = $mode === PilotSyllabusCatalog::MODE_DIRECT ? PilotSyllabusCatalog::MODE_DIRECT : PilotSyllabusCatalog::MODE_PROPOSE;
        $this->rules->updateSettings($tenantId, $ruleId, max(0.0, min(9999.0, $minScope)), max(0.0, min(9999.0, $minTrack)), $mode, $enabled);
    }

    /**
     * Passage automatique : chaque membre éligible reçoit la qualification (mode direct) ou une proposition « en
     * évaluation » qu'un instructeur valide ; les qualifications obtenues sont tenues à jour d'après le dernier vol.
     *
     * @return array{awarded: int, proposed: int, practiced: int}
     */
    public function runAuto(int $tenantId): array
    {
        $stats = ['awarded' => 0, 'proposed' => 0, 'practiced' => 0];
        $rules = $this->rules->listForTenant($tenantId, true);
        if ($rules === []) {
            return $stats;
        }
        $rowsByUser = $this->rules->pilotRowsByUser($tenantId);
        if ($rowsByUser === []) {
            return $stats;
        }
        $defIds = array_merge(
            array_map(static fn (array $r): int => (int) $r['definition_id'], $rules),
            array_map(static fn (array $r): int => (int) ($r['prereq_definition_id'] ?? 0), $rules)
        );
        $awardsByUser = $this->rules->awardsByUser($tenantId, $defIds);
        $members = $this->rules->memberNames($tenantId, array_keys($rowsByUser));

        foreach (array_keys($members) as $uid) {
            $totals = PilotQualificationEngine::scopeTotals($rowsByUser[$uid] ?? []);
            $awards = PilotQualificationEngine::awardsByDefinition($awardsByUser[$uid] ?? []);
            // Les paliers sont dans l'ordre du cursus : un palier obtenu dans ce passage débloque le suivant au passage d'après.
            foreach ($rules as $rule) {
                $ev = PilotQualificationEngine::evaluate($rule, $totals, $awards);
                if ($ev['state'] === PilotQualificationEngine::HELD) {
                    $stats['practiced'] += $this->syncPractice($tenantId, $uid, $awards[(int) $rule['definition_id']] ?? [], $ev['last_flown_at']);
                    continue;
                }
                if ($ev['state'] !== PilotQualificationEngine::ELIGIBLE) {
                    continue;
                }
                $direct = ($rule['award_mode'] ?? '') === PilotSyllabusCatalog::MODE_DIRECT;
                try {
                    $this->transitions->award($tenantId, $uid, [
                        'definition_id' => (int) $rule['definition_id'],
                        'admin_status' => $direct ? QualificationAdminStatus::OBTAINED : QualificationAdminStatus::IN_EVALUATION,
                        'source' => self::SOURCE,
                        'notes' => self::autoNote($rule, $ev, $direct),
                    ], null);
                    $stats[$direct ? 'awarded' : 'proposed']++;
                } catch (Throwable) {
                }
            }
        }

        return $stats;
    }

    /** @param array<string, mixed> $rule @param array<string, mixed> $ev */
    public static function autoNote(array $rule, array $ev, bool $direct): string
    {
        $scope = trim((string) ($rule['scope_label'] ?? '')) ?: PilotSyllabusCatalog::trackLabel((string) ($rule['track'] ?? ''));
        $note = ($direct ? 'Attribuée automatiquement' : 'Proposée automatiquement, à valider par un instructeur')
            . ' : ' . PilotQualificationEngine::formatHours((float) $ev['scope_hours']) . ' de vol (' . $scope . ')'
            . ' pour ' . PilotQualificationEngine::formatHours((float) $ev['target_scope']) . ' demandées';
        if ((float) $ev['target_track'] > 0) {
            $note .= ', ' . PilotQualificationEngine::formatHours((float) $ev['track_hours']) . ' sur la filière pour '
                . PilotQualificationEngine::formatHours((float) $ev['target_track']);
        }

        return $note . '.';
    }

    /**
     * Un vol sur le type après la dernière pratique notée compte comme pratique : la qualification redevient « à jour ».
     *
     * @param array<string, mixed> $award
     */
    private function syncPractice(int $tenantId, int $userId, array $award, string $lastFlownAt): int
    {
        $awardId = (int) ($award['id'] ?? 0);
        if ($this->currency === null || $awardId < 1 || $lastFlownAt === '' || !array_key_exists('last_practiced_at', $award)) {
            return 0;
        }
        if ((string) ($award['last_practiced_at'] ?? '') !== '' && strcmp($lastFlownAt, (string) $award['last_practiced_at']) <= 0) {
            return 0;
        }
        try {
            $r = $this->currency->recordPractice($tenantId, $userId, $awardId, null, 'vol', $lastFlownAt);

            return !empty($r['ok']) ? 1 : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Décision d'un instructeur sur une proposition : validée (obtenue) ou ajournée (échouée).
     */
    public function decide(int $tenantId, int $awardId, int $actorId, bool $approve, bool $bypassValidator = false): void
    {
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null) {
            throw new RuntimeException('Proposition introuvable.');
        }
        if (!in_array(PilotQualificationEngine::statusOf($award), [QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION], true)) {
            throw new RuntimeException('Cette proposition a déjà été traitée.');
        }
        $rule = $this->rules->findByDefinition($tenantId, (int) ($award['definition_id'] ?? 0));
        if ($rule === null) {
            throw new RuntimeException('Cette qualification ne fait pas partie du cursus pilote.');
        }
        if ((int) $award['user_id'] === $actorId) {
            throw new RuntimeException('On ne se lâche pas soi-même : un autre instructeur doit valider ce palier.');
        }
        if (!$bypassValidator && !$this->canValidate($tenantId, $rule, $actorId)) {
            throw new RuntimeException('Seul un ' . mb_strtolower(PilotSyllabusCatalog::tierLabel((string) $rule['validator_tier'])) . ' ' . mb_strtolower(PilotSyllabusCatalog::trackLabel((string) $rule['track'])) . ' peut valider ce palier.');
        }
        $to = $approve ? QualificationAdminStatus::OBTAINED : QualificationAdminStatus::FAILED;
        $this->transitions->transition($tenantId, $awardId, $to, $actorId, $approve ? 'Validé en vol par un instructeur' : 'Ajourné par un instructeur');
        if ($approve) {
            $this->awards->update($awardId, ['obtained_at' => (new \DateTimeImmutable('today'))->format('Y-m-d')], $actorId);
        }
    }

    /**
     * Qui peut valider un palier : les titulaires du palier validateur de la même filière (instructeurs). Tant que
     * personne ne l'est encore, le premier est validé par un gestionnaire de la communauté.
     *
     * @param array<string, mixed> $rule
     */
    public function canValidate(int $tenantId, array $rule, int $actorId): bool
    {
        $rules = $this->rules->listForTenant($tenantId);
        $ids = $this->validatorIds($rule, $rules, $this->rules->awardsByUser($tenantId, array_map(static fn (array $r): int => (int) $r['definition_id'], $rules)));

        return $ids === null || in_array($actorId, $ids, true);
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<array<string, mixed>> $rules
     * @param array<int, list<array<string, mixed>>> $awardsByUser
     * @return list<int>|null null : aucun validateur imposé (ou aucun titulaire encore)
     */
    private function validatorIds(array $rule, array $rules, array $awardsByUser): ?array
    {
        $tier = (string) ($rule['validator_tier'] ?? '');
        if ($tier === '') {
            return null;
        }
        $defs = [];
        foreach ($rules as $r) {
            if (($r['tier'] ?? '') === $tier && ($r['track'] ?? '') === ($rule['track'] ?? '')) {
                $defs[(int) $r['definition_id']] = true;
            }
        }
        $ids = [];
        foreach ($awardsByUser as $uid => $rows) {
            foreach ($rows as $row) {
                if (isset($defs[(int) ($row['definition_id'] ?? 0)]) && PilotQualificationEngine::statusOf($row) === QualificationAdminStatus::OBTAINED) {
                    $ids[] = (int) $uid;
                    break;
                }
            }
        }

        return $ids === [] ? null : array_values(array_unique($ids));
    }

    /**
     * Types d'appareils pilotés dans la communauté, par filière (pour proposer les qualifications de type).
     *
     * @param array<int, list<array<string, mixed>>> $rowsByUser
     * @return array<string, list<array{key: string, label: string, hours: float, pilots: int}>>
     */
    private function flownFamilies(array $rowsByUser): array
    {
        $acc = [];
        foreach ($rowsByUser as $uid => $rows) {
            foreach ($rows as $row) {
                $fam = AtakDistanceRepository::aircraftFamily((string) ($row['vehicle_class'] ?? ''), (string) ($row['vehicle_label'] ?? ''));
                $track = PilotSyllabusCatalog::familyTrack($fam['key'], (string) ($row['vehicle_kind'] ?? ''));
                $acc[$track][$fam['key']] ??= ['key' => $fam['key'], 'label' => $fam['label'], 'seconds' => 0, 'pilots' => []];
                $acc[$track][$fam['key']]['seconds'] += (int) ($row['seconds'] ?? 0);
                $acc[$track][$fam['key']]['pilots'][$uid] = true;
            }
        }
        $out = [];
        foreach ($acc as $track => $fams) {
            $list = array_map(static fn (array $f): array => [
                'key' => $f['key'],
                'label' => $f['label'],
                'hours' => round($f['seconds'] / 3600, 1),
                'pilots' => count($f['pilots']),
            ], array_values($fams));
            usort($list, static fn (array $a, array $b): int => $b['hours'] <=> $a['hours']);
            $out[$track] = $list;
        }

        return $out;
    }
}
