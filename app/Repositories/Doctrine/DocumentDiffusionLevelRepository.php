<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Core\Database;
use PDO;

final class DocumentDiffusionLevelRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM document_diffusion_levels LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_diffusion_levels WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        if (!$this->tableExists() || $id < 1 || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_diffusion_levels WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
