<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelAdminPanelRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, name: string, slug: string, description: ?string, display_order: int}> */
    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, description, display_order FROM personnel_admin_panels WHERE tenant_id = ? ORDER BY display_order ASC, id ASC'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['display_order'] = (int) ($r['display_order'] ?? 0);
            return $r;
        }, $rows);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM personnel_admin_panels WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $tenantId, string $name, string $slug, ?string $description = null, int $displayOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_admin_panels (tenant_id, name, slug, description, display_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $name, $slug, $description, $displayOrder]);
        return (int) $this->pdo->lastInsertId();
    }
}
