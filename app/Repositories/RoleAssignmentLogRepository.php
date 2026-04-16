<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RoleAssignmentLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_assignments_log' LIMIT 1");
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    public function logAssign(int $tenantId, int $userId, int $roleId, ?int $assignedBy, ?string $reason = null): void
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1 || $roleId < 1) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO role_assignments_log (tenant_id, user_id, role_id, action, assigned_by, assigned_at, reason)
             VALUES (?, ?, ?, \'assign\', ?, NOW(), ?)'
        );
        $st->execute([$tenantId, $userId, $roleId, $assignedBy ?: null, $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null]);
    }

    public function logRevoke(int $tenantId, int $userId, int $roleId, ?int $assignedBy, ?string $reason = null): void
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1 || $roleId < 1) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO role_assignments_log (tenant_id, user_id, role_id, action, assigned_by, assigned_at, reason)
             VALUES (?, ?, ?, \'revoke\', ?, NOW(), ?)'
        );
        $st->execute([$tenantId, $userId, $roleId, $assignedBy ?: null, $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null]);
    }

    public function isTableReady(): bool
    {
        return $this->tableExists();
    }

    /**
     * Plus ancienne date d’attribution (jour) parmi les rôles indiqués.
     *
     * @param list<int> $roleIds
     */
    public function earliestAssignDateYmdForRoles(int $tenantId, int $userId, array $roleIds): ?string
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1 || $roleIds === []) {
            return null;
        }
        $roleIds = array_values(array_unique(array_filter(array_map(static fn ($v): int => (int) $v, $roleIds), static fn (int $id): bool => $id > 0)));
        if ($roleIds === []) {
            return null;
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $params = array_merge([$tenantId, $userId], $roleIds);
        $st = $this->pdo->prepare(
            "SELECT DATE(MIN(assigned_at)) AS d
             FROM role_assignments_log
             WHERE tenant_id = ? AND user_id = ? AND action = 'assign'
               AND role_id IN ({$ph})"
        );
        $st->execute($params);
        $v = $st->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' ? $s : null;
    }
}
