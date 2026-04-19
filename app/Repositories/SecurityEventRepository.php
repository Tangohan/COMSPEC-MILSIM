<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SecurityEventRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT se.*, u.email AS user_email, t.name AS tenant_name
             FROM security_events se
             LEFT JOIN users u ON u.id = se.user_id
             LEFT JOIN tenants t ON t.id = se.tenant_id
             ORDER BY se.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
