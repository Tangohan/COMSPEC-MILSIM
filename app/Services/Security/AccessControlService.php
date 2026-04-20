<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Core\Database;
use App\Services\Security\Conditions\ConditionEvaluatorInterface;
use App\Services\Security\Conditions\DaysSinceCreationConditionEvaluator;
use App\Services\Security\Conditions\ManualApprovalConditionEvaluator;
use App\Services\Security\Conditions\ModuleValidatedConditionEvaluator;
use App\Services\Security\Conditions\StatusConditionEvaluator;
use App\Services\Security\Conditions\UnitConditionEvaluator;
use PDO;

final class AccessControlService
{
    private PDO $pdo;

    /** @var list<ConditionEvaluatorInterface> */
    private array $evaluators;

    /** @var array<string, array{allowed: bool, reason: string}> */
    private array $requestCache = [];

    public function __construct(?array $evaluators = null)
    {
        $this->pdo = Database::getPdo();
        $this->evaluators = $evaluators ?? [
            new DaysSinceCreationConditionEvaluator(),
            new ModuleValidatedConditionEvaluator(),
            new UnitConditionEvaluator(),
            new ManualApprovalConditionEvaluator(),
            new StatusConditionEvaluator(),
        ];
    }

    public function canAccess(array $user, string $resource, string $action): bool
    {
        return $this->decision($user, $resource, $action)['allowed'];
    }

    /** @return array{allowed: bool, reason: string, winner_rule_id?: int} */
    public function decision(array $user, string $resource, string $action): array
    {
        $cacheKey = implode('|', [(string) ($user['id'] ?? 0), strtolower($resource), strtoupper($action)]);
        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return ['allowed' => false, 'reason' => 'invalid_user_context'];
        }

        if ($this->isPlatformAdminBypass($user)) {
            $decision = ['allowed' => true, 'reason' => 'platform_admin_bypass'];
            $this->log($tenantId, $userId, $resource, $action, $decision, ['bypass' => true]);

            return $this->requestCache[$cacheKey] = $decision;
        }

        $rbac = $this->rbacAllows($userId, $tenantId, $resource, $action);
        if (!$rbac['allowed']) {
            $decision = ['allowed' => false, 'reason' => 'rbac_denied'];
            $this->log($tenantId, $userId, $resource, $action, $decision, ['rbac' => $rbac]);

            return $this->requestCache[$cacheKey] = $decision;
        }

        $abac = $this->resolveAbac($user, $resource, $action);
        $decision = [
            'allowed' => $abac['allowed'],
            'reason' => $abac['reason'],
            'winner_rule_id' => $abac['winner_rule_id'] ?? null,
        ];
        $this->log($tenantId, $userId, $resource, $action, $decision, ['abac' => $abac]);

        return $this->requestCache[$cacheKey] = $decision;
    }

    private function isPlatformAdminBypass(array $user): bool
    {
        return !empty($user['is_platform_admin']) || !empty($user['is_super_admin']);
    }

    /** @return array{allowed: bool, matched_codes: list<string>} */
    private function rbacAllows(int $userId, int $tenantId, string $resource, string $action): array
    {
        $roleIdsStmt = $this->pdo->prepare('SELECT role_id FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ?');
        $roleIdsStmt->execute([$userId, $tenantId]);
        $roleIds = array_values(array_filter(array_map('intval', $roleIdsStmt->fetchAll(PDO::FETCH_COLUMN)), static fn (int $id): bool => $id > 0));
        if ($roleIds === []) {
            $legacy = $this->pdo->prepare('SELECT role_id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
            $legacy->execute([$userId, $tenantId]);
            $legacyRole = (int) $legacy->fetchColumn();
            if ($legacyRole > 0) {
                $roleIds[] = $legacyRole;
            }
        }
        if ($roleIds === []) {
            return ['allowed' => false, 'matched_codes' => []];
        }

        $codes = $this->resourceCodes($resource, $action);
        $phRole = implode(',', array_fill(0, count($roleIds), '?'));
        $phCodes = implode(',', array_fill(0, count($codes), '?'));

        $sql = "SELECT DISTINCT p.code
                FROM role_permissions rp
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.allowed = 1 AND rp.role_id IN ($phRole)
                  AND p.code IN ($phCodes)
                  AND (p.tenant_id IS NULL OR p.tenant_id = ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($roleIds, $codes, [$tenantId]));
        $matched = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return ['allowed' => $matched !== [], 'matched_codes' => $matched];
    }

    /** @return array{allowed: bool, reason: string, winner_rule_id?: int} */
    private function resolveAbac(array $user, string $resource, string $action): array
    {
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $roleIds = $this->userRoleIds($userId, $tenantId);

        $stmt = $this->pdo->prepare(
            'SELECT ar.* FROM access_rules ar
             WHERE ar.tenant_id = ? AND ar.is_active = 1
               AND ((ar.target_type = "USER" AND ar.target_id = ?) OR (ar.target_type = "ROLE" AND ar.target_id IN (' . ($roleIds !== [] ? implode(',', array_fill(0, count($roleIds), '?')) : '0') . ')))
             ORDER BY ar.priority DESC, ar.id DESC'
        );
        $params = array_merge([$tenantId, $userId], $roleIds);
        $stmt->execute($params);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rules === []) {
            return ['allowed' => true, 'reason' => 'rbac_only_no_abac_rule'];
        }

        $winner = null;
        foreach ($rules as $rule) {
            if (!$this->ruleTargetsScope((int) $rule['id'], $resource, $action)) {
                continue;
            }
            $value = json_decode((string) ($rule['condition_value'] ?? '{}'), true);
            if (!is_array($value)) {
                $value = [];
            }
            if (!$this->evaluateCondition((string) ($rule['condition_type'] ?? ''), $user, $value)) {
                continue;
            }
            if (strtoupper((string) ($rule['effect'] ?? 'DENY')) === 'DENY') {
                return ['allowed' => false, 'reason' => 'abac_deny_rule', 'winner_rule_id' => (int) $rule['id']];
            }
            if ($winner === null) {
                $winner = (int) $rule['id'];
            }
        }

        if ($winner !== null) {
            return ['allowed' => true, 'reason' => 'abac_allow_rule', 'winner_rule_id' => $winner];
        }

        return ['allowed' => false, 'reason' => 'abac_deny_by_default'];
    }

    private function ruleTargetsScope(int $ruleId, string $resource, string $action): bool
    {
        $action = strtoupper($action);
        $module = explode(':', $resource, 2)[0];

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM access_scopes WHERE rule_id = ? AND action = ? AND (scope_identifier = ? OR scope_identifier = ? OR scope_identifier = "*") LIMIT 1'
        );
        $stmt->execute([$ruleId, $action, $resource, $module]);

        return (bool) $stmt->fetchColumn();
    }

    private function evaluateCondition(string $conditionType, array $user, array $conditionValue): bool
    {
        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($conditionType)) {
                return $evaluator->evaluate($user, $conditionValue);
            }
        }

        return false;
    }

    /** @return list<int> */
    private function userRoleIds(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT role_id FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ?');
        $stmt->execute([$userId, $tenantId]);

        return array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), static fn (int $id): bool => $id > 0));
    }

    /** @return list<string> */
    private function resourceCodes(string $resource, string $action): array
    {
        $action = strtolower($action);
        $module = strtolower(explode(':', $resource, 2)[0]);

        return array_values(array_unique([
            $resource . '.' . $action,
            $module . '.' . $action,
            $module . '.manage',
            'admin.access',
            'admin.access.manage',
        ]));
    }

    /** @param array<string,mixed> $decision @param array<string,mixed> $context */
    private function log(int $tenantId, int $userId, string $resource, string $action, array $decision, array $context): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO access_logs (tenant_id, user_id, resource, action, decision, reason, context_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                $userId,
                $resource,
                strtoupper($action),
                !empty($decision['allowed']) ? 'ALLOW' : 'DENY',
                (string) ($decision['reason'] ?? ''),
                json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable) {
        }
    }
}
