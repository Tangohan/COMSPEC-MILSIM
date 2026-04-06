<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Moderation\ModerationService;

final class ModerationOrganizationController
{
    public function __construct(
        private AuthService $authService,
        private ModerationRepository $moderationRepository,
        private ModerationService $moderationService,
        private UserRepository $userRepository,
        private IndicatorBlocklistService $indicatorBlocklistService,
        private BlockedIndicatorRepository $blockedIndicatorRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $rows = $this->moderationRepository->listRecentActions($tenantId);
        $users = $this->userRepository->allForTenant($tenantId);
        $blocklist = $this->blockedIndicatorRepository->listActiveForTenant($tenantId);

        return Response::view('layout.main', [
            'title' => 'Modération & sanctions',
            'content' => 'admin.organization.moderation',
            'actions' => $rows,
            'memberUsers' => $users,
            'blocklistRows' => $blocklist,
            'moduleLabels' => ModerationRestrictionsCatalog::moduleLabels(),
        ]);
    }

    public function apply(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/moderation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $targetId = (int) $request->input('target_user_id');
        $type = trim((string) $request->input('action_type'));
        $reason = trim((string) $request->input('reason'));
        if ($targetId <= 0 || $targetId === (int) $actor['id']) {
            Session::flash('error', 'Cible invalide.');

            return Response::redirect(url('back-office/moderation'));
        }
        $allowedTypes = ['warn', 'mute', 'suspend', 'ban'];
        if (!in_array($type, $allowedTypes, true)) {
            Session::flash('error', 'Type de mesure non reconnu.');

            return Response::redirect(url('back-office/moderation'));
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
            $this->moderationService->applySanction(
                $tenantId,
                (int) $actor['id'],
                $targetId,
                $type,
                $reason !== '' ? $reason : null,
                $expires,
                $restrictions
            );
            Session::flash('success', 'Mesure enregistrée.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/moderation'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/moderation'));
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

        return Response::redirect(url('back-office/moderation'));
    }

    public function blocklistAdd(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/moderation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $kind = trim((string) $request->input('indicator_kind'));
        $reason = trim((string) $request->input('block_reason'));
        $durationMode = $request->input('block_duration_mode') === 'temporary' ? 'temporary' : 'permanent';
        $expiresAt = null;
        if ($durationMode === 'temporary') {
            $days = max(1, (int) $request->input('block_duration_days'));
            $expiresAt = (new \DateTimeImmutable())->modify('+' . $days . ' days');
        }
        try {
            $raw = trim((string) $request->input('restriction_target'));
            if ($kind === 'email') {
                $this->indicatorBlocklistService->addEmailBlock(
                    (int) $actor['id'],
                    'tenant',
                    $tenantId,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt,
                    null
                );
            } elseif ($kind === 'ip') {
                $this->indicatorBlocklistService->addIpBlock(
                    (int) $actor['id'],
                    'tenant',
                    $tenantId,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt
                );
            } else {
                Session::flash('error', 'Type d’entrée non reconnu.');

                return Response::redirect(url('back-office/moderation'));
            }
            Session::flash('success', 'Entrée ajoutée à la liste de restriction.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/moderation'));
    }

    public function blocklistRevoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/moderation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $id = (int) $request->input('indicator_id');
        if ($this->indicatorBlocklistService->revokeIndicator((int) $actor['id'], $id, $tenantId)) {
            Session::flash('success', 'Entrée levée.');
        } else {
            Session::flash('error', 'Entrée introuvable ou déjà levée.');
        }

        return Response::redirect(url('back-office/moderation'));
    }
}
