<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ModerationRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\ModerationService;

final class ModerationOrganizationController
{
    public function __construct(
        private AuthService $authService,
        private ModerationRepository $moderationRepository,
        private ModerationService $moderationService,
        private UserRepository $userRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $rows = $this->moderationRepository->listRecentActions($tenantId);
        $users = $this->userRepository->allForTenant($tenantId);

        return Response::view('layout.main', [
            'title' => 'Modération & sanctions',
            'content' => 'admin.organization.moderation',
            'actions' => $rows,
            'memberUsers' => $users,
        ]);
    }

    public function apply(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/organization/moderation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $targetId = (int) $request->input('target_user_id');
        $type = trim((string) $request->input('action_type'));
        $reason = trim((string) $request->input('reason'));
        $days = (int) $request->input('duration_days');
        if ($targetId <= 0 || $targetId === (int) $actor['id']) {
            Session::flash('error', 'Cible invalide.');

            return Response::redirect(url('admin/organization/moderation'));
        }
        $expires = null;
        if ($days > 0 && in_array($type, ['mute', 'suspend', 'ban'], true)) {
            $expires = (new \DateTimeImmutable())->modify('+' . $days . ' days');
        }
        try {
            $this->moderationService->applySanction($tenantId, (int) $actor['id'], $targetId, $type, $reason !== '' ? $reason : null, $expires);
            Session::flash('success', 'Sanction enregistrée.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('admin/organization/moderation'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/organization/moderation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $actionId = (int) $request->input('action_id');
        if ($this->moderationService->revoke($tenantId, $actionId, (int) $actor['id'])) {
            Session::flash('success', 'Sanction levée.');
        } else {
            Session::flash('error', 'Action introuvable ou déjà levée.');
        }

        return Response::redirect(url('admin/organization/moderation'));
    }
}
