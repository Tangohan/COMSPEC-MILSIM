<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdminActionRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\SecurityEventRepository;
use App\Services\Admin\UndoService;

final class SystemCommandCenterController
{
    public function __construct(
        private ?AdminActionRepository $adminActions = null,
        private ?AuditLogRepository $auditLogs = null,
        private ?ForumReportRepository $forumReports = null,
        private ?ModerationRepository $moderation = null,
        private ?SecurityEventRepository $securityEvents = null,
        private ?UndoService $undoService = null,
    ) {
        $this->adminActions ??= new AdminActionRepository();
        $this->auditLogs ??= new AuditLogRepository();
        $this->forumReports ??= new ForumReportRepository();
        $this->moderation ??= new ModerationRepository();
        $this->securityEvents ??= new SecurityEventRepository();
        $this->undoService ??= new UndoService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $actionType = trim((string) $request->query('action_type', ''));
        $targetType = trim((string) $request->query('target_type', ''));
        $actorId = (int) $request->query('actor_id', 0);

        $actions = $this->adminActions->listPaginated([
            'action_type' => $actionType,
            'target_type' => $targetType,
            'actor_id' => $actorId,
        ], $page, $perPage);

        $forumPending = 0;
        try {
            $forumPending = $this->forumReports->countPendingAllTenants();
        } catch (\Throwable) {
            $forumPending = 0;
        }

        $activeSanctions = 0;
        try {
            $activeSanctions = count($this->moderation->listRecentActions((int) Session::get('tenant_id'), 100, 'platform'));
        } catch (\Throwable) {
            $activeSanctions = 0;
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.command_center',
            'title' => 'Centre de commandement admin',
            'commandCenterActions' => $actions['rows'],
            'commandCenterTotal' => $actions['total'],
            'commandCenterPage' => $page,
            'commandCenterPerPage' => $perPage,
            'commandCenterFilters' => [
                'action_type' => $actionType,
                'target_type' => $targetType,
                'actor_id' => $actorId,
            ],
            'commandCenterKpis' => [
                'forum_pending' => $forumPending,
                'admin_actions_24h' => count($this->auditLogs->recentSystemByActions(['site_role.assigned', 'site_role.revoked', 'moderation.action_applied', 'moderation.action_revoked'], 200)),
                'security_events_recent' => count($this->securityEvents->recent(20)),
                'sanctions_recent' => $activeSanctions,
            ],
            'commandCenterUndoQueue' => $this->adminActions->listRecentUndoable(12),
            'commandCenterSecurityEvents' => $this->securityEvents->recent(12),
            'commandCenterRecentAudit' => $this->auditLogs->recentSystem(12),
        ]);
    }

    public function undo(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/command-center'));
        }

        $actionId = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id');
        $reason = trim((string) $request->input('reason'));
        if ($actorId < 1 || $reason === '' || $actionId < 1) {
            Session::flash('error', 'Paramètres d’annulation invalides.');

            return Response::redirect(url('admin/command-center'));
        }

        $result = $this->undoService->undo($actionId, $actorId, $reason);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect(url('admin/command-center'));
    }
}
