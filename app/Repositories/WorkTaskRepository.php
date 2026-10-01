<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class WorkTaskRepository
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
            $st->execute(['work_tasks']);

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
            'INSERT INTO work_tasks
                (tenant_id, type, title, description, created_by, assigned_user_id, assigned_billet_id,
                 assigned_unit_id, assigned_position_slug, priority, status, due_at,
                 linked_entity_type, linked_entity_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            trim((string) ($data['type'] ?? 'generic')) ?: 'generic',
            trim((string) ($data['title'] ?? '')),
            trim((string) ($data['description'] ?? '')) ?: null,
            isset($data['created_by']) ? (int) $data['created_by'] : null,
            isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            isset($data['assigned_billet_id']) ? (int) $data['assigned_billet_id'] : null,
            isset($data['assigned_unit_id']) ? (int) $data['assigned_unit_id'] : null,
            trim((string) ($data['assigned_position_slug'] ?? '')) ?: null,
            trim((string) ($data['priority'] ?? 'normal')) ?: 'normal',
            trim((string) ($data['status'] ?? 'open')) ?: 'open',
            trim((string) ($data['due_at'] ?? '')) ?: null,
            trim((string) ($data['linked_entity_type'] ?? '')) ?: null,
            isset($data['linked_entity_id']) ? (int) $data['linked_entity_id'] : null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param list<int> $billetIds
     * @param list<string> $positionSlugs
     * @return list<array<string, mixed>>
     */
    public function listOpenForActor(
        int $tenantId,
        int $userId,
        array $billetIds = [],
        array $positionSlugs = [],
        int $limit = 40,
    ): array {
        if ($tenantId < 1 || $userId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $clauses = ['assigned_user_id = ?'];
        $params = [$tenantId, $userId];

        $billetIds = array_values(array_filter(array_map('intval', $billetIds), static fn (int $id): bool => $id > 0));
        if ($billetIds !== []) {
            $ph = implode(',', array_fill(0, count($billetIds), '?'));
            $clauses[] = 'assigned_billet_id IN (' . $ph . ')';
            foreach ($billetIds as $id) {
                $params[] = $id;
            }
        }

        $positionSlugs = array_values(array_filter(array_map(
            static fn ($s): string => strtolower(trim((string) $s)),
            $positionSlugs
        ), static fn (string $s): bool => $s !== ''));
        if ($positionSlugs !== []) {
            $ph = implode(',', array_fill(0, count($positionSlugs), '?'));
            $clauses[] = 'assigned_position_slug IN (' . $ph . ')';
            foreach ($positionSlugs as $slug) {
                $params[] = $slug;
            }
        }

        $sql = 'SELECT * FROM work_tasks
                WHERE tenant_id = ?
                  AND status IN (\'open\', \'acknowledged\', \'in_progress\')
                  AND (' . implode(' OR ', $clauses) . ')
                ORDER BY
                  FIELD(priority, \'critical\', \'high\', \'normal\', \'low\'),
                  CASE WHEN due_at IS NULL THEN 1 ELSE 0 END,
                  due_at ASC,
                  id ASC
                LIMIT ' . $limit;
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $tenantId, int $taskId, string $status, int $actorUserId = 0): bool
    {
        if ($tenantId < 1 || $taskId < 1 || !$this->schemaReady()) {
            return false;
        }
        $status = strtolower(trim($status));
        $allowed = ['open', 'acknowledged', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $sets = ['status = ?', 'updated_at = NOW()'];
        $params = [$status];
        if ($status === 'acknowledged') {
            $sets[] = 'acknowledged_at = COALESCE(acknowledged_at, NOW())';
        }
        if ($status === 'in_progress') {
            $sets[] = 'started_at = COALESCE(started_at, NOW())';
            $sets[] = 'acknowledged_at = COALESCE(acknowledged_at, NOW())';
        }
        if ($status === 'completed') {
            $sets[] = 'completed_at = NOW()';
            $sets[] = 'started_at = COALESCE(started_at, NOW())';
        }
        $params[] = $tenantId;
        $params[] = $taskId;
        $st = $this->pdo()->prepare(
            'UPDATE work_tasks SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute($params) && $st->rowCount() > 0;
    }
}
