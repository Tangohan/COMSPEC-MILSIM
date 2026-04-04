<?php

declare(strict_types=1);

namespace App\Repositories\Courrier;

use App\Core\Database;
use PDO;

class UserSignatureRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listByUser(int $userId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_signatures WHERE user_id = ? AND tenant_id = ? ORDER BY is_default DESC, created_at DESC');
        $stmt->execute([$userId, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $userId = null, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM user_signatures WHERE id = ?';
        $params = [$id];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $userId, int $tenantId, string $name, string $filePath, bool $isDefault = false): int
    {
        if ($isDefault) {
            $this->pdo->prepare('UPDATE user_signatures SET is_default = 0 WHERE user_id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        }
        $stmt = $this->pdo->prepare('INSERT INTO user_signatures (user_id, tenant_id, name, file_path, is_default, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $tenantId, $name, $filePath, $isDefault ? 1 : 0]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setDefault(int $id, int $userId, int $tenantId): void
    {
        $this->pdo->prepare('UPDATE user_signatures SET is_default = 0 WHERE user_id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        $this->pdo->prepare('UPDATE user_signatures SET is_default = 1 WHERE id = ? AND user_id = ? AND tenant_id = ?')->execute([$id, $userId, $tenantId]);
    }

    public function delete(int $id, ?int $userId = null): bool
    {
        $sql = 'DELETE FROM user_signatures WHERE id = ?';
        $params = [$id];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}
