<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DemoNdaVisitRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demo_nda_visits' LIMIT 1");

            return (bool) ($st && $st->fetch());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIp(string $ip): ?array
    {
        if (!$this->tableExists() || $ip === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM demo_nda_visits WHERE ip_address = ? LIMIT 1');
        $stmt->execute([$ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createPending(string $ip, string $firstSeenAt, string $claimExpiresAt, ?string $userAgent): array
    {
        $ua = $userAgent !== null ? mb_substr($userAgent, 0, 512) : null;
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO demo_nda_visits (ip_address, first_seen_at, claim_expires_at, status, user_agent)
                 VALUES (?, ?, ?, \'pending\', ?)'
            );
            $stmt->execute([$ip, $firstSeenAt, $claimExpiresAt, $ua]);
        } catch (\PDOException) {
            // Concurrent first hit — reuse the existing row
        }

        $row = $this->findByIp($ip);
        if ($row === null) {
            throw new \RuntimeException('Impossible d’enregistrer la visite de démonstration.');
        }

        return $row;
    }

    public function markGranted(int $id, string $grantedAt, string $sessionExpiresAt, string $tokenHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demo_nda_visits
             SET status = \'granted\', granted_at = ?, session_expires_at = ?, access_token_hash = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$grantedAt, $sessionExpiresAt, $tokenHash, $id]);
    }

    public function markExpired(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demo_nda_visits
             SET status = \'expired\', access_token_hash = NULL, updated_at = NOW()
             WHERE id = ? AND status <> \'expired\''
        );
        $stmt->execute([$id]);
    }

    public function resetToPending(int $id, string $firstSeenAt, string $claimExpiresAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE demo_nda_visits
             SET status = \'pending\',
                 first_seen_at = ?,
                 claim_expires_at = ?,
                 granted_at = NULL,
                 session_expires_at = NULL,
                 access_token_hash = NULL,
                 updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$firstSeenAt, $claimExpiresAt, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 80): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->query(
            'SELECT * FROM demo_nda_visits ORDER BY first_seen_at DESC LIMIT ' . $limit
        );
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!$this->tableExists() || $id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM demo_nda_visits WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
