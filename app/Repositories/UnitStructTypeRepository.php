<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Types d'entité ajoutés par une communauté (table tenant_unit_struct_types). */
final class UnitStructTypeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['tenant_unit_struct_types']);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array{slug: string, label: string}> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT slug, label FROM tenant_unit_struct_types WHERE tenant_id = ? ORDER BY label ASC'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = ['slug' => (string) $row['slug'], 'label' => (string) $row['label']];
        }

        return $out;
    }

    public function exists(int $tenantId, string $slug): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM tenant_unit_struct_types WHERE tenant_id = ? AND slug = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(int $tenantId, string $slug, string $label): bool
    {
        if (!$this->tableExists() || $slug === '' || $label === '') {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tenant_unit_struct_types (tenant_id, slug, label, created_at) VALUES (?, ?, ?, NOW())'
            );

            return $stmt->execute([$tenantId, $slug, mb_substr($label, 0, 120)]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function rename(int $tenantId, string $slug, string $label): bool
    {
        if (!$this->tableExists() || $label === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE tenant_unit_struct_types SET label = ? WHERE tenant_id = ? AND slug = ?');
        $stmt->execute([mb_substr($label, 0, 120), $tenantId, $slug]);

        return $this->exists($tenantId, $slug);
    }

    public function delete(int $tenantId, string $slug): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM tenant_unit_struct_types WHERE tenant_id = ? AND slug = ?');
        $stmt->execute([$tenantId, $slug]);

        return $stmt->rowCount() > 0;
    }

    /** Nombre d'unités de la communauté qui portent ce type. */
    public function countUnitsUsing(int $tenantId, string $slug): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND type = ?');
        $stmt->execute([$tenantId, $slug]);

        return (int) $stmt->fetchColumn();
    }
}
