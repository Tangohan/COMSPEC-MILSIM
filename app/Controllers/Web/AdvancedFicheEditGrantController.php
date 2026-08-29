<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserAdvancedEditGrantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;

/**
 * Administration plateforme : accorder le mode édition avancée de fiche à n’importe quel compte.
 */
final class AdvancedFicheEditGrantController
{
    private const PAGE_PATH = 'admin/system/advanced-fiche-edit';

    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserAdvancedEditGrantRepository $grantRepository,
    ) {
    }

    /** GET /admin/system/advanced-fiche-edit */
    public function index(Request $request, array $params = []): Response
    {
        if ($deny = $this->denyUnlessPlatformAdmin()) {
            return $deny;
        }
        $viewer = $this->authService->user();
        if ($viewer === null) {
            return Response::redirect(url('login'));
        }
        $q = trim((string) $request->query('q', ''));
        $searchResults = [];
        if ($q !== '' && mb_strlen($q) >= 2) {
            $searchResults = $this->userRepository->searchAccountsForPlatformOperator($q, 40);
        }

        return Response::view('layout.main', [
            'title' => 'Édition avancée de fiche',
            'content' => 'admin.system.advanced_fiche_edit',
            'activeGrants' => $this->grantRepository->listActiveGlobal(),
            'recentGrants' => $this->grantRepository->listRecentGlobal(),
            'searchQuery' => $q,
            'searchResults' => $searchResults,
            'durationHours' => UserAdvancedEditGrantRepository::durationHours(),
            'csrf' => Csrf::token(),
            'isPlatformAdminShell' => true,
        ]);
    }

    /** POST /admin/system/advanced-fiche-edit/grant */
    public function grant(Request $request, array $params = []): Response
    {
        if ($deny = $this->denyUnlessPlatformAdmin()) {
            return $deny;
        }
        $viewer = $this->authService->user();
        if ($viewer === null) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(self::PAGE_PATH));
        }
        $userId = (int) $request->input('user_id', 0);
        $target = $this->userRepository->findById($userId);
        if (!$target) {
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect(url(self::PAGE_PATH));
        }
        $tenantId = (int) ($target['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté du compte introuvable.');

            return Response::redirect(url(self::PAGE_PATH));
        }
        $result = $this->grantRepository->grant(
            $tenantId,
            $userId,
            (int) $viewer['id'],
            (string) $request->input('reason', '')
        );
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok'] && !empty($result['ends_at'])) {
            $name = trim((string) ($target['display_name'] ?? '')) ?: ('#' . $userId);
            Session::flash(
                'info',
                'Autorisation pour ' . $name . ' jusqu’au ' . date('d/m/Y H:i', strtotime((string) $result['ends_at'])) . '.'
            );
        }

        return Response::redirect(url(self::PAGE_PATH));
    }

    /** POST /admin/system/advanced-fiche-edit/{id}/revoke */
    public function revoke(Request $request, array $params = []): Response
    {
        if ($deny = $this->denyUnlessPlatformAdmin()) {
            return $deny;
        }
        $viewer = $this->authService->user();
        if ($viewer === null) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(self::PAGE_PATH));
        }
        $grantId = (int) ($params['id'] ?? 0);
        $ok = $this->grantRepository->revokeById($grantId, (int) $viewer['id']);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Autorisation révoquée.' : 'Impossible de révoquer cette autorisation.');

        return Response::redirect(url(self::PAGE_PATH));
    }

    private function denyUnlessPlatformAdmin(): ?Response
    {
        if ($this->authService->user() === null) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if ($gate->allows('admin.system')) {
            return null;
        }
        Session::flash('error', 'Accès réservé à l’administration plateforme.');
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            return Response::redirect(url('back-office'));
        }

        return Response::redirect(url('dashboard'));
    }
}
