<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UnitRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM units WHERE tenant_id = ? ORDER BY display_order ASC, name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM units WHERE id = ?';
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

    public function getTree(int $tenantId): array
    {
        $all = $this->allForTenant($tenantId);
        $byParent = [];
        foreach ($all as $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $byParent[$pid][] = $u;
        }
        return $this->buildTree($byParent, 0);
    }

    private function buildTree(array $byParent, int $parentId): array
    {
        $out = [];
        foreach ($byParent[$parentId] ?? [] as $u) {
            $u['children'] = $this->buildTree($byParent, (int) $u['id']);
            $out[] = $u;
        }
        return $out;
    }
}
