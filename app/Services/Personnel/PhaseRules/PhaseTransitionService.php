<?php

declare(strict_types=1);

namespace App\Services\Personnel\PhaseRules;

use App\Repositories\PersonnelPhaseRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use PDO;

final class PhaseTransitionService
{
    public function __construct(
        private PersonnelPhaseRepository $phases,
        private PersonnelProfileRepository $profiles,
        private UserRepository $users,
        private PhaseRuleEngine $engine,
        private PhaseFactsLoader $facts,
    ) {}

    /**
     * @return array{phase: ?array<string, mixed>, next: ?array<string, mixed>, rule_set: ?array<string, mixed>, conditions: list<array<string, mixed>>, evaluation: array<string, mixed>, effect: string}
     */
    public function checklistForMember(int $tenantId, int $userId): array
    {
        $emptyEval = ['eligible' => false, 'logic' => 'all', 'items' => []];
        if (!$this->phases->schemaReady()) {
            return ['phase' => null, 'next' => null, 'rule_set' => null, 'conditions' => [], 'evaluation' => $emptyEval, 'effect' => 'manual_gate'];
        }
        $phases = $this->phases->listPhases($tenantId, true);
        $profile = $this->profiles->getByUserId($userId, $tenantId) ?? [];
        $currentId = (int) ($profile['current_phase_id'] ?? 0);
        $current = null;
        $next = null;
        foreach ($phases as $i => $phase) {
            if ((int) $phase['id'] === $currentId) {
                $current = $phase;
                $next = $phases[$i + 1] ?? null;
                break;
            }
        }
        if ($current === null && $phases !== []) {
            $current = $phases[0];
            $next = $phases[1] ?? null;
        }
        if ($next === null) {
            return ['phase' => $current, 'next' => null, 'rule_set' => null, 'conditions' => [], 'evaluation' => $emptyEval, 'effect' => 'manual_gate'];
        }
        $ruleSet = $this->phases->ruleSetForPhase($tenantId, (int) $next['id']);
        $conditions = $ruleSet ? $this->phases->listConditions($tenantId, (int) $ruleSet['id']) : [];
        $effect = (string) ($ruleSet['effect'] ?? 'manual_gate');
        $logic = (string) ($ruleSet['logic'] ?? 'all');
        $active = $ruleSet && !empty($ruleSet['is_active']);
        if (!$active || $conditions === []) {
            return [
                'phase' => $current,
                'next' => $next,
                'rule_set' => $ruleSet,
                'conditions' => $conditions,
                'evaluation' => $emptyEval,
                'effect' => $effect,
            ];
        }
        $ctx = $this->facts->load($tenantId, $userId, $conditions[0] ?? []);
        $mergedItems = [];
        $allPass = true;
        $anyPass = false;
        foreach ($conditions as $condition) {
            $ctxOne = $this->facts->load($tenantId, $userId, $condition);
            $item = $this->engine->evaluateOne($condition, $ctxOne);
            $mergedItems[] = $item;
            if (!empty($item['passed'])) {
                $anyPass = true;
            } else {
                $allPass = false;
            }
        }
        $eligible = $logic === 'any' ? $anyPass : $allPass;

        return [
            'phase' => $current,
            'next' => $next,
            'rule_set' => $ruleSet,
            'conditions' => $conditions,
            'evaluation' => ['eligible' => $eligible, 'logic' => $logic, 'items' => $mergedItems],
            'effect' => $effect,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, applied?: bool}
     */
    public function applyManual(int $tenantId, int $userId, int $actorId, bool $override = false, string $reason = ''): array
    {
        $check = $this->checklistForMember($tenantId, $userId);
        $next = $check['next'];
        if ($next === null) {
            return ['ok' => false, 'error' => 'Aucune étape suivante.'];
        }
        $eligible = !empty($check['evaluation']['eligible']);
        if (!$eligible && !$override) {
            return ['ok' => false, 'error' => 'Les conditions ne sont pas encore remplies.'];
        }
        if ($override && trim($reason) === '') {
            return ['ok' => false, 'error' => 'Indiquez le motif du passage forcé.'];
        }

        return $this->apply(
            $tenantId,
            $userId,
            (int) ($check['phase']['id'] ?? 0) ?: null,
            (int) $next['id'],
            $check['rule_set'] ? (int) $check['rule_set']['id'] : null,
            $override ? 'admin_override' : 'manual',
            $actorId,
            $override ? $reason : null,
            $check['evaluation'],
            (string) ($next['target_member_status'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array{ok: bool, error?: string, applied?: bool}
     */
    public function apply(
        int $tenantId,
        int $userId,
        ?int $fromPhaseId,
        int $toPhaseId,
        ?int $ruleSetId,
        string $trigger,
        ?int $actorId,
        ?string $reason,
        array $snapshot,
        string $targetStatus
    ): array {
        $pdo = $this->phases->pdo();
        $pdo->beginTransaction();
        try {
            $user = $this->users->findById($userId, $tenantId);
            if (!$user || strtolower(trim((string) ($user['status'] ?? ''))) !== 'active') {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Membre inactif ou introuvable.'];
            }
            $lock = $pdo->prepare(
                'SELECT current_phase_id FROM personnel_profiles WHERE user_id = ? AND tenant_id = ? FOR UPDATE'
            );
            $lock->execute([$userId, $tenantId]);
            $locked = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$locked) {
                $lock = $pdo->prepare('SELECT current_phase_id FROM personnel_profiles WHERE user_id = ? FOR UPDATE');
                $lock->execute([$userId]);
                $locked = $lock->fetch(PDO::FETCH_ASSOC) ?: [];
            }
            $current = (int) ($locked['current_phase_id'] ?? 0);
            if ($fromPhaseId && $current > 0 && $current !== $fromPhaseId) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'L’étape a changé pendant le traitement.'];
            }
            if ($current === $toPhaseId) {
                $pdo->rollBack();

                return ['ok' => true, 'applied' => false];
            }
            if ($this->phases->lastTransitionMatches($tenantId, $userId, (int) $fromPhaseId, $toPhaseId)) {
                $pdo->rollBack();

                return ['ok' => true, 'applied' => false];
            }
            $target = $this->phases->findPhase($tenantId, $toPhaseId);
            if (!$target || empty($target['is_active'])) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Étape d’arrivée inactive.'];
            }
            $fromPhase = $fromPhaseId ? $this->phases->findPhase($tenantId, $fromPhaseId) : null;
            if ($fromPhase !== null && !self::isForwardTransition(
                (int) ($fromPhase['position'] ?? 0),
                (int) ($target['position'] ?? 0)
            )) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Le parcours n’autorise pas un retour en arrière.'];
            }
            if ($trigger !== 'admin_override') {
                $recheck = $this->checklistForMember($tenantId, $userId);
                if (empty($recheck['evaluation']['eligible'])) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => 'Les conditions ne sont plus remplies.'];
                }
                if ((int) ($recheck['next']['id'] ?? 0) !== $toPhaseId) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => 'L’étape a changé pendant le traitement.'];
                }
            }
            $this->profiles->update($userId, [
                'current_phase_id' => $toPhaseId,
                'operator_status' => $targetStatus !== '' ? $targetStatus : ($target['target_member_status'] ?? null),
            ]);
            $this->phases->addTransition($tenantId, $userId, [
                'from_phase_id' => $fromPhaseId ?: null,
                'to_phase_id' => $toPhaseId,
                'rule_set_id' => $ruleSetId,
                'trigger_kind' => $trigger,
                'actor_user_id' => $actorId,
                'override_reason' => $reason,
                'evaluation_snapshot_json' => $snapshot,
            ]);
            $pdo->commit();

            return ['ok' => true, 'applied' => true];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'error' => 'Le passage n’a pas pu être enregistré.'];
        }
    }

    public function runAutomaticForTenant(int $tenantId): array
    {
        $applied = 0;
        $skipped = 0;
        $errors = 0;
        if (!$this->phases->schemaReady()) {
            return ['applied' => 0, 'skipped' => 0, 'errors' => 0];
        }
        $users = $this->users->allForTenant($tenantId);
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId < 1 || strtolower(trim((string) ($user['status'] ?? ''))) !== 'active') {
                continue;
            }
            $check = $this->checklistForMember($tenantId, $userId);
            if (empty($check['next']) || ($check['effect'] ?? '') !== 'automatic') {
                $skipped++;
                continue;
            }
            if (empty($check['evaluation']['eligible'])) {
                $skipped++;
                continue;
            }
            $result = $this->apply(
                $tenantId,
                $userId,
                isset($check['phase']['id']) ? (int) $check['phase']['id'] : null,
                (int) $check['next']['id'],
                $check['rule_set'] ? (int) $check['rule_set']['id'] : null,
                'automatic',
                null,
                null,
                $check['evaluation'],
                (string) ($check['next']['target_member_status'] ?? '')
            );
            if (!empty($result['applied'])) {
                $applied++;
            } elseif (!empty($result['ok'])) {
                $skipped++;
            } else {
                $errors++;
                $this->phases->recordAutoError(
                    $tenantId,
                    $userId,
                    isset($check['next']['id']) ? (int) $check['next']['id'] : null,
                    'auto_failed',
                    (string) ($result['error'] ?? 'Passage automatique impossible')
                );
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function countPendingGates(int $tenantId): int
    {
        if (!$this->phases->schemaReady()) {
            return 0;
        }
        $n = 0;
        foreach ($this->users->allForTenant($tenantId) as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId < 1 || strtolower((string) ($user['status'] ?? '')) !== 'active') {
                continue;
            }
            $check = $this->checklistForMember($tenantId, $userId);
            if (!empty($check['evaluation']['eligible']) && ($check['effect'] ?? '') === 'manual_gate' && !empty($check['next'])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Une transition n’avance que vers une étape de position supérieure.
     */
    public static function isForwardTransition(?int $fromPosition, int $toPosition): bool
    {
        if ($fromPosition === null || $fromPosition < 1) {
            return true;
        }

        return $toPosition > $fromPosition;
    }

    /**
     * Pastille tableur : validation staff, progression, ou prêt (auto, transitoire).
     *
     * @param array<string, mixed> $check
     * @return array{kind: string, label: string}|null
     */
    public static function rosterBadge(array $check): ?array
    {
        if (empty($check['next'])) {
            return null;
        }
        $items = is_array($check['evaluation']['items'] ?? null) ? $check['evaluation']['items'] : [];
        $passed = 0;
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['passed'])) {
                $passed++;
            }
        }
        $total = count($items);
        $eligible = !empty($check['evaluation']['eligible']);
        $effect = (string) ($check['effect'] ?? 'manual_gate');
        if ($eligible && $effect === 'manual_gate') {
            return ['kind' => 'gate', 'label' => 'VALIDATION REQUISE'];
        }
        if ($eligible && $effect === 'automatic') {
            return ['kind' => 'ready', 'label' => 'Prêt'];
        }
        if ($total > 0) {
            $phaseLabel = trim((string) ($check['phase']['label'] ?? 'Étape')) ?: 'Étape';

            return ['kind' => 'progress', 'label' => $phaseLabel . ' · ' . $passed . '/' . $total];
        }

        return null;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{kind: string, label: string}>
     */
    public function rosterBadgesForUsers(int $tenantId, array $userIds): array
    {
        $out = [];
        if (!$this->phases->schemaReady()) {
            return $out;
        }
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId < 1) {
                continue;
            }
            try {
                $badge = self::rosterBadge($this->checklistForMember($tenantId, $userId));
                if ($badge !== null) {
                    $out[$userId] = $badge;
                }
            } catch (\Throwable) {
            }
        }

        return $out;
    }
}
