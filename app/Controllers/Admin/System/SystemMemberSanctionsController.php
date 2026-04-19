<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModerationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Moderation\ModerationService;
use App\Services\Admin\AdminActionService;
use App\Services\Audit\AuditAction;

/**
 * Sanctions « site » (niveaux 1 à 3) sur un membre d’une communauté — hors périmètre RH tenant.
 */
final class SystemMemberSanctionsController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private ModerationRepository $moderationRepository,
        private ModerationService $moderationService,
        private ?AdminActionService $adminActionService = null
    ) {
        $this->adminActionService ??= new AdminActionService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenants = $this->tenantRepository->listBasicAll();
        $tenantId = max(0, (int) $request->query('tenant_id'));
        $selected = null;
        foreach ($tenants as $t) {
            if ((int) ($t['id'] ?? 0) === $tenantId) {
                $selected = $t;
                break;
            }
        }
        $users = [];
        $actions = [];
        if ($tenantId > 0 && $selected !== null) {
            $users = $this->userRepository->allForTenant($tenantId);
            $actions = $this->moderationRepository->listRecentActions($tenantId, 80, 'platform');
        }

        return Response::view('layout.main', [
            'title' => 'Sanctions membres (plateforme)',
            'content' => 'admin.system.member_sanctions',
            'tenantsList' => $tenants,
            'selectedTenantId' => $tenantId,
            'selectedTenant' => $selected,
            'memberUsers' => $users,
            'actions' => $actions,
            'moduleLabels' => ModerationRestrictionsCatalog::moduleLabels(),
            'blocklistUrl' => url('admin/system/blocklist'),
        ]);
    }

    public function apply(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectBack($request);
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $request->input('tenant_id');
        $selected = $this->tenantRepository->findById($tenantId);
        if ($selected === null || $tenantId < 2) {
            Session::flash('error', 'Communauté invalide.');

            return Response::redirect(url('admin/system/member-sanctions'));
        }

        $targetId = (int) $request->input('target_user_id');
        $type = trim((string) $request->input('action_type'));
        $reason = trim((string) $request->input('reason'));
        if ($targetId <= 0) {
            Session::flash('error', 'Cible invalide.');

            return $this->redirectBack($request, $tenantId);
        }
        $targetUser = $this->userRepository->findById($targetId, $tenantId);
        if (!$targetUser) {
            Session::flash('error', 'Ce membre n’appartient pas à la communauté choisie.');

            return $this->redirectBack($request, $tenantId);
        }

        $allowedTypes = ['warn', 'mute', 'suspend', 'ban'];
        if (!in_array($type, $allowedTypes, true)) {
            Session::flash('error', 'Type de mesure non reconnu.');

            return $this->redirectBack($request, $tenantId);
        }

        $expires = null;
        if ($type !== 'warn') {
            $durationMode = $request->input('duration_mode') === 'temporary' ? 'temporary' : 'permanent';
            if ($durationMode === 'temporary') {
                $days = max(1, (int) $request->input('duration_days'));
                $expires = (new \DateTimeImmutable())->modify('+' . $days . ' days');
            }
        }

        $restrictions = [];
        if ($type !== 'warn') {
            $forum = trim((string) $request->input('forum_access'));
            if (!in_array($forum, ['full_access', 'read_only', 'none'], true)) {
                $forum = 'full_access';
            }
            $modsIn = $request->input('modules_blocked');
            if (!is_array($modsIn)) {
                $modsIn = [];
            }
            $modsClean = array_values(array_intersect(
                array_map('strval', $modsIn),
                ModerationRestrictionsCatalog::moduleKeys()
            ));
            $restrictions = [
                'account_lock' => $request->input('account_lock') === '1',
                'forum' => $forum,
                'messages_blocked' => $request->input('messages_blocked') === '1',
                'join_blocked' => $request->input('join_blocked') === '1',
                'modules_blocked' => $modsClean,
            ];
        }

        try {
            $moderationActionId = $this->moderationService->applySanction(
                $tenantId,
                (int) $actor['id'],
                $targetId,
                $type,
                $reason !== '' ? $reason : null,
                $expires,
                $restrictions,
                'platform'
            );
            $this->adminActionService->log($request, [
                'tenant_id' => $tenantId,
                'actor_user_id' => (int) $actor['id'],
                'action_type' => AuditAction::MODERATION_ACTION,
                'target_type' => 'user',
                'target_id' => (string) $targetId,
                'scope' => 'platform',
                'status' => 'applied',
                'reason' => $reason !== '' ? $reason : 'Sanction plateforme',
                'is_undoable' => 1,
                'is_compensable' => 1,
                'undo_strategy' => 'moderation.revoke',
            ], [], [
                'moderation_action_id' => (int) ($moderationActionId ?? 0),
                'type' => $type,
                'target_user_id' => $targetId,
                'expires_at' => $expires?->format(\DateTimeInterface::ATOM),
            ]);

            Session::flash('success', 'Mesure enregistrée.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirectBack($request, $tenantId);
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return $this->redirectBack($request);
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $request->input('tenant_id');
        $actionId = (int) $request->input('action_id');
        if ($tenantId < 2 || $this->tenantRepository->findById($tenantId) === null) {
            Session::flash('error', 'Communauté invalide.');

            return Response::redirect(url('admin/system/member-sanctions'));
        }
        if ($this->moderationService->revokeForScope($tenantId, $actionId, (int) $actor['id'], 'platform')) {
            $this->adminActionService->log($request, [
                'tenant_id' => $tenantId,
                'actor_user_id' => (int) $actor['id'],
                'action_type' => AuditAction::MODERATION_REVOKED,
                'target_type' => 'moderation_action',
                'target_id' => (string) $actionId,
                'scope' => 'platform',
                'status' => 'applied',
                'reason' => 'Action de levée de sanction',
                'is_undoable' => 0,
                'is_compensable' => 1,
                'non_reversible_reason' => 'La sanction doit être ré-émise explicitement.',
            ]);
            Session::flash('success', 'Sanction levée.');
        } else {
            Session::flash('error', 'Action introuvable, déjà levée, ou hors périmètre plateforme.');
        }

        return $this->redirectBack($request, $tenantId);
    }

    private function redirectBack(Request $request, ?int $tenantId = null): Response
    {
        $tid = $tenantId ?? max(0, (int) $request->input('tenant_id'));
        $q = $tid > 0 ? '?tenant_id=' . $tid : '';

        return Response::redirect(url('admin/system/member-sanctions') . $q);
    }
}
