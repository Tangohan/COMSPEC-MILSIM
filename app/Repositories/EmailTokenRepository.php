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

    public function deleteById(int $id): void
    {
        $this->pdo->prepare('DELETE FROM email_tokens WHERE id = ?')->execute([$id]);
    }

    public function deleteOtherPendingForUserPurpose(int $userId, string $purpose, int $keepId): void
    {
        $this->pdo->prepare(
            'DELETE FROM email_tokens WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL AND id <> ?'
        )->execute([$userId, $purpose, $keepId]);
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

    /**
     * Jetons encore valides (non consommés, non expirés) d'un utilisateur pour un usage, du plus récent au plus ancien.
     *
     * @return list<array<string, mixed>>
     */
    public function findPendingForUserPurpose(int $userId, string $purpose): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM email_tokens
             WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC'
        );
        $stmt->execute([$userId, $purpose]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Ne garde que les `$keep` jetons en attente les plus récents pour cet usage. */
    public function deleteOlderPendingBeyond(int $userId, string $purpose, int $keep): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM email_tokens WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC'
        );
        $stmt->execute([$userId, $purpose]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach (array_slice($ids, max(0, $keep)) as $id) {
            $this->deleteById($id);
        }
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

    /**
     * Jeton quel que soit son état (consommé, expiré) : permet d'expliquer au membre pourquoi
     * le lien ne marche plus au lieu d'un « lien invalide » générique.
     *
     * @return array<string, mixed>|null
     */
    public function findAnyByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_tokens WHERE token_hash = ? LIMIT 1');
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markConsumed(int $id): void
    {
        $this->pdo->prepare('UPDATE email_tokens SET consumed_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /**
     * Dernier jeton encore valide (non consommé, non expiré) pour un utilisateur et un usage.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestPendingForUserPurpose(int $userId, string $purpose): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at, expires_at, metadata FROM email_tokens
             WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Dernier envoi (tout jeton, même consommé) pour le délai entre renvois. */
    public function getLatestTokenCreatedAtForUserPurpose(int $userId, string $purpose): ?\DateTimeImmutable
    {
        $stmt = $this->pdo->prepare(
            'SELECT created_at FROM email_tokens WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['created_at'])) {
            return null;
        }

        return new \DateTimeImmutable((string) $row['created_at']);
    }
}
