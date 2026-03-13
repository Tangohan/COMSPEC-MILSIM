<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findByEmail(int $tenantId, string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE tenant_id = ? AND email = ? LIMIT 1');
        $stmt->execute([$tenantId, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['email'],
            $data['password_hash'],
            $data['display_name'] ?? null,
            $data['callsign'] ?? null,
            $data['role_id'] ?? null,
            $data['grade_id'] ?? null,
            $data['status'] ?? 'pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.tenant_id = ? ORDER BY u.email ASC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $userId, int $tenantId, array $data): bool
    {
        $allowed = ['email', 'password_hash', 'display_name', 'callsign', 'avatar_url'];
        $set = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $set[] = "`$key` = ?";
                $params[] = $data[$key];
            }
        }
        if (empty($set)) {
            return true;
        }
        $params[] = $userId;
        $params[] = $tenantId;
        $sql = 'UPDATE users SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /** Vérifie si un autre utilisateur (hors userId) a déjà cet email dans le tenant. */
    public function emailExistsInTenant(int $tenantId, string $email, ?int $excludeUserId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE tenant_id = ? AND email = ?';
        $params = [$tenantId, $email];
        if ($excludeUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }
}
