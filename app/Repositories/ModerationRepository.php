<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ModerationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
        require_once dirname(__DIR__, 2) . '/bootstrap/platform_unit_commander_migration.php';
        ensure_platform_unit_commander_schema($this->pdo);
        require_once dirname(__DIR__, 2) . '/bootstrap/moderation_granular_sanctions_migration.php';
        ensure_moderation_granular_sanctions_schema($this->pdo);
    }

    public function hasActiveAccessBlock(int $tenantId, int $userId): bool
    {
        $resolver = new \App\Services\Moderation\ModerationRestrictionResolver($this);

        return $resolver->isAccountLocked($tenantId, $userId);
    }

    /** @return list<array<string, mixed>> */
    public function listActiveActionsWithRestrictions(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT action_type, restrictions_json FROM moderation_actions
             WHERE tenant_id = ? AND target_user_id = ? AND revoked_at IS NULL
             AND action_type IN ('mute','suspend','ban')
             AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentActions(int $tenantId, int $limit = 100, ?string $sanctionScope = null): array
    {
        $lim = max(1, min(500, $limit));
        $scopeSql = '';
        $params = [$tenantId];
        if ($sanctionScope === 'tenant' && $this->hasSanctionScopeColumn()) {
            $scopeSql = " AND (ma.sanction_scope = 'tenant' OR ma.sanction_scope IS NULL OR ma.sanction_scope = '')";
        } elseif ($sanctionScope === 'platform' && $this->hasSanctionScopeColumn()) {
            $scopeSql = " AND ma.sanction_scope = 'platform'";
        }
        $stmt = $this->pdo->prepare(
            'SELECT ma.*, u.email AS target_email, a.email AS actor_email
             FROM moderation_actions ma
             INNER JOIN users u ON u.id = ma.target_user_id AND u.tenant_id = ma.tenant_id
             INNER JOIN users a ON a.id = ma.actor_user_id AND a.tenant_id = ma.tenant_id
             WHERE ma.tenant_id = ?' . $scopeSql . '
             ORDER BY ma.created_at DESC
             LIMIT ' . $lim
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createCase(int $tenantId, int $subjectUserId, int $openedByUserId, string $priority = 'normal'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO moderation_cases (tenant_id, subject_user_id, opened_by_user_id, status, priority, created_at) VALUES (?, ?, ?, \'open\', ?, NOW())'
        );
        $stmt->execute([$tenantId, $subjectUserId, $openedByUserId, $priority]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createAction(
        ?int $caseId,
        int $tenantId,
        int $targetUserId,
        int $actorUserId,
        string $actionType,
        ?string $reason,
        ?\DateTimeInterface $expiresAt,
        ?string $restrictionsJson = null,
        string $sanctionScope = 'tenant'
    ): int {
        $hasJson = $this->hasRestrictionsJsonColumn();
        $hasScope = $this->hasSanctionScopeColumn();
        $exp = $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null;
        if ($hasJson && $hasScope) {
            $scope = $sanctionScope === 'platform' ? 'platform' : 'tenant';
            $stmt = $this->pdo->prepare(
                'INSERT INTO moderation_actions (case_id, tenant_id, target_user_id, actor_user_id, action_type, reason, restrictions_json, sanction_scope, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $caseId,
                $tenantId,
                $targetUserId,
                $actorUserId,
                $actionType,
                $reason,
                $restrictionsJson,
                $scope,
                $exp,
            ]);
        } elseif ($hasJson) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO moderation_actions (case_id, tenant_id, target_user_id, actor_user_id, action_type, reason, restrictions_json, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $caseId,
                $tenantId,
                $targetUserId,
                $actorUserId,
                $actionType,
                $reason,
                $restrictionsJson,
                $exp,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO moderation_actions (case_id, tenant_id, target_user_id, actor_user_id, action_type, reason, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $caseId,
                $tenantId,
                $targetUserId,
                $actorUserId,
                $actionType,
                $reason,
                $exp,
            ]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function hasRestrictionsJsonColumn(): bool
    {
        static $v;
        if ($v !== null) {
            return $v;
        }
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_actions' AND COLUMN_NAME = 'restrictions_json' LIMIT 1");
            $v = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $v = false;
        }

        return $v;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActionById(int $tenantId, int $actionId): ?array
    {
        if ($tenantId < 1 || $actionId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM moderation_actions WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$actionId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function revokeAction(int $tenantId, int $actionId, int $revokedByUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE moderation_actions SET revoked_at = NOW(), revoked_by_user_id = ? WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$revokedByUserId, $actionId, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Révoque une action uniquement si son périmètre correspond (évite que l’org lève une sanction « site »).
     */
    public function revokeActionForScope(int $tenantId, int $actionId, int $revokedByUserId, string $expectedScope): bool
    {
        if (!$this->hasSanctionScopeColumn()) {
            return $expectedScope === 'tenant' && $this->revokeAction($tenantId, $actionId, $revokedByUserId);
        }
        $scope = $expectedScope === 'platform' ? 'platform' : 'tenant';
        if ($scope === 'tenant') {
            $stmt = $this->pdo->prepare(
                "UPDATE moderation_actions SET revoked_at = NOW(), revoked_by_user_id = ?
                 WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL
                 AND (sanction_scope = 'tenant' OR sanction_scope IS NULL OR sanction_scope = '')"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "UPDATE moderation_actions SET revoked_at = NOW(), revoked_by_user_id = ?
                 WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL AND sanction_scope = 'platform'"
            );
        }
        $stmt->execute([$revokedByUserId, $actionId, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    private function hasSanctionScopeColumn(): bool
    {
        static $v;
        if ($v !== null) {
            return $v;
        }
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_actions' AND COLUMN_NAME = 'sanction_scope' LIMIT 1");
            $v = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $v = false;
        }

        return $v;
    }
}
