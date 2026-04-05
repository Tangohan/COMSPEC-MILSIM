<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SiteRoleAssignmentRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

final class SystemSiteRoleAssignmentController
{
    public function __construct(
        private SiteRoleAssignmentRepository $siteRoleAssignments,
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
        $data = $this->siteRoleAssignments->listAllWithAssignments();

        return Response::view('layout.main', [
            'content' => 'admin.system.site_role_assignments',
            'title' => 'Affectations rôles site',
            'siteRolesData' => $data,
        ]);
    }

    public function assign(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/site-roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if (!$tenantId || !$actorId) {
            return Response::redirect(url('login'));
        }
        $email = strtolower(trim((string) $request->input('email')));
        $roleId = (int) $request->input('role_id');
        if (!$this->siteRoleAssignments->assign($email, $roleId, $actorId)) {
            Session::flash('error', 'Email ou rôle site invalide.');

            return Response::redirect(url('admin/site-roles'));
        }
        $this->auditService->log(
            AuditAction::SITE_ROLE_ASSIGNED,
            $tenantId,
            $actorId,
            'site_role',
            $roleId,
            null,
            $email
        );
        Session::flash('success', 'Rôle site affecté.');

        return Response::redirect(url('admin/site-roles'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/site-roles'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if (!$tenantId || !$actorId) {
            return Response::redirect(url('login'));
        }
        $id = (int) $request->input('id');
        if ($id <= 0 || !$this->siteRoleAssignments->revoke($id)) {
            Session::flash('error', 'Révocation impossible.');

            return Response::redirect(url('admin/site-roles'));
        }
        $this->auditService->log(
            AuditAction::SITE_ROLE_REVOKED,
            $tenantId,
            $actorId,
            'site_role_assignment',
            $id
        );
        Session::flash('success', 'Affectation révoquée.');

        return Response::redirect(url('admin/site-roles'));
    }
}
