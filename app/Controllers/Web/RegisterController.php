<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Rbac\RbacService;

final class RegisterController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
        private RbacService $rbacService,
        private AuditService $auditService
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('auth.register', [
            'title' => 'Créer un compte',
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('register'));
        }
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $displayName = trim((string) $request->input('display_name'));
        $v = new Validator(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => 'required|email', 'password' => 'required|min:8', 'password_confirmation' => 'required']
        );
        if (!$v->validate() || $password !== $confirm) {
            Session::flash('error', 'Vérifiez les champs (email valide, mot de passe 8+ caractères, confirmation identique).');

            return Response::redirect(url('register'));
        }

        $tenant = $this->tenantRepository->getDefaultTenant();
        if (!$tenant) {
            Session::flash('error', 'Aucune organisation de base configurée.');

            return Response::redirect(url('register'));
        }
        $tenantId = (int) $tenant['id'];
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');

            return Response::redirect(url('register'));
        }

        $roleId = $this->roleRepository->getIdBySlug($tenantId, 'member');
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $userId = $this->userRepository->create($tenantId, [
            'email' => $email,
            'password_hash' => $hash,
            'display_name' => $displayName !== '' ? $displayName : null,
            'role_id' => $roleId,
            'status' => 'active',
        ]);
        $user = $this->userRepository->findById($userId, $tenantId);
        if ($user) {
            $this->authService->loginUser($user);
            if (!empty($user['role_id'])) {
                $this->rbacService->setPermissionsForGate((int) $user['role_id']);
            }
        }
        $this->auditService->log(AuditAction::AUTH_REGISTER, $tenantId, $userId, 'user', $userId, null, $email);

        Session::flash('success', 'Compte créé. Vous pouvez créer une communauté ou rejoindre une invitation.');

        return Response::redirect(url('dashboard'));
    }
}
