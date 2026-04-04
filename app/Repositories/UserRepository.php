<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\User\UserProfileSlugService;
use PDO;

class UserRepository
{
    private PDO $pdo;

    private static ?bool $hasProfileSlugColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasProfileSlugColumn(): bool
    {
        if (self::$hasProfileSlugColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_slug' LIMIT 1");
            self::$hasProfileSlugColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasProfileSlugColumn;
    }

    public function findByProfileSlug(int $tenantId, string $slug): ?array
    {
        if (!$this->hasProfileSlugColumn()) {
            return null;
        }
        $slug = strtolower(trim($slug));
        if ($slug === '' || !UserProfileSlugService::isValidFormat($slug)) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE tenant_id = ? AND LOWER(profile_slug) = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function isProfileSlugTaken(int $tenantId, string $slug, ?int $exceptUserId = null): bool
    {
        if (!$this->hasProfileSlugColumn()) {
            return false;
        }
        $slug = strtolower(trim($slug));
        $sql = 'SELECT 1 FROM users WHERE tenant_id = ? AND LOWER(profile_slug) = ?';
        $params = [$tenantId, $slug];
        if ($exceptUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptUserId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
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

        $profileSlug = null;
        if ($this->hasProfileSlugColumn()) {
            if (isset($data['profile_slug']) && $data['profile_slug'] !== null && trim((string) $data['profile_slug']) !== '') {
                $profileSlug = strtolower(trim((string) $data['profile_slug']));
            } else {
                $profileSlug = UserProfileSlugService::generateForNewUser(
                    isset($data['display_name']) ? (string) $data['display_name'] : null,
                    (string) ($data['email'] ?? ''),
                    fn (string $s) => $this->isProfileSlugTaken($tenantId, $s)
                );
            }
        }

        if ($hasGradeColumns) {
            if ($this->hasProfileSlugColumn()) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, profile_slug, role_id, grade_id, status, nationality_code, preferred_grade_format, professional_category_code, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([
                    $tenantId,
                    $data['email'],
                    $data['password_hash'],
                    $data['display_name'] ?? null,
                    $data['callsign'] ?? null,
                    $profileSlug,
                    $data['role_id'] ?? null,
                    $data['grade_id'] ?? null,
                    $data['status'] ?? 'pending',
                    $data['nationality_code'] ?? null,
                    $data['preferred_grade_format'] ?? 'classic',
                    $data['professional_category_code'] ?? null,
                ]);
            } else {
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
            }
        } else {
            if ($this->hasProfileSlugColumn()) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, profile_slug, role_id, grade_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([
                    $tenantId,
                    $data['email'],
                    $data['password_hash'],
                    $data['display_name'] ?? null,
                    $data['callsign'] ?? null,
                    $profileSlug,
                    $data['role_id'] ?? null,
                    $data['grade_id'] ?? null,
                    $data['status'] ?? 'pending',
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
    public function listForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null, ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $params] = $this->buildUserListQuery($tenantId, $search, $status, $roleId);
        $sql .= ' ORDER BY u.email ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(200, (int) $limit)) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countListForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null): int
    {
        [$whereSql, $params] = $this->buildUserListWhere($tenantId, $search, $status, $roleId);
        $sql = 'SELECT COUNT(*) FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE ' . $whereSql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildUserListWhere(int $tenantId, ?string $search, ?string $status, ?int $roleId): array
    {
        $parts = ['u.tenant_id = ?'];
        $params = [$tenantId];
        if ($search !== null && $search !== '') {
            $term = '%' . trim($search) . '%';
            $parts[] = '(u.email LIKE ? OR u.display_name LIKE ? OR u.callsign LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($status !== null && $status !== '') {
            $parts[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($roleId !== null && $roleId > 0) {
            $parts[] = 'u.role_id = ?';
            $params[] = $roleId;
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildUserListQuery(int $tenantId, ?string $search, ?string $status, ?int $roleId): array
    {
        [$whereSql, $params] = $this->buildUserListWhere($tenantId, $search, $status, $roleId);
        $sql = 'SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE ' . $whereSql;

        return [$sql, $params];
    }

    public function update(int $userId, int $tenantId, array $data): bool
    {
        $allowed = ['email', 'password_hash', 'display_name', 'callsign', 'avatar_url', 'steam_id', 'role_id', 'grade_id', 'status', 'nationality_code', 'preferred_grade_format', 'professional_category_code'];
        if ($this->hasProfileSlugColumn()) {
            $allowed[] = 'profile_slug';
        }
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

    /** Utilisateurs actifs pour quotas d'abonnement (plan premium). */
    public function countActiveForTenant(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{id: int, tenant_id: int, name: string, slug: string}>
     */
    public function listTenantsForEmail(string $email): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.tenant_id, t.name, t.slug FROM users u INNER JOIN tenants t ON t.id = u.tenant_id WHERE u.email = ? AND u.status = ? ORDER BY t.name ASC'
        );
        $stmt->execute([$email, 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Comptes actifs pour un email avec détail tenant (connexion sans slug).
     *
     * @return list<array<string,mixed>> lignes users.* + tenant_name, tenant_slug
     */
    public function listActiveUsersWithTenantForEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $stmt = $this->pdo->prepare(
            'SELECT u.*, t.name AS tenant_name, t.slug AS tenant_slug
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE LOWER(TRIM(u.email)) = ? AND u.status = ?
             ORDER BY t.name ASC'
        );
        $stmt->execute([$email, 'active']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findIdByTenantAndEmail(int $tenantId, string $email): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND email = ? LIMIT 1');
        $stmt->execute([$tenantId, $email]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Premier compte trouvé pour cet email (tout tenant), pour rattachement invitation. */
    public function findFirstByEmailGlobal(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Duplique un compte vers un autre tenant (même hash mot de passe) pour rejoindre une nouvelle communauté.
     *
     * @return int Nouvel id utilisateur
     */
    public function cloneUserToTenant(int $sourceUserId, int $newTenantId, int $roleId, int $gradeId): int
    {
        $u = $this->findById($sourceUserId, null);
        if (!$u) {
            throw new \InvalidArgumentException('Utilisateur source introuvable.');
        }
        if ($this->emailExistsInTenant($newTenantId, (string) $u['email'])) {
            throw new \RuntimeException('Cet email est déjà inscrit dans cette communauté.');
        }
        $cloneData = [
            'email' => $u['email'],
            'password_hash' => $u['password_hash'],
            'display_name' => $u['display_name'] ?? null,
            'callsign' => $u['callsign'] ?? null,
            'role_id' => $roleId,
            'grade_id' => $gradeId,
            'status' => 'active',
        ];
        if ($this->hasProfileSlugColumn()) {
            $cloneData['profile_slug'] = UserProfileSlugService::generateForNewUser(
                $u['display_name'] ?? null,
                (string) $u['email'],
                fn (string $s) => $this->isProfileSlugTaken($newTenantId, $s)
            );
        }

        return $this->create($newTenantId, $cloneData);
    }
}
