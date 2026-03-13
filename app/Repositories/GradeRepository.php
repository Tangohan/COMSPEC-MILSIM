<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class GradeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, name: string, short_name: string, nato_code: ?string, rank_order: int}> */
    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, short_name, nato_code, rank_order FROM grades WHERE tenant_id = ? ORDER BY rank_order ASC, id ASC'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['rank_order'] = (int) ($r['rank_order'] ?? 0);
            return $r;
        }, $rows);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM grades WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateNatoCode(int $id, int $tenantId, ?string $natoCode): bool
    {
        $stmt = $this->pdo->prepare('UPDATE grades SET nato_code = ? WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$natoCode ?: null, $id, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}
