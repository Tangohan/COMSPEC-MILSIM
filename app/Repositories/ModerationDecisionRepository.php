<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ModerationDecisionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function insert(int $artifactId, ?int $actorUserId, string $action, ?string $reasonCode, ?string $note): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO moderation_decisions (artifact_id, actor_user_id, action, reason_code, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$artifactId, $actorUserId, $action, $reasonCode, $note]);

        return (int) $this->pdo->lastInsertId();
    }
}
