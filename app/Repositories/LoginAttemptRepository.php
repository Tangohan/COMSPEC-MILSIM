<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
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
        $emailEq = SqlText::equals($this->pdo, 'email');
        $ipEq = SqlText::equals($this->pdo, 'ip');
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ' . $emailEq . ' AND ' . $ipEq . ' AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([strtolower(trim($email)), $ip, $windowSeconds]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Adresses réseau d’où cette adresse e-mail s’est connectée avec succès (plus récente d’abord).
     *
     * @return list<array{ip: string, last_seen: string, logins: int}>
     */
    public function listRecentSuccessIpsForEmail(string $email, int $days = 180, int $limit = 10): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return [];
        }
        $days = max(1, min(3650, $days));
        $limit = max(1, min(50, $limit));
        $emailEq = SqlText::equals($this->pdo, 'email');
        $stmt = $this->pdo->prepare(
            'SELECT ip, MAX(created_at) AS last_seen, COUNT(*) AS logins FROM login_attempts
             WHERE ' . $emailEq . ' AND success = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
             GROUP BY ip ORDER BY last_seen DESC LIMIT ' . $limit
        );
        $stmt->execute([$email]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ip = trim((string) ($row['ip'] ?? ''));
            if ($ip === '') {
                continue;
            }
            $out[] = ['ip' => $ip, 'last_seen' => (string) ($row['last_seen'] ?? ''), 'logins' => (int) ($row['logins'] ?? 0)];
        }

        return $out;
    }
}
