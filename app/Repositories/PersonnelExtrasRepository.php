<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelExtrasRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_extras WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getProfileByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
