<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SubscriptionPlanRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function allOrdered(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
