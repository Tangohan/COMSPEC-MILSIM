<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId, ?int $categoryId = null): array
    {
        $sql = 'SELECT d.*, dv.file_path, dv.mime_type, dv.size FROM documents d
                LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
                WHERE d.tenant_id = ? AND d.status = ?';
        $params = [$tenantId, 'published'];
        if ($categoryId !== null) {
            $sql .= ' AND d.document_category_id = ?';
            $params[] = $categoryId;
        }
        $sql .= ' ORDER BY d.title ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT d.*, dv.id as version_id, dv.file_path, dv.mime_type, dv.size
                FROM documents d
                LEFT JOIN document_versions dv ON dv.document_id = d.id AND dv.is_current = 1
                WHERE d.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND d.tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
