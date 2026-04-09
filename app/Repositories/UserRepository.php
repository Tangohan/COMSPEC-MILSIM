<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\RoleAssignmentLogRepository;
use App\Services\Rbac\RoleCoherenceValidator;
use App\Services\User\UserProfileSlugService;
use InvalidArgumentException;
use PDO;

class UserRepository
{
    private PDO $pdo;

    private static ?bool $hasProfileSlugColumn = null;

    private static ?bool $hasServiceAccountColumn = null;

    private static ?bool $hasUserUnitsTable = null;

    private static ?bool $hasUserRolesTable = null;

    /** @var array{join: string, grade_short: string, order_grade: string}|null */
    private static ?array $gradesConfigPublicRoster = null;

    /** Email réservé au compte technique par tenant (modération auto, cron, futurs tickets / webhooks). */
    public const SYSTEM_MODERATOR_EMAIL = 'system.moderation@internal.local';

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

    private function hasServiceAccountColumn(): bool
    {
        if (self::$hasServiceAccountColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
            self::$hasServiceAccountColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasServiceAccountColumn;
    }

    private function hasUserUnitsTable(): bool
    {
        if (self::$hasUserUnitsTable === null) {
            try {
                $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_units' LIMIT 1");
                self::$hasUserUnitsTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasUserUnitsTable = false;
            }
        }

        return self::$hasUserUnitsTable;
    }

    private function hasUserRolesTable(): bool
    {
        if (self::$hasUserRolesTable === null) {
            try {
                $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_roles' LIMIT 1");
                self::$hasUserRolesTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasUserRolesTable = false;
            }
        }

        return self::$hasUserRolesTable;
    }

    private static ?bool $hasTenantUserRolesTable = null;

    public function hasTenantUserRolesTable(): bool
    {
        if (self::$hasTenantUserRolesTable === null) {
            try {
                $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");
                self::$hasTenantUserRolesTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasTenantUserRolesTable = false;
            }
        }

        return self::$hasTenantUserRolesTable;
    }

    /**
     * Rôles organisation (communauté / intra) attribués à l’utilisateur.
     *
     * @return list<int>
     */
    public function listOrganizationRoleIdsForUser(int $userId): array
    {
        if ($this->hasTenantUserRolesTable()) {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT tur.role_id FROM tenant_user_roles tur
                 INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = tur.tenant_id
                 WHERE tur.user_id = ? AND tur.org_unit_id IS NULL
                 ORDER BY tur.role_id ASC'
            );
            $stmt->execute([$userId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids !== []) {
                return $ids;
            }
        }
        if (!$this->hasUserRolesTable()) {
            $u = $this->findById($userId, null);

            return $u && !empty($u['role_id']) ? [(int) $u['role_id']] : [];
        }
        $stmt = $this->pdo->prepare('SELECT role_id FROM user_roles WHERE user_id = ? ORDER BY role_id ASC');
        $stmt->execute([$userId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids === []) {
            $u = $this->findById($userId, null);
            if ($u && !empty($u['role_id'])) {
                return [(int) $u['role_id']];
            }
        }

        return $ids;
    }

    /**
     * Identifiants de rôles tenant pour RBAC (union multi-rôles + repli sur users.role_id).
     *
     * @return list<int>
     */
    public function tenantRoleIdsForRbac(int $userId, ?int $legacyRoleId): array
    {
        $ids = $this->listOrganizationRoleIdsForUser($userId);
        if ($ids === [] && $legacyRoleId !== null && $legacyRoleId > 0) {
            return [$legacyRoleId];
        }

        return $ids;
    }

    public function userHasTenantRole(int $userId, int $roleId): bool
    {
        if ($roleId < 1) {
            return false;
        }
        if ($this->hasTenantUserRolesTable()) {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM tenant_user_roles WHERE user_id = ? AND role_id = ? AND org_unit_id IS NULL LIMIT 1'
            );
            $st->execute([$userId, $roleId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }
        if ($this->hasUserRolesTable()) {
            $st = $this->pdo->prepare('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1');
            $st->execute([$userId, $roleId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }
        $u = $this->findById($userId, null);

        return $u !== null && (int) ($u['role_id'] ?? 0) === $roleId;
    }

    /**
     * Remplace les rôles organisation de l’utilisateur et synchronise users.role_id (rôle « principal » affichage).
     *
     * @param list<int> $roleIds
     * @throws InvalidArgumentException si le jeu de rôles viole les règles de cohérence (sauf si $skipCoherenceCheck).
     */
    public function syncOrganizationRoles(int $userId, int $tenantId, array $roleIds, ?int $actorUserId = null, bool $skipCoherenceCheck = false): void
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $x) => $x > 0)));
        if (!$skipCoherenceCheck) {
            $err = RoleCoherenceValidator::validateOrgRoleSet($this->pdo, $tenantId, $roleIds);
            if ($err !== null) {
                throw new InvalidArgumentException($err);
            }
        }
        $beforeIds = $this->listOrganizationRoleIdsForUser($userId);
        if (!$this->hasUserRolesTable()) {
            $primary = $roleIds[0] ?? null;
            $this->pdo->prepare('UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$primary, $userId, $tenantId]);

            return;
        }
        $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        $valid = [];
        if ($roleIds !== []) {
            $ph = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id FROM roles WHERE tenant_id = ? AND role_layer IN ('community','intra') AND id IN ({$ph})"
            );
            $stmt->execute(array_merge([$tenantId], $roleIds));
            $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $ins = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($valid as $rid) {
                $ins->execute([$userId, $rid]);
            }
        }
        if ($this->hasTenantUserRolesTable()) {
            $this->pdo->prepare(
                'DELETE FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ? AND org_unit_id IS NULL'
            )->execute([$userId, $tenantId]);
            if ($valid !== []) {
                $insTur = $this->pdo->prepare(
                    'INSERT INTO tenant_user_roles (tenant_id, user_id, role_id, org_unit_id, co_unit_id, created_at) VALUES (?, ?, ?, NULL, 0, NOW())'
                );
                foreach ($valid as $rid) {
                    try {
                        $insTur->execute([$tenantId, $userId, $rid]);
                    } catch (\PDOException) {
                    }
                }
            }
        }
        $primary = $this->computePrimaryRoleIdForTenant($tenantId, $valid);
        $this->pdo->prepare('UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$primary, $userId, $tenantId]);

        if ($this->hasPreferredDisplayRoleColumn()) {
            $prefSt = $this->pdo->prepare('SELECT preferred_display_role_id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
            $prefSt->execute([$userId, $tenantId]);
            $prefRow = $prefSt->fetch(PDO::FETCH_ASSOC);
            $pref = isset($prefRow['preferred_display_role_id']) ? (int) $prefRow['preferred_display_role_id'] : 0;
            if ($pref > 0 && !in_array($pref, $valid, true)) {
                $this->pdo->prepare('UPDATE users SET preferred_display_role_id = NULL, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                    ->execute([$userId, $tenantId]);
            }
        }

        $logger = new RoleAssignmentLogRepository();
        $beforeSet = array_fill_keys($beforeIds, true);
        $afterSet = array_fill_keys($valid, true);
        foreach ($beforeIds as $rid) {
            if (!isset($afterSet[$rid])) {
                $logger->logRevoke($tenantId, $userId, $rid, $actorUserId, null);
            }
        }
        foreach ($valid as $rid) {
            if (!isset($beforeSet[$rid])) {
                $logger->logAssign($tenantId, $userId, $rid, $actorUserId, null);
            }
        }
    }

    private function hasPreferredDisplayRoleColumn(): bool
    {
        static $v;
        if ($v !== null) {
            return $v;
        }
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_display_role_id' LIMIT 1");
            $v = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $v = false;
        }

        return $v;
    }

    public function setPreferredDisplayRoleId(int $userId, int $tenantId, ?int $roleId): void
    {
        if (!$this->hasPreferredDisplayRoleColumn()) {
            return;
        }
        if ($roleId !== null && $roleId > 0 && !$this->userHasTenantRole($userId, $roleId)) {
            return;
        }
        $this->pdo->prepare('UPDATE users SET preferred_display_role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$roleId, $userId, $tenantId]);
    }

    /**
     * Rôle « principal » (affichage / session) pour un ensemble de rôles valides tenant.
     *
     * @param list<int> $roleIds
     */
    public function peekPrimaryRoleIdForTenant(int $tenantId, array $roleIds): ?int
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $x): bool => $x > 0)));
        if ($roleIds === []) {
            return null;
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id FROM roles WHERE tenant_id = ? AND role_layer IN ('community','intra') AND id IN ({$ph})"
        );
        $stmt->execute(array_merge([$tenantId], $roleIds));
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        return $this->computePrimaryRoleIdForTenant($tenantId, $valid);
    }

    /**
     * @param list<int> $validRoleIds
     */
    private function computePrimaryRoleIdForTenant(int $tenantId, array $validRoleIds): ?int
    {
        if ($validRoleIds === []) {
            return null;
        }
        $validRoleIds = array_values(array_unique($validRoleIds));
        $ph = implode(',', array_fill(0, count($validRoleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, slug, role_layer, COALESCE(semantic_tier, 'function') AS semantic_tier, COALESCE(display_priority, 0) AS display_priority
             FROM roles WHERE tenant_id = ? AND id IN ({$ph})"
        );
        $stmt->execute(array_merge([$tenantId], $validRoleIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return null;
        }
        foreach (['community_owner', 'tenant_admin'] as $slug) {
            foreach ($rows as $r) {
                if (($r['slug'] ?? '') === $slug) {
                    return (int) $r['id'];
                }
            }
        }
        usort(
            $rows,
            static function (array $a, array $b): int {
                $rank = static function (array $x): int {
                    return match ((string) ($x['semantic_tier'] ?? 'function')) {
                        'authority' => 1,
                        'function' => 2,
                        'liaison' => 3,
                        'support' => 4,
                        'specialty' => 5,
                        'status' => 6,
                        default => 2,
                    };
                };
                $cmp = $rank($a) <=> $rank($b);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = ((int) ($a['display_priority'] ?? 0)) <=> ((int) ($b['display_priority'] ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }
                $la = (string) ($a['role_layer'] ?? '');
                $lb = (string) ($b['role_layer'] ?? '');
                if ($la === 'community' && $lb !== 'community') {
                    return -1;
                }
                if ($lb === 'community' && $la !== 'community') {
                    return 1;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
        );

        return (int) $rows[0]['id'];
    }

    /**
     * Crée si besoin le compte technique « Modération automatique » pour le tenant (statut inactive, non connectable).
     *
     * @return int id utilisateur ou 0 si colonne is_service_account absente
     */
    public function ensureSystemModeratorUser(int $tenantId): int
    {
        if (!$this->hasServiceAccountColumn()) {
            return 0;
        }
        $existing = $this->findByEmail($tenantId, self::SYSTEM_MODERATOR_EMAIL);
        if ($existing) {
            return (int) $existing['id'];
        }
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
        $sql = 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, status, is_service_account, created_at, updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())';
        try {
            $this->pdo->prepare($sql)->execute([$tenantId, self::SYSTEM_MODERATOR_EMAIL, $hash, 'Modération automatique', 'SYSMOD', 'inactive']);
        } catch (\PDOException $e) {
            $again = $this->findByEmail($tenantId, self::SYSTEM_MODERATOR_EMAIL);
            if ($again) {
                return (int) $again['id'];
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function isServiceAccount(int $userId): bool
    {
        if (!$this->hasServiceAccountColumn()) {
            return false;
        }
        $u = $this->findById($userId);
        return $u !== null && !empty($u['is_service_account']);
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

    /**
     * Tous les comptes partageant exactement la même adresse (multi-communautés).
     *
     * @return list<int>
     */
    public function listIdsByEmailNormalized(string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
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
    public function listForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null, ?int $limit = null, ?int $offset = null, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
    {
        [$sql, $params] = $this->buildUserListQuery($tenantId, $search, $status, $roleId, $excludeServiceAccounts, $onlyWithoutUnit, $onlyWithoutRole);
        $sql .= ' ORDER BY u.email ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(200, (int) $limit)) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countListForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): int
    {
        [$whereSql, $params] = $this->buildUserListWhere($tenantId, $search, $status, $roleId, $excludeServiceAccounts, $onlyWithoutUnit, $onlyWithoutRole);
        $sql = 'SELECT COUNT(*) FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE ' . $whereSql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildUserListWhere(int $tenantId, ?string $search, ?string $status, ?int $roleId, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
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
            if ($this->hasUserRolesTable()) {
                $parts[] = '(u.role_id = ? OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?))';
                $params[] = $roleId;
                $params[] = $roleId;
            } else {
                $parts[] = 'u.role_id = ?';
                $params[] = $roleId;
            }
        }
        if ($excludeServiceAccounts && $this->hasServiceAccountColumn()) {
            $parts[] = '(u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        if ($onlyWithoutRole === true) {
            if ($this->hasUserRolesTable()) {
                $parts[] = '(u.role_id IS NULL AND NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id))';
            } else {
                $parts[] = 'u.role_id IS NULL';
            }
        }
        if ($onlyWithoutUnit === true) {
            if ($this->hasUserUnitsTable()) {
                $parts[] = 'NOT EXISTS (SELECT 1 FROM user_units uu WHERE uu.user_id = u.id AND (uu.ended_at IS NULL OR uu.ended_at > NOW()))';
            } else {
                $parts[] = '1=0';
            }
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildUserListQuery(int $tenantId, ?string $search, ?string $status, ?int $roleId, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
    {
        [$whereSql, $params] = $this->buildUserListWhere($tenantId, $search, $status, $roleId, $excludeServiceAccounts, $onlyWithoutUnit, $onlyWithoutRole);
        $extra = '';
        if ($this->hasUserRolesTable()) {
            $extra = ', COALESCE(
                (SELECT GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.role_layer DESC, r2.name SEPARATOR \', \')
                 FROM user_roles ur
                 INNER JOIN roles r2 ON r2.id = ur.role_id AND r2.tenant_id = u.tenant_id
                 WHERE ur.user_id = u.id),
                r.name
            ) AS roles_display';
        }
        $sql = 'SELECT u.*, r.name as role_name' . $extra . ' FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE ' . $whereSql;

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

    /** Indique si un autre utilisateur du tenant utilise déjà ce callsign (insensible à la casse). */
    public function callsignExistsInTenant(int $tenantId, string $callsign, ?int $excludeUserId = null): bool
    {
        $callsign = trim($callsign);
        if ($callsign === '') {
            return false;
        }
        $sql = 'SELECT 1 FROM users WHERE tenant_id = ? AND callsign IS NOT NULL AND TRIM(callsign) <> \'\' AND LOWER(TRIM(callsign)) = LOWER(?)';
        $params = [$tenantId, $callsign];
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

    /**
     * Résout un @identifiant unique (nom affiché ou indicatif exact, insensible à la casse).
     *
     * @return array{id: int, display_name: string, callsign: ?string}|null
     */
    public function findForForumMention(int $tenantId, string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, display_name, callsign FROM users WHERE tenant_id = ? AND (
                LOWER(display_name) = LOWER(?)
                OR (callsign IS NOT NULL AND TRIM(callsign) <> \'\' AND LOWER(TRIM(callsign)) = LOWER(?))
            ) LIMIT 1'
        );
        $stmt->execute([$tenantId, $token, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Recherche annuaire portail (nom d’affichage, indicatif, slug de profil public).
     *
     * @return list<array<string, mixed>>
     */
    public function searchForPortal(int $tenantId, string $query, int $limit = 12): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $term = '%' . $q . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, display_name, callsign, profile_slug FROM users
             WHERE tenant_id = ?
             AND (
                 display_name LIKE ?
                 OR (callsign IS NOT NULL AND TRIM(callsign) <> \'\' AND callsign LIKE ?)
                 OR (profile_slug IS NOT NULL AND TRIM(profile_slug) <> \'\' AND profile_slug LIKE ?)
             )
             ORDER BY display_name ASC
             LIMIT ?'
        );
        $stmt->execute([$tenantId, $term, $term, $term, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche transverse (toutes communautés) pour assistance opérateur site — liste de restriction, etc.
     * Minimum 2 caractères ; comptes techniques exclus lorsque la colonne existe.
     *
     * @return list<array<string, mixed>>
     */
    public function searchAccountsForPlatformOperator(string $query, int $limit = 20): array
    {
        $q = trim($query);
        $len = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
        if ($len < 2) {
            return [];
        }
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 120) : substr($q, 0, 120);
        $term = '%' . $q . '%';
        $limit = max(1, min(30, $limit));
        $svcSql = '';
        if ($this->hasServiceAccountColumn()) {
            $svcSql = ' AND (u.is_service_account IS NULL OR u.is_service_account = 0)';
        }
        $sql = 'SELECT u.id, u.email, u.display_name, u.callsign, t.name AS tenant_name, u.status
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE (
                 u.email LIKE ?
                 OR u.display_name LIKE ?
                 OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> \'\' AND u.callsign LIKE ?)
             )' . $svcSql . '
             ORDER BY t.name ASC, u.email ASC
             LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$term, $term, $term]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<int> User IDs ayant le rôle donné (pour assignation formation par rôle). */
    public function getIdsByRole(int $tenantId, int $roleId): array
    {
        if ($this->hasUserRolesTable()) {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT u.id FROM users u
                 WHERE u.tenant_id = ?
                 AND (u.role_id = ? OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?))'
            );
            $stmt->execute([$tenantId, $roleId, $roleId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND role_id = ?');
            $stmt->execute([$tenantId, $roleId]);
        }

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
        if ($this->hasUserRolesTable()) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT u.id) FROM users u
                 WHERE (u.role_id = ? OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?))'
            );
            $stmt->execute([$roleId, $roleId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
            $stmt->execute([$roleId]);
        }

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
     * Retire le tenant système (slug `default`) du sélecteur dès qu'une autre communauté existe.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function filterSwitchableTenantsForUser(array $rows): array
    {
        $hasNonDefault = false;
        foreach ($rows as $r) {
            if (($r['slug'] ?? '') !== 'default') {
                $hasNonDefault = true;
                break;
            }
        }
        if (! $hasNonDefault) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn ($r) => ($r['slug'] ?? '') !== 'default'));
    }

    /** Premier tenant_id dont le slug n'est pas `default`, ou null. */
    public function firstNonDefaultTenantId(array $rows): ?int
    {
        foreach ($rows as $r) {
            if (($r['slug'] ?? '') !== 'default') {
                return (int) $r['tenant_id'];
            }
        }

        return null;
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

    /**
     * Connexion : actifs + en attente de vérification e-mail.
     *
     * @return list<array<string, mixed>>
     */
    public function listUsersForLoginByEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $stmt = $this->pdo->prepare(
            "SELECT u.*, t.name AS tenant_name, t.slug AS tenant_slug
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE LOWER(TRIM(u.email)) = ? AND u.status IN ('active', 'pending_verification')
             ORDER BY t.name ASC"
        );
        $stmt->execute([$email]);

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

        $newId = $this->create($newTenantId, $cloneData);
        if ($this->hasEmailVerifiedColumn()) {
            $srcEv = $u['email_verified_at'] ?? null;
            if ($srcEv) {
                $this->pdo->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?')->execute([$srcEv, $newId]);
            } else {
                $this->pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$newId]);
            }
        }
        if ($roleId > 0) {
            $this->syncOrganizationRoles($newId, $newTenantId, [$roleId], null, true);
        }

        return $newId;
    }

    public function countActiveMembers(int $tenantId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * % de membres actifs ayant eu une activité (last_login) sur les 30 derniers jours.
     */
    public function activityRateLast30DaysPercent(int $tenantId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'"
        );
        $stmt->execute([$tenantId]);
        $total = (int) $stmt->fetchColumn();
        if ($total === 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'
             AND last_login_at IS NOT NULL AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$tenantId]);
        $recent = (int) $stmt->fetchColumn();

        return (int) round(100 * $recent / $total);
    }

    public function countPublicRosterOptIn(int $tenantId): int
    {
        $stmt = $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'public_roster_opt_in' LIMIT 1"
        );
        if (!$stmt || !$stmt->fetchColumn()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM users u
             INNER JOIN user_profile_display_settings ups ON ups.user_id = u.id AND ups.public_roster_opt_in = 1
             WHERE u.tenant_id = ? AND u.status = \'active\''
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Jointure / colonnes grades : ancienne table tenant (name, short_name, rank_order) ou référentiel (label_*, sort_order).
     *
     * @return array{join: string, grade_short: string, order_grade: string}
     */
    private function getGradesConfigForPublicRoster(): array
    {
        if (self::$gradesConfigPublicRoster !== null) {
            return self::$gradesConfigPublicRoster;
        }
        $stmt = $this->pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME IN ('name', 'label_long', 'tenant_id')"
        );
        $columns = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME') : [];
        $hasLabelLong = in_array('label_long', $columns, true);
        $hasTenantId = in_array('tenant_id', $columns, true);
        if ($hasLabelLong) {
            self::$gradesConfigPublicRoster = [
                'grade_short' => 'g.label_short AS grade_short',
                'order_grade' => 'COALESCE(g.sort_order, 999) ASC, g.label_short ASC',
                'join' => $hasTenantId
                    ? 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id'
                    : 'LEFT JOIN grades g ON g.id = u.grade_id',
            ];
        } else {
            self::$gradesConfigPublicRoster = [
                'grade_short' => 'g.short_name AS grade_short',
                'order_grade' => 'COALESCE(g.rank_order, 999) ASC, g.short_name ASC',
                'join' => 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id',
            ];
        }

        return self::$gradesConfigPublicRoster;
    }

    /**
     * Membres pour le roster public : actifs, opt-in, ordre stable.
     *
     * @return list<array<string,mixed>>
     */
    public function listPublicRosterForTenant(int $tenantId, int $limit = 120): array
    {
        $stmt = $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'public_roster_opt_in' LIMIT 1"
        );
        if (!$stmt || !$stmt->fetchColumn()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $gc = $this->getGradesConfigForPublicRoster();
        $gradeJoin = $gc['join'];
        $gradeShort = $gc['grade_short'];
        $orderGrade = $gc['order_grade'];
        $sql = "SELECT u.id, u.display_name, u.callsign, u.status,
                       {$gradeShort},
                       r.name AS role_name,
                       ups.forum_alias, ups.forum_label_mode,
                       un.name AS unit_name
                FROM users u
                INNER JOIN user_profile_display_settings ups ON ups.user_id = u.id AND COALESCE(ups.public_roster_opt_in, 0) = 1
                {$gradeJoin}
                LEFT JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
                LEFT JOIN user_units uu ON uu.user_id = u.id AND uu.is_primary = 1
                    AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                LEFT JOIN units un ON un.id = uu.unit_id AND un.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active'
                ORDER BY {$orderGrade}, u.display_name ASC, u.callsign ASC
                LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static ?bool $hasEmailVerifiedColumn = null;

    public function hasEmailVerifiedColumn(): bool
    {
        if (self::$hasEmailVerifiedColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at' LIMIT 1");
            self::$hasEmailVerifiedColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasEmailVerifiedColumn;
    }

    public function markEmailVerified(int $userId, int $tenantId): void
    {
        if (!$this->hasEmailVerifiedColumn()) {
            $this->pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute(['active', $userId, $tenantId]);

            return;
        }
        $this->pdo->prepare('UPDATE users SET email_verified_at = NOW(), status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute(['active', $userId, $tenantId]);
    }

    /** Comptes créés par l’admin : considérer l’e-mail comme déjà vérifié sans changer le statut choisi. */
    public function markEmailVerifiedWithoutStatusChange(int $userId, int $tenantId): void
    {
        if (!$this->hasEmailVerifiedColumn()) {
            return;
        }
        $this->pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$userId, $tenantId]);
    }

    /**
     * Emails des gouvernants communauté (alertes nouveau membre, etc.).
     *
     * @return list<string>
     */
    public function listGovernanceEmailsForTenant(int $tenantId): array
    {
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.slug IN ('tenant_admin', 'community_owner')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $emails = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $e = strtolower(trim((string) ($row['email'] ?? '')));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Destinataires pour une demande d’accès (gouvernance + profils pouvant gérer les rôles sur la communauté).
     *
     * @return list<string>
     */
    public function listEmailsForTenantAccessDelegation(int $tenantId): array
    {
        $emails = $this->listGovernanceEmailsForTenant($tenantId);
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            INNER JOIN role_permissions rp ON rp.role_id = r.id
            INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND p.slug IN ('admin.organization', 'admin.access')";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $e = strtolower(trim((string) ($row['email'] ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $e;
                }
            }
        } catch (\Throwable) {
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.email FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active'
                AND p.slug IN ('admin.organization', 'admin.access')";
            try {
                $st = $this->pdo->prepare($sql2);
                $st->execute([$tenantId]);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $e = strtolower(trim((string) ($row['email'] ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Emails des rôles recrutement : recruteur, fondateur (propriétaire communauté), RH.
     *
     * @return list<string>
     */
    public function listRecruitmentNotificationEmailsForTenant(int $tenantId): array
    {
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active'
            AND r.slug IN ('recruiter', 'community_owner', 'hr')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $emails = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $e = strtolower(trim((string) ($row['email'] ?? '')));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Membres actifs susceptibles de modérer le forum (alertes internes).
     *
     * @return list<int>
     */
    public function listForumAlertRecipientUserIds(int $tenantId): array
    {
        $ids = [];
        $svcExcl = $this->hasServiceAccountColumn() ? '(u.is_service_account IS NULL OR u.is_service_account = 0)' : '1';
        $slugs = "'tenant_admin', 'community_owner', 'forum_moderator', 'administrator'";
        $sql = "SELECT DISTINCT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$svcExcl}
            AND r.slug IN ({$slugs})";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        } catch (\Throwable) {
            return [];
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.id FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$svcExcl}
                AND r.slug IN ({$slugs})";
            try {
                $st = $this->pdo->prepare($sql2);
                $st->execute([$tenantId]);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Membres actifs ayant au moins une des permissions listées (rôle principal et rôles additionnels).
     *
     * @param list<string> $permissionSlugs
     * @return list<int>
     */
    public function listActiveUserIdsWithAnyPermissionSlug(int $tenantId, array $permissionSlugs): array
    {
        if ($tenantId < 1 || $permissionSlugs === []) {
            return [];
        }
        $permissionSlugs = array_values(array_unique(array_filter(array_map('trim', $permissionSlugs))));
        if ($permissionSlugs === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($permissionSlugs), '?'));
        $params = array_merge([$tenantId], $permissionSlugs);
        $ids = [];
        $sql = "SELECT DISTINCT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            INNER JOIN role_permissions rp ON rp.role_id = r.id
            INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND p.slug IN ({$placeholders})";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        } catch (\Throwable) {
            return [];
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.id FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND p.slug IN ({$placeholders})";
            try {
                $st = $this->pdo->prepare($sql2);
                $st->execute($params);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    public function invalidateAllSessionsForUser(int $userId, ?int $tenantId = null): void
    {
        if ($tenantId !== null) {
            $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        } else {
            $this->pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
        }
    }
}
