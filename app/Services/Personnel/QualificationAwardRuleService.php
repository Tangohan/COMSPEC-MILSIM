<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AtakDistanceRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PilotFleetRepository;
use App\Repositories\PilotLogbookRepository;
use App\Repositories\PilotQualificationRuleRepository;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\UserRepository;
use App\Services\GameEquipment\GameItemKinds;
use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog;
use App\Support\QualificationAdminStatus;
use App\Support\QualificationCriteriaCatalog as Cat;
use RuntimeException;
use Throwable;

/**
 * Qualifications de toute nature : réglages d'obtention (manuelle, Academy, automatique sur seuils mesurés),
 * évaluation des membres, délivrance automatique et renouvellement (tâche planifiée, idempotente), matrice des
 * effectifs, progression d'un membre et délivrance / retrait à la main.
 */
final class QualificationAwardRuleService
{
    public const SOURCE = 'auto_rule';

    /** @var array<int, array<string, mixed>> contexte de calcul par communauté (une requête par donnée) */
    private array $ctx = [];

    public function __construct(
        private QualificationAwardRuleRepository $repo,
        private QualificationDefinitionRepository $definitions,
        private PilotQualificationRuleRepository $pilotRules,
        private QualificationStatusTransitionService $transitions,
        private QualificationTemporalStatusService $temporal,
        private QualificationAwardRepository $awards,
        private UserRepository $users,
        private ?PilotLogbookRepository $logbook = null,
        private ?GradeRepository $grades = null,
        private ?PilotFleetRepository $fleet = null,
    ) {
    }

    public function schemaReady(): bool
    {
        return $this->repo->schemaReady();
    }

    /** Réglages par défaut : ce qui se passait avant (délivrance à la main, visible partout). @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'allow_manual' => 1,
            'auto_enabled' => 0,
            'auto_mode' => Cat::AWARD_DIRECT,
            'rule_json' => null,
            'prereq_grade_id' => null,
            'prereq_job_role_id' => null,
            'show_on_profile' => 1,
            'show_on_orbat' => 1,
            'min_holders_per_unit' => null,
            'last_evaluated_at' => null,
            'exists' => false,
        ];
    }

    /** @return array<string, mixed> réglages + rule (règle normalisée) */
    public function settings(int $tenantId, int $definitionId): array
    {
        $row = $this->repo->find($tenantId, $definitionId);
        $s = $row !== null ? array_merge(self::defaults(), $row, ['exists' => true]) : self::defaults();
        $s['rule'] = QualificationCriteriaEngine::normalize($s['rule_json'] ?? null);

        return $s;
    }

    /**
     * Enregistre les réglages envoyés par le formulaire de la qualification.
     *
     * @param array<string, mixed> $in
     */
    public function saveFromInput(int $tenantId, int $definitionId, array $in, ?int $actorId): void
    {
        if (!$this->repo->schemaReady()) {
            throw new RuntimeException('Les modes d’obtention arrivent avec la prochaine mise à jour du site (migration à lancer).');
        }
        $rule = QualificationCriteriaEngine::normalize($in['rule_json'] ?? '');
        $auto = !empty($in['auto_enabled']) && $rule !== [];
        $manual = !empty($in['allow_manual']);
        if (!$manual && !$auto) {
            throw new RuntimeException('Choisissez au moins un mode d’obtention : à la main, ou automatique avec au moins une condition.');
        }
        foreach (QualificationCriteriaEngine::criteria($rule) as $c) {
            if (($c['type'] ?? '') === 'qualification' && (int) $c['definition_id'] === $definitionId) {
                throw new RuntimeException('Une qualification ne peut pas se demander elle-même.');
            }
        }
        $min = (int) ($in['min_holders_per_unit'] ?? 0);
        $this->repo->save($tenantId, $definitionId, [
            'allow_manual' => $manual ? 1 : 0,
            'auto_enabled' => $auto ? 1 : 0,
            'auto_mode' => ($in['auto_mode'] ?? '') === Cat::AWARD_PROPOSE ? Cat::AWARD_PROPOSE : Cat::AWARD_DIRECT,
            'rule_json' => $rule !== [] ? json_encode($rule, JSON_UNESCAPED_UNICODE) : null,
            'prereq_grade_id' => (int) ($in['prereq_grade_id'] ?? 0) > 0 ? (int) $in['prereq_grade_id'] : null,
            'prereq_job_role_id' => (int) ($in['prereq_job_role_id'] ?? 0) > 0 ? (int) $in['prereq_job_role_id'] : null,
            'show_on_profile' => !empty($in['show_on_profile']) ? 1 : 0,
            'show_on_orbat' => !empty($in['show_on_orbat']) ? 1 : 0,
            'min_holders_per_unit' => $min > 0 ? min(999, $min) : null,
        ], $actorId);
        unset($this->ctx[$tenantId]);
    }

    /* ——————————————————— Contexte de calcul ——————————————————— */

    /** @return array<string, mixed> */
    private function context(int $tenantId): array
    {
        if (isset($this->ctx[$tenantId])) {
            return $this->ctx[$tenantId];
        }
        $members = [];
        try {
            $rows = $this->users->listForTenant($tenantId);
        } catch (Throwable) {
            $rows = [];
        }
        foreach ($rows as $u) {
            $status = strtolower((string) ($u['status'] ?? 'active'));
            if (in_array($status, ['inactive', 'banned', 'deleted'], true)) {
                continue;
            }
            $members[(int) $u['id']] = [
                'user_id' => (int) $u['id'],
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
            ];
        }
        $details = $this->repo->memberDetails($tenantId, array_keys($members));
        $gradeById = $this->gradeIndex($tenantId);
        foreach ($members as $uid => $m) {
            $d = $details[$uid] ?? ['unit_id' => 0, 'unit_name' => '', 'unit_short' => '', 'grade_id' => 0, 'steam_id' => ''];
            $g = $gradeById[$d['grade_id']] ?? null;
            $members[$uid] = $m + $d + ['grade_label' => (string) ($g['label'] ?? '')];
        }
        $defs = [];
        foreach ($this->definitions->listForTenant($tenantId) as $d) {
            $defs[(int) $d['id']] = $d;
        }
        $pilotDefs = [];
        foreach ($this->pilotRules->listForTenant($tenantId) as $r) {
            $pilotDefs[(int) $r['definition_id']] = true;
        }

        return $this->ctx[$tenantId] = [
            'members' => $members,
            'definitions' => $defs,
            'settings' => $this->repo->listForTenant($tenantId),
            'pilot' => $pilotDefs,
            'awards' => $this->repo->awardsByUser($tenantId),
            'academy' => $this->academyCompletions($tenantId),
            'prereqs' => $this->repo->prerequisitesByDefinition($tenantId),
            'jobs' => $this->repo->jobRolesByUser($tenantId),
            'grades' => $gradeById,
            'metrics' => [],
            'flight' => null,
            'flight_counts' => null,
            'best' => [],
            'attest' => $this->repo->attestationsByUser($tenantId),
        ];
    }

    /**
     * Formations réussies par membre : attestations Academy valides (id de formation) et cours intégrés terminés
     * (clé du cours : JTAC, cours inspirés des standards OTAN).
     *
     * @return array<int, array<int|string, bool>>
     */
    private function academyCompletions(int $tenantId): array
    {
        $out = $this->repo->validAcademyCourses($tenantId);
        foreach ($this->repo->builtInCourseCompletions($tenantId) as $userId => $keys) {
            foreach ($keys as $key => $done) {
                $out[$userId][$key] = $done;
            }
        }

        return $out;
    }

    /** @return array<int, array{label: string, order: int, system: int}> */
    private function gradeIndex(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->grades?->listForTenant($tenantId) ?? [] as $g) {
                $out[(int) $g['id']] = [
                    'label' => trim((string) ($g['label_short'] ?? '')) ?: trim((string) ($g['label_long'] ?? '')),
                    'order' => (int) ($g['sort_order'] ?? 0),
                    'system' => (int) ($g['grade_system_id'] ?? 0),
                ];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /** Valeur mesurée d'un critère pour un membre. @param array<string, mixed> $c */
    private function measure(int $tenantId, int $userId, array $c): float
    {
        $metric = (string) ($c['metric'] ?? '');
        $scope = (string) ($c['scope'] ?? '');
        if ($metric === 'flight_count') {
            return (float) ($this->flightCountTotals($tenantId)[$userId][$scope] ?? 0);
        }
        if ($metric === 'flight_hours' || $metric === 'flight_cond_hours') {
            $totals = $this->flightTotals($tenantId)[$userId] ?? [];
            if ($metric === 'flight_hours' && $scope === '') {
                return round((($totals['kind:heli']['seconds'] ?? 0) + ($totals['kind:plane']['seconds'] ?? 0)) / 3600, 2);
            }

            return round(($totals[$scope]['seconds'] ?? 0) / 3600, 2);
        }
        $key = $metric . '|' . $scope . '|' . (int) ($c['window_days'] ?? 0);
        $ctx = &$this->ctx[$tenantId];
        if (!isset($ctx['metrics'][$key])) {
            $ctx['metrics'][$key] = $this->repo->metricValues($tenantId, $metric, $scope, (int) ($c['window_days'] ?? 0));
        }

        return (float) ($ctx['metrics'][$key][$userId] ?? 0.0);
    }

    /**
     * Heures de vol de chaque membre par périmètre : celles du cursus pilote (kind:, family:, cond:) et, en plus,
     * par appareil exact (vclass:<classe>).
     *
     * @return array<int, array<string, array{seconds: int, last_flown_at: string}>>
     */
    private function flightTotals(int $tenantId): array
    {
        $ctx = &$this->ctx[$tenantId];
        if (is_array($ctx['flight'] ?? null)) {
            return $ctx['flight'];
        }
        $rowsByUser = $this->pilotRules->pilotRowsByUser($tenantId);
        try {
            $logByUser = $this->logbook?->countersignedByUser($tenantId) ?? [];
        } catch (Throwable) {
            $logByUser = [];
        }

        return $ctx['flight'] = self::flightScopes($rowsByUser, $logByUser, $this->fleetFor($tenantId));
    }

    private function fleetFor(int $tenantId): PilotFleet
    {
        try {
            return $this->fleet?->fleet($tenantId) ?? PilotFleet::empty();
        } catch (Throwable) {
            return PilotFleet::empty();
        }
    }

    /** @return array<int, array<string, int>> */
    private function flightCountTotals(int $tenantId): array
    {
        $ctx = &$this->ctx[$tenantId];
        if (is_array($ctx['flight_counts'] ?? null)) {
            return $ctx['flight_counts'];
        }

        return $ctx['flight_counts'] = self::flightCounts($this->fleet?->flightCountsByUser($tenantId) ?? [], $this->fleetFor($tenantId));
    }

    /**
     * Nombre de vols de chaque membre par périmètre : « » (tous), filière, appareil reconnu ou de la communauté,
     * famille et classe exacte.
     *
     * @param array<int, list<array<string, mixed>>> $rowsByUser vols du carnet par classe (vehicle_class, vehicle_label, vehicle_kind, flights)
     * @return array<int, array<string, int>>
     */
    public static function flightCounts(array $rowsByUser, ?PilotFleet $fleet = null): array
    {
        $fleet ??= PilotFleet::empty();
        $out = [];
        foreach ($rowsByUser as $uid => $rows) {
            $t = [];
            foreach ($rows as $r) {
                $n = max(0, (int) ($r['flights'] ?? 0));
                if ($n < 1) {
                    continue;
                }
                $class = (string) ($r['vehicle_class'] ?? '');
                $kind = ($r['vehicle_kind'] ?? '') === 'plane' ? 'plane' : 'heli';
                $scopes = array_merge([''], $fleet->scopes($class, (string) ($r['vehicle_label'] ?? ''), $kind));
                if (trim($class) !== '') {
                    $scopes[] = 'vclass:' . strtolower(trim($class));
                }
                foreach (array_unique($scopes) as $sc) {
                    $t[$sc] = ($t[$sc] ?? 0) + $n;
                }
            }
            $out[(int) $uid] = $t;
        }

        return $out;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $rowsByUser atak_pilot_time
     * @param array<int, list<array<string, mixed>>> $logByUser carnet de vol contresigné
     * @return array<int, array<string, array{seconds: int, last_flown_at: string}>>
     */
    public static function flightScopes(array $rowsByUser, array $logByUser, ?PilotFleet $fleet = null): array
    {
        $out = [];
        foreach (array_unique(array_merge(array_keys($rowsByUser), array_keys($logByUser))) as $uid) {
            $rows = $rowsByUser[$uid] ?? [];
            $log = $logByUser[$uid] ?? [];
            $totals = PilotQualificationEngine::mergeLogbook(PilotQualificationEngine::scopeTotals($rows, $fleet), $log, $fleet);
            $byClass = [];
            foreach ($rows as $r) {
                $byClass[] = [(string) ($r['vehicle_class'] ?? ''), (int) ($r['seconds'] ?? 0)];
            }
            foreach ($log as $e) {
                if (($e['status'] ?? '') === 'countersigned' && ($e['source'] ?? '') === 'pilot') {
                    $byClass[] = [(string) ($e['vehicle_class'] ?? ''), (int) ($e['seconds'] ?? 0)];
                }
            }
            foreach ($byClass as [$class, $seconds]) {
                $class = strtolower(trim($class));
                if ($class === '' || $seconds < 1) {
                    continue;
                }
                $k = 'vclass:' . $class;
                $totals[$k] = ['seconds' => ($totals[$k]['seconds'] ?? 0) + $seconds, 'last_flown_at' => ''];
            }
            $out[(int) $uid] = $totals;
        }

        return $out;
    }

    /* ——————————————————— Évaluation ——————————————————— */

    /**
     * Statut administratif d'une attribution ; les anciennes lignes (attestations Academy) n'ont que status, et une
     * attestation échue reste « obtenue » mais expirée (elle se renouvelle en repassant la formation).
     */
    public static function adminStatus(?array $award): string
    {
        if ($award === null) {
            return '';
        }
        $raw = trim((string) ($award['admin_status'] ?? ''));
        if ($raw === '') {
            $raw = match ((string) ($award['status'] ?? '')) {
                'valid', 'expiring', 'expired' => QualificationAdminStatus::OBTAINED,
                default => QualificationAdminStatus::IN_TRAINING,
            };
        }

        return QualificationAdminStatus::normalize($raw);
    }

    /**
     * Meilleure attribution de chaque qualification pour un membre : obtenue (la plus récente), sinon en cours,
     * sinon la dernière.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function bestAwards(array $rows): array
    {
        $rank = static fn (array $a): int => match (self::adminStatus($a)) {
            QualificationAdminStatus::OBTAINED => 3,
            QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION => 2,
            default => 1,
        };
        $out = [];
        foreach ($rows as $row) {
            $def = (int) ($row['definition_id'] ?? 0);
            if ($def < 1) {
                continue;
            }
            $prev = $out[$def] ?? null;
            if ($prev === null || $rank($row) > $rank($prev) || ($rank($row) === $rank($prev) && (int) ($row['id'] ?? 0) > (int) ($prev['id'] ?? 0))) {
                $out[$def] = $row;
            }
        }

        return $out;
    }

    /** Meilleures attributions d'un membre (mises en cache pour la matrice). @return array<int, array<string, mixed>> */
    private function best(int $tenantId, int $userId): array
    {
        $this->context($tenantId);
        if (!isset($this->ctx[$tenantId]['best'][$userId])) {
            $this->ctx[$tenantId]['best'][$userId] = self::bestAwards($this->ctx[$tenantId]['awards'][$userId] ?? []);
        }

        return $this->ctx[$tenantId]['best'][$userId];
    }

    /** @param array<string, mixed> $award @param array<string, mixed> $def */
    private function temporalCode(array $award, array $def): string
    {
        return $this->temporal->resolve($award + [
            'alert_before_expiry_days' => $def['alert_before_expiry_days'] ?? null,
            'grace_period_days' => $def['grace_period_days'] ?? null,
            'admin_status' => self::adminStatus($award),
        ])['code'];
    }

    /**
     * Prérequis d'un membre pour une qualification : autres qualifications (obtention), grade minimum, fonction.
     *
     * @param array<string, mixed> $settings
     * @return array{ok: bool, missing: list<string>}
     */
    private function prerequisites(int $tenantId, int $userId, int $definitionId, array $settings): array
    {
        $ctx = $this->context($tenantId);
        $missing = [];
        $best = $this->best($tenantId, $userId);
        foreach ($ctx['prereqs'][$definitionId] ?? [] as $req) {
            if (!$this->holdsActive($tenantId, $best[$req] ?? null, $req)) {
                $missing[] = 'Qualification « ' . (string) ($ctx['definitions'][$req]['name'] ?? ('#' . $req)) . ' »';
            }
        }
        $gradeId = (int) ($settings['prereq_grade_id'] ?? 0);
        if ($gradeId > 0) {
            $req = $ctx['grades'][$gradeId] ?? null;
            $cur = $ctx['grades'][(int) ($ctx['members'][$userId]['grade_id'] ?? 0)] ?? null;
            $ok = $req === null || ($cur !== null && ($req['system'] === 0 || $cur['system'] === $req['system']) && $cur['order'] >= $req['order']);
            if (!$ok) {
                $missing[] = 'Grade minimum « ' . (string) ($req['label'] ?? '') . ' »';
            }
        }
        $jobId = (int) ($settings['prereq_job_role_id'] ?? 0);
        if ($jobId > 0 && !in_array($jobId, $ctx['jobs'][$userId] ?? [], true)) {
            $missing[] = 'Fonction demandée';
        }

        return ['ok' => $missing === [], 'missing' => $missing];
    }

    /** @param array<string, mixed>|null $award */
    private function holdsActive(int $tenantId, ?array $award, int $definitionId): bool
    {
        if ($award === null || self::adminStatus($award) !== QualificationAdminStatus::OBTAINED) {
            return false;
        }
        $def = $this->context($tenantId)['definitions'][$definitionId] ?? [];

        return in_array($this->temporalCode($award, $def), [QualificationTemporalStatusService::VALID, QualificationTemporalStatusService::EXPIRING_SOON, QualificationTemporalStatusService::EXPIRED_GRACE], true);
    }

    /**
     * Évalue un membre pour une qualification.
     *
     * @param array<string, mixed> $settings
     * @return array{state: string, pct: int, met: bool, eval: array<string, mixed>|null, award: array<string, mixed>|null, temporal: string, prereq: array{ok: bool, missing: list<string>}}
     */
    public function evaluateMember(int $tenantId, int $definitionId, array $settings, int $userId): array
    {
        $ctx = $this->context($tenantId);
        $best = $this->best($tenantId, $userId);
        $award = $best[$definitionId] ?? null;
        $def = $ctx['definitions'][$definitionId] ?? [];
        $temporal = $award !== null ? $this->temporalCode($award, $def) : '';
        $rule = !empty($settings['auto_enabled']) ? ($settings['rule'] ?? QualificationCriteriaEngine::normalize($settings['rule_json'] ?? null)) : [];
        $eval = null;
        if ($rule !== []) {
            $eval = QualificationCriteriaEngine::evaluate(
                $rule,
                fn (array $c): float => $this->measure($tenantId, $userId, $c),
                fn (int $id): bool => $this->holdsActive($tenantId, $best[$id] ?? null, $id),
                static fn (int|string $course): bool => !empty($ctx['academy'][$userId][$course]),
                static fn (array $c): ?float => isset($ctx['attest'][$userId][Cat::attestationStoreKey($c, $definitionId)])
                    ? (float) $ctx['attest'][$userId][Cat::attestationStoreKey($c, $definitionId)]['value']
                    : null,
            );
        }
        $prereq = $this->prerequisites($tenantId, $userId, $definitionId, $settings);
        $met = $eval !== null && $eval['met'];

        return [
            'state' => QualificationCriteriaEngine::state($award !== null ? ['admin_status' => self::adminStatus($award)] + $award : null, $met, $prereq['ok'], $temporal),
            'pct' => $eval['pct'] ?? 0,
            'met' => $met,
            'eval' => $eval,
            'award' => $award,
            'temporal' => $temporal,
            'prereq' => $prereq,
        ];
    }

    /**
     * Passage automatique (tâche planifiée ou bouton « Évaluer maintenant ») : rattache les attestations Academy,
     * délivre ou propose les qualifications atteintes, renouvelle celles qui expirent quand la règle est toujours
     * remplie. Un membre déjà titulaire, en attente de validation ou dont la qualification a été retirée n'est jamais
     * traité deux fois : relancer ne change rien.
     *
     * @return array{awarded: int, proposed: int, renewed: int, linked: int}
     */
    public function runAuto(int $tenantId, ?int $onlyDefinitionId = null): array
    {
        $stats = ['awarded' => 0, 'proposed' => 0, 'renewed' => 0, 'linked' => 0];
        if (!$this->repo->schemaReady()) {
            return $stats;
        }
        unset($this->ctx[$tenantId]);
        $ctx = $this->context($tenantId);
        foreach ($ctx['settings'] as $defId => $row) {
            if (($onlyDefinitionId !== null && $defId !== $onlyDefinitionId) || empty($row['auto_enabled']) || !isset($ctx['definitions'][$defId])) {
                continue;
            }
            $settings = array_merge(self::defaults(), $row);
            $settings['rule'] = QualificationCriteriaEngine::normalize($row['rule_json'] ?? null);
            if ($settings['rule'] === []) {
                continue;
            }
            $def = $ctx['definitions'][$defId];
            $academyOnly = QualificationCriteriaEngine::academyOnlyCourses($settings['rule']);
            if ($academyOnly !== []) {
                $n = $this->repo->attachAcademyAwards($tenantId, $defId, (string) ($def['name'] ?? ''), $academyOnly);
                $stats['linked'] += $n;
                if ($n > 0) {
                    unset($this->ctx[$tenantId]);
                    $ctx = $this->context($tenantId);
                }
                $this->repo->touchEvaluated($tenantId, $defId);
                continue;
            }
            $direct = ($settings['auto_mode'] ?? '') !== Cat::AWARD_PROPOSE;
            foreach (array_keys($ctx['members']) as $uid) {
                $ev = $this->evaluateMember($tenantId, $defId, $settings, $uid);
                try {
                    if ($ev['state'] === QualificationCriteriaEngine::ELIGIBLE) {
                        $this->transitions->award($tenantId, $uid, [
                            'definition_id' => $defId,
                            'admin_status' => $direct ? QualificationAdminStatus::OBTAINED : QualificationAdminStatus::IN_EVALUATION,
                            'source' => self::SOURCE,
                            'notes' => self::autoNote($ev['eval'] ?? [], $direct, false),
                        ], null);
                        $stats[$direct ? 'awarded' : 'proposed']++;
                    } elseif ($ev['state'] === QualificationCriteriaEngine::RENEW && $ev['award'] !== null) {
                        $this->transitions->renew($tenantId, (int) $ev['award']['id'], [
                            'source' => self::SOURCE,
                            'obtained_at' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                            'expires_at' => null,
                            'notes' => self::autoNote($ev['eval'] ?? [], true, true),
                        ], null);
                        $stats['renewed']++;
                    }
                } catch (Throwable) {
                }
            }
            $this->repo->touchEvaluated($tenantId, $defId);
            unset($this->ctx[$tenantId]);
            $ctx = $this->context($tenantId);
        }

        return $stats;
    }

    /** @param array<string, mixed> $eval */
    public static function autoNote(array $eval, bool $direct, bool $renewal): string
    {
        $parts = [];
        foreach ($eval['groups'] ?? [] as $g) {
            foreach ($g['items'] as $it) {
                if (!$it['met']) {
                    continue;
                }
                $c = $it['criterion'];
                if (($c['type'] ?? 'metric') === 'metric') {
                    $parts[] = Cat::formatNumber((float) $it['current']) . ' ' . Cat::unit((string) $c['metric']) . ' (' . mb_strtolower(Cat::metricLabel((string) $c['metric'])) . ') pour ' . Cat::formatNumber((float) $it['target']) . ' demandé(e)s';
                } else {
                    $parts[] = Cat::describe($c);
                }
            }
        }
        $head = $renewal ? 'Renouvelée automatiquement' : ($direct ? 'Délivrée automatiquement' : 'Proposée automatiquement, à valider par un responsable');

        return $head . ($parts !== [] ? ' : ' . implode(' ; ', $parts) : '') . '.';
    }

    /* ——————————————————— Conditions attestées par un instructeur ——————————————————— */

    /**
     * Conditions non mesurées de la règle d'une qualification et ce qui est déjà attesté, membre par membre.
     *
     * @return list<array{criterion: array<string, mixed>, store_key: string, counts: bool, attested: array<int, array{value: float, note: string, attested_by: int, attested_at: string, member: array<string, mixed>}>}>
     */
    public function attestationCriteria(int $tenantId, int $definitionId): array
    {
        $settings = $this->settings($tenantId, $definitionId);
        $ctx = $this->context($tenantId);
        $out = [];
        foreach (QualificationCriteriaEngine::criteria($settings['rule']) as $c) {
            if (($c['type'] ?? '') !== 'attestation') {
                continue;
            }
            $key = Cat::attestationStoreKey($c, $definitionId);
            $attested = [];
            foreach ($ctx['attest'] as $uid => $rows) {
                if (isset($rows[$key], $ctx['members'][$uid])) {
                    $attested[(int) $uid] = $rows[$key] + ['member' => $ctx['members'][$uid]];
                }
            }
            $out[] = ['criterion' => $c, 'store_key' => $key, 'counts' => Cat::attestationCounts((string) $c['key']), 'attested' => $attested];
        }

        return $out;
    }

    /**
     * Un instructeur atteste une condition non mesurée pour un membre (nombre de sauts en chute libre, épreuve de tir
     * réussie…) ; « retirer » efface l'attestation. Le membre est ensuite réévalué tout de suite.
     */
    public function attest(int $tenantId, int $definitionId, int $userId, string $storeKey, ?float $value, string $note, int $actorId, bool $remove = false): string
    {
        if (!$this->repo->attestationsReady()) {
            throw new RuntimeException('Les attestations arrivent avec la prochaine mise à jour du site (migration à lancer).');
        }
        $criterion = null;
        foreach ($this->attestationCriteria($tenantId, $definitionId) as $a) {
            if ($a['store_key'] === $storeKey) {
                $criterion = $a;
            }
        }
        if ($criterion === null) {
            throw new RuntimeException('Cette condition ne fait plus partie de la qualification.');
        }
        if (!isset($this->context($tenantId)['members'][$userId])) {
            throw new RuntimeException('Membre introuvable dans la communauté.');
        }
        if ($remove) {
            $this->repo->deleteAttestation($tenantId, $userId, $storeKey);
            unset($this->ctx[$tenantId]);

            return 'Attestation retirée.';
        }
        $v = $criterion['counts'] ? max(0.0, (float) $value) : 1.0;
        if ($criterion['counts'] && $v <= 0) {
            throw new RuntimeException('Indiquez le nombre attesté.');
        }
        $this->repo->saveAttestation($tenantId, $userId, $storeKey, $v, $note, $actorId > 0 ? $actorId : null);
        unset($this->ctx[$tenantId]);
        $r = $this->runAuto($tenantId, $definitionId);

        return 'Attestation enregistrée.' . ($r['awarded'] + $r['proposed'] > 0 ? ' Toutes les conditions sont remplies : qualification ' . ($r['awarded'] > 0 ? 'délivrée.' : 'proposée à la validation.') : '');
    }

    /* ——————————————————— Écrans ——————————————————— */

    /**
     * Fiche d'une qualification : titulaires, propositions à valider, membres en progression vers les seuils.
     *
     * @return array{settings: array<string, mixed>, modes: list<string>, holders: list<array<string, mixed>>, pending: list<array<string, mixed>>, progress: list<array<string, mixed>>, blocked: list<array<string, mixed>>, candidates: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function board(int $tenantId, int $definitionId): array
    {
        unset($this->ctx[$tenantId]);
        $ctx = $this->context($tenantId);
        $settings = $this->settings($tenantId, $definitionId);
        $out = ['holders' => [], 'pending' => [], 'progress' => [], 'blocked' => [], 'candidates' => []];
        $counts = ['ok' => 0, 'soon' => 0, 'exp' => 0, 'train' => 0];
        foreach ($ctx['members'] as $uid => $m) {
            $ev = $this->evaluateMember($tenantId, $definitionId, $settings, $uid);
            $cell = $this->cellState($ev, !empty($settings['auto_enabled']));
            if (isset($counts[$cell])) {
                $counts[$cell]++;
            }
            $row = ['member' => $m, 'cell' => $cell] + $ev;
            if (!in_array($ev['state'], [QualificationCriteriaEngine::HELD, QualificationCriteriaEngine::PENDING], true)) {
                $out['candidates'][] = $m;
            }
            match ($ev['state']) {
                QualificationCriteriaEngine::HELD, QualificationCriteriaEngine::RENEW => $out['holders'][] = $row,
                QualificationCriteriaEngine::PENDING => $out['pending'][] = $row,
                QualificationCriteriaEngine::BLOCKED => $out['blocked'][] = $row,
                default => $ev['eval'] !== null && ($ev['pct'] > 0 || $ev['met']) ? $out['progress'][] = $row : null,
            };
        }
        usort($out['progress'], static fn (array $a, array $b): int => [$b['met'], $b['pct']] <=> [$a['met'], $a['pct']]);
        usort($out['holders'], static fn (array $a, array $b): int => strcmp((string) ($a['award']['expires_at'] ?? '9999'), (string) ($b['award']['expires_at'] ?? '9999')));

        return $out + [
            'settings' => $settings,
            'modes' => QualificationCriteriaEngine::modes($settings['exists'] ? $settings : null, isset($ctx['pilot'][$definitionId])),
            'counts' => $counts,
        ];
    }

    /**
     * Résumé de plusieurs qualifications (paliers du cursus pilote créés par la communauté) en un seul calcul :
     * titulaires, propositions à valider, membres éligibles et les plus avancés vers les conditions.
     *
     * @param list<int> $definitionIds
     * @return array<int, array{held: int, eligible: int, pending: list<array{member: array<string, mixed>, award_id: int}>, near: list<array{member: array<string, mixed>, pct: int, missing: list<string>}>, auto: bool, rule: array<string, mixed>}>
     */
    public function summaries(int $tenantId, array $definitionIds): array
    {
        $out = [];
        if (!$this->repo->schemaReady() || $definitionIds === []) {
            return $out;
        }
        unset($this->ctx[$tenantId]);
        $ctx = $this->context($tenantId);
        foreach (array_unique(array_map('intval', $definitionIds)) as $defId) {
            $settings = $this->settings($tenantId, $defId);
            $sum = ['held' => 0, 'eligible' => 0, 'pending' => [], 'near' => [], 'auto' => !empty($settings['auto_enabled']), 'rule' => $settings['rule']];
            foreach ($ctx['members'] as $uid => $m) {
                $ev = $this->evaluateMember($tenantId, $defId, $settings, $uid);
                if (in_array($ev['state'], [QualificationCriteriaEngine::HELD, QualificationCriteriaEngine::RENEW], true)) {
                    $sum['held']++;
                } elseif ($ev['state'] === QualificationCriteriaEngine::PENDING) {
                    $sum['pending'][] = ['member' => $m, 'award_id' => (int) ($ev['award']['id'] ?? 0)];
                } elseif ($ev['state'] === QualificationCriteriaEngine::ELIGIBLE) {
                    $sum['eligible']++;
                } elseif ($ev['state'] === QualificationCriteriaEngine::PROGRESS && $ev['pct'] > 0) {
                    $sum['near'][] = ['member' => $m, 'pct' => (int) $ev['pct'], 'missing' => $ev['prereq']['missing']];
                }
            }
            usort($sum['near'], static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);
            $sum['near'] = array_slice($sum['near'], 0, 3);
            $out[$defId] = $sum;
        }

        return $out;
    }

    /**
     * Case de la matrice : qualifié, expire bientôt (ou en grâce), expirée, en cours (formation, à valider,
     * progression vers les seuils), retirée, rien.
     *
     * @param array<string, mixed> $ev evaluateMember()
     */
    public function cellState(array $ev, bool $auto): string
    {
        $status = self::adminStatus($ev['award']);
        if ($status === QualificationAdminStatus::OBTAINED) {
            return match ($ev['temporal']) {
                QualificationTemporalStatusService::EXPIRING_SOON, QualificationTemporalStatusService::EXPIRED_GRACE => 'soon',
                QualificationTemporalStatusService::EXPIRED => 'exp',
                default => 'ok',
            };
        }
        if ($ev['state'] === QualificationCriteriaEngine::PENDING) {
            return 'train';
        }
        if ($ev['state'] === QualificationCriteriaEngine::BLOCKED) {
            return 'off';
        }

        return $auto && ($ev['pct'] > 0 || $ev['met']) ? 'train' : 'none';
    }

    /**
     * Matrice membres × qualifications de la page Effectifs > Qualifications.
     *
     * @return array{definitions: list<array<string, mixed>>, members: list<array<string, mixed>>, cells: array<int, array<int, array<string, mixed>>>, counts: array<string, int>, units: list<array{id: int, name: string, short: string}>, coverage: array<int, array<int, int>>, totals: array<int, int>, unlinked_academy: int}
     */
    public function matrix(int $tenantId): array
    {
        unset($this->ctx[$tenantId]);
        $ctx = $this->context($tenantId);
        $defs = array_values($ctx['definitions']);
        $settingsByDef = [];
        foreach ($defs as $d) {
            $id = (int) $d['id'];
            $row = $ctx['settings'][$id] ?? null;
            $s = $row !== null ? array_merge(self::defaults(), $row, ['exists' => true]) : self::defaults();
            $s['rule'] = QualificationCriteriaEngine::normalize($s['rule_json'] ?? null);
            $settingsByDef[$id] = $s;
        }
        $cells = [];
        $counts = ['ok' => 0, 'soon' => 0, 'exp' => 0, 'train' => 0];
        $coverage = [];
        $totals = [];
        foreach ($ctx['members'] as $uid => $m) {
            foreach ($defs as $d) {
                $id = (int) $d['id'];
                $ev = $this->evaluateMember($tenantId, $id, $settingsByDef[$id], $uid);
                $state = $this->cellState($ev, !empty($settingsByDef[$id]['auto_enabled']));
                if (isset($counts[$state])) {
                    $counts[$state]++;
                }
                if ($state === 'ok' || $state === 'soon') {
                    $totals[$id] = ($totals[$id] ?? 0) + 1;
                    $coverage[$id][(int) $m['unit_id']] = ($coverage[$id][(int) $m['unit_id']] ?? 0) + 1;
                }
                $cells[$uid][$id] = [
                    'state' => $state,
                    'award_id' => (int) ($ev['award']['id'] ?? 0),
                    'obtained_at' => (string) ($ev['award']['obtained_at'] ?? ''),
                    'expires_at' => (string) ($ev['award']['expires_at'] ?? ''),
                    'pct' => (int) $ev['pct'],
                    'admin' => self::adminStatus($ev['award']),
                ];
            }
        }
        $units = [];
        foreach ($ctx['members'] as $m) {
            $uid = (int) $m['unit_id'];
            if (!isset($units[$uid])) {
                $units[$uid] = ['id' => $uid, 'name' => $uid > 0 ? ($m['unit_name'] !== '' ? $m['unit_name'] : ('#' . $uid)) : '', 'short' => (string) $m['unit_short']];
            }
        }
        uasort($units, static fn (array $a, array $b): int => [$a['id'] === 0, $a['name']] <=> [$b['id'] === 0, $b['name']]);
        $members = array_values($ctx['members']);
        usort($members, static function (array $a, array $b) use ($ctx): int {
            $ga = $ctx['grades'][(int) $a['grade_id']]['order'] ?? 0;
            $gb = $ctx['grades'][(int) $b['grade_id']]['order'] ?? 0;

            return [$gb, mb_strtolower($a['callsign'] ?: $a['display_name'])] <=> [$ga, mb_strtolower($b['callsign'] ?: $b['display_name'])];
        });
        foreach ($defs as $i => $d) {
            $id = (int) $d['id'];
            $defs[$i]['modes'] = QualificationCriteriaEngine::modes($settingsByDef[$id]['exists'] ? $settingsByDef[$id] : null, isset($ctx['pilot'][$id]));
            $defs[$i]['min_holders_per_unit'] = (int) ($settingsByDef[$id]['min_holders_per_unit'] ?? 0);
            $defs[$i]['allow_manual'] = !empty($settingsByDef[$id]['allow_manual']);
        }

        return [
            'definitions' => $defs,
            'members' => $members,
            'cells' => $cells,
            'counts' => $counts,
            'units' => array_values($units),
            'coverage' => $coverage,
            'totals' => $totals,
            'unlinked_academy' => $this->repo->countUnlinkedAcademyAwards($tenantId),
        ];
    }

    /**
     * Progression d'un membre vers les qualifications automatiques qu'il n'a pas encore (sa fiche, « Mes
     * qualifications ») : la plus avancée d'abord, sans celles qu'il n'a pas commencées.
     *
     * @return list<array{definition: array<string, mixed>, state: string, pct: int, met: bool, eval: array<string, mixed>, prereq: array{ok: bool, missing: list<string>}}>
     */
    public function memberProgress(int $tenantId, int $userId): array
    {
        if (!$this->repo->schemaReady()) {
            return [];
        }
        $ctx = $this->context($tenantId);
        $out = [];
        foreach ($ctx['settings'] as $defId => $row) {
            if (empty($row['auto_enabled']) || !isset($ctx['definitions'][$defId])) {
                continue;
            }
            $settings = array_merge(self::defaults(), $row);
            $settings['rule'] = QualificationCriteriaEngine::normalize($row['rule_json'] ?? null);
            if ($settings['rule'] === []) {
                continue;
            }
            $ev = $this->evaluateMember($tenantId, $defId, $settings, $userId);
            if (!in_array($ev['state'], [QualificationCriteriaEngine::PROGRESS, QualificationCriteriaEngine::ELIGIBLE, QualificationCriteriaEngine::PENDING, QualificationCriteriaEngine::RENEW], true) || $ev['eval'] === null) {
                continue;
            }
            if ($ev['state'] === QualificationCriteriaEngine::PROGRESS && $ev['pct'] <= 0) {
                continue;
            }
            $out[] = ['definition' => $ctx['definitions'][$defId], 'state' => $ev['state'], 'pct' => $ev['pct'], 'met' => $ev['met'], 'eval' => $ev['eval'], 'prereq' => $ev['prereq']];
        }
        usort($out, static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);

        return $out;
    }

    /**
     * Libellés des formations, qualifications et périmètres (appareils, objets vus en jeu) pour décrire les critères.
     *
     * @return array{courses: array<int, string>, definitions: array<int, string>, scopes: array<string, string>}
     */
    public function names(int $tenantId): array
    {
        $defs = [];
        foreach ($this->definitions->listForTenant($tenantId, true) as $d) {
            $defs[(int) $d['id']] = (string) ($d['name'] ?? '');
        }
        $scopes = [];
        foreach ($this->aircraftOptions($tenantId) as [$value, $label]) {
            $scopes[$value] = $label;
        }
        foreach ($this->repo->itemsSeen($tenantId) as $it) {
            $scopes['class:' . $it['class_name']] = trim((string) $it['name']) !== '' ? (string) $it['name'] : (string) $it['class_name'];
        }

        $lang = function_exists('locale') ? (string) locale() : 'fr';
        $builtIn = \App\Services\Academy\AcademyBuiltInQualifications::courseTitles($lang);
        $studio = self::studioCourseTitles($tenantId, $lang);

        // builtin : tous les cours désignés par leur clé (intégrés et Studio), pour décrire les critères ; studio : ceux du Studio.
        return ['courses' => $this->repo->courseTitles($tenantId), 'builtin' => $builtIn + $studio, 'studio' => $studio, 'definitions' => $defs, 'scopes' => $scopes];
    }

    /** @return array<string, string> cours publiés du Studio de la communauté : clé => titre */
    private static function studioCourseTitles(int $tenantId, string $lang): array
    {
        $lang = str_starts_with(strtolower($lang), 'en') ? 'en' : 'fr';
        $out = [];
        try {
            foreach ((new \App\Repositories\AcademyRepository())->courses($tenantId) as $course) {
                if (empty($course['built_in']) && is_string($course['key'] ?? null)) {
                    $out[$course['key']] = trim((string) ($course['title'][$lang] ?? '')) ?: (trim((string) ($course['title']['fr'] ?? '')) ?: $course['key']);
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * Périmètres d'heures de vol : filières, familles d'appareils (connues et pilotées dans la communauté), appareils
     * exacts vus en jeu.
     *
     * @return list<array{0: string, 1: string, 2: string}> valeur, libellé, groupe
     */
    public function aircraftOptions(int $tenantId): array
    {
        $out = [['', 'Tous les aéronefs', ''], ['kind:heli', 'Hélicoptères', ''], ['kind:plane', 'Avions', '']];
        $families = [];
        foreach ([PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE] as $track) {
            foreach (PilotSyllabusCatalog::knownFamilies($track) as $key => $label) {
                $families['family:' . $key] = $label;
            }
        }
        $fleet = $this->fleetFor($tenantId);
        foreach ($fleet->aircraftRows() as $key => $row) {
            if (empty($row['archived_at']) && trim((string) ($row['name'] ?? '')) !== '') {
                $families['family:' . $key] = (string) $row['name'];
            }
        }
        $classes = [];
        foreach ($this->repo->aircraftSeen($tenantId) as $a) {
            $class = (string) ($a['vehicle_class'] ?? '');
            $label = trim((string) ($a['vehicle_label'] ?? '')) ?: $class;
            $fam = AtakDistanceRepository::aircraftFamily($class, $label);
            $families['family:' . $fam['key']] ??= $fam['label'];
            $classes['vclass:' . strtolower($class)] = $label . ' (' . $class . ')';
        }
        foreach ($families as $v => $l) {
            $out[] = [$v, $l, 'family'];
        }
        foreach ($fleet->groups() as $g => $l) {
            $out[] = ['group:' . $g, $l, 'group'];
        }
        foreach ($classes as $v => $l) {
            $out[] = [$v, $l, 'class'];
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string, 2: string}> objets vus en jeu (familles puis objets) */
    public function itemOptions(int $tenantId): array
    {
        $out = [];
        foreach (GameItemKinds::KINDS as $k => $meta) {
            $out[] = ['kind:' . $k, $meta['plural'], 'kind'];
        }
        $items = $this->repo->itemsSeen($tenantId);
        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        foreach ($items as $it) {
            $name = trim((string) $it['name']) ?: (string) $it['class_name'];
            $out[] = ['class:' . $it['class_name'], $name . ' (' . $it['class_name'] . ')', 'class'];
        }

        return $out;
    }

    /** @return list<string> */
    public function sessionCategories(int $tenantId): array
    {
        return $this->repo->sessionCategories($tenantId);
    }

    /* ——————————————————— Délivrance à la main ——————————————————— */

    /**
     * Change la case d'un membre depuis la matrice ou la fiche : « ok » délivre (ou renouvelle), « train » met en
     * formation, « none » retire (motif obligatoire).
     *
     * @return string message pour l'utilisateur
     */
    public function setCell(int $tenantId, int $definitionId, int $userId, string $target, string $date, string $notes, string $reason, int $actorId, bool $force = false): string
    {
        $ctx = $this->context($tenantId);
        if (!isset($ctx['definitions'][$definitionId])) {
            throw new RuntimeException('Qualification introuvable ou archivée.');
        }
        if (!isset($ctx['members'][$userId])) {
            throw new RuntimeException('Ce membre ne fait pas partie de la communauté.');
        }
        $settings = $this->settings($tenantId, $definitionId);
        $ev = $this->evaluateMember($tenantId, $definitionId, $settings, $userId);
        $award = $ev['award'];
        $status = self::adminStatus($award);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date <= date('Y-m-d') ? $date : date('Y-m-d');
        $notes = mb_substr(trim($notes), 0, 2000);
        unset($this->ctx[$tenantId]);

        if ($target === 'none') {
            if ($award === null || in_array($status, [QualificationAdminStatus::REVOKED, QualificationAdminStatus::FAILED], true)) {
                return 'Rien à retirer : le membre n’a pas cette qualification.';
            }
            if (trim($reason) === '') {
                throw new RuntimeException('Indiquez le motif du retrait.');
            }
            $this->transitions->transition($tenantId, (int) $award['id'], QualificationAdminStatus::REVOKED, $actorId, mb_substr(trim($reason), 0, 500));

            return 'Qualification retirée.';
        }
        if (empty($settings['allow_manual'])) {
            throw new RuntimeException('Cette qualification ne se délivre pas à la main : elle s’obtient automatiquement (voir sa fiche).');
        }
        if ($target === 'train') {
            if ($status === QualificationAdminStatus::OBTAINED) {
                throw new RuntimeException('Le membre détient déjà cette qualification : retirez-la d’abord.');
            }
            if (in_array($status, [QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION], true)) {
                return 'Le membre est déjà en cours sur cette qualification.';
            }
            $this->transitions->award($tenantId, $userId, [
                'definition_id' => $definitionId,
                'admin_status' => QualificationAdminStatus::IN_TRAINING,
                'notes' => $notes !== '' ? $notes : null,
                'source' => 'manual',
            ], $actorId);

            return 'Membre mis en formation.';
        }
        if (!$ev['prereq']['ok'] && !$force) {
            throw new RuntimeException('Prérequis manquants : ' . implode(', ', $ev['prereq']['missing']) . '. Cochez « Délivrer quand même » pour passer outre.');
        }
        if (in_array($status, [QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION], true)) {
            $this->transitions->transition($tenantId, (int) $award['id'], QualificationAdminStatus::OBTAINED, $actorId, $notes !== '' ? $notes : 'Validée par un responsable');
            $def = $ctx['definitions'][$definitionId];
            $this->awards->update((int) $award['id'], [
                'obtained_at' => $date,
                'expires_at' => $this->temporal->computeDefaultExpiresAt($date, isset($def['default_validity_months']) ? (int) $def['default_validity_months'] : null, !empty($def['is_permanent'])),
                'notes' => $notes !== '' ? $notes : ($award['notes'] ?? null),
            ], $actorId);

            return 'Qualification validée.';
        }
        if ($status === QualificationAdminStatus::OBTAINED) {
            if (in_array($ev['temporal'], [QualificationTemporalStatusService::VALID, ''], true)) {
                return 'Le membre détient déjà cette qualification.';
            }
            $this->transitions->renew($tenantId, (int) $award['id'], ['obtained_at' => $date, 'expires_at' => null, 'notes' => $notes !== '' ? $notes : null, 'source' => 'manual'], $actorId);

            return 'Qualification renouvelée.';
        }
        $this->transitions->award($tenantId, $userId, [
            'definition_id' => $definitionId,
            'admin_status' => QualificationAdminStatus::OBTAINED,
            'obtained_at' => $date,
            'notes' => $notes !== '' ? $notes : null,
            'source' => 'manual',
        ], $actorId);

        return 'Qualification délivrée.';
    }

    /** Décision sur une proposition automatique : validée (obtenue) ou refusée (échouée). */
    public function decide(int $tenantId, int $awardId, bool $approve, int $actorId): void
    {
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null) {
            throw new RuntimeException('Proposition introuvable.');
        }
        if (!in_array(self::adminStatus($award), [QualificationAdminStatus::CANDIDATE, QualificationAdminStatus::IN_TRAINING, QualificationAdminStatus::IN_EVALUATION], true)) {
            throw new RuntimeException('Cette proposition a déjà été traitée.');
        }
        if ((int) $award['user_id'] === $actorId) {
            throw new RuntimeException('Un autre responsable doit valider votre propre qualification.');
        }
        $this->transitions->transition($tenantId, $awardId, $approve ? QualificationAdminStatus::OBTAINED : QualificationAdminStatus::FAILED, $actorId, $approve ? 'Validée par un responsable' : 'Refusée par un responsable');
        unset($this->ctx[$tenantId]);
    }
}
