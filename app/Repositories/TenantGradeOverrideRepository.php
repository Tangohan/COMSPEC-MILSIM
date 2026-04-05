<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantGradeOverrideRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_grade_overrides' LIMIT 1");

        return $stmt && (bool) $stmt->fetchColumn();
    }

    /** @param list<array{grade_id: int, label_short: ?string, label_long: ?string, sort_order: ?int, is_enabled: bool}> $rows */
    public function replaceForTenant(int $tenantId, array $rows): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $del = $this->pdo->prepare('DELETE FROM tenant_grade_overrides WHERE tenant_id = ?');
        $del->execute([$tenantId]);
        if ($rows === []) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO tenant_grade_overrides (tenant_id, grade_id, label_short_override, label_long_override, sort_order_override, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        foreach ($rows as $r) {
            $ins->execute([
                $tenantId,
                $r['grade_id'],
                $r['label_short'] !== null && $r['label_short'] !== '' ? $r['label_short'] : null,
                $r['label_long'] !== null && $r['label_long'] !== '' ? $r['label_long'] : null,
                $r['sort_order'] !== null ? $r['sort_order'] : null,
                $r['is_enabled'] ? 1 : 0,
            ]);
        }
    }
}
