<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class LoginAttemptRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function record(string $email, string $ip, bool $success): void
    {
        $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([strtolower(trim($email)), $ip, $success ? 1 : 0]);
    }

    public function countRecentFailuresForEmailAndIp(string $email, string $ip, int $windowSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip = ? AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([strtolower(trim($email)), $ip, $windowSeconds]);

        return (int) $stmt->fetchColumn();
    }
}
