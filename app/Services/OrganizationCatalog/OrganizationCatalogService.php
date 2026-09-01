<?php

declare(strict_types=1);

namespace App\Services\OrganizationCatalog;

use App\Core\Database;
use App\Repositories\OrganizationCatalogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Services\Admin\TenantRolePermissionPresetService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;
use PDO;

final class OrganizationCatalogService
{
    public function __construct(
        private OrganizationCatalogRepository $catalog,
        private UnitRepository $units,
        private PersonnelJobRoleRepository $jobRoles,
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private TenantRolePermissionPresetService $presets,
        private TenantRepository $tenants,
        private ?AuditService $audit = null,
        private ?ConfigurationUpdateService $configurationUpdates = null,
        private ?PDO $pdo = null,
    ) {
        $this->audit ??= new AuditService();
        $this->pdo ??= Database::getPdo();
    }

    public function ensureOfficialSeeded(): void
    {
        if (!$this->catalog->tablesExist()) {
            return;
        }
        foreach (OrganizationKitDefinitions::officialKits() as $kit) {
            $this->catalog->upsertOfficial(
                (string) $kit['code'],
                (string) $kit['title'],
                (string) ($kit['summary'] ?? ''),
                (int) ($kit['version'] ?? 1),
                $kit
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        $this->ensureOfficialSeeded();
        $rows = $this->catalog->listVisibleForTenant($tenantId);
        $out = [];
        foreach ($rows as $row) {
            $def = $this->decodeDefinition($row);
            if ($def === []) {
                continue;
            }
            $out[] = $this->presentItem($row, $def);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVisibleItem(int $tenantId, string $code, bool $includeArchived = false): ?array
    {
        $this->ensureOfficialSeeded();
        $row = $this->catalog->findByCode($code);
        if ($row === null) {
            return null;
        }
        $visibility = (string) ($row['visibility'] ?? '');
        $owner = (int) ($row['owner_tenant_id'] ?? 0);
        $archived = !empty($row['archived_at']);
        if ($archived && !$includeArchived) {
            return null;
        }
        if ($visibility === 'official') {
            $def = $this->decodeDefinition($row);

            return $def === [] ? null : $this->presentItem($row, $def);
        }
        if ($visibility === 'private' && $owner === $tenantId) {
            $def = $this->decodeDefinition($row);

            return $def === [] ? null : $this->presentItem($row, $def);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listArchivedForTenant(int $tenantId): array
    {
        $out = [];
        foreach ($this->catalog->listArchivedForTenant($tenantId) as $row) {
            $def = $this->decodeDefinition($row);
            if ($def === []) {
                continue;
            }
            $out[] = $this->presentItem($row, $def);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function currentInventory(int $tenantId): array
    {
        $units = $tenantId > 0 ? $this->units->allForTenant($tenantId) : [];
        $roles = $tenantId > 0 ? $this->roles->forTenantOrganization($tenantId) : [];
        $functions = [];
        if ($tenantId > 0 && $this->jobRoles->tablesExist()) {
            $functions = $this->jobRoles->listRolesWithCategory($tenantId);
        }
        $grades = [];
        $gradeCode = '';
        try {
            $settings = $this->tenants->getSettings($tenantId);
            $gradeCode = strtoupper(trim((string) ($settings['grade_system_code'] ?? '')));
            $grades = (new \App\Repositories\GradeRepository())->listForTenant($tenantId);
        } catch (\Throwable) {
        }

        return [
            'unit_count' => count($units),
            'function_count' => count($functions),
            'role_count' => count($roles),
            'grade_count' => count($grades),
            'grade_label' => self::gradeSystemLabel($gradeCode),
            'install_count' => $this->catalog->countInstallsForTenant($tenantId),
        ];
    }

    public static function gradeSystemLabel(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'US_CLASSIC' => 'Système de grades américain',
            'FR_CLASSIC' => 'Système de grades français',
            '' => 'Aucun système de grades choisi',
            default => 'Système de grades déjà en place',
        };
    }

    /**
     * @param array{orbat?: bool, grades?: bool, functions?: bool, roles?: bool} $parts
     * @return array<string, mixed>
     */
    public function preview(int $tenantId, string $code, array $parts = []): array
    {
        $item = $this->getVisibleItem($tenantId, $code);
        if ($item === null) {
            return ['ok' => false, 'error' => 'Ce modèle n’est pas disponible pour votre communauté.'];
        }
        $def = $item['definition'];
        $parts = $this->normalizeParts($parts);
        $dry = $this->applyDefinition($tenantId, $def, $parts, true);

        return [
            'ok' => true,
            'item' => $item,
            'parts' => $parts,
            'report' => $dry['report'],
        ];
    }

    /**
     * @param array{orbat?: bool, grades?: bool, functions?: bool, roles?: bool} $parts
     * @return array{ok: bool, error?: string, report?: array<string, mixed>}
     */
    public function apply(int $tenantId, string $code, array $parts, ?int $userId): array
    {
        $item = $this->getVisibleItem($tenantId, $code);
        if ($item === null) {
            return ['ok' => false, 'error' => 'Ce modèle n’est pas disponible pour votre communauté.'];
        }
        $parts = $this->normalizeParts($parts);
        if (!$parts['orbat'] && !$parts['grades'] && !$parts['functions'] && !$parts['roles']) {
            return ['ok' => false, 'error' => 'Cochez au moins un élément à appliquer : organigramme, grades, fonctions ou rôles.'];
        }
        if (!empty($item['archived'])) {
            return ['ok' => false, 'error' => 'Ce modèle a été retiré. Restaurez-le avant de l’appliquer.'];
        }

        $started = false;
        $report = [];
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }
            $result = $this->applyDefinition($tenantId, $item['definition'], $parts, false);
            $report = $result['report'];
            $report['item_title'] = (string) $item['title'];
            $this->catalog->recordInstall(
                $tenantId,
                (int) $item['id'],
                (int) $item['version'],
                $userId,
                $report
            );
            if ($started) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['ok' => false, 'error' => 'Le modèle n’a pas pu être appliqué. Rien n’a été modifié.'];
        }

        try {
            $this->audit->log(
                AuditAction::ORGANIZATION_CATALOG_APPLIED,
                $tenantId,
                $userId,
                'organization_catalog',
                (int) $item['id'],
                null,
                (string) $item['title']
            );
        } catch (\Throwable) {
        }

        try {
            $this->configurationUpdates ??= \App\Core\Container::get(ConfigurationUpdateService::class);
            $this->configurationUpdates->markCompleted($tenantId, 'ORGANIZATION_CATALOG_V1', $userId);
        } catch (\Throwable) {
        }

        return ['ok' => true, 'report' => $report];
    }

    /**
     * @return array{ok: bool, error?: string, item?: array<string, mixed>}
     */
    public function snapshot(int $tenantId, ?int $userId, string $title = ''): array
    {
        if ($tenantId < 1) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }
        $this->ensureOfficialSeeded();
        $tenant = $this->tenants->findById($tenantId) ?? [];
        $communityName = trim((string) ($tenant['name'] ?? 'cette organisation'));
        $title = trim($title);
        if ($title === '') {
            $title = 'Modèle de ' . $communityName;
        }
        $def = $this->buildSnapshotDefinition($tenantId, $title);
        $code = 'private.' . $tenantId . '.' . date('YmdHis');
        try {
            $id = $this->catalog->createPrivate(
                $tenantId,
                $code,
                $title,
                'Copie de l’organisation au ' . date('d/m/Y') . ' — à réappliquer plus tard, sans lien vivant.',
                1,
                $def
            );
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'L’enregistrement du modèle n’a pas abouti.'];
        }

        try {
            $this->audit->log(
                AuditAction::ORGANIZATION_CATALOG_SNAPSHOT,
                $tenantId,
                $userId,
                'organization_catalog',
                $id,
                null,
                $title
            );
        } catch (\Throwable) {
        }

        $row = $this->catalog->findById($id);
        $item = $row !== null ? $this->presentItem($row, $def) : null;

        return ['ok' => true, 'item' => $item];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function renamePrivate(int $tenantId, string $code, string $title, ?int $userId): array
    {
        $item = $this->getVisibleItem($tenantId, $code, true);
        if ($item === null || !empty($item['official'])) {
            return ['ok' => false, 'error' => 'Seul un modèle de cette organisation peut être renommé.'];
        }
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'Indiquez un nom pour ce modèle.'];
        }
        if (!$this->catalog->renamePrivate($tenantId, $code, $title)) {
            return ['ok' => false, 'error' => 'Le modèle n’a pas pu être renommé.'];
        }
        $this->auditCatalog($tenantId, $userId, AuditAction::ORGANIZATION_CATALOG_RENAMED, (int) $item['id'], $title);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, item?: array<string, mixed>}
     */
    public function refreshPrivate(int $tenantId, string $code, ?int $userId): array
    {
        $item = $this->getVisibleItem($tenantId, $code, true);
        if ($item === null || !empty($item['official'])) {
            return ['ok' => false, 'error' => 'Seul un modèle de cette organisation peut être actualisé.'];
        }
        $title = (string) $item['title'];
        $def = $this->buildSnapshotDefinition($tenantId, $title);
        $version = max(1, (int) $item['version']) + 1;
        $summary = 'Copie actualisée de l’organisation au ' . date('d/m/Y') . ' — à réappliquer plus tard, sans lien vivant.';
        if (!$this->catalog->replacePrivateDefinition($tenantId, $code, $title, $summary, $version, $def)) {
            return ['ok' => false, 'error' => 'L’actualisation n’a pas abouti.'];
        }
        $this->auditCatalog($tenantId, $userId, AuditAction::ORGANIZATION_CATALOG_REFRESHED, (int) $item['id'], $title);
        $row = $this->catalog->findByCode($code);

        return ['ok' => true, 'item' => $row !== null ? $this->presentItem($row, $def) : $item];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function archivePrivate(int $tenantId, string $code, ?int $userId): array
    {
        $item = $this->getVisibleItem($tenantId, $code);
        if ($item === null || !empty($item['official'])) {
            return ['ok' => false, 'error' => 'Seul un modèle de cette organisation peut être retiré du catalogue.'];
        }
        if (!$this->catalog->archivePrivate($tenantId, $code)) {
            return ['ok' => false, 'error' => 'Le modèle n’a pas pu être retiré.'];
        }
        $this->auditCatalog($tenantId, $userId, AuditAction::ORGANIZATION_CATALOG_ARCHIVED, (int) $item['id'], (string) $item['title']);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function restorePrivate(int $tenantId, string $code, ?int $userId): array
    {
        $item = $this->getVisibleItem($tenantId, $code, true);
        if ($item === null || !empty($item['official'])) {
            return ['ok' => false, 'error' => 'Seul un modèle de cette organisation peut être restauré.'];
        }
        if (empty($item['archived'])) {
            return ['ok' => true];
        }
        if (!$this->catalog->restorePrivate($tenantId, $code)) {
            return ['ok' => false, 'error' => 'Le modèle n’a pas pu être restauré.'];
        }
        $this->auditCatalog($tenantId, $userId, AuditAction::ORGANIZATION_CATALOG_RESTORED, (int) $item['id'], (string) $item['title']);

        return ['ok' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentInstalls(int $tenantId): array
    {
        return $this->installHistory($tenantId, 8);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installHistory(int $tenantId, int $limit = 200): array
    {
        $rows = $this->catalog->listInstallsForTenant($tenantId, $limit);
        $actorIds = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['applied_by'] ?? 0);
            if ($uid > 0) {
                $actorIds[] = $uid;
            }
        }
        $actors = [];
        if ($actorIds !== []) {
            try {
                $actors = (new \App\Repositories\UserRepository())->findByIdsForTenant($tenantId, $actorIds);
            } catch (\Throwable) {
                $actors = [];
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->presentInstall($row, $actors);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    private function presentItem(array $row, array $def): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'version' => (int) ($row['version'] ?? 1),
            'visibility' => (string) ($row['visibility'] ?? 'official'),
            'official' => (string) ($row['visibility'] ?? '') === 'official',
            'archived' => !empty($row['archived_at']),
            'archived_at' => (string) ($row['archived_at'] ?? ''),
            'recorded_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'volume' => OrganizationKitDefinitions::volumeLabel($def),
            'unit_count' => count($def['units'] ?? []),
            'function_count' => count($def['job_roles'] ?? []),
            'role_count' => count($def['roles'] ?? []),
            'grade_system_code' => (string) ($def['grade_system_code'] ?? ''),
            'grade_label' => self::gradeSystemLabel((string) ($def['grade_system_code'] ?? '')),
            'unit_outline' => $this->unitOutline($def),
            'function_groups' => $this->functionOutline($def),
            'role_names' => $this->roleNames($def),
            'definition' => $def,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeDefinition(array $row): array
    {
        $raw = $row['definition_json'] ?? '';
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array{orbat?: bool, grades?: bool, functions?: bool, roles?: bool} $parts
     * @return array{orbat: bool, grades: bool, functions: bool, roles: bool}
     */
    public function normalizeParts(array $parts): array
    {
        if ($parts === []) {
            return ['orbat' => true, 'grades' => true, 'functions' => true, 'roles' => true];
        }

        return [
            'orbat' => !empty($parts['orbat']),
            'grades' => !empty($parts['grades']),
            'functions' => !empty($parts['functions']),
            'roles' => !empty($parts['roles']),
        ];
    }

    /**
     * @param array<string, mixed> $def
     * @param array{orbat: bool, grades: bool, functions: bool, roles: bool} $parts
     * @return array{report: array<string, mixed>}
     */
    private function applyDefinition(int $tenantId, array $def, array $parts, bool $dryRun): array
    {
        $unitsAdded = 0;
        $unitsKept = 0;
        $functionsAdded = 0;
        $functionsKept = 0;
        $rolesAdded = 0;
        $rolesKept = 0;
        $unitsAddedNames = [];
        $unitsKeptNames = [];
        $functionsAddedNames = [];
        $functionsKeptNames = [];
        $rolesAddedNames = [];
        $rolesKeptNames = [];
        $gradeNote = 'Non demandé.';

        if ($parts['grades']) {
            $wanted = strtoupper(trim((string) ($def['grade_system_code'] ?? '')));
            $settings = $this->tenants->getSettings($tenantId);
            $current = strtoupper(trim((string) ($settings['grade_system_code'] ?? '')));
            if ($wanted === '') {
                $gradeNote = 'Aucun système de grades dans ce modèle.';
            } elseif ($current === '') {
                if (!$dryRun) {
                    $this->tenants->mergeSettings($tenantId, ['grade_system_code' => $wanted]);
                }
                $gradeNote = $wanted === 'US_CLASSIC'
                    ? 'Système de grades américain retenu.'
                    : 'Système de grades français retenu.';
            } else {
                $gradeNote = 'Système de grades déjà en place, inchangé.';
            }
        }

        if ($parts['orbat']) {
            $keyToId = [];
            $unitRows = is_array($def['units'] ?? null) ? $def['units'] : [];
            $remaining = $unitRows;
            $guard = 0;
            while ($remaining !== [] && $guard++ < 500) {
                $next = [];
                $progress = false;
                foreach ($remaining as $u) {
                    if (!is_array($u)) {
                        continue;
                    }
                    $key = trim((string) ($u['key'] ?? $u['slug'] ?? ''));
                    $parentKey = trim((string) ($u['parent_key'] ?? ''));
                    $parentId = null;
                    if ($parentKey !== '') {
                        if (!isset($keyToId[$parentKey])) {
                            $next[] = $u;
                            continue;
                        }
                        $parentId = $keyToId[$parentKey];
                    }
                    $name = trim((string) ($u['name'] ?? ''));
                    $slug = trim((string) ($u['slug'] ?? ''));
                    if ($name === '') {
                        $progress = true;
                        continue;
                    }
                    $existing = $this->findExistingUnit($tenantId, $slug, $name);
                    if ($existing !== null) {
                        $keyToId[$key !== '' ? $key : $slug] = (int) ($existing['id'] ?? 0);
                        $unitsKept++;
                        $unitsKeptNames[] = $name;
                        $progress = true;
                        continue;
                    }
                    if (!$dryRun) {
                        $created = $this->units->create($tenantId, [
                            'name' => $name,
                            'slug' => $slug !== '' ? $slug : null,
                            'type' => (string) ($u['type'] ?? 'group'),
                            'code' => $u['code'] ?? null,
                            'parent_id' => $parentId,
                            'display_order' => (int) ($u['display_order'] ?? 0),
                        ]);
                        $newId = (int) ($created['id'] ?? 0);
                    } else {
                        $newId = 0;
                    }
                    $keyToId[$key !== '' ? $key : $slug] = $newId;
                    $unitsAdded++;
                    $unitsAddedNames[] = $name;
                    $progress = true;
                }
                if (!$progress) {
                    break;
                }
                $remaining = $next;
            }
        }

        /** @var array<string, int> $catKeyToId */
        $catKeyToId = [];
        if ($parts['functions'] && $this->jobRoles->tablesExist()) {
            $catRows = is_array($def['job_role_categories'] ?? null) ? $def['job_role_categories'] : [];
            $remaining = $catRows;
            $guard = 0;
            while ($remaining !== [] && $guard++ < 200) {
                $next = [];
                $progress = false;
                foreach ($remaining as $cat) {
                    if (!is_array($cat)) {
                        continue;
                    }
                    $key = trim((string) ($cat['key'] ?? $cat['slug'] ?? ''));
                    $parentKey = trim((string) ($cat['parent_key'] ?? ''));
                    $parentId = null;
                    if ($parentKey !== '') {
                        if (!isset($catKeyToId[$parentKey])) {
                            $next[] = $cat;
                            continue;
                        }
                        $parentId = $catKeyToId[$parentKey];
                    }
                    $name = trim((string) ($cat['name'] ?? ''));
                    $slug = trim((string) ($cat['slug'] ?? ''));
                    if ($name === '' || $slug === '') {
                        $progress = true;
                        continue;
                    }
                    $existingId = $this->jobRoles->findCategoryIdBySlug($tenantId, $slug);
                    if ($existingId !== null) {
                        $catKeyToId[$key !== '' ? $key : $slug] = $existingId;
                        $progress = true;
                        continue;
                    }
                    if (!$dryRun) {
                        $newId = $this->jobRoles->createCategory(
                            $tenantId,
                            $parentId,
                            $name,
                            $slug,
                            (int) ($cat['sort_order'] ?? 0)
                        );
                    } else {
                        $newId = 0;
                    }
                    $catKeyToId[$key !== '' ? $key : $slug] = $newId;
                    $progress = true;
                }
                if (!$progress) {
                    break;
                }
                $remaining = $next;
            }

            foreach (is_array($def['job_roles'] ?? null) ? $def['job_roles'] : [] as $jr) {
                if (!is_array($jr)) {
                    continue;
                }
                $slug = trim((string) ($jr['slug'] ?? ''));
                $name = trim((string) ($jr['name'] ?? ''));
                $catKey = trim((string) ($jr['category_key'] ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }
                if ($this->jobRoles->findRoleIdBySlug($tenantId, $slug) !== null) {
                    $functionsKept++;
                    $functionsKeptNames[] = $name;
                    continue;
                }
                $categoryId = $catKeyToId[$catKey] ?? 0;
                if ($categoryId < 1 && !$dryRun) {
                    continue;
                }
                if (!$dryRun) {
                    $this->jobRoles->createRole(
                        $tenantId,
                        $categoryId,
                        $name,
                        $slug,
                        isset($jr['description']) ? (string) $jr['description'] : null,
                        (int) ($jr['sort_order'] ?? 0),
                        false
                    );
                }
                $functionsAdded++;
                $functionsAddedNames[] = $name;
            }
        }

        if ($parts['roles']) {
            foreach (is_array($def['roles'] ?? null) ? $def['roles'] : [] as $role) {
                if (!is_array($role)) {
                    continue;
                }
                $slug = trim((string) ($role['slug'] ?? ''));
                $name = trim((string) ($role['name'] ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }
                $existingId = $this->roles->getIdBySlug($tenantId, $slug);
                if ($existingId !== null) {
                    $rolesKept++;
                    $rolesKeptNames[] = $name;
                    continue;
                }
                $byName = $this->findCommunityRoleByName($tenantId, $name);
                if ($byName !== null) {
                    $rolesKept++;
                    $rolesKeptNames[] = $name;
                    continue;
                }
                if (!$dryRun) {
                    $newId = $this->roles->createOrganizationRole(
                        $tenantId,
                        $name,
                        $slug,
                        isset($role['description']) ? (string) $role['description'] : null
                    );
                    $preset = trim((string) ($role['preset'] ?? ''));
                    if ($newId > 0 && $preset !== '') {
                        $permIds = $this->presets->getPermissionIdsForPreset($tenantId, $preset);
                        if ($permIds !== []) {
                            $this->permissions->setPermissionsForRole($newId, $permIds);
                        }
                    }
                }
                $rolesAdded++;
                $rolesAddedNames[] = $name;
            }
        }

        $summary = $this->humanReport(
            $parts,
            $unitsAdded,
            $unitsKept,
            $functionsAdded,
            $functionsKept,
            $rolesAdded,
            $rolesKept,
            $gradeNote
        );

        return [
            'report' => [
                'summary' => $summary,
                'units_added' => $unitsAdded,
                'units_kept' => $unitsKept,
                'functions_added' => $functionsAdded,
                'functions_kept' => $functionsKept,
                'roles_added' => $rolesAdded,
                'roles_kept' => $rolesKept,
                'grades' => $gradeNote,
                'units_added_names' => $unitsAddedNames,
                'units_kept_names' => $unitsKeptNames,
                'functions_added_names' => $functionsAddedNames,
                'functions_kept_names' => $functionsKeptNames,
                'roles_added_names' => $rolesAddedNames,
                'roles_kept_names' => $rolesKeptNames,
                'parts' => [
                    'organigramme' => $parts['orbat'],
                    'grades' => $parts['grades'],
                    'fonctions' => $parts['functions'],
                    'roles' => $parts['roles'],
                ],
            ],
        ];
    }

    private function findExistingUnit(int $tenantId, string $slug, string $name): ?array
    {
        if ($slug !== '') {
            $bySlug = $this->units->findBySlugForTenant($tenantId, $slug);
            if (is_array($bySlug)) {
                return $bySlug;
            }
        }
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }
        foreach ($this->units->allForTenant($tenantId) as $row) {
            if (mb_strtolower(trim((string) ($row['name'] ?? ''))) === $needle) {
                return $row;
            }
        }

        return null;
    }

    private function findCommunityRoleByName(int $tenantId, string $name): ?array
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }
        foreach ($this->roles->forTenantOrganization($tenantId) as $row) {
            if (mb_strtolower(trim((string) ($row['name'] ?? ''))) === $needle) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array{orbat: bool, grades: bool, functions: bool, roles: bool} $parts
     */
    private function humanReport(
        array $parts,
        int $unitsAdded,
        int $unitsKept,
        int $functionsAdded,
        int $functionsKept,
        int $rolesAdded,
        int $rolesKept,
        string $gradeNote
    ): string {
        $bits = [];
        if ($parts['orbat']) {
            $bits[] = $this->countPhrase($unitsAdded, 'unité ajoutée', 'unités ajoutées')
                . ', '
                . $this->countPhrase($unitsKept, 'déjà présente, inchangée', 'déjà présentes, inchangées');
        }
        if ($parts['functions']) {
            $bits[] = $this->countPhrase($functionsAdded, 'fonction ajoutée', 'fonctions ajoutées')
                . ', '
                . $this->countPhrase($functionsKept, 'déjà présente, inchangée', 'déjà présentes, inchangées');
        }
        if ($parts['roles']) {
            $bits[] = $this->countPhrase($rolesAdded, 'rôle ajouté', 'rôles ajoutés')
                . ', '
                . $this->countPhrase($rolesKept, 'déjà présent, inchangé', 'déjà présents, inchangés');
        }
        if ($parts['grades']) {
            $bits[] = $gradeNote;
        }

        return implode(' ', $bits);
    }

    private function countPhrase(int $n, string $one, string $many): string
    {
        if ($n === 1) {
            return '1 ' . $one;
        }

        return $n . ' ' . $many;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshotDefinition(int $tenantId, string $title): array
    {
        $settings = $this->tenants->getSettings($tenantId);
        $units = [];
        foreach ($this->units->allForTenant($tenantId) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $units[] = [
                'key' => 'u' . $id,
                'parent_key' => !empty($row['parent_id']) ? 'u' . (int) $row['parent_id'] : null,
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'type' => (string) ($row['type'] ?? 'group'),
                'code' => $row['code'] ?? null,
                'display_order' => (int) ($row['display_order'] ?? 0),
            ];
        }

        $categories = [];
        $jobRoles = [];
        if ($this->jobRoles->tablesExist()) {
            foreach ($this->jobRoles->listCategories($tenantId) as $cat) {
                $id = (int) ($cat['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $categories[] = [
                    'key' => 'c' . $id,
                    'parent_key' => !empty($cat['parent_id']) ? 'c' . (int) $cat['parent_id'] : null,
                    'name' => (string) ($cat['name'] ?? ''),
                    'slug' => (string) ($cat['slug'] ?? ''),
                    'sort_order' => (int) ($cat['sort_order'] ?? 0),
                ];
            }
            foreach ($this->jobRoles->listRolesWithCategory($tenantId) as $jr) {
                $jobRoles[] = [
                    'category_key' => !empty($jr['category_id']) ? 'c' . (int) $jr['category_id'] : '',
                    'name' => (string) ($jr['name'] ?? ''),
                    'slug' => (string) ($jr['slug'] ?? ''),
                    'description' => isset($jr['description']) ? (string) $jr['description'] : null,
                    'sort_order' => (int) ($jr['sort_order'] ?? 0),
                ];
            }
        }

        $roles = [];
        foreach ($this->roles->forTenantOrganization($tenantId) as $role) {
            if (!empty($role['is_system']) || !empty($role['is_locked'])) {
                continue;
            }
            $roles[] = [
                'name' => (string) ($role['name'] ?? ''),
                'slug' => (string) ($role['slug'] ?? ''),
                'description' => isset($role['description']) ? (string) $role['description'] : null,
                'preset' => '',
            ];
        }

        return [
            'code' => '',
            'title' => $title,
            'summary' => 'Instantané local de l’organisation.',
            'version' => 1,
            'grade_system_code' => strtoupper(trim((string) ($settings['grade_system_code'] ?? ''))),
            'units' => $units,
            'job_role_categories' => $categories,
            'job_roles' => $jobRoles,
            'roles' => $roles,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $actors
     * @return array<string, mixed>
     */
    private function presentInstall(array $row, array $actors): array
    {
        $report = json_decode((string) ($row['report_json'] ?? ''), true);
        if (!is_array($report)) {
            $report = [];
        }
        $uid = (int) ($row['applied_by'] ?? 0);
        $whenRaw = (string) ($row['applied_at'] ?? '');
        $whenTs = $whenRaw !== '' ? strtotime($whenRaw) : false;
        $parts = is_array($report['parts'] ?? null) ? $report['parts'] : [];
        $partLabels = [];
        if (!empty($parts['organigramme'])) {
            $partLabels[] = 'Organigramme';
        }
        if (!empty($parts['grades'])) {
            $partLabels[] = 'Grades';
        }
        if (!empty($parts['fonctions'])) {
            $partLabels[] = 'Fonctions';
        }
        if (!empty($parts['roles'])) {
            $partLabels[] = 'Rôles';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) (($row['item_title'] ?? '') !== '' ? $row['item_title'] : ($report['item_title'] ?? 'Modèle')),
            'code' => (string) ($row['item_code'] ?? ''),
            'applied_at' => $whenRaw,
            'applied_at_label' => $whenTs !== false ? date('d/m/Y H:i', $whenTs) : '',
            'actor' => $this->actorLabel($actors[$uid] ?? null, $uid),
            'summary' => trim((string) ($report['summary'] ?? '')),
            'units_added' => (int) ($report['units_added'] ?? 0),
            'units_kept' => (int) ($report['units_kept'] ?? 0),
            'functions_added' => (int) ($report['functions_added'] ?? 0),
            'functions_kept' => (int) ($report['functions_kept'] ?? 0),
            'roles_added' => (int) ($report['roles_added'] ?? 0),
            'roles_kept' => (int) ($report['roles_kept'] ?? 0),
            'grades' => (string) ($report['grades'] ?? ''),
            'units_added_names' => $this->stringList($report['units_added_names'] ?? []),
            'units_kept_names' => $this->stringList($report['units_kept_names'] ?? []),
            'functions_added_names' => $this->stringList($report['functions_added_names'] ?? []),
            'functions_kept_names' => $this->stringList($report['functions_kept_names'] ?? []),
            'roles_added_names' => $this->stringList($report['roles_added_names'] ?? []),
            'roles_kept_names' => $this->stringList($report['roles_kept_names'] ?? []),
            'parts_labels' => $partLabels,
            'model_available' => trim((string) ($row['item_code'] ?? '')) !== '' && empty($row['item_archived_at']),
            'source_version' => (int) ($row['source_version'] ?? 0),
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function actorLabel(?array $user, int $userId): string
    {
        if ($userId < 1) {
            return 'Système';
        }
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return 'Un membre de l’organisation';
    }

    /**
     * @param array<string, mixed> $def
     * @return list<array{name: string, depth: int}>
     */
    private function unitOutline(array $def): array
    {
        $rows = is_array($def['units'] ?? null) ? $def['units'] : [];
        $byKey = [];
        $children = [];
        $roots = [];
        foreach ($rows as $i => $u) {
            if (!is_array($u)) {
                continue;
            }
            $key = trim((string) ($u['key'] ?? $u['slug'] ?? ('u' . $i)));
            $byKey[$key] = $u;
            $parent = trim((string) ($u['parent_key'] ?? ''));
            if ($parent === '' || $parent === $key) {
                $roots[] = $key;
            } else {
                $children[$parent][] = $key;
            }
        }
        $seenAsChild = [];
        foreach ($children as $kids) {
            foreach ($kids as $k) {
                $seenAsChild[(string) $k] = true;
            }
        }
        foreach ($byKey as $key => $_) {
            if (!isset($seenAsChild[$key]) && !in_array($key, $roots, true)) {
                $roots[] = $key;
            }
        }
        $out = [];
        $walk = function (string $key, int $depth) use (&$walk, &$out, $byKey, $children): void {
            $u = $byKey[$key] ?? null;
            if (!is_array($u)) {
                return;
            }
            $name = trim((string) ($u['name'] ?? ''));
            if ($name !== '') {
                $out[] = ['name' => $name, 'depth' => $depth];
            }
            foreach ($children[$key] ?? [] as $child) {
                $walk((string) $child, $depth + 1);
            }
        };
        foreach ($roots as $root) {
            $walk((string) $root, 0);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     * @return list<array{category: string, names: list<string>}>
     */
    private function functionOutline(array $def): array
    {
        $cats = [];
        foreach (is_array($def['job_role_categories'] ?? null) ? $def['job_role_categories'] : [] as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $key = trim((string) ($cat['key'] ?? $cat['slug'] ?? ''));
            $name = trim((string) ($cat['name'] ?? ''));
            if ($key !== '' && $name !== '') {
                $cats[$key] = $name;
            }
        }
        $grouped = [];
        foreach (is_array($def['job_roles'] ?? null) ? $def['job_roles'] : [] as $jr) {
            if (!is_array($jr)) {
                continue;
            }
            $name = trim((string) ($jr['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $catKey = trim((string) ($jr['category_key'] ?? ''));
            $catName = $cats[$catKey] ?? 'Autres fonctions';
            $grouped[$catName][] = $name;
        }
        $out = [];
        foreach ($grouped as $category => $names) {
            $out[] = ['category' => $category, 'names' => $names];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $def
     * @return list<string>
     */
    private function roleNames(array $def): array
    {
        $out = [];
        foreach (is_array($def['roles'] ?? null) ? $def['roles'] : [] as $role) {
            if (!is_array($role)) {
                continue;
            }
            $name = trim((string) ($role['name'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    private function auditCatalog(int $tenantId, ?int $userId, string $action, int $itemId, string $title): void
    {
        try {
            $this->audit->log($action, $tenantId, $userId, 'organization_catalog', $itemId, null, $title);
        } catch (\Throwable) {
        }
    }
}
