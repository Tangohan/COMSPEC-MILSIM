<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelQualificationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_qualifications WHERE user_id = ? ORDER BY expires_at IS NULL DESC, expires_at DESC, obtained_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $userId, string $qualificationName, array $options = []): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_qualifications (user_id, qualification_name, level, status, obtained_at, expires_at, issued_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $status = $options['status'] ?? 'valid';
        $obtainedAt = $options['obtained_at'] ?? null;
        $expiresAt = $options['expires_at'] ?? null;
        $issuedBy = $options['issued_by'] ?? null;
        $level = $options['level'] ?? null;
        $stmt->execute([$userId, $qualificationName, $level, $status, $obtainedAt, $expiresAt, $issuedBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE personnel_qualifications SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    /** Prochaine date d'expiration parmi les qualifications avec expires_at. */
    public function getNextExpiration(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(expires_at) FROM personnel_qualifications WHERE user_id = ? AND expires_at IS NOT NULL AND expires_at > CURDATE()'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_COLUMN);
        return $row ? (string) $row : null;
    }
}
