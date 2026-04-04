<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Services\Admin\RolePermissionService;

class SystemRoleController
{
    public function __construct(
        private RolePermissionService $rolePermissionService,
        private PermissionRepository $permissionRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $roles = $this->rolePermissionService->listRoles($tenantId);
        $permissionCounts = [];
        foreach ($roles as $r) {
            $permissionCounts[(int) $r['id']] = count($this->rolePermissionService->getPermissionIdsForRole((int) $r['id']));
        }
        return Response::view('layout.main', [
            'content' => 'admin.system.roles.index',
            'title' => 'Rôles système',
            'roles' => $roles,
            'permissionCounts' => $permissionCounts,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/system/roles'));
        }
        $roles = $this->rolePermissionService->listRoles($tenantId);
        $role = null;
        foreach ($roles as $r) {
            if ((int) $r['id'] === $id) {
                $role = $r;
                break;
            }
        }
        if (!$role) {
            Session::flash('error', 'Rôle introuvable.');
            return Response::redirect(url('admin/system/roles'));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allForTenant($tenantId);
        $rolePermissions = array_filter($allPermissions, fn ($p) => in_array((int) $p['id'], $permissionIds, true));
        return Response::view('layout.main', [
            'content' => 'admin.system.roles.show',
            'title' => 'Détail rôle',
            'role' => $role,
            'rolePermissions' => $rolePermissions,
            'allPermissions' => $allPermissions,
            'isLocked' => $this->rolePermissionService->isRoleLocked($id),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/system/roles'));
        }
        if ($this->rolePermissionService->isRoleLocked($id)) {
            Session::flash('error', 'Ce rôle est verrouillé.');
            return Response::redirect(url('admin/system/roles/' . $id));
        }
        $roles = $this->rolePermissionService->listRoles($tenantId);
        $role = null;
        foreach ($roles as $r) {
            if ((int) $r['id'] === $id) {
                $role = $r;
                break;
            }
        }
        if (!$role) {
            return Response::redirect(url('admin/system/roles'));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allForTenant($tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.system.roles.edit',
            'title' => 'Modifier les permissions du rôle',
            'role' => $role,
            'permissionIds' => $permissionIds,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/system/roles'));
        }
        if ($this->rolePermissionService->isRoleLocked($id)) {
            Session::flash('error', 'Ce rôle est verrouillé.');
            return Response::redirect(url('admin/system/roles/' . $id));
        }
        $permissionIds = $request->input('permission_ids');
        if (is_array($permissionIds)) {
            $permissionIds = array_map('intval', array_filter($permissionIds));
        } else {
            $permissionIds = [];
        }
        $this->rolePermissionService->setPermissionsForRole($id, $permissionIds);
        Session::flash('success', 'Permissions mises à jour.');
        return Response::redirect(url('admin/system/roles/' . $id));
    }
}
