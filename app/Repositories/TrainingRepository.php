<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listPublishedForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_modules WHERE tenant_id = ? AND status = ? ORDER BY title ASC'
        );
        $stmt->execute([$tenantId, 'published']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlug(string $slug, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM training_modules WHERE slug = ? AND status = ?';
        $params = [$slug, 'published'];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
