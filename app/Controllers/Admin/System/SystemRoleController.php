<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\Admin\RolePermissionService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

class SystemRoleController
{
    public function __construct(
        private RolePermissionService $rolePermissionService,
        private PermissionRepository $permissionRepository,
        private RoleRepository $roleRepository,
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
        $roles = $this->rolePermissionService->listSiteRoles();
        $permissionCounts = [];
        foreach ($roles as $r) {
            $permissionCounts[(int) $r['id']] = count($this->rolePermissionService->getPermissionIdsForRole((int) $r['id']));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.roles.index',
            'title' => 'Rôles site (plateforme)',
            'roles' => $roles,
            'permissionCounts' => $permissionCounts,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('admin/roles'));
        }
        $role = $this->roleRepository->findById($id, null);
        if (!$role || ($role['tenant_id'] ?? null) !== null || (($role['role_layer'] ?? '') !== 'site')) {
            Session::flash('error', 'Rôle site introuvable.');

            return Response::redirect(url('admin/roles'));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allGlobalSite();
        $rolePermissions = array_filter($allPermissions, fn ($p) => in_array((int) $p['id'], $permissionIds, true));

        return Response::view('layout.main', [
            'content' => 'admin.system.roles.show',
            'title' => 'Détail rôle site',
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
            return Response::redirect(url('admin/roles'));
        }
        $role = $this->roleRepository->findById($id, null);
        if (!$role || ($role['tenant_id'] ?? null) !== null || (($role['role_layer'] ?? '') !== 'site')) {
            return Response::redirect(url('admin/roles'));
        }
        if ($this->rolePermissionService->isRoleLocked($id) || !empty($role['is_system_critical'])) {
            Session::flash('error', 'Ce rôle est verrouillé ou réservé à la plateforme : les habilitations ne peuvent pas être modifiées ici.');

            return Response::redirect(url('admin/roles/' . $id));
        }
        $permissionIds = $this->rolePermissionService->getPermissionIdsForRole($id);
        $allPermissions = $this->permissionRepository->allGlobalSite();

        return Response::view('layout.main', [
            'content' => 'admin.system.roles.edit',
            'title' => 'Modifier les permissions du rôle site',
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
            return Response::redirect(url('admin/roles'));
        }
        $role = $this->roleRepository->findById($id, null);
        if (!$role || ($role['tenant_id'] ?? null) !== null || (($role['role_layer'] ?? '') !== 'site')) {
            Session::flash('error', 'Rôle invalide.');

            return Response::redirect(url('admin/roles'));
        }
        if ($this->rolePermissionService->isRoleLocked($id) || !empty($role['is_system_critical'])) {
            Session::flash('error', 'Ce rôle est verrouillé ou réservé : modification impossible.');

            return Response::redirect(url('admin/roles/' . $id));
        }
        $permissionIds = $request->input('permission_ids');
        if (is_array($permissionIds)) {
            $permissionIds = array_map('intval', array_filter($permissionIds));
        } else {
            $permissionIds = [];
        }
        $allowedIds = array_map(static fn ($p) => (int) $p['id'], $this->permissionRepository->allGlobalSite());
        $permissionIds = array_values(array_intersect($permissionIds, $allowedIds));
        $this->rolePermissionService->setPermissionsForRole($id, $permissionIds);
        $actorId = (int) Session::get('user_id');
        if ($actorId > 0) {
            $payload = json_encode(['permission_count' => count($permissionIds), 'scope' => 'site'], JSON_UNESCAPED_UNICODE);
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
        Session::flash('success', 'Permissions mises à jour.');

        return Response::redirect(url('admin/roles/' . $id));
    }
}
