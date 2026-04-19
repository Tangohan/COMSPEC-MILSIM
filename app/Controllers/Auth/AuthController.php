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
use App\Repositories\EmailTokenRepository;
use App\Repositories\PasswordResetRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Auth\LoginSecurityNotificationService;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Support\LoginIntendedDestination;
use App\Services\Auth\LoginSecurityOtpService;

class AuthController
{
    private const RESET_TOKEN_EXPIRE_HOURS = 1;

    /** Sélection de communauté après mot de passe (multi-tenant). */
    private const PENDING_COMMUNITY_TTL_SEC = 600;

    /** Tenants techniques non proposés à l’utilisateur (ex. tenant « par défaut » plateforme). */
    private const HIDDEN_COMMUNITY_SLUGS = ['default'];

    public function __construct(
        private AuthService $authService,
        private RbacService $rbacService,
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private EmailTokenRepository $emailTokenRepository,
        private PasswordResetRepository $passwordResetRepository,
        private AuditService $auditService,
        private EmailService $emailService,
        private LoginSecurityNotificationService $loginSecurityNotifications,
        private IndicatorBlocklistService $indicatorBlocklist,
        private LoginSecurityOtpService $loginSecurityOtpService,
    ) {}

    /**
     * @param list<array{tenant_id: int, user_id: int, tenant_name: string, tenant_slug: string}> $candidates
     * @return list<array{tenant_id: int, user_id: int, tenant_name: string, tenant_slug: string}>
     */
    private function filterCommunityCandidates(array $candidates): array
    {
        $hidden = array_map('strtolower', self::HIDDEN_COMMUNITY_SLUGS);

        return array_values(array_filter($candidates, static function (array $c) use ($hidden): bool {
            $slug = strtolower(trim((string) ($c['tenant_slug'] ?? '')));

            return $slug === '' || !in_array($slug, $hidden, true);
        }));
    }

    private function loginAllowedForTenantAndClient(int $tenantId, string $emailNorm, Request $request): bool
    {
        if ($this->indicatorBlocklist->isEmailBlockedForTenant($tenantId, $emailNorm)) {
            return false;
        }
        $ip = trim($request->ip());
        if ($ip !== '' && $this->indicatorBlocklist->isIpBlockedForLogin($tenantId, $ip)) {
            return false;
        }

        return true;
    }

    private function redirectToDashboardAfterLogin(array $user, Request $request): Response
    {
        Session::forget('pending_verification_email');
        $this->authService->loginUser($user);
        $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        $this->auditService->log(
            AuditAction::AUTH_LOGIN_SUCCESS,
            (int) $user['tenant_id'],
            (int) $user['id'],
            'auth',
            (int) $user['id']
        );
        $this->loginSecurityNotifications->onSuccessfulLogin($request, $user);

        $after = LoginIntendedDestination::consumeRedirectUrl();
        if ($after !== null) {
            return Response::redirect($after);
        }

        return Response::redirect(url('dashboard'));
    }

    public function showLogin(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $pendingOtp = Session::get('pending_login_security_otp');
        if (is_array($pendingOtp) && (int) ($pendingOtp['expires_at'] ?? 0) >= time()) {
            return Response::redirect(url('login/otp'));
        }
        $pending = Session::get('pending_community_selection');
        if (
            is_array($pending)
            && !empty($pending['candidates'])
            && (int) ($pending['expires_at'] ?? 0) >= time()
        ) {
            return Response::redirect(url('login/select-community'));
        }
        return Response::view('auth.login', [
            'title' => 'Connexion',
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
        Session::forget('pending_community_selection');

        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        if ($email === '' || $password === '') {
            Session::flash('error', 'Email et mot de passe requis.');
            return Response::redirect(url('login'));
        }

        $candidates = $this->userRepository->listUsersForLoginByEmail($email);
        $matches = [];
        foreach ($candidates as $row) {
            if (!password_verify($password, (string) ($row['password_hash'] ?? ''))) {
                continue;
            }
            $matches[] = $row;
        }

        if ($matches === []) {
            Session::forget('pending_verification_email');
            $auditTenantId = null;
            if ($candidates !== []) {
                $auditTenantId = (int) $candidates[0]['tenant_id'];
            } else {
                $def = $this->tenantRepository->getDefaultTenant();
                $auditTenantId = $def ? (int) $def['id'] : null;
            }
            $this->auditService->log(
                AuditAction::AUTH_LOGIN_FAILURE,
                $auditTenantId,
                null,
                'auth',
                null,
                null,
                substr($email, 0, 120)
            );
            $this->loginSecurityNotifications->onFailedLogin($request, $email);
            Session::flash('error', 'Identifiants incorrects ou compte inactif.');
            return Response::redirect(url('login'));
        }

        $emailNorm = strtolower(trim($email));
        $matchesAfterBlocklist = array_values(array_filter(
            $matches,
            fn (array $row): bool => $this->loginAllowedForTenantAndClient((int) ($row['tenant_id'] ?? 0), $emailNorm, $request)
        ));
        if ($matchesAfterBlocklist === []) {
            Session::forget('pending_verification_email');
            Session::flash('error', 'Votre accès est restreint pour cette communauté ou depuis cet équipement.');
            return Response::redirect(url('login'));
        }
        $matches = $matchesAfterBlocklist;

        if (count($matches) === 1) {
            $row = $matches[0];
            if (($row['status'] ?? '') === 'pending_verification') {
                Session::set('pending_verification_email', $email);
                Session::flash('error', 'Confirmez votre adresse e-mail avant de vous connecter (lien envoyé à l’inscription).');
                return Response::redirect(url('login'));
            }
            Session::forget('pending_verification_email');
            $user = $this->userRepository->findById((int) $row['id'], (int) $row['tenant_id']);
            if (!$user) {
                Session::flash('error', 'Compte introuvable.');
                return Response::redirect(url('login'));
            }
            if ($this->loginSecurityOtpService->isMandatoryForUserId((int) ($user['id'] ?? 0))) {
                return $this->loginSecurityOtpService->beginLoginChallenge($user);
            }
            return $this->redirectToDashboardAfterLogin($user, $request);
        }

        $pick = [];
        foreach ($matches as $row) {
            $pick[] = [
                'tenant_id' => (int) $row['tenant_id'],
                'user_id' => (int) $row['id'],
                'tenant_name' => (string) ($row['tenant_name'] ?? ''),
                'tenant_slug' => (string) ($row['tenant_slug'] ?? ''),
            ];
        }
        $pick = $this->filterCommunityCandidates($pick);

        if ($pick === []) {
            Session::flash('error', 'Aucune communauté disponible pour cette connexion. Si le problème persiste, contactez un administrateur.');
            return Response::redirect(url('login'));
        }

        if (count($pick) === 1) {
            $only = $pick[0];
            $user = $this->userRepository->findById((int) $only['user_id'], (int) $only['tenant_id']);
            if (!$user) {
                Session::flash('error', 'Compte introuvable.');
                return Response::redirect(url('login'));
            }
            if (($user['status'] ?? '') === 'pending_verification') {
                Session::flash('error', 'Confirmez votre adresse e-mail avant de vous connecter (lien envoyé à l’inscription).');
                return Response::redirect(url('login'));
            }
            if (!in_array(($user['status'] ?? ''), ['active', 'pending_verification'], true)) {
                Session::flash('error', 'Compte indisponible.');
                return Response::redirect(url('login'));
            }
            if (($user['status'] ?? '') === 'active' && $this->loginSecurityOtpService->isMandatoryForUserId((int) ($user['id'] ?? 0))) {
                return $this->loginSecurityOtpService->beginLoginChallenge($user);
            }

            return $this->redirectToDashboardAfterLogin($user, $request);
        }

        Session::forget('pending_verification_email');
        Session::set('pending_community_selection', [
            'email' => $email,
            'candidates' => $pick,
            'expires_at' => time() + self::PENDING_COMMUNITY_TTL_SEC,
        ]);

        return Response::redirect(url('login/select-community'));
    }

    public function showSelectCommunity(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $pending = Session::get('pending_community_selection');
        if (!is_array($pending) || empty($pending['candidates'])) {
            Session::flash('error', 'Reconnectez-vous pour choisir une communauté.');
            return Response::redirect(url('login'));
        }
        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_community_selection');
            Session::flash('error', 'Délai dépassé. Reconnectez-vous.');
            return Response::redirect(url('login'));
        }

        $candidates = $this->filterCommunityCandidates($pending['candidates']);
        if ($candidates === []) {
            Session::forget('pending_community_selection');
            Session::flash('error', 'Aucune communauté à afficher. Reconnectez-vous.');
            return Response::redirect(url('login'));
        }
        $pending['candidates'] = $candidates;
        Session::set('pending_community_selection', $pending);

        return Response::view('auth.select-community', [
            'title' => 'Choisir une communauté',
            'email' => (string) ($pending['email'] ?? ''),
            'candidates' => $candidates,
        ]);
    }

    public function selectCommunity(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('login/select-community'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');
            return Response::redirect(url('login/select-community'));
        }
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $pending = Session::get('pending_community_selection');
        if (!is_array($pending) || empty($pending['candidates'])) {
            Session::flash('error', 'Reconnectez-vous.');
            return Response::redirect(url('login'));
        }
        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_community_selection');
            Session::flash('error', 'Délai dépassé. Reconnectez-vous.');
            return Response::redirect(url('login'));
        }

        $allowed = $this->filterCommunityCandidates($pending['candidates']);
        if ($allowed === []) {
            Session::forget('pending_community_selection');
            Session::flash('error', 'Session invalide. Reconnectez-vous.');
            return Response::redirect(url('login'));
        }

        $tenantId = (int) $request->input('tenant_id');
        $chosen = null;
        foreach ($allowed as $c) {
            if ((int) ($c['tenant_id'] ?? 0) === $tenantId) {
                $chosen = $c;
                break;
            }
        }
        if ($chosen === null) {
            Session::flash('error', 'Choix invalide.');
            return Response::redirect(url('login/select-community'));
        }
        $user = $this->userRepository->findById((int) $chosen['user_id'], $tenantId);
        if (!$user || !in_array(($user['status'] ?? ''), ['active', 'pending_verification'], true)) {
            Session::forget('pending_community_selection');
            Session::flash('error', 'Compte indisponible.');
            return Response::redirect(url('login'));
        }
        if (($user['status'] ?? '') === 'pending_verification') {
            Session::forget('pending_community_selection');
            Session::set('pending_verification_email', strtolower(trim((string) ($pending['email'] ?? ''))));
            Session::flash('error', 'Confirmez votre adresse e-mail avant de vous connecter.');
            return Response::redirect(url('login'));
        }

        $emailNorm = strtolower(trim((string) ($pending['email'] ?? '')));
        if (!$this->loginAllowedForTenantAndClient($tenantId, $emailNorm, $request)) {
            Session::flash('error', 'Cette communauté n’est pas accessible avec votre compte ou depuis cet équipement pour le moment.');

            return Response::redirect(url('login/select-community'));
        }

        Session::forget('pending_community_selection');
        Session::forget('pending_verification_email');
        if ($this->loginSecurityOtpService->isMandatoryForUserId((int) ($user['id'] ?? 0))) {
            return $this->loginSecurityOtpService->beginLoginChallenge($user);
        }

        return $this->redirectToDashboardAfterLogin($user, $request);
    }

    public function showLoginOtp(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $pending = Session::get('pending_login_security_otp');
        if (!is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', 'Code OTP expiré. Reconnectez-vous.');

            return Response::redirect(url('login'));
        }

        return Response::view('auth.login-otp', [
            'title' => 'Validation OTP',
            'emailMasked' => (string) ($pending['email_masked'] ?? '—'),
            'expiresAt' => (int) ($pending['expires_at'] ?? 0),
        ]);
    }

    public function verifyLoginOtp(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('login/otp'));
        }
        $pending = Session::get('pending_login_security_otp');
        if (!is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', 'Code OTP expiré. Reconnectez-vous.');

            return Response::redirect(url('login'));
        }
        $code = preg_replace('/\D/', '', (string) $request->input('otp_code', '')) ?? '';
        $stored = (string) ($pending['token_hash'] ?? '');
        $userId = (int) ($pending['user_id'] ?? 0);
        $tenantId = (int) ($pending['tenant_id'] ?? 0);
        if ($code === '' || strlen($code) !== 6 || $stored === '') {
            Session::flash('error', 'Code OTP invalide.');

            return Response::redirect(url('login/otp'));
        }

        // On retrouve le token exact en vérifiant les jetons valides de l’utilisateur.
        $row = $this->emailTokenRepository->findValidByHash($stored);
        if (!$row || (string) ($row['purpose'] ?? '') !== EmailTokenPurpose::LOGIN_SECURITY_OTP || (int) ($row['user_id'] ?? 0) !== $userId) {
            Session::flash('error', 'Code OTP invalide ou expiré.');

            return Response::redirect(url('login/otp'));
        }
        $nonce = (string) ($row['nonce'] ?? '');
        $candidateHash = hash('sha256', $code . '|' . $nonce);
        if (!hash_equals((string) $row['token_hash'], $candidateHash)) {
            Session::flash('error', 'Code OTP incorrect.');

            return Response::redirect(url('login/otp'));
        }
        $this->emailTokenRepository->markConsumed((int) $row['id']);
        Session::forget('pending_login_security_otp');
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            Session::flash('error', 'Compte indisponible.');

            return Response::redirect(url('login'));
        }

        return $this->redirectToDashboardAfterLogin($user, $request);
    }

    public function resendLoginOtp(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('login/otp'));
        }
        $pending = Session::get('pending_login_security_otp');
        if (!is_array($pending)) {
            Session::flash('error', 'Aucune session OTP en cours.');

            return Response::redirect(url('login'));
        }
        $generated = (int) ($pending['generated_at'] ?? 0);
        if ($generated > 0 && (time() - $generated) < LoginSecurityOtpService::RESEND_INTERVAL_SEC) {
            Session::flash('error', 'Attendez quelques secondes avant de renvoyer un code.');

            return Response::redirect(url('login/otp'));
        }
        $user = $this->userRepository->findById((int) ($pending['user_id'] ?? 0), (int) ($pending['tenant_id'] ?? 0));
        if (!$user) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', 'Compte introuvable.');

            return Response::redirect(url('login'));
        }

        return $this->loginSecurityOtpService->beginLoginChallenge($user);
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
        if ($user && empty($user['is_service_account'])) {
            $this->passwordResetRepository->deleteExpired();
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = new \DateTimeImmutable('+' . self::RESET_TOKEN_EXPIRE_HOURS . ' hours');
            $this->passwordResetRepository->create((int) $user['id'], $hash, $expires);
            $resetUrl = url('reset-password') . '?token=' . $token;
            $this->emailService->sendPasswordReset($email, $resetUrl, self::RESET_TOKEN_EXPIRE_HOURS, (int) $tenant['id']);
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
        if ($user !== null && ($user['status'] ?? '') === 'pending_verification') {
            $this->userRepository->markEmailVerified((int) $reset['user_id'], $tenantId);
        }
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
