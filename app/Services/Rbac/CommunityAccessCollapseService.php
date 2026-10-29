<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Community\TenantSeedHelper;
use App\Services\Personnel\PersonnelDutyPositionService;
use PDO;

/**
 * Convertit une communauté vers les modèles d’accès (Membre / RH / Gestionnaire).
 * Les emplois, rôles personnalisés, droits et attributions de la communauté restent en place.
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
        // Canonical access profiles are additive. Custom roles and all current assignments belong
        // to the organisation and must survive migration replay, bootstrap and tenant-type switches.
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

    private function hasTable(string $table): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }
}
