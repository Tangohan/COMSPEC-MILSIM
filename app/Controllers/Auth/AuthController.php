<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Core\Validator;
use App\Services\Auth\AuthService;
use App\Services\Rbac\RbacService;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Repositories\PasswordResetRepository;

class AuthController
{
    private const RESET_TOKEN_EXPIRE_HOURS = 1;

    public function __construct(
        private AuthService $authService,
        private RbacService $rbacService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private PasswordResetRepository $passwordResetRepository
    ) {}

    public function showLogin(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        return Response::view('auth.login', ['title' => 'Connexion']);
    }

    public function login(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('login'));
        }
        $email = trim((string) $request->input('email'));
        $password = $request->input('password');
        if ($email === '' || $password === '') {
            Session::flash('error', 'Email et mot de passe requis.');
            return Response::redirect(url('login'));
        }

        $tenant = $this->tenantRepository->getDefaultTenant();
        if (!$tenant) {
            Session::flash('error', 'Aucune organisation configurée. Exécutez les migrations et le seed.');
            return Response::redirect(url('login'));
        }

        if ($this->authService->attempt((int) $tenant['id'], $email, $password)) {
            $user = $this->authService->user();
            if ($user && !empty($user['role_id'])) {
                $this->rbacService->setPermissionsForGate((int) $user['role_id']);
            }
            return Response::redirect(url('dashboard'));
        }

        Session::flash('error', 'Identifiants incorrects ou compte inactif.');
        return Response::redirect(url('login'));
    }

    public function logout(Request $request, array $params = []): Response
    {
        $this->authService->logout();
        return Response::redirect(url(''));
    }
}
