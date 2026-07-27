<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use PDO;

final class RolePermissionMatrixRepository
{
    private PDO $pdo;
    private bool $ensured = false;

    public function __construct(
        ?PDO $pdo = null,
        private ?RoleRepository $roles = null,
        private ?PermissionRepository $permissions = null,
    ) {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->roles ??= new RoleRepository();
        $this->permissions ??= new PermissionRepository();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->ensured = true;
        $migration = dirname(__DIR__, 2) . '/bootstrap/role_permission_matrix_migration.php';
        if (!is_file($migration)) {
            return;
        }
        try {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($this->pdo);
            }
        } catch (\Throwable) {
        }
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_module_access' LIMIT 1");

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{scope?: string, level?: string|int, active?: string, q?: string} $filters
     * @return array{rows: list<array<string,mixed>>, stats: array<string,mixed>}
     */
    public function listMatrix(int $tenantId, array $filters = []): array
    {
        if ($tenantId < 1) {
            return ['rows' => [], 'stats' => $this->emptyStats()];
        }

        $this->bootstrapTenantRoles($tenantId);
        $roles = $this->roles->forTenantOrganization($tenantId);
        $roleIds = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $roles)));
        $memberCounts = $this->roles->countMembersByRoleIds($tenantId, $roleIds);
        $moduleRows = $this->loadModuleAccessMap($tenantId, $roleIds);

        $rows = [];
        foreach ($roles as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            if ($roleId < 1) {
                continue;
            }
            $slug = (string) ($role['slug'] ?? '');
            $roleCode = trim((string) ($role['role_code'] ?? ''));
            if ($roleCode === '') {
                $roleCode = RolePermissionMatrixCatalog::roleCodeFromSlug($slug, $roleId);
            }
            $level = (int) ($role['level'] ?? 0);
            $isActive = !isset($role['is_active']) || (int) $role['is_active'] === 1;
            $modules = [];
            foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
                $entry = $moduleRows[$roleId][$moduleKey] ?? null;
                $modules[$moduleKey] = [
                    'access_level' => RolePermissionMatrixCatalog::normalizeAccessLevel((string) ($entry['access_level'] ?? RolePermissionMatrixCatalog::LEVEL_NONE)),
                    'access_label' => RolePermissionMatrixCatalog::accessLevelLabelsFr()[RolePermissionMatrixCatalog::normalizeAccessLevel((string) ($entry['access_level'] ?? RolePermissionMatrixCatalog::LEVEL_NONE))],
                ];
            }
            $transversal = $moduleRows[$roleId]['__transversal'] ?? ['can_delete' => false, 'can_export' => false];

            $rows[] = [
                'id' => $roleId,
                'code' => $roleCode,
                'name' => (string) ($role['name'] ?? ''),
                'slug' => $slug,
                'level' => $level,
                'holders_count' => (int) ($memberCounts[$roleId] ?? 0),
                'modules' => $modules,
                'can_delete' => !empty($transversal['can_delete']),
                'can_delete_label' => !empty($transversal['can_delete']) ? 'Oui' : 'Non',
                'can_export' => !empty($transversal['can_export']),
                'can_export_label' => !empty($transversal['can_export']) ? 'Oui' : 'Non',
                'last_reviewed_at' => $role['last_reviewed_at'] ?? null,
                'last_reviewed_label' => $this->formatReviewDate($role['last_reviewed_at'] ?? null),
                'is_active' => $isActive,
                'status_label' => $isActive ? 'Actif' : 'Inactif',
                'role_layer' => (string) ($role['role_layer'] ?? 'community'),
            ];
        }

        $rows = $this->applyFilters($rows, $filters);

        return [
            'rows' => $rows,
            'stats' => $this->buildStats($tenantId, $roles, $rows),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveRoleRow(int $tenantId, int $roleId, array $payload): bool
    {
        if ($tenantId < 1 || $roleId < 1 || !$this->tablesReady()) {
            return false;
        }
        $role = $this->roles->findById($roleId, $tenantId);
        if (!$role) {
            return false;
        }

        $level = isset($payload['level']) ? (int) $payload['level'] : (int) ($role['level'] ?? 0);
        $roleCode = trim((string) ($payload['code'] ?? $payload['role_code'] ?? $role['role_code'] ?? ''));
        if ($roleCode === '') {
            $roleCode = RolePermissionMatrixCatalog::roleCodeFromSlug((string) ($role['slug'] ?? ''), $roleId);
        }
        $isActive = array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : ((int) ($role['is_active'] ?? 1) === 1);
        $reviewedAt = trim((string) ($payload['last_reviewed_at'] ?? ''));
        if ($reviewedAt === '' && !empty($payload['mark_reviewed'])) {
            $reviewedAt = date('Y-m-d H:i:s');
        }

        $st = $this->pdo->prepare(
            'UPDATE roles SET role_code = ?, level = ?, is_active = ?, last_reviewed_at = COALESCE(?, last_reviewed_at) WHERE id = ? AND tenant_id = ?'
        );
        $st->execute([
            mb_substr($roleCode, 0, 16),
            max(0, min(5, $level)),
            $isActive ? 1 : 0,
            $reviewedAt !== '' ? $reviewedAt : null,
            $roleId,
            $tenantId,
        ]);

        $canDelete = !empty($payload['can_delete']);
        $canExport = !empty($payload['can_export']);
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];

        foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
            $accessLevel = RolePermissionMatrixCatalog::normalizeAccessLevel((string) ($modules[$moduleKey] ?? RolePermissionMatrixCatalog::LEVEL_NONE));
            $this->upsertModuleAccess($tenantId, $roleId, $moduleKey, $accessLevel, $canDelete, $canExport);
        }

        return true;
    }

    public function markAllReviewed(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            "UPDATE roles SET last_reviewed_at = NOW() WHERE tenant_id = ? AND role_layer IN ('community','intra')"
        );
        $st->execute([$tenantId]);

        return $st->rowCount();
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    public function exportCsv(array $rows): string
    {
        $moduleLabels = RolePermissionMatrixCatalog::moduleLabelsFr();
        $headers = ['Code', 'Rôle', 'Niveau', 'Titulaires'];
        foreach ($moduleLabels as $label) {
            $headers[] = $label;
        }
        $headers = array_merge($headers, ['Suppression', 'Export', 'Dernière revue', 'État']);

        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            $line = [
                (string) ($row['code'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['level'] ?? 0),
                (string) ($row['holders_count'] ?? 0),
            ];
            foreach (RolePermissionMatrixCatalog::moduleKeys() as $moduleKey) {
                $line[] = (string) ($row['modules'][$moduleKey]['access_label'] ?? '—');
            }
            $line[] = (string) ($row['can_delete_label'] ?? 'Non');
            $line[] = (string) ($row['can_export_label'] ?? 'Non');
            $line[] = (string) ($row['last_reviewed_label'] ?? '—');
            $line[] = (string) ($row['status_label'] ?? 'Actif');
            fputcsv($out, $line, ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return is_string($csv) ? $csv : '';
    }

    public function bootstrapTenantRoles(int $tenantId): void
    {
        if ($tenantId < 1 || !$this->tablesReady()) {
            return;
        }
        $roles = $this->roles->forTenantOrganization($tenantId);
        foreach ($roles as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            $slug = (string) ($role['slug'] ?? '');
            if ($roleId < 1) {
                continue;
            }
            if (trim((string) ($role['role_code'] ?? '')) === '') {
                $code = RolePermissionMatrixCatalog::roleCodeFromSlug($slug, $roleId);
                $st = $this->pdo->prepare('UPDATE roles SET role_code = ? WHERE id = ? AND tenant_id = ? AND (role_code IS NULL OR role_code = \'\')');
                $st->execute([$code, $roleId, $tenantId]);
            }
            $existing = $this->pdo->prepare('SELECT COUNT(*) FROM role_module_access WHERE tenant_id = ? AND role_id = ?');
            $existing->execute([$tenantId, $roleId]);
            if ((int) $existing->fetchColumn() > 0) {
                continue;
            }
            $profile = RolePermissionMatrixCatalog::defaultProfileForRoleSlug($slug);
            if ($profile === null) {
                continue;
            }
            if ((int) ($role['level'] ?? 0) < 1 && isset($profile['level'])) {
                $st = $this->pdo->prepare('UPDATE roles SET level = ? WHERE id = ? AND tenant_id = ? AND level = 0');
                $st->execute([(int) $profile['level'], $roleId, $tenantId]);
            }
            foreach ($profile['modules'] as $moduleKey => $accessLevel) {
                $this->upsertModuleAccess(
                    $tenantId,
                    $roleId,
                    (string) $moduleKey,
                    (string) $accessLevel,
                    (bool) $profile['can_delete'],
                    (bool) $profile['can_export']
                );
            }
        }
    }

    private function upsertModuleAccess(
        int $tenantId,
        int $roleId,
        string $moduleKey,
        string $accessLevel,
        bool $canDelete,
        bool $canExport
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO role_module_access (tenant_id, role_id, module_key, access_level, can_delete, can_export, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE access_level = VALUES(access_level), can_delete = VALUES(can_delete), can_export = VALUES(can_export), updated_at = NOW()'
        );
        $st->execute([
            $tenantId,
            $roleId,
            $moduleKey,
            RolePermissionMatrixCatalog::normalizeAccessLevel($accessLevel),
            $canDelete ? 1 : 0,
            $canExport ? 1 : 0,
        ]);
    }

    /**
     * @param list<int> $roleIds
     * @return array<int, array<string, array<string,mixed>>>
     */
    private function loadModuleAccessMap(int $tenantId, array $roleIds): array
    {
        $map = [];
        if ($tenantId < 1 || $roleIds === [] || !$this->tablesReady()) {
            return $map;
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT role_id, module_key, access_level, can_delete, can_export
             FROM role_module_access
             WHERE tenant_id = ? AND role_id IN ({$ph})"
        );
        $st->execute(array_merge([$tenantId], $roleIds));
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $rid = (int) ($row['role_id'] ?? 0);
            $moduleKey = (string) ($row['module_key'] ?? '');
            if ($rid < 1 || $moduleKey === '') {
                continue;
            }
            $map[$rid][$moduleKey] = $row;
            $map[$rid]['__transversal'] = [
                'can_delete' => !empty($row['can_delete']) || !empty($map[$rid]['__transversal']['can_delete'] ?? false),
                'can_export' => !empty($row['can_export']) || !empty($map[$rid]['__transversal']['can_export'] ?? false),
            ];
        }

        return $map;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{scope?: string, level?: string|int, active?: string, q?: string} $filters
     * @return list<array<string,mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        $scope = strtolower(trim((string) ($filters['scope'] ?? '')));
        $level = trim((string) ($filters['level'] ?? ''));
        $active = trim((string) ($filters['active'] ?? ''));

        return array_values(array_filter($rows, static function (array $row) use ($q, $scope, $level, $active): bool {
            if ($q !== '') {
                $hay = strtolower(implode(' ', [
                    (string) ($row['code'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['slug'] ?? ''),
                ]));
                if (!str_contains($hay, $q)) {
                    return false;
                }
            }
            if ($scope !== '' && $scope !== 'all') {
                $hasScope = false;
                foreach ((array) ($row['modules'] ?? []) as $module) {
                    $lvl = (string) ($module['access_level'] ?? RolePermissionMatrixCatalog::LEVEL_NONE);
                    if ($scope === 'admin' && $lvl === RolePermissionMatrixCatalog::LEVEL_COMPLET) {
                        $hasScope = true;
                        break;
                    }
                    if ($scope === 'section' && $lvl === RolePermissionMatrixCatalog::LEVEL_SA_SECTION) {
                        $hasScope = true;
                        break;
                    }
                    if ($scope === 'read' && $lvl === RolePermissionMatrixCatalog::LEVEL_LECTURE) {
                        $hasScope = true;
                        break;
                    }
                }
                if (!$hasScope) {
                    return false;
                }
            }
            if ($level !== '' && $level !== 'all' && (string) ($row['level'] ?? '') !== $level) {
                return false;
            }
            if ($active === '1' && empty($row['is_active'])) {
                return false;
            }
            if ($active === '0' && !empty($row['is_active'])) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<array<string,mixed>> $allRoles
     * @param list<array<string,mixed>> $filteredRows
     * @return array<string,mixed>
     */
    private function buildStats(int $tenantId, array $allRoles, array $filteredRows): array
    {
        $roleCount = count($allRoles);
        $adminHolders = 0;
        $permissionCells = 0;
        $technicianRoles = 0;
        $latestReview = null;

        foreach ($allRoles as $role) {
            $slug = (string) ($role['slug'] ?? '');
            if (in_array($slug, ['technical_admin', 'operations_officer'], true)) {
                $technicianRoles++;
            }
            $review = $role['last_reviewed_at'] ?? null;
            if (is_string($review) && $review !== '' && ($latestReview === null || $review > $latestReview)) {
                $latestReview = $review;
            }
        }

        $roleIds = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $allRoles)));
        $memberCounts = $this->roles->countMembersByRoleIds($tenantId, $roleIds);
        foreach ($allRoles as $role) {
            $rid = (int) ($role['id'] ?? 0);
            $lvl = (int) ($role['level'] ?? 0);
            if ($lvl >= 4) {
                $adminHolders += (int) ($memberCounts[$rid] ?? 0);
            }
        }

        foreach ($filteredRows as $row) {
            foreach ((array) ($row['modules'] ?? []) as $module) {
                if ((string) ($module['access_level'] ?? RolePermissionMatrixCatalog::LEVEL_NONE) !== RolePermissionMatrixCatalog::LEVEL_NONE) {
                    $permissionCells++;
                }
            }
        }

        return [
            'roles_defined' => $roleCount,
            'technician_roles' => $technicianRoles,
            'admin_holders' => $adminHolders,
            'permission_cells' => $permissionCells,
            'access_review_label' => $this->formatReviewDate($latestReview),
            'access_review_up_to_date' => $latestReview !== null && strtotime((string) $latestReview) >= strtotime('-90 days'),
            'filtered_count' => count($filteredRows),
            'total_count' => $roleCount,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyStats(): array
    {
        return [
            'roles_defined' => 0,
            'technician_roles' => 0,
            'admin_holders' => 0,
            'permission_cells' => 0,
            'access_review_label' => '—',
            'access_review_up_to_date' => false,
            'filtered_count' => 0,
            'total_count' => 0,
        ];
    }

    private function formatReviewDate(mixed $raw): string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return '—';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '—';
        }

        return date('d/m/Y', $ts);
    }
}
