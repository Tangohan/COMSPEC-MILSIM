<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\TenantFunctionKitRepository;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;

final class PersonnelFunctionKitService
{
    public function __construct(
        private TenantFunctionKitRepository $kitState,
        private PersonnelJobRoleRepository $jobRoles,
        private ?ConfigurationUpdateService $configurationUpdates = null,
    ) {}

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   summary: string,
     *   enabled: bool,
     *   key_count: int
     * }>
     */
    public function kitsForDisplay(int $tenantId): array
    {
        $selected = array_fill_keys($this->selectedKitIds($tenantId), true);
        $out = [];
        foreach (PersonnelFunctionKitCatalog::all() as $kit) {
            $out[] = [
                'id' => $kit['id'],
                'label' => $kit['label'],
                'summary' => $kit['summary'],
                'enabled' => isset($selected[$kit['id']]),
                'key_count' => count($kit['key_slugs']),
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
     * Null = aucune restriction (catalogue complet).
     *
     * @return array<string, true>|null
     */
    public function allowedSlugSet(int $tenantId): ?array
    {
        $ids = $this->selectedKitIds($tenantId);
        if ($ids === []) {
            return null;
        }
        $set = [];
        foreach (PersonnelFunctionKitCatalog::slugsForKitIds($ids) as $slug) {
            $set[$slug] = true;
        }
        foreach (PersonnelFunctionKitCatalog::visualOnlySlugs() as $slug) {
            $set[$slug] = true;
        }
        foreach ($this->jobRoles->listAssignedJobRoleSlugsForTenant($tenantId) as $slug) {
            $set[$slug] = true;
        }

        return $set;
    }

    /**
     * @param list<string> $kitIds
     */
    public function save(int $tenantId, array $kitIds, ?int $userId): void
    {
        $normalized = PersonnelFunctionKitCatalog::normalizeIds($kitIds);
        $this->kitState->save($tenantId, $normalized, $userId);
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
     *   role_id: int|null,
     *   holders: list<array{user_id: int, display_name: string}>
     * }>
     */
    public function boardForTenant(int $tenantId): array
    {
        $kitIds = $this->selectedKitIds($tenantId);
        $keys = PersonnelFunctionKitCatalog::keyFunctionsForKitIds($kitIds);
        if ($keys === []) {
            return [];
        }
        $roleIdsBySlug = [];
        foreach ($keys as $key) {
            $rid = $this->jobRoles->findRoleIdBySlug($tenantId, $key['slug']);
            $roleIdsBySlug[$key['slug']] = $rid;
        }
        $roleIds = array_values(array_filter($roleIdsBySlug, static fn (?int $id): bool => $id !== null && $id > 0));
        $holdersByRole = $this->jobRoles->listHoldersByJobRoleIds($tenantId, $roleIds);
        $out = [];
        foreach ($keys as $key) {
            $rid = $roleIdsBySlug[$key['slug']] ?? null;
            $out[] = [
                'slug' => $key['slug'],
                'name' => $key['name'],
                'kit_id' => $key['kit_id'],
                'kit_label' => $key['kit_label'],
                'role_id' => $rid,
                'holders' => $rid !== null && $rid > 0 ? ($holdersByRole[$rid] ?? []) : [],
            ];
        }

        return $out;
    }
}
