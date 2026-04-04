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
    }

    public function hasActiveAccessBlock(int $tenantId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM moderation_actions WHERE tenant_id = ? AND target_user_id = ? AND revoked_at IS NULL
             AND action_type IN ('mute','suspend','ban')
             AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1"
        );
        $stmt->execute([$tenantId, $userId]);

        return (bool) $stmt->fetchColumn();
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
        ?\DateTimeInterface $expiresAt
    ): int {
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

        return (int) $this->pdo->lastInsertId();
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
