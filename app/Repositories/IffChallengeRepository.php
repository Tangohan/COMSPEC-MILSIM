<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class IffChallengeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getCurrentForMission(string $missionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM iff_challenges WHERE mission_id = ? AND valid_until > NOW() ORDER BY valid_until DESC LIMIT 1');
        $stmt->execute([$missionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $missionId, string $code, string $validFrom, string $validUntil): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO iff_challenges (mission_id, code, valid_from, valid_until) VALUES (?, ?, ?, ?)');
        $stmt->execute([$missionId, $code, $validFrom, $validUntil]);
        return (int) $this->pdo->lastInsertId();
    }
}
