<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Services\Admin\RolePermissionService;

class RoleAdminController
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
            'content' => 'admin.organization.roles.index',
            'title' => 'Rôles',
            'roles' => $roles,
            'permissionCounts' => $permissionCounts,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/organization/roles'));
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
            return Response::redirect(url('admin/organization/roles'));
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
}
