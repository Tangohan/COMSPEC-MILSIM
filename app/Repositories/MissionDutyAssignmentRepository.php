<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MissionDutyAssignmentRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['mission_duty_assignments']);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO mission_duty_assignments
                (tenant_id, operation_id, mission_label, user_id, organic_billet_id, duty_billet_id,
                 duty_title, duty_callsign, capability_template, starts_at, ends_at, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            isset($data['operation_id']) ? (int) $data['operation_id'] : null,
            trim((string) ($data['mission_label'] ?? '')),
            (int) ($data['user_id'] ?? 0),
            isset($data['organic_billet_id']) ? (int) $data['organic_billet_id'] : null,
            isset($data['duty_billet_id']) ? (int) $data['duty_billet_id'] : null,
            trim((string) ($data['duty_title'] ?? '')),
            trim((string) ($data['duty_callsign'] ?? '')) ?: null,
            trim((string) ($data['capability_template'] ?? '')) ?: null,
            trim((string) ($data['starts_at'] ?? '')) ?: date('Y-m-d H:i:s'),
            trim((string) ($data['ends_at'] ?? '')) ?: null,
            trim((string) ($data['status'] ?? 'active')) ?: 'active',
            isset($data['created_by']) ? (int) $data['created_by'] : null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveForUser(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM mission_duty_assignments
             WHERE tenant_id = ? AND user_id = ? AND status = \'active\'
               AND starts_at <= NOW()
               AND (ends_at IS NULL OR ends_at > NOW())
             ORDER BY starts_at DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenant(int $tenantId, int $limit = 100): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $st = $this->pdo()->prepare(
            'SELECT * FROM mission_duty_assignments
             WHERE tenant_id = ? AND status = \'active\'
               AND starts_at <= NOW()
               AND (ends_at IS NULL OR ends_at > NOW())
             ORDER BY mission_label ASC, duty_title ASC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function end(int $tenantId, int $assignmentId): bool
    {
        if ($tenantId < 1 || $assignmentId < 1 || !$this->schemaReady()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE mission_duty_assignments
             SET status = \'ended\', ends_at = COALESCE(ends_at, NOW()), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status = \'active\''
        );

        return $st->execute([$tenantId, $assignmentId]) && $st->rowCount() > 0;
    }
}
