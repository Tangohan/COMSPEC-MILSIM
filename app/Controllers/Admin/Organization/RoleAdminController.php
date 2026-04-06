<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\Admin\TenantRolePermissionPresetService;

class RoleAdminController
{
    public function __construct(
        private RolePermissionService $rolePermissionService,
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
        private TenantRolePermissionPresetService $presetService
    ) {}

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
        $permissionCounts = [];
        foreach ($roles as $r) {
            $permissionCounts[(int) $r['id']] = count($this->rolePermissionService->getPermissionIdsForRole((int) $r['id']));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.index',
            'title' => 'Rôles communauté',
            'roles' => $roles,
            'permissionCounts' => $permissionCounts,
            'roleLayerFilter' => $layer,
        ]);
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
        $rolePermissions = array_filter($allPermissions, fn ($p) => in_array((int) $p['id'], $permissionIds, true));

        return Response::view('layout.main', [
            'content' => 'admin.organization.roles.show',
            'title' => 'Détail rôle',
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
            'excludedSlugs' => TenantRolePermissionPresetService::EXCLUDED_SLUGS,
            'roles' => $roles,
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
        $allowedIds = array_column($this->presetService->listPresetMeta(), 'id');
        if ($presetId === '' || !in_array($presetId, $allowedIds, true)) {
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
        $layer = (string) ($role['role_layer'] ?? '');
        if ($layer !== 'community' && $layer !== 'intra') {
            Session::flash('error', 'Seuls les rôles communauté ou opérationnels peuvent être configurés ici.');

            return Response::redirect(url('back-office/roles/presets'));
        }
        if ($this->rolePermissionService->isRoleLocked($roleId)) {
            Session::flash('error', 'Ce rôle est verrouillé : les permissions ne peuvent pas être remplacées par un profil.');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $ids = $this->presetService->getPermissionIdsForPreset($tenantId, $presetId);
        if ($ids === []) {
            Session::flash('error', 'Aucune permission correspondante dans cette communauté (migrations ou catalogue à jour ?).');

            return Response::redirect(url('back-office/roles/presets'));
        }

        $this->rolePermissionService->setPermissionsForRole($roleId, $ids);
        Session::flash('success', 'Permissions du rôle « ' . (string) ($role['name'] ?? '') . ' » mises à jour selon le profil sélectionné (' . count($ids) . ' droits).');

        return Response::redirect(url('back-office/roles/' . $roleId));
    }
}
