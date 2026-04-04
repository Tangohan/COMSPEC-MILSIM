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
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

class AuthController
{
    private const RESET_TOKEN_EXPIRE_HOURS = 1;

    public function __construct(
        private AuthService $authService,
        private RbacService $rbacService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private PasswordResetRepository $passwordResetRepository,
        private AuditService $auditService
    ) {}

    public function showLogin(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        return Response::view('auth.login', [
            'title' => 'Connexion',
            'tenant_slug_prefill' => trim((string) $request->query('tenant_slug', '')),
        ]);
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

        $tenantSlug = trim((string) $request->input('tenant_slug', ''));
        $tenant = null;
        if ($tenantSlug !== '') {
            $tenant = $this->tenantRepository->findBySlug($tenantSlug);
            if (!$tenant) {
                Session::flash('error', 'Communauté inconnue (slug).');
                return Response::redirect(url('login'));
            }
        }
        if ($tenant === null) {
            $tenant = $this->tenantRepository->getDefaultTenant();
        }
        if (!$tenant) {
            Session::flash('error', 'Aucune organisation configurée. Exécutez les migrations et le seed.');
            return Response::redirect(url('login'));
        }

        if ($this->authService->attempt((int) $tenant['id'], $email, $password)) {
            $user = $this->authService->user();
            if ($user && !empty($user['role_id'])) {
                $this->rbacService->setPermissionsForGate((int) $user['role_id']);
            }
            if ($user) {
                $this->auditService->log(
                    AuditAction::AUTH_LOGIN_SUCCESS,
                    (int) $tenant['id'],
                    (int) $user['id'],
                    'auth',
                    (int) $user['id']
                );
            }

            return Response::redirect(url('dashboard'));
        }

        $this->auditService->log(
            AuditAction::AUTH_LOGIN_FAILURE,
            (int) $tenant['id'],
            null,
            'auth',
            null,
            null,
            substr($email, 0, 120)
        );
        Session::flash('error', 'Identifiants incorrects ou compte inactif.');
        return Response::redirect(url('login'));
    }

    public function logout(Request $request, array $params = []): Response
    {
        $u = $this->authService->user();
        $tid = Session::get('tenant_id');
        if ($u && $tid) {
            $this->auditService->log(AuditAction::AUTH_LOGOUT, (int) $tid, (int) $u['id'], 'auth', (int) $u['id']);
        }
        $this->authService->logout();
        return Response::redirect(url(''));
    }

    public function showForgotPassword(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $error = Session::getFlash('error');
        $success = Session::getFlash('success');
        return Response::view('auth.forgot-password', [
            'title' => 'Mot de passe oublié',
            'error' => $error,
            'success' => $success,
        ]);
    }

    public function sendResetLink(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('forgot-password'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('forgot-password'));
        }
        $email = trim((string) $request->input('email'));
        $v = new Validator(['email' => $email], ['email' => 'required|email']);
        if (!$v->validate()) {
            Session::flash('error', 'Adresse email invalide.');
            return Response::redirect(url('forgot-password'));
        }

        $tenant = $this->tenantRepository->getDefaultTenant();
        if (!$tenant) {
            Session::flash('error', 'Service indisponible.');
            return Response::redirect(url('forgot-password'));
        }
        $user = $this->userRepository->findByEmail((int) $tenant['id'], $email);
        if ($user) {
            $this->passwordResetRepository->deleteExpired();
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = new \DateTimeImmutable('+' . self::RESET_TOKEN_EXPIRE_HOURS . ' hours');
            $this->passwordResetRepository->create((int) $user['id'], $hash, $expires);
            $resetUrl = url('reset-password') . '?token=' . $token;
            $appUrl = rtrim(env('APP_URL', ''), '/');
            $subject = 'Réinitialisation de votre mot de passe — Athena';
            $body = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe (valide " . self::RESET_TOKEN_EXPIRE_HOURS . " h) :\n\n" . $resetUrl . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.";
            $headers = 'From: ' . (env('MAIL_FROM', 'noreply@athena.local')) . "\r\nReply-To: " . (env('MAIL_FROM', 'noreply@athena.local')) . "\r\nContent-Type: text/plain; charset=utf-8";
            @mail($email, $subject, $body, $headers);
            $this->auditService->log(
                AuditAction::AUTH_PASSWORD_RESET_REQUESTED,
                (int) $tenant['id'],
                (int) $user['id'],
                'user',
                (int) $user['id']
            );
        }
        Session::flash('success', 'Si cette adresse est connue, un lien de réinitialisation a été envoyé.');
        return Response::redirect(url('forgot-password'));
    }

    public function showResetPassword(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $token = trim((string) ($request->query('token') ?? $request->input('token') ?? ''));
        if ($token === '') {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('forgot-password'));
        }
        $hash = hash('sha256', $token);
        $reset = $this->passwordResetRepository->findValidByToken($hash);
        if (!$reset) {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('forgot-password'));
        }
        $error = Session::getFlash('error');
        return Response::view('auth.reset-password', [
            'title' => 'Nouveau mot de passe',
            'token' => $token,
            'error' => $error,
        ]);
    }

    public function processResetPassword(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('forgot-password'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('forgot-password'));
        }
        $token = trim((string) $request->input('token'));
        $hash = hash('sha256', $token);
        $reset = $this->passwordResetRepository->findValidByToken($hash);
        if (!$reset) {
            Session::flash('error', 'Lien invalide ou expiré.');
            return Response::redirect(url('forgot-password'));
        }
        $new = $request->input('password');
        $confirm = $request->input('password_confirmation');
        $v = new Validator(
            ['password' => $new, 'password_confirmation' => $confirm],
            ['password' => 'required|min:8', 'password_confirmation' => 'required']
        );
        if (!$v->validate() || $new !== $confirm) {
            Session::flash('error', 'Les deux mots de passe doivent être identiques (min. 8 caractères).');
            return Response::redirect(url('reset-password') . '?token=' . $token);
        }
        $passwordHash = password_hash((string) $new, PASSWORD_ARGON2ID);
        $user = $this->userRepository->findById((int) $reset['user_id']);
        $tenantId = $user ? (int) $user['tenant_id'] : 0;
        $this->userRepository->update((int) $reset['user_id'], $tenantId, ['password_hash' => $passwordHash]);
        $this->passwordResetRepository->deleteByToken($hash);
        $this->auditService->log(
            AuditAction::AUTH_PASSWORD_RESET_COMPLETED,
            $tenantId,
            (int) $reset['user_id'],
            'user',
            (int) $reset['user_id']
        );
        Session::flash('success', 'Mot de passe réinitialisé. Connectez-vous.');
        return Response::redirect(url('login'));
    }
}
