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
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_grade_format' LIMIT 1");
        $hasGradeColumns = $stmt && $stmt->fetch();

        if ($hasGradeColumns) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, nationality_code, preferred_grade_format, professional_category_code, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
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
                $data['nationality_code'] ?? null,
                $data['preferred_grade_format'] ?? 'classic',
                $data['professional_category_code'] ?? null,
            ]);
        } else {
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
        }
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

    /** Liste avec filtres optionnels (recherche, statut, rôle). */
    public function listForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null): array
    {
        $sql = 'SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.tenant_id = ?';
        $params = [$tenantId];
        if ($search !== null && $search !== '') {
            $term = '%' . trim($search) . '%';
            $sql .= ' AND (u.email LIKE ? OR u.display_name LIKE ? OR u.callsign LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($status !== null && $status !== '') {
            $sql .= ' AND u.status = ?';
            $params[] = $status;
        }
        if ($roleId !== null && $roleId > 0) {
            $sql .= ' AND u.role_id = ?';
            $params[] = $roleId;
        }
        $sql .= ' ORDER BY u.email ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $userId, int $tenantId, array $data): bool
    {
        $allowed = ['email', 'password_hash', 'display_name', 'callsign', 'avatar_url', 'steam_id', 'role_id', 'grade_id', 'status', 'nationality_code', 'preferred_grade_format', 'professional_category_code'];
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

    /** Recherche utilisateurs pour @mention (display_name, callsign). */
    public function searchForMention(int $tenantId, string $query, int $limit = 10): array
    {
        $term = '%' . trim($query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, display_name, callsign FROM users WHERE tenant_id = ? AND (display_name LIKE ? OR callsign LIKE ?) ORDER BY display_name ASC LIMIT ?'
        );
        $stmt->execute([$tenantId, $term, $term, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<int> User IDs ayant le rôle donné (pour assignation formation par rôle). */
    public function getIdsByRole(int $tenantId, int $roleId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND role_id = ?');
        $stmt->execute([$tenantId, $roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> User IDs appartenant à l'unité (user_units, affectation non terminée). */
    public function getIdsByUnit(int $unitId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM user_units WHERE unit_id = ? AND (ended_at IS NULL OR ended_at > NOW())'
        );
        $stmt->execute([$unitId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> Unit IDs auxquelles l'utilisateur est affecté (user_units, non terminée). */
    public function getUnitIdsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT unit_id FROM user_units WHERE user_id = ? AND (ended_at IS NULL OR ended_at > NOW())'
        );
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return string|null Role slug de l'utilisateur (via users.role_id -> roles.slug). */
    public function getRoleSlugForUser(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_COLUMN);
        return $row !== false ? (string) $row : null;
    }

    /** Nombre d'utilisateurs ayant le rôle donné (pour garde-fou dernier super-admin). */
    public function countUsersWithRole(int $roleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
        $stmt->execute([$roleId]);
        return (int) $stmt->fetchColumn();
    }
}
