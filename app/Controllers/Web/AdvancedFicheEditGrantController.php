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

final class AdvancedFicheEditGrantController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private UserAdvancedEditGrantRepository $grantRepository,
    ) {
    }

    /** GET /back-office/personnel/advanced-edit */
    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Droits insuffisants pour gérer le mode édition avancée.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId] = $ctx;
        $q = trim((string) $request->query('q', ''));
        $searchResults = [];
        if ($q !== '' && mb_strlen($q) >= 2) {
            $searchResults = $this->userRepository->searchForPortal($tenantId, $q, 25);
        }

        return Response::view('layout.main', [
            'title' => 'Édition avancée de fiche',
            'content' => 'personnel.advanced_edit_grants',
            'activeGrants' => $this->grantRepository->listActiveForTenant($tenantId),
            'recentGrants' => $this->grantRepository->listRecentForTenant($tenantId),
            'searchQuery' => $q,
            'searchResults' => $searchResults,
            'durationHours' => UserAdvancedEditGrantRepository::durationHours(),
            'csrf' => Csrf::token(),
            'isBackOfficeShell' => true,
            'usesAdminSidebarShell' => true,
        ]);
    }

    /** POST /back-office/personnel/advanced-edit/grant */
    public function grant(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel/advanced-edit'));
        }
        $userId = (int) $request->input('user_id', 0);
        $target = $this->userRepository->findById($userId, $tenantId);
        if (!$target) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('back-office/personnel/advanced-edit'));
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

        return Response::redirect(url('back-office/personnel/advanced-edit'));
    }

    /** POST /back-office/personnel/advanced-edit/{id}/revoke */
    public function revoke(Request $request, array $params = []): Response
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }
        [$tenantId, $viewer] = $ctx;
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/personnel/advanced-edit'));
        }
        $grantId = (int) ($params['id'] ?? 0);
        $ok = $this->grantRepository->revoke($tenantId, $grantId, (int) $viewer['id']);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Autorisation révoquée.' : 'Impossible de révoquer cette autorisation.');

        return Response::redirect(url('back-office/personnel/advanced-edit'));
    }

    /** @return array{0: int, 1: array<string, mixed>}|null */
    private function authContext(): ?array
    {
        $user = $this->authService->user();
        $tenantId = (int) Session::get('tenant_id');
        if (!$user || $tenantId < 1) {
            return null;
        }

        return [$tenantId, $user];
    }

    private function canManage(): bool
    {
        if ($this->authService->user() === null) {
            return false;
        }
        $gate = Gate::getInstance();
        foreach (['personnel.profile.update', 'admin.organization', 'admin.access', 'personnel.grades.manage', 'personnel.status.manage'] as $slug) {
            if ($gate->allows($slug)) {
                return true;
            }
        }

        return false;
    }
}
