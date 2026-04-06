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
    public function listRecentActions(int $tenantId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ma.*, u.email AS target_email, a.email AS actor_email
             FROM moderation_actions ma
             INNER JOIN users u ON u.id = ma.target_user_id AND u.tenant_id = ma.tenant_id
             INNER JOIN users a ON a.id = ma.actor_user_id AND a.tenant_id = ma.tenant_id
             WHERE ma.tenant_id = ?
             ORDER BY ma.created_at DESC
             LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        ?string $restrictionsJson = null
    ): int {
        $hasJson = $this->hasRestrictionsJsonColumn();
        if ($hasJson) {
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
                $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
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
                $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
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

    public function revokeAction(int $tenantId, int $actionId, int $revokedByUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE moderation_actions SET revoked_at = NOW(), revoked_by_user_id = ? WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$revokedByUserId, $actionId, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
