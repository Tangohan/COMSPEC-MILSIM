<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Authorization\SystemReservedPermissions;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantFunctionKitRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;

final class PersonnelFunctionKitService
{
    public function __construct(
        private TenantFunctionKitRepository $kitState,
        private PersonnelJobRoleRepository $jobRoles,
        private ?ConfigurationUpdateService $configurationUpdates = null,
        private ?RoleRepository $roles = null,
        private ?PermissionRepository $permissions = null,
        private ?RolePermissionService $rolePermissions = null,
        private ?UserRepository $users = null,
    ) {
        $this->roles ??= new RoleRepository();
        $this->permissions ??= new PermissionRepository();
        $this->rolePermissions ??= new RolePermissionService($this->roles, $this->permissions);
        $this->users ??= new UserRepository();
    }

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   summary: string,
     *   tone: string,
     *   enabled: bool,
     *   key_count: int,
     *   permission_count: int
     * }>
     */
    public function kitsForDisplay(int $tenantId): array
    {
        $selected = array_fill_keys($this->selectedKitIds($tenantId), true);
        $out = [];
        foreach (PersonnelFunctionKitCatalog::all() as $kit) {
            $permCount = count(SystemReservedPermissions::filter($kit['permission_slugs']));
            $out[] = [
                'id' => $kit['id'],
                'label' => $kit['label'],
                'summary' => $kit['summary'],
                'tone' => $kit['tone'],
                'enabled' => isset($selected[$kit['id']]),
                'key_count' => $permCount,
                'permission_count' => $permCount,
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public function selectedKitIds(int $tenantId): array
    {
        $row = $this->kitState->find($tenantId);
        if ($row === null) {
            return [];
        }

        return PersonnelFunctionKitCatalog::normalizeIds($row['kit_ids']);
    }

    public function isReviewed(int $tenantId): bool
    {
        return $this->kitState->hasReviewed($tenantId);
    }

    /**
     * Les kits d’accès n’imposent plus de filtre sur le référentiel d’emplois métier.
     *
     * @return array<string, true>|null
     */
    public function allowedSlugSet(int $tenantId): ?array
    {
        unset($tenantId);

        return null;
    }

    /**
     * @param list<string> $kitIds
     */
    public function save(int $tenantId, array $kitIds, ?int $userId): void
    {
        $normalized = PersonnelFunctionKitCatalog::normalizeIds($kitIds);
        $this->kitState->save($tenantId, $normalized, $userId);
        foreach ($normalized as $kitId) {
            $this->ensureKitCommunityRole($tenantId, $kitId);
        }
        if ($this->configurationUpdates !== null) {
            try {
                $this->configurationUpdates->markCompleted($tenantId, 'FUNCTION_KITS_V1', $userId);
            } catch (\Throwable) {
            }
        }
    }

    public function markReviewedKeepingFullCatalog(int $tenantId, ?int $userId): void
    {
        if ($this->kitState->hasReviewed($tenantId)) {
            return;
        }
        $this->save($tenantId, [], $userId);
    }

    /**
     * @param list<array<string, mixed>> $options
     * @return list<array<string, mixed>>
     */
    public function filterRoleOptions(int $tenantId, array $options): array
    {
        $allowed = $this->allowedSlugSet($tenantId);
        if ($allowed === null) {
            return $options;
        }

        return self::filterOptionsByAllowedSlugs(
            $options,
            $allowed,
            MilitaryOperationalRoleCatalog::catalogSlugSet()
        );
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<array<string, mixed>>
     */
    public function filterRolesWithCategory(int $tenantId, array $roles): array
    {
        $allowed = $this->allowedSlugSet($tenantId);
        if ($allowed === null) {
            return $roles;
        }
        $catalog = MilitaryOperationalRoleCatalog::catalogSlugSet();
        $out = [];
        foreach ($roles as $role) {
            $slug = trim((string) ($role['slug'] ?? ''));
            if ($this->slugPasses($slug, $allowed, $catalog)) {
                $out[] = $role;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $options
     * @param array<string, true> $allowed
     * @param array<string, true> $catalogSlugs
     * @return list<array<string, mixed>>
     */
    public static function filterOptionsByAllowedSlugs(array $options, array $allowed, array $catalogSlugs): array
    {
        $out = [];
        foreach ($options as $opt) {
            $slug = trim((string) ($opt['slug'] ?? ''));
            if (self::slugPassesStatic($slug, $allowed, $catalogSlugs)) {
                $out[] = $opt;
            }
        }

        return $out;
    }

    /**
     * @param array<string, true> $allowed
     * @param array<string, true> $catalogSlugs
     */
    private function slugPasses(string $slug, array $allowed, array $catalogSlugs): bool
    {
        return self::slugPassesStatic($slug, $allowed, $catalogSlugs);
    }

    /**
     * @param array<string, true> $allowed
     * @param array<string, true> $catalogSlugs
     */
    private static function slugPassesStatic(string $slug, array $allowed, array $catalogSlugs): bool
    {
        if ($slug === '' || !isset($catalogSlugs[$slug])) {
            return true;
        }

        return isset($allowed[$slug]);
    }

    /**
     * @return list<array{
     *   slug: string,
     *   name: string,
     *   kit_id: string,
     *   kit_label: string,
     *   tone: string,
     *   summary: string,
     *   permission_count: int,
     *   role_id: int|null,
     *   holders: list<array{user_id: int, display_name: string}>
     * }>
     */
    public function boardForTenant(int $tenantId): array
    {
        $kitIds = $this->selectedKitIds($tenantId);
        if ($kitIds === []) {
            return [];
        }
        $out = [];
        foreach ($kitIds as $kitId) {
            $kit = PersonnelFunctionKitCatalog::find($kitId);
            if ($kit === null) {
                continue;
            }
            $roleId = $this->ensureKitCommunityRole($tenantId, $kitId);
            $out[] = [
                'slug' => $kit['role_slug'],
                'name' => $kit['label'],
                'kit_id' => $kit['id'],
                'kit_label' => $kit['label'],
                'tone' => $kit['tone'],
                'summary' => $kit['summary'],
                'permission_count' => count(SystemReservedPermissions::filter($kit['permission_slugs'])),
                'role_id' => $roleId > 0 ? $roleId : null,
                'holders' => $roleId > 0 ? $this->listHoldersForRole($tenantId, $roleId) : [],
            ];
        }

        return $out;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function assignKitToUser(int $tenantId, string $kitId, int $userId, ?int $actorUserId = null): array
    {
        $kit = PersonnelFunctionKitCatalog::find($kitId);
        if ($kit === null) {
            return ['ok' => false, 'message' => 'Kit inconnu.'];
        }
        if (!in_array($kitId, $this->selectedKitIds($tenantId), true)) {
            return ['ok' => false, 'message' => 'Activez d’abord ce kit avant de l’attribuer.'];
        }
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Membre introuvable dans cette communauté.'];
        }
        $roleId = $this->ensureKitCommunityRole($tenantId, $kitId);
        if ($roleId < 1) {
            return ['ok' => false, 'message' => 'Impossible de préparer le kit d’accès.'];
        }
        try {
            $added = $this->users->addOrganizationRoleIfMissing($userId, $tenantId, $roleId, $actorUserId);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name === '') {
            $name = 'Le membre';
        }

        return [
            'ok' => true,
            'message' => $added
                ? $name . ' dispose maintenant du kit « ' . $kit['label'] . ' ».'
                : $name . ' avait déjà le kit « ' . $kit['label'] . ' ».',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function unassignKitFromUser(int $tenantId, string $kitId, int $userId, ?int $actorUserId = null): array
    {
        $kit = PersonnelFunctionKitCatalog::find($kitId);
        if ($kit === null) {
            return ['ok' => false, 'message' => 'Kit inconnu.'];
        }
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Membre introuvable dans cette communauté.'];
        }
        $roleId = $this->roles->getIdBySlug($tenantId, $kit['role_slug']);
        if ($roleId === null || $roleId < 1) {
            return ['ok' => false, 'message' => 'Ce kit n’est pas encore en place.'];
        }
        $current = $this->users->listOrganizationRoleIdsForUser($userId);
        if (!in_array($roleId, $current, true)) {
            return ['ok' => false, 'message' => 'Ce membre n’a pas ce kit.'];
        }
        $next = array_values(array_filter($current, static fn (int $id): bool => $id !== $roleId));
        try {
            $this->users->syncOrganizationRoles($userId, $tenantId, $next, $actorUserId);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name === '') {
            $name = 'Le membre';
        }

        return [
            'ok' => true,
            'message' => 'Kit « ' . $kit['label'] . ' » retiré pour ' . $name . '.',
        ];
    }

    public function ensureKitCommunityRole(int $tenantId, string $kitId): int
    {
        $kit = PersonnelFunctionKitCatalog::find($kitId);
        if ($kit === null || $tenantId < 1) {
            return 0;
        }
        $roleId = $this->roles->createOrganizationRole(
            $tenantId,
            $kit['label'],
            $kit['role_slug'],
            $kit['summary']
        );
        if ($roleId < 1) {
            return 0;
        }
        $this->roles->updateOrganizationRolePresentation($tenantId, $roleId, $kit['label'], $kit['summary']);
        $permissionIds = $this->permissionIdsForSlugs($tenantId, $kit['permission_slugs']);
        try {
            $this->rolePermissions->setPermissionsForOrganizationTenantRole($tenantId, $roleId, $permissionIds);
        } catch (\Throwable) {
            // Rôle verrouillé ou hors périmètre : on laisse l’attribution UI sans bloquer l’écran.
        }

        return $roleId;
    }

    /**
     * @param list<string> $slugs
     * @return list<int>
     */
    private function permissionIdsForSlugs(int $tenantId, array $slugs): array
    {
        $wanted = array_fill_keys(SystemReservedPermissions::filter($slugs), true);
        if ($wanted === []) {
            return [];
        }
        $ids = [];
        foreach ($this->permissions->allForTenant($tenantId) as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '' && isset($wanted[$slug])) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return list<array{user_id: int, display_name: string}>
     */
    private function listHoldersForRole(int $tenantId, int $roleId): array
    {
        if ($roleId < 1) {
            return [];
        }
        try {
            $members = $this->users->listForTenant($tenantId, null, 'active', null, 500, 0);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($members as $m) {
            $uid = (int) ($m['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            if (!$this->users->userHasTenantRole($uid, $roleId)) {
                continue;
            }
            $name = trim((string) ($m['display_name'] ?? ''));
            $out[] = [
                'user_id' => $uid,
                'display_name' => $name !== '' ? $name : 'Membre',
            ];
        }

        return $out;
    }
}
