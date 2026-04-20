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
use App\Services\Admin\AdminActionService;

final class SystemSiteRoleAssignmentController
{
    public function __construct(
        private SiteRoleAssignmentRepository $siteRoleAssignments,
        private ?AuditService $auditService = null,
        private ?AdminActionService $adminActionService = null
    ) {
        $this->auditService ??= new AuditService();
        $this->adminActionService ??= new AdminActionService();
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
        $assignment = $this->siteRoleAssignments->findActiveAssignmentByEmailAndRole($email, $roleId);

        $this->auditService->logChange(
            AuditAction::SITE_ROLE_ASSIGNED,
            $tenantId,
            $actorId,
            'site_role',
            $roleId,
            [],
            ['email' => $email, 'role_id' => $roleId],
        );
        $this->adminActionService->log($request, [
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'action_type' => AuditAction::SITE_ROLE_ASSIGNED,
            'target_type' => 'site_role_assignment',
            'target_id' => isset($assignment['id']) ? (string) $assignment['id'] : null,
            'scope' => 'platform',
            'status' => 'applied',
            'reason' => 'Affectation de rôle site',
            'is_undoable' => 1,
            'is_compensable' => 0,
            'undo_strategy' => 'site_role.revoke',
        ], [], [
            'email' => $email,
            'role_id' => $roleId,
            'assignment_id' => (int) ($assignment['id'] ?? 0),
        ]);

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
        $row = $this->siteRoleAssignments->findActiveAssignmentById($id);
        if ($id <= 0 || !$this->siteRoleAssignments->revoke($id)) {
            Session::flash('error', 'Révocation impossible.');

            return Response::redirect(url('admin/site-roles'));
        }
        $old = $row !== null
            ? [
                'email' => (string) ($row['email_normalized'] ?? ''),
                'role_id' => (int) ($row['role_id'] ?? 0),
                'role_name' => (string) ($row['role_name'] ?? ''),
            ]
            : [];
        $this->auditService->logChange(
            AuditAction::SITE_ROLE_REVOKED,
            $tenantId,
            $actorId,
            'site_role_assignment',
            $id,
            $old,
            [],
        );
        $this->adminActionService->log($request, [
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'action_type' => AuditAction::SITE_ROLE_REVOKED,
            'target_type' => 'site_role_assignment',
            'target_id' => (string) $id,
            'scope' => 'platform',
            'status' => 'applied',
            'reason' => 'Révocation de rôle site',
            'is_undoable' => 0,
            'is_compensable' => 1,
            'non_reversible_reason' => 'Nécessite une ré-affectation explicite validée.',
        ], $old, []);

        Session::flash('success', 'Affectation révoquée.');

        return Response::redirect(url('admin/site-roles'));
    }
}
