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
}
