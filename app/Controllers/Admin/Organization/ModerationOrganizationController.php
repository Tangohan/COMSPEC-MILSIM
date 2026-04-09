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
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Moderation\ModerationService;

/**
 * Restrictions d’activité au niveau organisation (niveau 0) — pas les sanctions « site ».
 */
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
        $rows = $this->moderationRepository->listRecentActions($tenantId, 100, 'tenant');
        $users = $this->userRepository->allForTenant($tenantId);

        return Response::view('layout.main', [
            'title' => 'Restrictions membres (organisation)',
            'content' => 'admin.organization.moderation',
            'actions' => $rows,
            'memberUsers' => $users,
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

        if ($type === 'warn') {
            try {
                $this->moderationService->applySanction(
                    $tenantId,
                    (int) $actor['id'],
                    $targetId,
                    'warn',
                    $reason !== '' ? $reason : null,
                    null,
                    [],
                    'tenant'
                );
                Session::flash('success', 'Avertissement enregistré sur le dossier du membre.');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
            }

            return Response::redirect(url('back-office/moderation'));
        }

        if ($type !== 'restriction') {
            Session::flash('error', 'Type de mesure non reconnu.');

            return Response::redirect(url('back-office/moderation'));
        }

        $modsIn = $request->input('modules_blocked');
        if (!is_array($modsIn)) {
            $modsIn = [];
        }
        $modsClean = array_values(array_intersect(
            array_map('strval', $modsIn),
            ModerationRestrictionsCatalog::moduleKeys()
        ));
        if ($modsClean === []) {
            Session::flash('error', 'Sélectionnez au moins un domaine restreint (formations, documents, etc.).');

            return Response::redirect(url('back-office/moderation'));
        }

        $expires = null;
        $durationMode = $request->input('duration_mode') === 'temporary' ? 'temporary' : 'permanent';
        if ($durationMode === 'temporary') {
            $days = max(1, (int) $request->input('duration_days'));
            $expires = (new \DateTimeImmutable())->modify('+' . $days . ' days');
        }

        $restrictions = [
            'account_lock' => false,
            'forum' => 'full_access',
            'messages_blocked' => false,
            'join_blocked' => false,
            'modules_blocked' => $modsClean,
        ];

        try {
            $this->moderationService->applySanction(
                $tenantId,
                (int) $actor['id'],
                $targetId,
                'mute',
                $reason !== '' ? $reason : null,
                $expires,
                $restrictions,
                'tenant'
            );
            Session::flash('success', 'Restriction d’activité enregistrée.');
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
        if ($this->moderationService->revokeForScope($tenantId, $actionId, (int) $actor['id'], 'tenant')) {
            Session::flash('success', 'Mesure levée.');
        } else {
            Session::flash('error', 'Action introuvable, déjà levée, ou relevant d’un autre périmètre.');
        }

        return Response::redirect(url('back-office/moderation'));
    }
}
