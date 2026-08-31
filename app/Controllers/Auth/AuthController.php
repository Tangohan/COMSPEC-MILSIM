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
use App\Services\EmailService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Support\LoginIntendedDestination;
use App\Support\LoginWelcomeGate;
use App\Support\PortalAccessChoice;
use App\Services\Auth\LoginSecurityOtpService;
use App\Services\Auth\LoginWelcomeProfileService;

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
        private LoginWelcomeProfileService $loginWelcomeProfileService,
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

        $continueUrl = $this->resolvePostLoginContinueUrl();
        LoginWelcomeGate::arm($continueUrl);

        return Response::redirect(url('login/accueil'));
    }

    /**
     * Destination après le sas d’accueil (intended → espace mémorisé → sélecteur → dashboard).
     */
    private function resolvePostLoginContinueUrl(): string
    {
        $after = LoginIntendedDestination::consumeRedirectUrl();
        if ($after !== null) {
            return $after;
        }

        if (PortalAccessChoice::isNoOrganizationContext()) {
            return url('dashboard');
        }

        $remembered = PortalAccessChoice::remembered();
        if ($remembered !== null) {
            if ($remembered === PortalAccessChoice::PORTAL_TBA && !PortalAccessChoice::canAccessTba()) {
                return PortalAccessChoice::redirectUrlFor(PortalAccessChoice::PORTAL_JNET);
            }

            return PortalAccessChoice::redirectUrlFor($remembered);
        }

        return url('login/choisir-espace');
    }

    public function showWelcome(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            LoginWelcomeGate::clear();

            return Response::redirect(url('login'));
        }
        $user = $this->authService->user();
        if (!is_array($user)) {
            LoginWelcomeGate::clear();

            return Response::redirect(url('login'));
        }
        $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);

        if (!LoginWelcomeGate::isPending()) {
            if (PortalAccessChoice::isNoOrganizationContext()) {
                return Response::redirect(url('dashboard'));
            }
            $remembered = PortalAccessChoice::remembered();
            if ($remembered !== null) {
                return Response::redirect(PortalAccessChoice::redirectUrlFor($remembered));
            }

            return Response::redirect(url('login/choisir-espace'));
        }

        $profile = $this->loginWelcomeProfileService->build($user);
        $brand = function_exists('email_brand_name') ? email_brand_name() : 'Athena';

        return Response::view('auth.welcome', [
            'title' => 'Bienvenue',
            'brand' => $brand,
            'displayName' => $profile['display_name'],
            'gradeLabel' => $profile['grade_label'],
            'unitLabel' => $profile['unit_label'],
            'avatarUrl' => $profile['avatar_url'],
            'initials' => $profile['initials'],
            'changes' => $profile['changes'],
            'enterUrl' => url('login/accueil'),
            'lockBackgroundUrl' => asset_url('assets/images/WES_Operator_V2_re_05.jpg'),
        ]);
    }

    public function enterWelcome(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            LoginWelcomeGate::clear();

            return Response::redirect(url('login'));
        }
        if (!$request->isPost()) {
            return Response::redirect(url('login/accueil'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', __('auth.flash_session_expired'));

            return Response::redirect(url('login/accueil'));
        }

        return Response::redirect(LoginWelcomeGate::consume());
    }

    public function showSelectPortal(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (LoginWelcomeGate::isPending()) {
            return Response::redirect(url('login/accueil'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        if (PortalAccessChoice::isNoOrganizationContext()) {
            return Response::redirect(url('dashboard'));
        }
        $canTba = PortalAccessChoice::canAccessTba();
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $tenantName = is_array($tenant) ? community_display_name($tenant) : '';

        return Response::view('auth.select-portal', [
            'title' => 'Choisir un espace',
            'canTba' => $canTba,
            'tenantName' => $tenantName,
            'displayName' => (string) (Session::get('display_name') ?? ($user['display_name'] ?? '')),
            'callsign' => (string) (Session::get('callsign') ?? ($user['callsign'] ?? '')),
        ]);
    }

    public function selectPortal(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (PortalAccessChoice::isNoOrganizationContext()) {
            return Response::redirect(url('dashboard'));
        }
        if (!$request->isPost()) {
            return Response::redirect(url('login/choisir-espace'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', __('auth.flash_session_expired'));

            return Response::redirect(url('login/choisir-espace'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        $portal = PortalAccessChoice::normalize((string) $request->input('portal', ''));
        if ($portal === null) {
            Session::flash('error', 'Choisissez un espace pour continuer.');

            return Response::redirect(url('login/choisir-espace'));
        }
        if ($portal === PortalAccessChoice::PORTAL_TBA && !PortalAccessChoice::canAccessTba()) {
            Session::flash('error', 'Vous n’avez pas accès au tableau de bord administratif.');

            return Response::redirect(url('login/choisir-espace'));
        }
        $persist = (string) $request->input('remember', '') === '1';
        PortalAccessChoice::remember($portal, $persist);
        if (is_array($user)) {
            $this->auditService->log(
                AuditAction::AUTH_PORTAL_SELECTED,
                (int) ($user['tenant_id'] ?? 0),
                (int) ($user['id'] ?? 0),
                'portal',
                null,
                null,
                json_encode(['portal' => $portal, 'remember' => $persist], JSON_UNESCAPED_UNICODE)
            );
        }

        return Response::redirect(PortalAccessChoice::redirectUrlFor($portal));
    }

    public function showLogin(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            if (LoginWelcomeGate::isPending()) {
                return Response::redirect(url('login/accueil'));
            }
            if (PortalAccessChoice::isNoOrganizationContext()) {
                return Response::redirect(url('dashboard'));
            }
            $remembered = PortalAccessChoice::remembered();
            if ($remembered !== null) {
                return Response::redirect(PortalAccessChoice::redirectUrlFor($remembered));
            }

            return Response::redirect(url('login/choisir-espace'));
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
            'title' => __('auth.title_login'),
        ]);
    }

    public function login(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', __('auth.flash_session_expired'));
            return Response::redirect(url('login'));
        }
        Session::forget('pending_community_selection');

        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        if ($email === '' || $password === '') {
            Session::flash('error', __('auth.flash_email_password_required'));
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
            Session::flash('error', __('auth.flash_invalid_credentials'));
            return Response::redirect(url('login'));
        }

        $emailNorm = strtolower(trim($email));
        $matchesAfterBlocklist = array_values(array_filter(
            $matches,
            fn (array $row): bool => $this->loginAllowedForTenantAndClient((int) ($row['tenant_id'] ?? 0), $emailNorm, $request)
        ));
        if ($matchesAfterBlocklist === []) {
            Session::forget('pending_verification_email');
            Session::flash('error', __('auth.flash_access_restricted'));
            return Response::redirect(url('login'));
        }
        $matches = $matchesAfterBlocklist;

        if (count($matches) === 1) {
            $row = $matches[0];
            if (($row['status'] ?? '') === 'pending_verification') {
                Session::set('pending_verification_email', $email);
                Session::flash('error', __('auth.flash_confirm_email'));
                return Response::redirect(url('login'));
            }
            Session::forget('pending_verification_email');
            $user = $this->userRepository->findById((int) $row['id'], (int) $row['tenant_id']);
            if (!$user) {
                Session::flash('error', __('auth.flash_account_not_found'));
                return Response::redirect(url('login'));
            }
            if ($this->loginSecurityOtpService->isLoginEmailOtpRequired($user)) {
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
            Session::flash('error', __('auth.flash_no_community'));
            return Response::redirect(url('login'));
        }

        if (count($pick) === 1) {
            $only = $pick[0];
            $user = $this->userRepository->findById((int) $only['user_id'], (int) $only['tenant_id']);
            if (!$user) {
                Session::flash('error', __('auth.flash_account_not_found'));
                return Response::redirect(url('login'));
            }
            if (($user['status'] ?? '') === 'pending_verification') {
                Session::flash('error', __('auth.flash_confirm_email'));
                return Response::redirect(url('login'));
            }
            if (!in_array(($user['status'] ?? ''), ['active', 'pending_verification'], true)) {
                Session::flash('error', __('auth.flash_account_unavailable'));
                return Response::redirect(url('login'));
            }
            if (($user['status'] ?? '') === 'active' && $this->loginSecurityOtpService->isLoginEmailOtpRequired($user)) {
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
            return Response::redirect(url('login/choisir-espace'));
        }
        $pending = Session::get('pending_community_selection');
        if (!is_array($pending) || empty($pending['candidates'])) {
            Session::flash('error', __('auth.flash_reconnect_choose'));
            return Response::redirect(url('login'));
        }
        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_community_selection');
            Session::flash('error', __('auth.flash_timeout'));
            return Response::redirect(url('login'));
        }

        $candidates = $this->filterCommunityCandidates($pending['candidates']);
        if ($candidates === []) {
            Session::forget('pending_community_selection');
            Session::flash('error', __('auth.flash_no_community_display'));
            return Response::redirect(url('login'));
        }
        $pending['candidates'] = $candidates;
        Session::set('pending_community_selection', $pending);

        return Response::view('auth.select-community', [
            'title' => __('auth.title_select_community'),
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
            Session::flash('error', __('auth.flash_session_expired'));
            return Response::redirect(url('login/select-community'));
        }
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $pending = Session::get('pending_community_selection');
        if (!is_array($pending) || empty($pending['candidates'])) {
            Session::flash('error', __('auth.flash_reconnect'));
            return Response::redirect(url('login'));
        }
        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_community_selection');
            Session::flash('error', __('auth.flash_timeout'));
            return Response::redirect(url('login'));
        }

        $allowed = $this->filterCommunityCandidates($pending['candidates']);
        if ($allowed === []) {
            Session::forget('pending_community_selection');
            Session::flash('error', __('auth.flash_invalid_session'));
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
            Session::flash('error', __('auth.flash_invalid_choice'));
            return Response::redirect(url('login/select-community'));
        }
        $user = $this->userRepository->findById((int) $chosen['user_id'], $tenantId);
        if (!$user || !in_array(($user['status'] ?? ''), ['active', 'pending_verification'], true)) {
            Session::forget('pending_community_selection');
            Session::flash('error', __('auth.flash_account_unavailable'));
            return Response::redirect(url('login'));
        }
        if (($user['status'] ?? '') === 'pending_verification') {
            Session::forget('pending_community_selection');
            Session::set('pending_verification_email', strtolower(trim((string) ($pending['email'] ?? ''))));
            Session::flash('error', __('auth.flash_confirm_email'));
            return Response::redirect(url('login'));
        }

        $emailNorm = strtolower(trim((string) ($pending['email'] ?? '')));
        if (!$this->loginAllowedForTenantAndClient($tenantId, $emailNorm, $request)) {
            Session::flash('error', __('auth.flash_community_inaccessible'));

            return Response::redirect(url('login/select-community'));
        }

        Session::forget('pending_community_selection');
        Session::forget('pending_verification_email');
        if ($this->loginSecurityOtpService->isLoginEmailOtpRequired($user)) {
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
            Session::flash('error', __('auth.flash_code_expired'));

            return Response::redirect(url('login'));
        }

        return Response::view('auth.login-otp', [
            'title' => __('auth.title_otp'),
            'emailMasked' => (string) ($pending['email_masked'] ?? '—'),
            'expiresAt' => (int) ($pending['expires_at'] ?? 0),
            'channel' => (string) ($pending['channel'] ?? LoginSecurityOtpService::CHANNEL_EMAIL),
            'canFallbackEmail' => !empty($pending['can_fallback_email']),
            'canFallbackTotp' => !empty($pending['can_fallback_totp']),
            'canResend' => ((string) ($pending['channel'] ?? '')) !== LoginSecurityOtpService::CHANNEL_TOTP,
        ]);
    }

    public function verifyLoginOtp(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', __('auth.flash_session_expired'));

            return Response::redirect(url('login/otp'));
        }
        $pending = Session::get('pending_login_security_otp');
        if (!is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', __('auth.flash_code_expired'));

            return Response::redirect(url('login'));
        }
        $code = preg_replace('/\D/', '', (string) $request->input('otp_code', '')) ?? '';
        $result = $this->loginSecurityOtpService->verifyPendingChallenge($pending, $code);
        if (!$result['ok']) {
            $msg = (string) ($result['message'] ?? __('auth.flash_wrong_code'));
            if (str_contains($msg, 'Trop de tentatives') || str_contains($msg, 'Reconnectez')) {
                Session::flash('error', $msg);

                return Response::redirect(url('login'));
            }
            Session::flash('error', $msg);

            return Response::redirect(url('login/otp'));
        }
        $user = $result['user'] ?? null;
        if (!is_array($user) || ($user['status'] ?? '') !== 'active') {
            Session::flash('error', __('auth.flash_account_unavailable'));

            return Response::redirect(url('login'));
        }

        return $this->redirectToDashboardAfterLogin($user, $request);
    }

    public function resendLoginOtp(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', __('auth.flash_session_expired'));

            return Response::redirect(url('login/otp'));
        }
        $pending = Session::get('pending_login_security_otp');
        if (!is_array($pending)) {
            Session::flash('error', __('auth.flash_no_verification'));

            return Response::redirect(url('login'));
        }
        if ((string) ($pending['channel'] ?? '') === LoginSecurityOtpService::CHANNEL_TOTP) {
            Session::flash('info', 'Utilisez le code affiché dans votre application d’authentification.');

            return Response::redirect(url('login/otp'));
        }
        $generated = (int) ($pending['generated_at'] ?? 0);
        if ($generated > 0 && (time() - $generated) < LoginSecurityOtpService::RESEND_INTERVAL_SEC) {
            Session::flash('error', __('auth.flash_wait_resend'));

            return Response::redirect(url('login/otp'));
        }
        $user = $this->userRepository->findById((int) ($pending['user_id'] ?? 0), (int) ($pending['tenant_id'] ?? 0));
        if (!$user) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', __('auth.flash_account_not_found'));

            return Response::redirect(url('login'));
        }

        return $this->loginSecurityOtpService->beginLoginChallenge($user, LoginSecurityOtpService::CHANNEL_EMAIL);
    }

    public function switchLoginOtpChannel(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', __('auth.flash_session_expired'));

            return Response::redirect(url('login/otp'));
        }
        $pending = Session::get('pending_login_security_otp');
        if (!is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', __('auth.flash_code_expired'));

            return Response::redirect(url('login'));
        }
        $target = (string) $request->input('channel', '');
        $user = $this->userRepository->findById((int) ($pending['user_id'] ?? 0), (int) ($pending['tenant_id'] ?? 0));
        if (!$user) {
            Session::forget('pending_login_security_otp');
            Session::flash('error', __('auth.flash_account_not_found'));

            return Response::redirect(url('login'));
        }
        if ($target === LoginSecurityOtpService::CHANNEL_EMAIL) {
            if (empty($pending['can_fallback_email']) && !$this->loginSecurityOtpService->isEmailOtpEnabled($user)
                && !$this->loginSecurityOtpService->isMandatoryForUserId((int) $user['id'])) {
                Session::flash('error', 'Le code par e-mail n’est pas disponible pour ce compte.');

                return Response::redirect(url('login/otp'));
            }

            return $this->loginSecurityOtpService->beginLoginChallenge($user, LoginSecurityOtpService::CHANNEL_EMAIL);
        }
        if ($target === LoginSecurityOtpService::CHANNEL_TOTP) {
            if (!$this->loginSecurityOtpService->isTotpEnabled($user)) {
                Session::flash('error', 'L’application d’authentification n’est pas activée sur ce compte.');

                return Response::redirect(url('login/otp'));
            }

            return $this->loginSecurityOtpService->beginLoginChallenge($user, LoginSecurityOtpService::CHANNEL_TOTP);
        }
        Session::flash('error', __('auth.flash_invalid_choice'));

        return Response::redirect(url('login/otp'));
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
            'title' => __('auth.title_forgot'),
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
            Session::flash('error', __('auth.flash_session_expired'));
            return Response::redirect(url('forgot-password'));
        }
        $email = trim((string) $request->input('email'));
        $v = new Validator(['email' => $email], ['email' => 'required|email']);
        if (!$v->validate()) {
            Session::flash('error', __('auth.flash_invalid_email'));
            return Response::redirect(url('forgot-password'));
        }

        $tenant = $this->tenantRepository->getDefaultTenant();
        if (!$tenant) {
            Session::flash('error', __('auth.flash_service_unavailable'));
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
        Session::flash('success', __('auth.flash_reset_sent'));
        return Response::redirect(url('forgot-password'));
    }

    public function showResetPassword(Request $request, array $params = []): Response
    {
        if ($this->authService->check()) {
            return Response::redirect(url('dashboard'));
        }
        $token = trim((string) ($request->query('token') ?? $request->input('token') ?? ''));
        if ($token === '') {
            Session::flash('error', __('auth.flash_link_invalid'));
            return Response::redirect(url('forgot-password'));
        }
        $hash = hash('sha256', $token);
        $reset = $this->passwordResetRepository->findValidByToken($hash);
        if (!$reset) {
            Session::flash('error', __('auth.flash_link_invalid'));
            return Response::redirect(url('forgot-password'));
        }
        $error = Session::getFlash('error');
        return Response::view('auth.reset-password', [
            'title' => __('auth.title_reset'),
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
            Session::flash('error', __('auth.flash_session_expired'));
            return Response::redirect(url('forgot-password'));
        }
        $token = trim((string) $request->input('token'));
        $hash = hash('sha256', $token);
        $reset = $this->passwordResetRepository->findValidByToken($hash);
        if (!$reset) {
            Session::flash('error', __('auth.flash_link_invalid'));
            return Response::redirect(url('forgot-password'));
        }
        $new = $request->input('password');
        $confirm = $request->input('password_confirmation');
        $v = new Validator(
            ['password' => $new, 'password_confirmation' => $confirm],
            ['password' => 'required|min:8', 'password_confirmation' => 'required']
        );
        if (!$v->validate() || $new !== $confirm) {
            Session::flash('error', __('auth.flash_passwords_mismatch'));
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
        Session::flash('success', __('auth.flash_password_reset_ok'));
        return Response::redirect(url('login'));
    }
}
