<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\RoleplayGameSessionService;
use App\Services\Personnel\RoleplayGameSessionSettings;

final class RoleplayGameSessionAdminController
{
    public function __construct(
        private RoleplayGameSessionRepository $sessions,
        private RoleplayGameSessionService $service,
        private TenantRepository $tenants,
        private UserRepository $users,
        private CommunityEventRepository $events,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $list = $this->sessions->schemaReady() ? $this->sessions->listForTenant($tenantId, 80) : [];

        return Response::view('layout.main', [
            'title' => 'Sessions Arma',
            'content' => 'admin.organization.roleplay_game_sessions',
            'sessionList' => $list,
            'sessionSettings' => $cfg,
            'sessionFormAction' => url('back-office/roleplay/sessions'),
        ]);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/roleplay/sessions');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        RoleplayGameSessionSettings::saveFromPost($tenantId, $request->all(), $this->tenants);
        Session::flash('success', 'Réglages des sessions enregistrés.');

        return Response::redirect($redirect);
    }

    public function store(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/roleplay/sessions');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::redirect($redirect);
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $kind = (string) $request->input('session_kind', 'officielle');
        if (!in_array($kind, ['officielle', 'entrainement', 'libre'], true)) {
            $kind = 'libre';
        }
        $id = $this->sessions->create($tenantId, [
            'session_uid' => 'staff-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)),
            'mission_name' => trim((string) $request->input('mission_name', '')) ?: 'Session',
            'server_name' => trim((string) $request->input('server_name', '')) ?: null,
            'session_kind' => $kind,
            'hour_category' => trim((string) $request->input('hour_category', '')) ?: 'Opération',
            'community_event_id' => ((int) $request->input('community_event_id', 0)) ?: null,
            'is_official' => $kind === 'officielle' ? 1 : 0,
            'attendance_enabled' => $kind === 'officielle' || $request->input('attendance_enabled') ? 1 : 0,
            'status' => 'open',
            'planned_starts_at' => trim((string) $request->input('planned_starts_at', '')) ?: null,
            'planned_ends_at' => trim((string) $request->input('planned_ends_at', '')) ?: null,
            'started_at' => date('Y-m-d H:i:s'),
            'min_minutes' => $cfg['min_minutes'],
            'min_percent' => $cfg['min_percent'],
            'late_tolerance_minutes' => $cfg['late_tolerance_minutes'],
            'created_by_user_id' => (int) Session::get('user_id') ?: null,
        ]);
        Session::flash('success', 'Session créée.');

        return Response::redirect(url('back-office/roleplay/sessions/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $session = $this->sessions->findById($tenantId, $id);
        if (!$session) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        $members = $this->sessions->listMembers($tenantId, $id);
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $events = [];
        try {
            $events = $this->events->listUpcomingForTenant($tenantId, 40);
        } catch (\Throwable) {
            $events = [];
        }

        return Response::view('layout.main', [
            'title' => 'Session Arma',
            'content' => 'admin.organization.roleplay_game_session_show',
            'gameSession' => $session,
            'sessionMembers' => $members,
            'sessionSettings' => $cfg,
            'sessionEvents' => is_array($events) ? $events : [],
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id);
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::redirect($redirect);
        }
        $kind = (string) $request->input('session_kind', 'officielle');
        if (!in_array($kind, ['officielle', 'entrainement', 'libre'], true)) {
            $kind = 'libre';
        }
        $this->sessions->update($tenantId, $id, [
            'mission_name' => trim((string) $request->input('mission_name', '')),
            'server_name' => trim((string) $request->input('server_name', '')) ?: null,
            'session_kind' => $kind,
            'hour_category' => trim((string) $request->input('hour_category', '')),
            'community_event_id' => ((int) $request->input('community_event_id', 0)) ?: null,
            'is_official' => $kind === 'officielle' ? 1 : 0,
            'attendance_enabled' => $request->input('attendance_enabled') ? 1 : 0,
            'status' => (string) $request->input('status', 'open'),
            'min_minutes' => (int) $request->input('min_minutes', 45),
            'min_percent' => (int) $request->input('min_percent', 50),
            'late_tolerance_minutes' => (int) $request->input('late_tolerance_minutes', 15),
        ]);
        Session::flash('success', 'Session mise à jour.');

        return Response::redirect($redirect);
    }

    public function memberAction(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id);
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return Response::redirect($redirect);
        }
        $action = (string) $request->input('member_action', '');
        $actor = (int) Session::get('user_id');
        if ($action === 'validate') {
            $this->service->staffValidate($tenantId, $id, $memberId, $actor, true, false);
            Session::flash('success', 'Présence retenue pour le suivi.');
        } elseif ($action === 'exclude') {
            $this->service->staffValidate($tenantId, $id, $memberId, $actor, false, true);
            Session::flash('success', 'Cette présence ne compte plus pour le suivi.');
        } elseif ($action === 'add') {
            $uid = (int) $request->input('user_id', 0);
            $user = $this->users->findById($uid, $tenantId);
            if ($user) {
                $mid = $this->sessions->upsertMember($tenantId, $id, [
                    'user_id' => $uid,
                    'checked_in_at' => date('Y-m-d H:i:s'),
                    'joined_at' => date('Y-m-d H:i:s'),
                    'raw_seconds' => max(0, (int) $request->input('raw_seconds', 0)),
                ]);
                $this->service->refreshMember($tenantId, $id, $mid);
                Session::flash('success', 'Membre ajouté à la session.');
            }
        }

        return Response::redirect($redirect);
    }
}
