<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumBlacklistedDomainRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, tenant_id, domain, created_at FROM forum_blacklisted_domains WHERE tenant_id = ? ORDER BY domain ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $tenantId, string $domain): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO forum_blacklisted_domains (tenant_id, domain, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$tenantId, $domain]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM forum_blacklisted_domains WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}
