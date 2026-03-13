<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefaultTenant(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM tenants ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
