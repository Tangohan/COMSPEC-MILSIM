<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\Admin\TenantRolePermissionPresetService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Rbac\RoleCoherenceValidator;

class RoleAdminController
{
    public function __construct(
        private RolePermissionService $rolePermissionService,
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
        private TenantRolePermissionPresetService $presetService,
        private TenantRepository $tenantRepository,
        private ?AuditService $auditService = null
    ) {
        $this->auditService ??= new AuditService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $layer = trim((string) $request->query('layer', ''));
        $roles = match ($layer) {
            'community' => $this->rolePermissionService->listOrganizationRolesByLayer($tenantId, 'community'),
            'intra' => $this->rolePermissionService->listOrganizationRolesByLayer($tenantId, 'intra'),
            default => $this->rolePermissionService->listOrganizationRoles($tenantId),
        };

        $tierFilter = trim((string) $request->query('tier', ''));
        $validTiers = ['authority', 'function', 'specialty', 'status', 'support', 'liaison'];
        if ($tierFilter !== '' && !in_array($tierFilter, $validTiers, true)) {
            $tierFilter = '';
        }
        if ($tierFilter !== '') {
            $roles = array_values(array_filter(
                $roles,
                static function (array $r) use ($tierFilter): bool {
                    $t = (string) ($r['semantic_tier'] ?? 'function');

                    return $t === $tierFilter;
                }
            ));
        }

        $roleIds = array_values(array_filter(
            array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $roles),
            static fn (int $id): bool => $id > 0
        ));
        $permissionCounts = $this->roleRepository->countPermissionsByRoleIds($roleIds);
        $memberCounts = $this->roleRepository->countMembersByRoleIds($tenantId, $roleIds);

        $roleViewSections = $this->buildRoleViewSections($roles);

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.index',
            'title' => 'Rôles communauté',
            'roles' => $roles,
            'permissionCounts' => $permissionCounts,
            'memberCounts' => $memberCounts,
            'roleLayerFilter' => $layer,
            'roleTierFilter' => $tierFilter,
            'roleViewSections' => $roleViewSections,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $roles
     *
     * @return list<array{kind: string, title: string, category?: string, subcategory?: string, roles: list<array<string, mixed>>}>
     */
    private function buildRoleViewSections(array $roles): array
    {
        $ungroupedIntraKey = "\0other_intra\0";
        $community = [];
        /** @var array<string, list<array<string, mixed>>> $intraBuckets */
        $intraBuckets = [];
        foreach ($roles as $r) {
            if (($r['role_layer'] ?? '') === 'community') {
                $community[] = $r;
                continue;
            }
            $cat = trim((string) ($r['category'] ?? ''));
            $sub = trim((string) ($r['subcategory'] ?? ''));
            if ($cat === '' && $sub === '') {
                $intraBuckets[$ungroupedIntraKey][] = $r;
            } else {
                $k = $cat . "\n" . $sub;
                $intraBuckets[$k][] = $r;
            }
        }

        $sections = [];
        if ($community !== []) {
            $sections[] = ['kind' => 'community', 'title' => 'Gouvernance de la communauté', 'roles' => $community];
        }
        foreach ($intraBuckets as $k => $list) {
            if ($k === $ungroupedIntraKey) {
                $sections[] = ['kind' => 'flat', 'title' => 'Autres rôles opérationnels', 'roles' => $list];

                continue;
            }
            $parts = explode("\n", $k, 2);
            $sections[] = [
                'kind' => 'group',
                'title' => ($parts[0] ?? '') . ' — ' . ($parts[1] ?? ''),
                'category' => $parts[0] ?? '',
                'subcategory' => $parts[1] ?? '',
                'roles' => $list,
            ];
        }

        return $sections;
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);
        $role = null;
        foreach ($roles as $r) {
            if (!is_array($r)) {
                continue;
            }
            if ((int) ($r['id'] ?? 0) === $id) {
                $role = $r;
                break;
            }
        }
        if (!is_array($role)) {
            Session::flash('error', 'Rôle introuvable.');

            return Response::redirect(url('back-office/roles'));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allForTenant($tenantId);
        $rolePermissions = array_values(array_filter(
            $allPermissions,
            static fn ($p): bool => is_array($p) && in_array((int) ($p['id'] ?? 0), $permissionIds, true)
        ));

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.show',
            'title' => 'Rôle : ' . (string) ($role['name'] ?? ''),
            'role' => $role,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    /**
     * Profils prédéfinis : applique un jeu complet de permissions à un rôle communauté / intra.
     */
    public function presets(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Vous n’avez pas la permission de gérer les profils de droits.');

            return Response::redirect(url('dashboard'));
        }

        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.presets',
            'title' => 'Profils de permissions',
            'presetMeta' => $this->presetService->listPresetMeta(),
            'customPresetKits' => $this->listCustomPresetKits($tenantId),
            'allPermissions' => $this->permissionRepository->allForTenant($tenantId),
            'roles' => $roles,
            'presetsPreviewUrl' => url('back-office/roles/presets/preview'),
        ]);
    }

    /**
     * Aperçu JSON : ajouts / retraits si l’on applique un profil à un rôle (sans modification).
     */
    public function presetsPreview(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Reconnectez-vous.'], 401);
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            return Response::json(['ok' => false, 'error' => 'Permission refusée.'], 403);
        }

        $roleId = (int) $request->query('role_id', 0);
        $presetId = trim((string) $request->query('preset_id', ''));
        if ($roleId < 1 || $presetId === '') {
            return Response::json(['ok' => false, 'error' => 'Choisissez un rôle et un profil pour afficher le récapitulatif.'], 400);
        }

        $role = $this->roleRepository->findById($roleId, $tenantId);
        if (!$role || !in_array((string) ($role['role_layer'] ?? ''), ['community', 'intra'], true)) {
            return Response::json(['ok' => false, 'error' => 'Rôle introuvable ou hors périmètre de votre communauté.'], 404);
        }
        if ($this->rolePermissionService->isRoleLocked($roleId)) {
            return Response::json(['ok' => false, 'error' => 'Ce rôle est verrouillé : un profil automatique ne peut pas s’y appliquer.'], 403);
        }

        $rows = $this->permissionRepository->allForTenant($tenantId);
        $current = $this->rolePermissionService->getPermissionIdsForRole($roleId);
        $resolved = $this->resolvePresetDetails($tenantId, $presetId, $rows);
        if (!$resolved['ok']) {
            return Response::json(['ok' => false, 'error' => $resolved['error'] ?? 'Profil invalide.'], 400);
        }
        $diff = $this->buildDiffFromPermissionIds($current, $resolved['permission_ids'], $rows);
        $presetLabel = (string) ($resolved['label'] ?? $presetId);
        $presetDescription = (string) ($resolved['description'] ?? '');

        return Response::json([
            'ok' => true,
            'role_name' => (string) ($role['name'] ?? ''),
            'preset_id' => $presetId,
            'preset_label' => $presetLabel,
            'preset_description' => $presetDescription,
            'module_labels' => TenantRolePermissionPresetService::permissionModuleLabelsFr(),
            'diff' => $diff,
        ]);
    }

    public function presetsApply(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $presetId = trim((string) $request->input('preset_id'));
        $roleId = (int) $request->input('role_id');
        if ($presetId === '') {
            Session::flash('error', 'Profil invalide.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        if ($roleId < 1) {
            Session::flash('error', 'Choisissez un rôle.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $role = $this->roleRepository->findById($roleId, $tenantId);
        if (!$role) {
            Session::flash('error', 'Rôle introuvable pour cette communauté.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        if ($this->rolePermissionService->isRoleLocked($roleId)) {
            Session::flash('error', 'Ce rôle est verrouillé : les permissions ne peuvent pas être remplacées par un profil.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $rows = $this->permissionRepository->allForTenant($tenantId);
        $currentIds = $this->rolePermissionService->getPermissionIdsForRole($roleId);
        $resolved = $this->resolvePresetDetails($tenantId, $presetId, $rows);
        if (!$resolved['ok']) {
            Session::flash('error', $resolved['error'] ?? 'Profil invalide.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $ids = $resolved['permission_ids'];
        if ($ids === []) {
            Session::flash('error', 'Aucune habilitation correspondante dans cette communauté (migrations ou catalogue à jour ?).');

            return Response::redirect(url('back-office/roles/presets'));
        }

        try {
            $this->rolePermissionService->setPermissionsForOrganizationTenantRole($tenantId, $roleId, $ids);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/roles/presets'));
        }

        $diff = $this->buildDiffFromPermissionIds($currentIds, $ids, $rows);

        $presetLabel = (string) ($resolved['label'] ?? $presetId);

        $roleName = (string) ($role['name'] ?? '');
        $msg = 'Rôle « ' . $roleName . ' » mis à jour avec le profil « ' . $presetLabel . ' ». ';
        $msg .= $diff['added_count'] . ' habilitation(s) ajoutée(s), '
            . $diff['removed_count'] . ' retirée(s), '
            . $diff['unchanged_count'] . ' inchangée(s). '
            . 'Total après application : ' . $diff['preset_total'] . '.';
        if ($diff['removed_count'] > 0) {
            $msg .= ' Les droits retirés ne sont plus actifs pour ce rôle.';
        }
        Session::flash('success', $msg);

        return Response::redirect(url('back-office/roles/' . $roleId));
    }

    public function saveCustomPresetKit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $label = trim((string) $request->input('kit_label', ''));
        $description = trim((string) $request->input('kit_description', ''));
        $rawPermissionIds = $request->input('kit_permission_ids', []);
        $permissionIds = is_array($rawPermissionIds) ? array_values(array_unique(array_map('intval', $rawPermissionIds))) : [];
        $allowedPermissionIds = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $this->permissionRepository->allForTenant($tenantId));
        $permissionIds = array_values(array_intersect($permissionIds, $allowedPermissionIds));

        if ($label === '' || mb_strlen($label) > 90) {
            Session::flash('error', 'Le nom du kit est requis (90 caractères max).');

            return Response::redirect(url('back-office/roles/presets'));
        }
        if ($permissionIds === []) {
            Session::flash('error', 'Sélectionnez au moins une permission pour créer le kit.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $kits = $this->listCustomPresetKits($tenantId);
        if (count($kits) >= 24) {
            Session::flash('error', 'Limite atteinte : 24 kits personnalisés maximum.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $kitId = 'custom_' . substr(sha1((string) microtime(true) . ':' . $label . ':' . (string) random_int(1, 999999)), 0, 10);
        $kits[] = [
            'id' => $kitId,
            'label' => $label,
            'description' => $description,
            'permission_ids' => $permissionIds,
            'created_at' => date('c'),
        ];
        $this->saveCustomPresetKits($tenantId, $kits);
        Session::flash('success', 'Kit personnalisé enregistré.');

        return Response::redirect(url('back-office/roles/presets'));
    }

    public function deleteCustomPresetKit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId || !$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        $kitId = trim((string) $request->input('kit_id', ''));
        if ($kitId === '') {
            Session::flash('error', 'Kit invalide.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $kits = $this->listCustomPresetKits($tenantId);
        $before = count($kits);
        $kits = array_values(array_filter($kits, static fn (array $k): bool => (string) ($k['id'] ?? '') !== $kitId));
        if (count($kits) === $before) {
            Session::flash('error', 'Kit introuvable.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        $this->saveCustomPresetKits($tenantId, $kits);
        Session::flash('success', 'Kit personnalisé supprimé.');

        return Response::redirect(url('back-office/roles/presets'));
    }

    /**
     * @param list<array<string, mixed>> $tenantPermissionRows
     * @return array{ok: bool, permission_ids?: list<int>, label?: string, description?: string, error?: string}
     */
    private function resolvePresetDetails(int $tenantId, string $presetId, array $tenantPermissionRows): array
    {
        $builtinIds = array_column($this->presetService->listPresetMeta(), 'id');
        if (in_array($presetId, $builtinIds, true)) {
            $label = $presetId;
            $description = '';
            foreach ($this->presetService->listPresetMeta() as $m) {
                if (($m['id'] ?? '') === $presetId) {
                    $label = (string) ($m['label'] ?? $presetId);
                    $description = (string) ($m['description'] ?? '');
                    break;
                }
            }

            return [
                'ok' => true,
                'permission_ids' => $this->presetService->permissionIdsForPresetFromTenantRows($presetId, $tenantPermissionRows),
                'label' => $label,
                'description' => $description,
            ];
        }

        if (str_starts_with($presetId, 'custom:')) {
            $target = substr($presetId, strlen('custom:'));
            foreach ($this->listCustomPresetKits($tenantId) as $kit) {
                if ((string) ($kit['id'] ?? '') !== $target) {
                    continue;
                }
                $ids = is_array($kit['permission_ids'] ?? null) ? array_values(array_unique(array_map('intval', $kit['permission_ids']))) : [];

                return [
                    'ok' => true,
                    'permission_ids' => $ids,
                    'label' => (string) ($kit['label'] ?? $presetId),
                    'description' => (string) ($kit['description'] ?? ''),
                ];
            }
        }

        return ['ok' => false, 'error' => 'Profil introuvable.'];
    }

    /**
     * @param list<int> $currentIds
     * @param list<int> $targetIds
     * @param list<array<string,mixed>> $tenantPermissionRows
     * @return array{ok: true, added: list<array{id: int, name: string, module: string, slug: string}>, removed: list<array{id: int, name: string, module: string, slug: string}>, added_by_module: array<string, list<array{id: int, name: string, module: string, slug: string}>>, removed_by_module: array<string, list<array{id: int, name: string, module: string, slug: string}>>, unchanged_count: int, current_total: int, preset_total: int, added_count: int, removed_count: int}
     */
    private function buildDiffFromPermissionIds(array $currentIds, array $targetIds, array $tenantPermissionRows): array
    {
        $currentSet = array_flip(array_values(array_unique(array_map('intval', $currentIds))));
        $targetSet = array_flip(array_values(array_unique(array_map('intval', $targetIds))));
        $byId = [];
        foreach ($tenantPermissionRows as $r) {
            $pid = (int) ($r['id'] ?? 0);
            if ($pid > 0) {
                $byId[$pid] = [
                    'id' => $pid,
                    'name' => (string) ($r['name'] ?? ''),
                    'module' => (string) ($r['module'] ?? 'autre'),
                    'slug' => (string) ($r['slug'] ?? ''),
                ];
            }
        }
        $added = [];
        $removed = [];
        foreach (array_keys($targetSet) as $id) {
            if (!isset($currentSet[$id])) {
                $added[] = $byId[$id] ?? ['id' => $id, 'name' => 'Permission #' . $id, 'module' => 'autre', 'slug' => ''];
            }
        }
        foreach (array_keys($currentSet) as $id) {
            if (!isset($targetSet[$id])) {
                $removed[] = $byId[$id] ?? ['id' => $id, 'name' => 'Permission #' . $id, 'module' => 'autre', 'slug' => ''];
            }
        }

        $group = static function (array $rows): array {
            $out = [];
            foreach ($rows as $r) {
                $m = (string) ($r['module'] ?? 'autre');
                $out[$m][] = $r;
            }
            ksort($out);

            return $out;
        };

        return [
            'ok' => true,
            'added' => $added,
            'removed' => $removed,
            'added_by_module' => $group($added),
            'removed_by_module' => $group($removed),
            'unchanged_count' => count(array_intersect(array_keys($currentSet), array_keys($targetSet))),
            'current_total' => count($currentSet),
            'preset_total' => count($targetSet),
            'added_count' => count($added),
            'removed_count' => count($removed),
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, permission_ids: list<int>, created_at: string}>
     */
    private function listCustomPresetKits(int $tenantId): array
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $rows = [];
        if (is_array($settings['roles_custom_preset_kits'] ?? null)) {
            $rows = $settings['roles_custom_preset_kits'];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($id === '' || $label === '') {
                continue;
            }
            $ids = is_array($row['permission_ids'] ?? null) ? array_values(array_unique(array_map('intval', $row['permission_ids']))) : [];
            if ($ids === []) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => $label,
                'description' => trim((string) ($row['description'] ?? '')),
                'permission_ids' => $ids,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $kits
     */
    private function saveCustomPresetKits(int $tenantId, array $kits): void
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $settings['roles_custom_preset_kits'] = array_values($kits);
        $this->tenantRepository->updateSettings($tenantId, $settings);
    }

    /**
     * Intitulé, description et apparence du badge (hors permissions et slug).
     */
    public function editPresentation(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Vous n’avez pas la permission de modifier les rôles.');

            return Response::redirect(url('dashboard'));
        }
        $role = $this->roleRepository->findById($id, $tenantId);
        if (!$role || !in_array((string) ($role['role_layer'] ?? ''), ['community', 'intra'], true)) {
            Session::flash('error', 'Rôle introuvable ou hors périmètre organisation.');

            return Response::redirect(url('back-office/roles'));
        }
        $badge = [];
        $rawBadge = $role['badge_style'] ?? null;
        if (is_string($rawBadge) && $rawBadge !== '') {
            $decoded = json_decode($rawBadge, true);
            $badge = is_array($decoded) ? $decoded : [];
        } elseif (is_array($rawBadge)) {
            $badge = $rawBadge;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.edit_presentation',
            'title' => 'Présentation du rôle',
            'role' => $role,
            'badgeStyle' => $badge,
        ]);
    }

    public function updatePresentation(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/' . $id . '/edit-presentation'));
        }
        $role = $this->roleRepository->findById($id, $tenantId);
        if (!$role || !in_array((string) ($role['role_layer'] ?? ''), ['community', 'intra'], true)) {
            Session::flash('error', 'Rôle invalide.');

            return Response::redirect(url('back-office/roles'));
        }
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $ok = $this->roleRepository->updateOrganizationRolePresentation($tenantId, $id, $name, $description);
        if (!$ok) {
            Session::flash('error', 'Enregistrement impossible (intitulé vide ou rôle introuvable).');

            return Response::redirect(url('back-office/roles/' . $id . '/edit-presentation'));
        }

        $allowedColors = ['slate', 'blue', 'indigo', 'emerald', 'amber', 'red', 'purple'];
        $allowedIcons = ['shield', 'star', 'user', 'flag', 'briefcase', 'award', 'megaphone', 'none'];
        $allowedVariants = ['soft', 'solid', 'outline'];
        $c = trim((string) $request->input('badge_color', ''));
        $ic = trim((string) $request->input('badge_icon', ''));
        $v = trim((string) $request->input('badge_variant', ''));
        $style = [];
        if ($c !== '' && in_array($c, $allowedColors, true)) {
            $style['color'] = $c;
        }
        if ($ic !== '' && in_array($ic, $allowedIcons, true) && $ic !== 'none') {
            $style['icon'] = $ic;
        }
        if ($v !== '' && in_array($v, $allowedVariants, true)) {
            $style['variant'] = $v;
        }
        $this->roleRepository->updateOrganizationRoleBadgeStyle($tenantId, $id, $style === [] ? null : $style);

        $msg = 'Présentation du rôle mise à jour.';
        $tier = (string) ($role['semantic_tier'] ?? 'function');
        if ($tier === 'authority') {
            $pdo = Database::getPdo();
            if (!RoleCoherenceValidator::authorityRoleHasPermissions($pdo, $id)) {
                $msg .= ' Pensez à associer au moins une habilitation à ce rôle d’autorité (depuis la fiche du rôle).';
            }
        }
        Session::flash('success', $msg);

        return Response::redirect(url('back-office/roles/' . $id));
    }

    /**
     * Formulaire : cocher les habilitations accordées à un rôle communauté / intra.
     */
    public function editPermissions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Vous n’avez pas la permission de modifier les habilitations des rôles.');

            return Response::redirect(url('dashboard'));
        }
        if ($this->rolePermissionService->isRoleLocked($id)) {
            Session::flash('error', 'Ce rôle est verrouillé : les habilitations ne peuvent pas être modifiées manuellement.');

            return Response::redirect(url('back-office/roles/' . $id));
        }
        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);
        $role = null;
        foreach ($roles as $r) {
            if ((int) $r['id'] === $id) {
                $role = $r;
                break;
            }
        }
        if (!$role) {
            Session::flash('error', 'Rôle introuvable.');

            return Response::redirect(url('back-office/roles'));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.edit_permissions',
            'title' => 'Habilitations du rôle',
            'role' => $role,
            'permissionIds' => $permissionIds,
            'allPermissions' => $allPermissions,
            'moduleLabels' => TenantRolePermissionPresetService::permissionModuleLabelsFr(),
        ]);
    }

    public function updatePermissions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/roles'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.roles.manage') && !$gate->allows('admin.permissions.manage')) {
            Session::flash('error', 'Permission refusée.');

            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/roles/' . $id . '/permissions'));
        }
        if ($this->rolePermissionService->isRoleLocked($id)) {
            Session::flash('error', 'Ce rôle est verrouillé.');

            return Response::redirect(url('back-office/roles/' . $id));
        }
        $roles = $this->rolePermissionService->listOrganizationRoles($tenantId);
        $role = null;
        foreach ($roles as $r) {
            if ((int) $r['id'] === $id) {
                $role = $r;
                break;
            }
        }
        if (!$role) {
            Session::flash('error', 'Rôle introuvable.');

            return Response::redirect(url('back-office/roles'));
        }
        $raw = $request->input('permission_ids');
        $permissionIds = is_array($raw) ? array_map('intval', array_filter($raw)) : [];
        $allowedIds = array_map(static fn ($p) => (int) $p['id'], $this->permissionRepository->allForTenant($tenantId));
        $permissionIds = array_values(array_intersect($permissionIds, $allowedIds));

        try {
            $this->rolePermissionService->setPermissionsForOrganizationTenantRole($tenantId, $id, $permissionIds);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('back-office/roles/' . $id . '/permissions'));
        }

        $actorId = (int) Session::get('user_id');
        if ($actorId > 0) {
            $payload = json_encode(['permission_count' => count($permissionIds), 'scope' => 'tenant_organization'], JSON_UNESCAPED_UNICODE);
            $this->auditService->log(
                AuditAction::ROLE_PERMISSIONS_UPDATED,
                $tenantId,
                $actorId,
                'role',
                $id,
                null,
                $payload !== false ? $payload : null
            );
        }

        Session::flash('success', 'Habilitations du rôle enregistrées.');

        return Response::redirect(url('back-office/roles/' . $id));
    }
}
