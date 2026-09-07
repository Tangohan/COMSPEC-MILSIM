<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Repositories\MemberIntegrationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Community\TenantSeedHelper;
use App\Services\Personnel\PersonnelDutyPositionService;
use PDO;

/**
 * Convertit une communauté vers les modèles d’accès (Membre / RH / Gestionnaire).
 * Les copies hors modèles et hors niveaux créés par la communauté sont retirées.
 * Les packs de départ ne sont posés que si le niveau n’a encore aucun droit.
 */
final class CommunityAccessCollapseService
{
    public function __construct(
        private PDO $pdo,
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * Slugs conservés par communauté : trois accès + deux positions de service.
     *
     * @return list<string>
     */
    public static function retainedTenantSlugs(): array
    {
        return array_values(array_unique(array_merge(
            CommunityAccessProfiles::slugs(),
            [
                PersonnelDutyPositionService::SLUG_TRAINING,
                PersonnelDutyPositionService::SLUG_ACTIVE,
            ]
        )));
    }

    public static function mayCreateTenantRoleSlug(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return false;
        }
        if (CommunityAccessProfiles::isCustomAccessSlug($slug)) {
            return true;
        }

        return in_array($slug, self::retainedTenantSlugs(), true);
    }

    public function collapseAllTenants(): void
    {
        $st = $this->pdo->query('SELECT id FROM tenants');
        if (!$st) {
            return;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $this->collapseTenant((int) ($row['id'] ?? 0));
        }
    }

    public function collapseTenant(int $tenantId): void
    {
        if ($tenantId < 1 || !$this->hasTable('roles')) {
            return;
        }

        TenantSeedHelper::ensureAccessProfilesForTenant($this->pdo, $tenantId);
        $ids = $this->accessRoleIds($tenantId);
        if ($ids === []) {
            return;
        }

        $this->applyPermissionPacksIfEmpty($tenantId, $ids);
        $this->stripNonAccessRolePermissions($tenantId);
        $this->clearJobRolePermissionLinks($tenantId);
        $this->remapUsers($tenantId, $ids);
        $this->purgeLeftoverTenantRoles($tenantId, $ids);
        $this->restoreDutyPositions($tenantId);
    }

    /**
     * @return array<string, int>
     */
    private function accessRoleIds(int $tenantId): array
    {
        $out = [];
        foreach (CommunityAccessProfiles::definitions() as $def) {
            $id = $this->roleRepository->getIdBySlug($tenantId, $def['slug']);
            if ($id !== null && $id > 0) {
                $out[$def['key']] = $id;
            }
        }

        return $out;
    }

    /**
     * @param array<string, int> $ids
     */
    private function applyPermissionPacksIfEmpty(int $tenantId, array $ids): void
    {
        $permIds = [];
        $q = $this->pdo->prepare('SELECT id, slug FROM permissions WHERE tenant_id = ?');
        $q->execute([$tenantId]);
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $permIds[(string) ($row['slug'] ?? '')] = (int) ($row['id'] ?? 0);
        }

        $countSt = $this->pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?');
        $ins = $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (CommunityAccessProfiles::keys() as $key) {
            $roleId = $ids[$key] ?? 0;
            if ($roleId < 1) {
                continue;
            }
            $countSt->execute([$roleId]);
            if ((int) $countSt->fetchColumn() > 0) {
                continue;
            }
            foreach (CommunityAccessProfiles::permissionSlugsFor($key) as $slug) {
                $pid = $permIds[$slug] ?? 0;
                if ($pid > 0) {
                    $ins->execute([$roleId, $pid]);
                }
            }
        }
    }

    private function stripNonAccessRolePermissions(int $tenantId): void
    {
        $access = CommunityAccessProfiles::slugs();
        $ph = implode(',', array_fill(0, count($access), '?'));
        $like = CommunityAccessProfiles::CUSTOM_PREFIX . '%';
        $this->pdo->prepare(
            "DELETE rp FROM role_permissions rp
             INNER JOIN roles r ON r.id = rp.role_id
             WHERE r.tenant_id = ? AND r.slug NOT IN ($ph) AND r.slug NOT LIKE ?"
        )->execute(array_merge([$tenantId], $access, [$like]));
    }

    private function clearJobRolePermissionLinks(int $tenantId): void
    {
        if (!$this->hasTable('personnel_job_role_permissions') || !$this->hasTable('personnel_job_roles')) {
            return;
        }
        $this->pdo->prepare(
            'DELETE jrp FROM personnel_job_role_permissions jrp
             INNER JOIN personnel_job_roles jr ON jr.id = jrp.personnel_job_role_id
             WHERE jr.tenant_id = ?'
        )->execute([$tenantId]);
    }

    /**
     * @param array<string, int> $ids
     */
    private function remapUsers(int $tenantId, array $ids): void
    {
        $users = $this->pdo->prepare('SELECT id FROM users WHERE tenant_id = ? AND deleted_at IS NULL');
        try {
            $users->execute([$tenantId]);
        } catch (\PDOException) {
            $users = $this->pdo->prepare('SELECT id FROM users WHERE tenant_id = ?');
            $users->execute([$tenantId]);
        }
        $rows = $users->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            $currentIds = $this->userRepository->listOrganizationRoleIdsForUser($userId);
            $slugs = $this->slugsForRoleIds($tenantId, $currentIds);
            if (CommunityAccessProfiles::hasCustomAccessSlug($slugs)) {
                continue;
            }
            $key = CommunityAccessProfiles::resolveFromSlugs($slugs);
            $targetId = $ids[$key] ?? ($ids[CommunityAccessProfiles::MEMBER] ?? 0);
            if ($targetId < 1) {
                continue;
            }
            try {
                $this->userRepository->syncOrganizationRoles($userId, $tenantId, [$targetId], null, true);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param array<string, int> $accessIds
     */
    private function purgeLeftoverTenantRoles(int $tenantId, array $accessIds): void
    {
        $keep = self::retainedTenantSlugs();
        $ph = implode(',', array_fill(0, count($keep), '?'));
        $like = CommunityAccessProfiles::CUSTOM_PREFIX . '%';
        $st = $this->pdo->prepare(
            "SELECT id FROM roles WHERE tenant_id = ? AND slug NOT IN ($ph) AND slug NOT LIKE ?"
        );
        $st->execute(array_merge([$tenantId], $keep, [$like]));
        $leftover = array_values(array_filter(
            array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []),
            static fn (int $id): bool => $id > 0
        ));
        if ($leftover === []) {
            return;
        }

        $in = implode(',', array_fill(0, count($leftover), '?'));
        $fallbackId = $accessIds[CommunityAccessProfiles::MEMBER]
            ?? ($accessIds[CommunityAccessProfiles::MANAGER] ?? 0);

        if ($this->hasColumn('roles', 'parent_role_id')) {
            $this->tryExec(
                "UPDATE roles SET parent_role_id = NULL WHERE tenant_id = ? AND parent_role_id IN ($in)",
                array_merge([$tenantId], $leftover)
            );
        }
        if ($fallbackId > 0 && $this->hasColumn('users', 'role_id')) {
            $this->tryExec(
                "UPDATE users SET role_id = ? WHERE tenant_id = ? AND role_id IN ($in)",
                array_merge([$fallbackId, $tenantId], $leftover)
            );
        }
        if ($this->hasTable('user_profile_display_settings')
            && $this->hasColumn('user_profile_display_settings', 'forum_visible_role_id')) {
            $this->tryExec(
                "UPDATE user_profile_display_settings SET forum_visible_role_id = NULL WHERE forum_visible_role_id IN ($in)",
                $leftover
            );
        }
        if ($this->hasTable('forum_categories') && $this->hasColumn('forum_categories', 'min_role_id')) {
            $this->tryExec(
                "UPDATE forum_categories SET min_role_id = NULL WHERE tenant_id = ? AND min_role_id IN ($in)",
                array_merge([$tenantId], $leftover)
            );
        }

        $this->tryExec(
            "DELETE FROM roles WHERE tenant_id = ? AND id IN ($in)",
            array_merge([$tenantId], $leftover)
        );
    }

    private function restoreDutyPositions(int $tenantId): void
    {
        try {
            $duty = new PersonnelDutyPositionService(
                $this->userRepository,
                $this->roleRepository,
                new MemberIntegrationRepository(),
                new TenantRepository(),
                $this->pdo,
            );
            $duty->backfillTenant($tenantId);
        } catch (\Throwable) {
        }
    }

    /**
     * @param list<int> $roleIds
     * @return list<string>
     */
    private function slugsForRoleIds(int $tenantId, array $roleIds): array
    {
        $roleIds = array_values(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0));
        if ($roleIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT slug FROM roles WHERE tenant_id = ? AND id IN ($ph)"
        );
        $st->execute(array_merge([$tenantId], $roleIds));

        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<mixed> $params
     */
    private function tryExec(string $sql, array $params): void
    {
        try {
            $this->pdo->prepare($sql)->execute($params);
        } catch (\PDOException) {
        }
    }

    private function hasTable(string $table): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}
