<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PasswordResetRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(int $userId, string $tokenHash, \DateTimeInterface $expiresAt): void
    {
        $this->pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')->execute([
            $userId,
            $tokenHash,
            $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findValidByToken(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function deleteByToken(string $tokenHash): void
    {
        $this->pdo->prepare('DELETE FROM password_resets WHERE token_hash = ?')->execute([$tokenHash]);
    }

    public function deleteForUser(int $userId): void
    {
        if ($userId < 1) {
            return;
        }
        $this->pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    }

    public function deleteExpired(): void
    {
        $this->pdo->exec('DELETE FROM password_resets WHERE expires_at <= NOW()');
    }
}
