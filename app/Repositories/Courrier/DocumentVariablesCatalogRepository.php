<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class DocumentVariablesCatalogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listActive(?int $tenantId = null, ?string $category = null): array
    {
        $sql = 'SELECT * FROM document_variables_catalog WHERE is_active = 1 AND (tenant_id IS NULL OR tenant_id = ?)';
        $params = [$tenantId ?? 0];
        if ($category !== null && $category !== '') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        $sql .= ' ORDER BY category ASC, code ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCode(string $code, ?int $tenantId = null): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_variables_catalog WHERE code = ? AND (tenant_id IS NULL OR tenant_id = ?) AND is_active = 1 LIMIT 1');
        $stmt->execute([$code, $tenantId ?? 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getGroupedByCategory(?int $tenantId = null): array
    {
        $rows = $this->listActive($tenantId);
        $grouped = [];
        foreach ($rows as $row) {
            $cat = $row['category'] ?? 'other';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $row;
        }
        return $grouped;
    }
}
