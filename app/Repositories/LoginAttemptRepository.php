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

    /** @return array{sources: list<array<string, mixed>>, targets: list<array<string, mixed>>, successful: list<array<string, mixed>>} */
    public function securitySignals(): array
    {
        $sources = $this->pdo->query(
            "SELECT ip, COUNT(*) AS failures, COUNT(DISTINCT NULLIF(email, '')) AS targets,
                    MAX(created_at) AS last_seen
             FROM login_attempts
             WHERE success = 0 AND ip NOT IN ('', '0.0.0.0', '::') AND created_at >= NOW() - INTERVAL 15 MINUTE
             GROUP BY ip
             HAVING COUNT(*) >= 10
             ORDER BY failures DESC, last_seen DESC, ip
             LIMIT 20"
        );
        $targets = $this->pdo->query(
            "SELECT email, COUNT(*) AS failures, COUNT(DISTINCT ip) AS sources,
                    MAX(created_at) AS last_seen
             FROM login_attempts
             WHERE success = 0 AND email <> '' AND ip NOT IN ('', '0.0.0.0', '::')
               AND created_at >= NOW() - INTERVAL 24 HOUR
             GROUP BY email
             HAVING COUNT(*) >= 5 AND COUNT(DISTINCT ip) >= 3
             ORDER BY failures DESC, last_seen DESC, email
             LIMIT 20"
        );

        $successful = $this->pdo->query(
            "SELECT email, COUNT(*) AS logins, COUNT(DISTINCT ip) AS sources,
                    MAX(created_at) AS last_seen
             FROM login_attempts
             WHERE success = 1 AND email <> '' AND ip NOT IN ('', '0.0.0.0', '::')
               AND created_at >= NOW() - INTERVAL 1 HOUR
             GROUP BY email
             HAVING COUNT(DISTINCT ip) >= 3
             ORDER BY sources DESC, last_seen DESC, email
             LIMIT 20"
        );

        return [
            'sources' => $sources->fetchAll(PDO::FETCH_ASSOC),
            'targets' => $targets->fetchAll(PDO::FETCH_ASSOC),
            'successful' => $successful->fetchAll(PDO::FETCH_ASSOC),
        ];
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
