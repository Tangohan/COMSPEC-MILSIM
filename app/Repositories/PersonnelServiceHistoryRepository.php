<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelServiceHistoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_service_history WHERE user_id = ? ORDER BY event_date DESC, id DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $userId, string $eventType, string $title, string $description = '', string $eventDate = '', ?int $createdBy = null): int
    {
        $date = $eventDate ?: date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_service_history (user_id, event_type, title, description, event_date, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $eventType, $title, $description, $date, $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }
}
