<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OrganizationVisibilityHistoryRepository
{
    private ?PDO $pdo = null;

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tableExists(string $table = 'organization_visibility_history'): bool
    {
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    public function record(
        int $tenantId,
        string $subjectType,
        int $subjectId,
        string $fieldName,
        ?string $oldValue,
        ?string $newValue,
        ?int $actorUserId = null,
        ?string $reason = null
    ): void {
        if (!$this->tableExists()) {
            return;
        }
        if ($oldValue === $newValue) {
            return;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO organization_visibility_history
                (tenant_id, subject_type, subject_id, field_name, old_value, new_value, reason, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            mb_substr($subjectType, 0, 32),
            $subjectId,
            mb_substr($fieldName, 0, 64),
            $oldValue !== null ? mb_substr($oldValue, 0, 64) : null,
            $newValue !== null ? mb_substr($newValue, 0, 64) : null,
            $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForSubject(int $tenantId, string $subjectType, int $subjectId, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM organization_visibility_history
             WHERE tenant_id = ? AND subject_type = ? AND subject_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId, $subjectType, $subjectId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordUnitStatus(
        int $tenantId,
        int $unitId,
        ?string $oldStatus,
        string $newStatus,
        ?int $actorUserId = null,
        ?string $reason = null,
        ?string $comment = null,
        ?string $effectiveAt = null
    ): void {
        if (!$this->tableExists('unit_admin_status_history')) {
            return;
        }
        if ($oldStatus === $newStatus) {
            return;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO unit_admin_status_history
                (tenant_id, unit_id, old_status, new_status, reason, comment, effective_at, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $unitId,
            $oldStatus,
            $newStatus,
            $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null,
            $comment !== null && $comment !== '' ? $comment : null,
            $effectiveAt !== null && $effectiveAt !== '' ? $effectiveAt : null,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUnitStatusHistory(int $tenantId, int $unitId, int $limit = 50): array
    {
        if (!$this->tableExists('unit_admin_status_history')) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM unit_admin_status_history
             WHERE tenant_id = ? AND unit_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId, $unitId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
