<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class EmailTokenRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function deletePendingForUserPurpose(int $userId, string $purpose): void
    {
        $this->pdo->prepare(
            'DELETE FROM email_tokens WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL'
        )->execute([$userId, $purpose]);
    }

    public function create(
        int $tenantId,
        int $userId,
        string $purpose,
        string $tokenHash,
        string $nonce,
        \DateTimeInterface $expiresAt,
        ?array $metadata = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_tokens (tenant_id, user_id, purpose, token_hash, nonce, expires_at, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $purpose,
            $tokenHash,
            $nonce,
            $expiresAt->format('Y-m-d H:i:s'),
            $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findValidByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM email_tokens WHERE token_hash = ? AND consumed_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markConsumed(int $id): void
    {
        $this->pdo->prepare('UPDATE email_tokens SET consumed_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
