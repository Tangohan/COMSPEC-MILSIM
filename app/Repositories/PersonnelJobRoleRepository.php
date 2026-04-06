<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelJobRoleRepository
{
    private PDO $pdo;

    private static ?bool $personnelProfilesJobRoleColumns = null;

    private static ?bool $usersServiceAccountColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function personnelProfilesHaveJobRoleColumns(): bool
    {
        if (self::$personnelProfilesJobRoleColumns !== null) {
            return self::$personnelProfilesJobRoleColumns;
        }
        if (!$this->tablesExist()) {
            self::$personnelProfilesJobRoleColumns = false;

            return false;
        }
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND COLUMN_NAME = 'personnel_job_role_id' LIMIT 1");
        self::$personnelProfilesJobRoleColumns = (bool) ($stmt && $stmt->fetchColumn());

        return self::$personnelProfilesJobRoleColumns;
    }

    private function usersExcludeServiceAccountCondition(): ?string
    {
        if (self::$usersServiceAccountColumn === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
            self::$usersServiceAccountColumn = (bool) ($stmt && $stmt->fetchColumn());
        }

        return self::$usersServiceAccountColumn
            ? '(u.is_service_account IS NULL OR u.is_service_account = 0)'
            : null;
    }

    /**
     * Liste paginée des membres avec rôle métier dossier (pour back-office attributions).
     *
     * @return list<array<string, mixed>>
     */
    public function listUsersForJobRoleAssignments(
        int $tenantId,
        ?string $search,
        ?int $filterJobRoleId,
        bool $onlyUnassigned,
        int $limit,
        int $offset
    ): array {
        if (!$this->tablesExist() || !$this->personnelProfilesHaveJobRoleColumns()) {
            return [];
        }
        [$sql, $params] = $this->buildAssignmentListQuery($tenantId, $search, $filterJobRoleId, $onlyUnassigned);
        $sql .= ' ORDER BY u.display_name ASC, u.id ASC LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUsersForJobRoleAssignments(
        int $tenantId,
        ?string $search,
        ?int $filterJobRoleId,
        bool $onlyUnassigned
    ): int {
        if (!$this->tablesExist() || !$this->personnelProfilesHaveJobRoleColumns()) {
            return 0;
        }
        [$whereSql, $params] = $this->buildAssignmentWhere($tenantId, $search, $filterJobRoleId, $onlyUnassigned);
        $sql = 'SELECT COUNT(*) FROM users u LEFT JOIN personnel_profiles pp ON pp.user_id = u.id WHERE ' . $whereSql;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildAssignmentWhere(
        int $tenantId,
        ?string $search,
        ?int $filterJobRoleId,
        bool $onlyUnassigned
    ): array {
        $parts = ['u.tenant_id = ?'];
        $params = [$tenantId];
        $svc = $this->usersExcludeServiceAccountCondition();
        if ($svc !== null) {
            $parts[] = $svc;
        }
        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $parts[] = '(u.email LIKE ? OR u.display_name LIKE ? OR u.callsign LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($onlyUnassigned) {
            $parts[] = '(pp.personnel_job_role_id IS NULL OR pp.personnel_job_role_id = 0)';
        } elseif ($filterJobRoleId !== null && $filterJobRoleId > 0) {
            $parts[] = 'pp.personnel_job_role_id = ?';
            $params[] = $filterJobRoleId;
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildAssignmentListQuery(
        int $tenantId,
        ?string $search,
        ?int $filterJobRoleId,
        bool $onlyUnassigned
    ): array {
        [$whereSql, $params] = $this->buildAssignmentWhere($tenantId, $search, $filterJobRoleId, $onlyUnassigned);
        $sql = 'SELECT u.id, u.display_name, u.callsign, u.email, u.status, u.profile_slug,
                       pp.personnel_job_role_id, pp.role_sub_label, pp.primary_role,
                       pp.primary_unit_id,
                       pjr.name AS personnel_job_role_name, pjr.slug AS personnel_job_role_slug
                FROM users u
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                LEFT JOIN personnel_job_roles pjr ON pjr.id = pp.personnel_job_role_id AND pjr.tenant_id = u.tenant_id
                WHERE ' . $whereSql;

        return [$sql, $params];
    }

    public function tablesExist(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_job_roles' LIMIT 1");

        return (bool) ($stmt && $stmt->fetchColumn());
    }

    /** @return list<array<string, mixed>> */
    public function listCategories(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_job_role_categories WHERE tenant_id = ? ORDER BY parent_id IS NULL DESC, sort_order ASC, name ASC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listRolesWithCategory(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT r.*, c.name AS category_name, c.parent_id AS category_parent_id
             FROM personnel_job_roles r
             INNER JOIN personnel_job_role_categories c ON c.id = r.category_id AND c.tenant_id = r.tenant_id
             WHERE r.tenant_id = ?
             ORDER BY c.sort_order ASC, r.sort_order ASC, r.name ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRoleById(int $id, int $tenantId): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_job_roles WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<int> */
    public function getPermissionIdsForRole(int $jobRoleId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT permission_id FROM personnel_job_role_permissions WHERE personnel_job_role_id = ?');
        $stmt->execute([$jobRoleId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function setPermissionsForRole(int $jobRoleId, array $permissionIds): void
    {
        if (!$this->tablesExist()) {
            return;
        }
        $this->pdo->prepare('DELETE FROM personnel_job_role_permissions WHERE personnel_job_role_id = ?')->execute([$jobRoleId]);
        $ins = $this->pdo->prepare('INSERT INTO personnel_job_role_permissions (personnel_job_role_id, permission_id) VALUES (?, ?)');
        foreach ($permissionIds as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $ins->execute([$jobRoleId, $pid]);
            }
        }
    }

    public function createCategory(int $tenantId, ?int $parentId, string $name, string $slug, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_job_role_categories (tenant_id, parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $parentId, $name, $slug, $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createRole(int $tenantId, int $categoryId, string $name, string $slug, ?string $description, int $sortOrder, bool $isSystem = false): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_job_roles (tenant_id, category_id, name, slug, description, sort_order, is_system) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $categoryId, $name, $slug, $description, $sortOrder, $isSystem ? 1 : 0]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateRole(int $id, int $tenantId, int $categoryId, string $name, string $slug, ?string $description, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE personnel_job_roles SET category_id = ?, name = ?, slug = ?, description = ?, sort_order = ? WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$categoryId, $name, $slug, $description, $sortOrder, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteRole(int $id, int $tenantId): bool
    {
        $row = $this->findRoleById($id, $tenantId);
        if (!$row || !empty($row['is_system'])) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM personnel_job_roles WHERE id = ? AND tenant_id = ? AND is_system = 0');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function findCategoryById(int $id, int $tenantId): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_job_role_categories WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findRoleIdBySlug(int $tenantId, string $slug): ?int
    {
        if (!$this->tablesExist() || $slug === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM personnel_job_roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    public function updateCategory(int $id, int $tenantId, ?int $parentId, string $name, string $slug, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE personnel_job_role_categories SET parent_id = ?, name = ?, slug = ?, sort_order = ? WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$parentId, $name, $slug, $sortOrder, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public function countChildCategories(int $parentId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM personnel_job_role_categories WHERE tenant_id = ? AND parent_id = ?');
        $stmt->execute([$tenantId, $parentId]);

        return (int) $stmt->fetchColumn();
    }

    public function countRolesInCategory(int $categoryId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM personnel_job_roles WHERE tenant_id = ? AND category_id = ?');
        $stmt->execute([$tenantId, $categoryId]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteCategory(int $id, int $tenantId): bool
    {
        if ($this->countChildCategories($id, $tenantId) > 0 || $this->countRolesInCategory($id, $tenantId) > 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM personnel_job_role_categories WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<int, int> id rôle => nombre de permissions liées */
    public function permissionCountsForTenant(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT r.id, COUNT(p.permission_id) AS c
             FROM personnel_job_roles r
             LEFT JOIN personnel_job_role_permissions p ON p.personnel_job_role_id = r.id
             WHERE r.tenant_id = ?
             GROUP BY r.id'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['id']] = (int) $row['c'];
        }

        return $out;
    }

    /**
     * Libellés pour &lt;select&gt; : « Racine › Sous-cat › Nom du rôle ».
     *
     * @return list<array{id: int, label: string, name: string}>
     */
    public function listRoleOptionsForSelect(int $tenantId): array
    {
        $roles = $this->listRolesWithCategory($tenantId);
        $cats = $this->listCategories($tenantId);
        $catById = [];
        foreach ($cats as $c) {
            $catById[(int) $c['id']] = $c;
        }
        $path = function (array $c) use (&$catById): string {
            $parts = [];
            $cur = $c;
            $guard = 0;
            while ($cur && $guard++ < 10) {
                array_unshift($parts, (string) $cur['name']);
                $pid = isset($cur['parent_id']) ? (int) $cur['parent_id'] : 0;
                $cur = $pid > 0 && isset($catById[$pid]) ? $catById[$pid] : null;
            }

            return implode(' › ', $parts);
        };

        $out = [];
        foreach ($roles as $r) {
            $cid = (int) ($r['category_id'] ?? 0);
            $c = $catById[$cid] ?? null;
            $prefix = $c ? $path($c) : '';
            $label = $prefix !== '' ? $prefix . ' › ' . $r['name'] : (string) $r['name'];
            $out[] = ['id' => (int) $r['id'], 'label' => $label, 'name' => (string) $r['name']];
        }

        return $out;
    }
}
