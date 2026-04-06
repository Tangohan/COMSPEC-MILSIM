<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RoleSetRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tablesExist(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $a = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_sets' LIMIT 1");
            $b = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_set_roles' LIMIT 1");
            $ok = (bool) $a->fetchColumn() && (bool) $b->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT * FROM role_sets WHERE tenant_id = ? ORDER BY name ASC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<int> */
    public function roleIdsForSet(int $tenantId, int $setId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT rsr.role_id FROM role_set_roles rsr
             INNER JOIN role_sets rs ON rs.id = rsr.role_set_id
             WHERE rs.tenant_id = ? AND rs.id = ?'
        );
        $st->execute([$tenantId, $setId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
