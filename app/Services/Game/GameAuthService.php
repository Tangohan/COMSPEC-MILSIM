<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Repositories\AthenaAccountRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\CommunityMediaDetails;
use App\Support\OperatorTacticalIdentity;
use App\Support\SilentSchemaMigration;
use App\Support\SteamId;

final class GameAuthService
{
    public const ACCESS_TTL_SEC = 7200;

    public const REFRESH_TTL_SEC = 2592000;

    public const OTP_TTL_SEC = 600;

    public function __construct(
        private AthenaAccountRepository $accounts,
        private UserRepository $users,
        private TenantRepository $tenants,
        private EmailService $email,
        private GameOverwatchExperienceService $experience,
        private ?UserProfileRepository $profiles = null,
        private ?TenantBrandingRepository $branding = null,
        private ?UserNotificationPreferencesRepository $notificationPreferences = null,
    ) {
        $this->profiles ??= new UserProfileRepository();
        $this->branding ??= new TenantBrandingRepository();
        $this->notificationPreferences ??= new UserNotificationPreferencesRepository();
        SilentSchemaMigration::run(base_path('bootstrap/athena_game_auth_migration.php'));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function authPassword(array $body): array
    {
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            return $this->fail('INVALID_CREDENTIALS', 401);
        }
        $account = $this->resolveOrPromoteAccount($email, $password);
        if ($account === null) {
            return $this->fail('INVALID_CREDENTIALS', 401);
        }
        if ((string) ($account['status'] ?? '') !== 'active') {
            return $this->fail('ACCOUNT_DISABLED', 403);
        }

        return $this->issueForAccount($account, $body, null, true);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function requestOtp(array $body): array
    {
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('INVALID_CREDENTIALS', 400);
        }
        $account = $this->accounts->findByEmail($email);
        if ($account === null) {
            $users = $this->users->listUsersForLoginByEmail($email);
            if ($users === []) {
                // Ne pas révéler l’existence du compte.
                return ['ok' => true, 'status' => 200, 'payload' => ['sent' => true]];
            }
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->accounts->insertOtp(
            $email,
            hash('sha256', $code),
            gmdate('Y-m-d H:i:s', time() + self::OTP_TTL_SEC)
        );
        $html = '<p>Votre code de connexion Athena (valable 10 minutes) :</p><p style="font-size:28px;letter-spacing:6px;font-weight:bold">'
            . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p><p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>';
        $this->email->send(
            EmailEvents::LOGIN_SECURITY_OTP,
            $email,
            'Code de connexion Athena',
            $html,
            'Votre code de connexion Athena : ' . $code,
            null,
            null,
            ['channel' => 'game_otp'],
            null,
            true
        );

        return ['ok' => true, 'status' => 200, 'payload' => ['sent' => true]];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function verifyOtp(array $body): array
    {
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $code = preg_replace('/\D+/', '', (string) ($body['code'] ?? '')) ?? '';
        if ($email === '' || strlen($code) !== 6) {
            return $this->fail('INVALID_CREDENTIALS', 401);
        }
        $row = $this->accounts->findLatestOtp($email);
        if ($row === null) {
            return $this->fail('OTP_EXPIRED', 401);
        }
        if (strtotime((string) ($row['expires_at'] ?? '')) < time()) {
            return $this->fail('OTP_EXPIRED', 401);
        }
        if ((int) ($row['attempts'] ?? 0) >= 8) {
            return $this->fail('OTP_EXPIRED', 401);
        }
        if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
            $this->accounts->bumpOtpAttempts((int) $row['id']);

            return $this->fail('INVALID_CREDENTIALS', 401);
        }
        $this->accounts->consumeOtp((int) $row['id']);
        $account = $this->accounts->findByEmail($email);
        if ($account === null) {
            $account = $this->promoteAccountFromUsers($email, null);
        }
        if ($account === null) {
            return $this->fail('INVALID_CREDENTIALS', 401);
        }
        if ((string) ($account['status'] ?? '') !== 'active') {
            return $this->fail('ACCOUNT_DISABLED', 403);
        }

        return $this->issueForAccount($account, $body, null, true);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function steamChallenge(array $body): array
    {
        $deviceId = $this->sanitizeDeviceId((string) ($body['device_id'] ?? ''));
        $steamId = SteamId::normalize((string) ($body['steam_id'] ?? $body['steam_uid'] ?? ''));
        if ($deviceId === '' || !$this->hasSteamId($steamId)) {
            return $this->fail('STEAM_NOT_LINKED', 400);
        }
        $nonce = bin2hex(random_bytes(16));

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'nonce' => $nonce,
                'steam_id' => $steamId,
                'requires_pairing' => true,
            ],
        ];
    }

    /**
     * SteamID seul n’est jamais une preuve : device + jeton de liaison Athena obligatoire.
     *
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function steamExchange(array $body): array
    {
        $deviceId = $this->sanitizeDeviceId((string) ($body['device_id'] ?? ''));
        $steamId = SteamId::normalize((string) ($body['steam_id'] ?? $body['steam_uid'] ?? ''));
        $pairing = trim((string) ($body['pairing_token'] ?? ''));
        if ($deviceId === '' || !$this->hasSteamId($steamId) || strlen($pairing) < 32) {
            return $this->fail('STEAM_NOT_LINKED', 401);
        }
        $account = $this->accounts->findBySteamId($steamId);
        if ($account === null) {
            return $this->fail('STEAM_NOT_LINKED', 401);
        }
        $row = $this->accounts->findPairing((int) $account['id'], $deviceId);
        if ($row === null) {
            return $this->fail('STEAM_NOT_LINKED', 401);
        }
        if (!hash_equals((string) $row['pairing_token_hash'], hash('sha256', $pairing))) {
            return $this->fail('STEAM_NOT_LINKED', 401);
        }
        if (SteamId::normalize((string) ($row['steam_id'] ?? '')) !== $steamId) {
            return $this->fail('STEAM_NOT_LINKED', 401);
        }
        $this->accounts->touchPairing((int) $row['id']);
        $body['steam_id'] = $steamId;

        return $this->issueForAccount($account, $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function restore(array $body): array
    {
        $refresh = trim((string) ($body['refresh_token'] ?? ''));
        if (strlen($refresh) < 32) {
            return $this->fail('SESSION_EXPIRED', 401);
        }
        $session = $this->accounts->findSessionByRefreshHash(hash('sha256', $refresh));
        if ($session === null) {
            return $this->fail('SESSION_EXPIRED', 401);
        }
        if (strtotime((string) ($session['refresh_expires_at'] ?? '')) < time()) {
            return $this->fail('SESSION_EXPIRED', 401);
        }
        $account = $this->accounts->findById((int) $session['account_id']);
        if ($account === null || (string) ($account['status'] ?? '') !== 'active') {
            return $this->fail('ACCOUNT_DISABLED', 403);
        }
        $body['device_id'] = (string) ($session['device_id'] ?? $body['device_id'] ?? '');
        $body['preferred_tenant_id'] = (int) $session['tenant_id'];
        $this->carrySteamIdFromSession($body, $session);

        return $this->issueForAccount($account, $body, (int) $session['id']);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    public function refresh(array $body, array $session): array
    {
        $account = $this->accounts->findById((int) $session['account_id']);
        if ($account === null) {
            return $this->fail('SESSION_EXPIRED', 401);
        }
        $body['device_id'] = (string) ($session['device_id'] ?? '');
        $body['preferred_tenant_id'] = (int) $session['tenant_id'];
        $this->carrySteamIdFromSession($body, $session);

        return $this->issueForAccount($account, $body, (int) $session['id']);
    }

    public function logout(array $session): void
    {
        $this->accounts->revokeSession((int) $session['id']);
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function bootstrap(array $session): array
    {
        $this->accounts->touchSession((int) $session['id']);
        $account = $this->accounts->findById((int) $session['account_id']);
        $membership = $this->accounts->findMembership((int) $session['account_id'], (int) $session['tenant_id']);
        if ($account === null || $membership === null) {
            return ['error' => 'SESSION_EXPIRED'];
        }
        $payload = $this->buildAuthenticatedPayload($account, $membership, $session, null, false);
        $payload['authenticated'] = true;

        return $payload;
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function profile(array $session, int $knownRevision = 0): array
    {
        $boot = $this->bootstrap($session);
        $rev = (int) ($boot['profile']['revision'] ?? 0);
        $out = ['revision' => $rev, 'changed' => $rev !== $knownRevision];
        if ($out['changed']) {
            $out['profile'] = $boot['profile'] ?? [];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sessionFromBearer(?string $token): ?array
    {
        $token = trim((string) $token);
        if (strlen($token) < 32) {
            return null;
        }
        $row = $this->accounts->findSessionByAccessHash(hash('sha256', $token));
        if ($row === null) {
            return null;
        }
        if (strtotime((string) ($row['expires_at'] ?? '')) < time()) {
            return null;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function brandingForSlug(string $slug): array
    {
        $tenant = $this->tenants->findBySlug($slug);
        if ($tenant === null) {
            return $this->genericBranding();
        }
        $tid = (int) $tenant['id'];
        $exp = $this->experience->get($tid);
        $brandRow = $this->branding?->findByTenantId($tid);
        $merged = $this->branding?->mergeWithTenantLogo($tenant, $brandRow) ?? [];
        $name = trim((string) ($exp['display_name'] ?: ($tenant['name'] ?? 'Athena')));
        $image = $this->experience->loginImageUrl($tid, $exp, (string) ($merged['logo_url'] ?? $tenant['logo_url'] ?? ''));
        $logo = CommunityMediaDetails::publicUrl((string) ($exp['logo_path'] ?? '')) ?: (string) ($merged['logo_url'] ?? '');

        return [
            'name' => $name,
            'login_image' => $image,
            'logo' => $logo,
            'welcome_message' => (string) ($exp['welcome_message'] ?? ''),
            'generic' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function genericBranding(): array
    {
        return [
            'name' => 'ATHENA',
            'login_image' => '',
            'logo' => '',
            'welcome_message' => 'Connexion à l’environnement opérationnel',
            'generic' => true,
        ];
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $body
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    private function issueForAccount(array $account, array $body, ?int $rotateSessionId = null, bool $fromEmailLogin = false): array
    {
        $memberships = $this->accounts->listActiveMemberships((int) $account['id']);
        if ($memberships === []) {
            return $this->fail('NO_TENANT', 403);
        }
        $requested = (int) ($body['preferred_tenant_id'] ?? $body['tenant_id'] ?? 0);
        $chosen = $this->pickMembership($memberships, $requested);
        if ($chosen === null) {
            return $this->fail('NO_TENANT', 403);
        }
        $tenantId = (int) $chosen['tenant_id'];
        $exp = $this->experience->get($tenantId);
        $modVersion = trim((string) ($body['mod_version'] ?? ''));
        $min = trim((string) ($exp['min_mod_version'] ?? ''));
        if ($modVersion !== '' && $min !== '' && version_compare($modVersion, $min, '<')) {
            return $this->fail('MOD_OUTDATED', 426, [
                'min_mod_version' => $min,
                'detected_mod_version' => $modVersion,
            ]);
        }
        $deviceId = $this->sanitizeDeviceId((string) ($body['device_id'] ?? ''));
        if ($deviceId === '') {
            $deviceId = bin2hex(random_bytes(16));
        }
        $steamLink = ['status' => 'none', 'notice' => '', 'linked_now' => false];
        if ($fromEmailLogin) {
            $steamLink = $this->attachSteamFromEmailLogin($account, $chosen, $this->clientSteamFromBody($body));
            if (in_array((string) $steamLink['status'], ['conflict', 'mismatch'], true)) {
                unset($body['steam_id'], $body['steam_uid']);
            }
        }
        $steamId = $this->resolveSteamId($body, $account);
        $access = bin2hex(random_bytes(32));
        $refresh = bin2hex(random_bytes(32));
        $pairingPlain = null;
        $pairingHash = null;
        // SteamId::normalize() renvoie null, pas ''. `!== ''` laisserait passer null
        // jusqu’à upsertPairing(string) — TypeError en production au restore.
        if ($this->hasSteamId($steamId)) {
            $pairingPlain = bin2hex(random_bytes(32));
            $pairingHash = hash('sha256', $pairingPlain);
            $this->accounts->upsertPairing((int) $account['id'], $deviceId, $steamId, $pairingHash);
        }
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::ACCESS_TTL_SEC);
        $refreshExpires = gmdate('Y-m-d H:i:s', time() + self::REFRESH_TTL_SEC);
        if ($rotateSessionId !== null && $rotateSessionId > 0) {
            $this->accounts->rotateSessionTokens(
                $rotateSessionId,
                hash('sha256', $access),
                hash('sha256', $refresh),
                $expiresAt,
                $refreshExpires
            );
        } else {
            $this->accounts->insertSession([
                'account_id' => (int) $account['id'],
                'user_id' => (int) $chosen['user_id'],
                'tenant_id' => $tenantId,
                'device_id' => $deviceId,
                'access_token_hash' => hash('sha256', $access),
                'refresh_token_hash' => hash('sha256', $refresh),
                'steam_id' => $this->hasSteamId($steamId) ? $steamId : null,
                'pairing_token_hash' => $pairingHash,
                'mod_version' => $modVersion !== '' ? $modVersion : null,
                'extension_version' => trim((string) ($body['extension_version'] ?? '')) ?: null,
                'expires_at' => $expiresAt,
                'refresh_expires_at' => $refreshExpires,
            ]);
        }
        $this->accounts->touchMembership((int) $account['id'], $tenantId);
        $sessionMeta = [
            'id' => $rotateSessionId ?? 0,
            'account_id' => (int) $account['id'],
            'user_id' => (int) $chosen['user_id'],
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'expires_at' => $expiresAt,
        ];
        $payload = $this->buildAuthenticatedPayload($account, $chosen, $sessionMeta, $exp, true);
        $payload['authenticated'] = true;
        $payload['tokens'] = [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'device_id' => $deviceId,
            'pairing_token' => $pairingPlain,
        ];
        $payload['notices'] = [
            'steam_status' => (string) ($steamLink['status'] ?? 'none'),
            'steam_linked' => $this->hasSteamId($steamId) || !empty($steamLink['linked_now']),
            'steam_message' => (string) ($steamLink['notice'] ?? ''),
        ];

        return ['ok' => true, 'status' => 200, 'payload' => $payload];
    }

    /**
     * @param list<array<string, mixed>> $memberships
     * @return array<string, mixed>|null
     */
    private function pickMembership(array $memberships, int $requestedTenantId): ?array
    {
        if ($requestedTenantId > 0) {
            foreach ($memberships as $row) {
                if ((int) $row['tenant_id'] === $requestedTenantId && (string) ($row['status'] ?? '') === 'active') {
                    return $row;
                }
            }

            return null;
        }
        if (count($memberships) === 1) {
            return $memberships[0];
        }
        foreach ($memberships as $row) {
            if ((int) ($row['is_default'] ?? 0) === 1) {
                return $row;
            }
        }

        return $memberships[0] ?? null;
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $membership
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $exp
     * @return array<string, mixed>
     */
    private function buildAuthenticatedPayload(array $account, array $membership, array $session, ?array $exp, bool $includeTokensHint): array
    {
        unset($includeTokensHint);
        $tenantId = (int) $membership['tenant_id'];
        $userId = (int) $membership['user_id'];
        $tenant = $this->tenants->findById($tenantId) ?? [];
        $exp ??= $this->experience->get($tenantId);
        $user = $this->users->findById($userId, $tenantId) ?? [];
        $profileRow = [];
        try {
            $profileRow = $this->profiles?->getByUserId($userId) ?? [];
        } catch (\Throwable) {
            $profileRow = [];
        }
        $brandRow = $this->branding?->findByTenantId($tenantId);
        $mergedBrand = $this->branding?->mergeWithTenantLogo($tenant, $brandRow) ?? [];
        $tenantName = trim((string) ($tenant['name'] ?? $membership['tenant_name'] ?? ''));
        $displayName = trim((string) ($exp['display_name'] ?: ($tenantName !== '' ? $tenantName : 'Athena')));
        $identity = $this->resolveOperatorIdentity($user, $profileRow, $membership, $userId, $tenantId);
        $first = $identity['first_name'];
        $last = $identity['last_name'];
        $grade = $identity['grade'];
        $unit = OperatorTacticalIdentity::unitAssignment($identity['unit'], $tenantName, $displayName);
        $callsign = OperatorTacticalIdentity::sanitizeCallsign($identity['callsign'], $tenantName, $displayName);
        $avatar = $identity['avatar'];
        $role = $identity['role'];
        $function = $identity['function'];
        $rev = $this->profileRevision($user, $profileRow);
        $loginImage = $this->experience->loginImageUrl(
            $tenantId,
            $exp,
            (string) ($mergedBrand['logo_url'] ?? $tenant['logo_url'] ?? '')
        );
        $logo = CommunityMediaDetails::publicUrl((string) ($exp['logo_path'] ?? ''))
            ?: (string) ($mergedBrand['logo_url'] ?? $tenant['logo_url'] ?? '');
        $expiresIso = gmdate('c', strtotime((string) ($session['expires_at'] ?? 'now')) ?: time());

        return [
            'session' => [
                'expires_at' => $expiresIso,
            ],
            'account' => [
                'id' => (string) ($account['public_id'] ?? ''),
                'email' => (string) ($account['email'] ?? ''),
            ],
            'tenant' => [
                'id' => $tenantId,
                'slug' => (string) ($tenant['slug'] ?? $membership['tenant_slug'] ?? ''),
                'name' => $tenantName,
                'short_name' => $displayName,
            ],
            'profile' => [
                'user_id' => $userId,
                'first_name' => $first,
                'last_name' => $last,
                'grade' => $grade,
                'callsign' => $callsign,
                'unit' => $unit,
                'role' => $role,
                'function' => $function,
                'avatar' => $avatar,
                'revision' => $rev,
            ],
            'branding' => [
                'name' => $displayName,
                'login_image' => $loginImage,
                'logo' => $logo,
                'welcome_message' => (string) ($exp['welcome_message'] ?? ''),
                'render_url' => rtrim((string) url('api/game/v1/branding/render/' . rawurlencode((string) ($tenant['slug'] ?? ''))), '/'),
            ],
            'overwatch' => [
                'enabled' => true,
                'update_interval' => (int) ($exp['update_interval'] ?? 5),
                'markers_enabled' => (bool) ($exp['markers_enabled'] ?? true),
                'chat_enabled' => (bool) ($exp['chat_enabled'] ?? true),
                'intel_enabled' => (bool) ($exp['intel_enabled'] ?? true),
                'bft_enabled' => (bool) ($exp['bft_enabled'] ?? true),
                'photos_enabled' => (bool) ($exp['photos_enabled'] ?? true),
                'jtac_enabled' => (bool) ($exp['jtac_enabled'] ?? true),
                'auth_password' => (bool) ($exp['auth_password'] ?? true),
                'auth_otp' => (bool) ($exp['auth_otp'] ?? true),
                'auth_steam' => (bool) ($exp['auth_steam'] ?? true),
                'allow_auto_reconnect' => (bool) ($exp['allow_auto_reconnect'] ?? true),
                'channel' => (string) ($exp['channel'] ?? 'PROD'),
                'min_mod_version' => (string) ($exp['min_mod_version'] ?? ''),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $profile
     */
    private function profileRevision(array $user, array $profile): int
    {
        $stamp = max(
            (int) strtotime((string) ($user['updated_at'] ?? '0')),
            (int) strtotime((string) ($profile['updated_at'] ?? '0')),
            (int) ($user['id'] ?? 0)
        );

        return $stamp > 0 ? $stamp : 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveOrPromoteAccount(string $email, string $password): ?array
    {
        $account = $this->accounts->findByEmail($email);
        if ($account !== null) {
            if (!password_verify($password, (string) ($account['password_hash'] ?? ''))) {
                return $this->tryUserPasswordThenPromote($email, $password);
            }

            return $account;
        }

        return $this->tryUserPasswordThenPromote($email, $password);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryUserPasswordThenPromote(string $email, string $password): ?array
    {
        $users = $this->users->listUsersForLoginByEmail($email);
        $matched = null;
        foreach ($users as $row) {
            $hash = (string) ($row['password_hash'] ?? '');
            if ($hash !== '' && password_verify($password, $hash)) {
                $matched = $row;
                break;
            }
        }
        if ($matched === null) {
            return null;
        }

        return $this->promoteAccountFromUsers($email, (string) $matched['password_hash']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function promoteAccountFromUsers(string $email, ?string $passwordHash): ?array
    {
        $users = $this->users->listUsersForLoginByEmail($email);
        if ($users === []) {
            return null;
        }
        $existing = $this->accounts->findByEmail($email);
        if ($existing !== null) {
            foreach ($users as $row) {
                $this->accounts->ensureMembership((int) $existing['id'], (int) $row['tenant_id'], (int) $row['id']);
            }

            return $existing;
        }
        $primary = $users[0];
        $hash = $passwordHash ?: (string) ($primary['password_hash'] ?? '');
        if ($hash === '') {
            return null;
        }
        $steam = null;
        foreach ($users as $row) {
            $sid = SteamId::normalize((string) ($row['steam_id'] ?? ''));
            if ($this->hasSteamId($sid)) {
                $steam = $sid;
                break;
            }
        }
        $id = $this->accounts->create([
            'email' => $email,
            'password_hash' => $hash,
            'email_verified_at' => $primary['email_verified_at'] ?? null,
            'steam_id' => $steam,
            'status' => 'active',
        ]);
        $default = true;
        foreach ($users as $row) {
            $this->accounts->ensureMembership($id, (int) $row['tenant_id'], (int) $row['id'], $default);
            $default = false;
        }

        return $this->accounts->findById($id);
    }

    /**
     * Texte d’identité joueur : jamais une adresse interne ni un champ vide.
     */
    public static function usableIdentityText(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return '';
        }
        $lower = strtolower($value);
        if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
            return '';
        }
        if (str_contains($lower, '/api/')) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $profileRow
     * @param array<string, mixed> $membership
     * @return array{first_name: string, last_name: string, callsign: string, grade: string, unit: string, role: string, function: string, avatar: string}
     */
    private function resolveOperatorIdentity(array $user, array $profileRow, array $membership, int $userId, int $tenantId): array
    {
        $personnel = [];
        try {
            $personnel = (new PersonnelProfileRepository())->getByUserId($userId) ?? [];
        } catch (\Throwable) {
            $personnel = [];
        }

        $first = trim((string) ($profileRow['first_name'] ?? ''));
        $last = trim((string) ($profileRow['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $bits = preg_split('/\s+/', trim((string) ($user['display_name'] ?? '')), 2) ?: [];
            $first = (string) ($bits[0] ?? '');
            $last = (string) ($bits[1] ?? '');
        }
        if ($first === '' && $last === '') {
            $character = trim((string) ($personnel['character_name'] ?? ''));
            if ($character !== '') {
                $bits = preg_split('/\s+/', $character, 2) ?: [];
                $first = (string) ($bits[0] ?? '');
                $last = (string) ($bits[1] ?? '');
            }
        }

        $callsign = OperatorTacticalIdentity::callsign(
            [
                (string) ($personnel['callsign'] ?? ''),
                (string) ($user['callsign'] ?? ''),
                (string) ($membership['callsign'] ?? ''),
            ]
        );

        $extras = $this->loadOperatorExtras($userId, $tenantId);
        $grade = self::usableIdentityText((string) ($user['grade_short'] ?? $user['grade_label'] ?? ''));
        if ($grade === '') {
            $gradeRow = [
                'grade_long' => $extras['grade_long'],
                'grade_short' => $extras['grade_short'],
                'rank_display' => (string) ($personnel['rank_display'] ?? ''),
                'role_name' => $extras['role'],
            ];
            $grade = function_exists('personnel_assigned_grade_label')
                ? self::usableIdentityText(personnel_assigned_grade_label($gradeRow, $extras['role']))
                : self::usableIdentityText($extras['grade_short'] !== '' ? $extras['grade_short'] : $extras['grade_long']);
        }
        $unit = self::usableIdentityText((string) ($user['unit_name'] ?? $membership['unit_name'] ?? ''));
        if ($unit === '') {
            $unit = self::usableIdentityText($extras['unit']);
        }
        $role = self::usableRoleLabel($extras['role'], $extras['role_slug']);
        $function = self::usableIdentityText((string) ($personnel['primary_role'] ?? ''));
        if ($function === '') {
            $function = self::usableIdentityText($extras['function']);
        }

        $displaySettings = null;
        try {
            $displaySettings = (new UserProfileDisplaySettingsRepository())->getByUserId($userId);
        } catch (\Throwable) {
            $displaySettings = null;
        }
        $avatar = '';
        if (function_exists('user_site_avatar_url')) {
            $picked = user_site_avatar_url($user, $personnel, is_array($displaySettings) ? $displaySettings : null);
            $avatar = is_string($picked) ? trim($picked) : '';
        }
        if ($avatar === '' && function_exists('personnel_operator_portrait_url')) {
            $row = $user;
            $row['character_portrait_path'] = (string) ($personnel['character_portrait_path'] ?? '');
            $picked = personnel_operator_portrait_url($row);
            $avatar = is_string($picked) ? trim($picked) : '';
        }
        if ($avatar === '') {
            $raw = trim((string) ($user['avatar_url'] ?? ''));
            if ($raw !== '' && function_exists('user_media_public_url')) {
                $avatar = (string) (user_media_public_url($raw) ?? '');
            } else {
                $avatar = $raw;
            }
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
            'callsign' => $callsign,
            'grade' => $grade,
            'unit' => $unit,
            'role' => $role,
            'function' => $function,
            'avatar' => $avatar,
        ];
    }

    /**
     * Intitulé de rôle lisible. Un slug technique n’est pas un libellé joueur.
     */
    public static function usableRoleLabel(?string $name, ?string $slug = ''): string
    {
        $name = self::usableIdentityText($name);
        $slug = trim((string) $slug);
        if ($name === '') {
            return '';
        }
        if ($slug !== '' && strcasecmp($name, $slug) === 0 && preg_match('/^[a-z0-9_]+$/', $name) === 1) {
            return '';
        }
        if (preg_match('/^[a-z0-9_]+$/', $name) === 1) {
            return '';
        }

        return $name;
    }

    /**
     * @return array{grade_short: string, grade_long: string, unit: string, role: string, role_slug: string, function: string}
     */
    private function loadOperatorExtras(int $userId, int $tenantId): array
    {
        $out = [
            'grade_short' => '',
            'grade_long' => '',
            'unit' => '',
            'role' => '',
            'role_slug' => '',
            'function' => '',
        ];
        try {
            $pdo = \App\Core\Database::getPdo();
            $st = $pdo->prepare(
                'SELECT COALESCE(g.label_short, \'\') AS grade_short,
                        COALESCE(g.label_long, \'\') AS grade_long,
                        COALESCE(r.name, \'\') AS role_name,
                        COALESCE(r.slug, \'\') AS role_slug
                 FROM users u
                 LEFT JOIN grades g ON g.id = u.grade_id
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ? AND u.tenant_id = ? LIMIT 1'
            );
            $st->execute([$userId, $tenantId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            $out['grade_short'] = trim((string) ($row['grade_short'] ?? ''));
            $out['grade_long'] = trim((string) ($row['grade_long'] ?? ''));
            $out['role'] = trim((string) ($row['role_name'] ?? ''));
            $out['role_slug'] = trim((string) ($row['role_slug'] ?? ''));
        } catch (\Throwable) {
        }
        try {
            $pdo = \App\Core\Database::getPdo();
            $st = $pdo->prepare(
                'SELECT un.name FROM personnel_profiles pp
                 INNER JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = ?
                 WHERE pp.user_id = ? LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $out['unit'] = trim((string) ($st->fetchColumn() ?: ''));
        } catch (\Throwable) {
        }
        if ($out['unit'] === '') {
            try {
                $pdo = \App\Core\Database::getPdo();
                $st = $pdo->prepare(
                    'SELECT un.name FROM user_units uu
                     INNER JOIN units un ON un.id = uu.unit_id
                     WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                     ORDER BY uu.id ASC LIMIT 1'
                );
                $st->execute([$userId]);
                $out['unit'] = trim((string) ($st->fetchColumn() ?: ''));
            } catch (\Throwable) {
            }
        }
        try {
            $pdo = \App\Core\Database::getPdo();
            $st = $pdo->prepare(
                'SELECT r.name AS role_name, pj.role_detail
                 FROM personnel_profile_job_roles pj
                 INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = pj.tenant_id
                 WHERE pj.tenant_id = ? AND pj.user_id = ?
                 ORDER BY pj.is_primary DESC, pj.sort_order ASC, pj.id ASC
                 LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $job = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
            $name = trim((string) ($job['role_name'] ?? ''));
            $detail = trim((string) ($job['role_detail'] ?? ''));
            if ($name !== '' || $detail !== '') {
                $out['function'] = $detail !== '' && $name !== '' ? $name . ' — ' . $detail : ($name !== '' ? $name : $detail);
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Identifiant Steam envoyé par le jeu pour cette requête, ou chaîne vide.
     *
     * @param array<string, mixed> $body
     */
    private function clientSteamFromBody(array $body): string
    {
        $fromClient = SteamId::normalize((string) ($body['steam_id'] ?? $body['steam_uid'] ?? ''));

        return $this->hasSteamId($fromClient) ? $fromClient : '';
    }

    /**
     * Après une connexion e-mail / code : associe l’identifiant Steam envoyé par le jeu
     * s’il n’était pas encore enregistré. N’écrase jamais un identifiant déjà présent.
     *
     * @param array<string, mixed> $account
     * @param array<string, mixed> $chosen
     * @return array{status: string, notice: string, linked_now: bool}
     */
    private function attachSteamFromEmailLogin(array &$account, array $chosen, string $clientSteam): array
    {
        $empty = ['status' => 'none', 'notice' => '', 'linked_now' => false];
        if (!$this->hasSteamId($clientSteam)) {
            return $empty;
        }
        $accountId = (int) ($account['id'] ?? 0);
        $userId = (int) ($chosen['user_id'] ?? 0);
        $tenantId = (int) ($chosen['tenant_id'] ?? 0);
        if ($accountId < 1 || $userId < 1 || $tenantId < 1) {
            return $empty;
        }
        $accountSteam = SteamId::normalize(isset($account['steam_id']) ? (string) $account['steam_id'] : null);
        $userSteam = SteamId::normalize((string) ($chosen['user_steam_id'] ?? ''));
        if (!$this->hasSteamId($userSteam)) {
            $userRow = $this->users->findById($userId, $tenantId) ?? [];
            $userSteam = SteamId::normalize(isset($userRow['steam_id']) ? (string) $userRow['steam_id'] : null);
        }

        if ($this->hasSteamId($accountSteam) && $accountSteam === $clientSteam) {
            if (!$this->hasSteamId($userSteam)) {
                $this->trySetUserSteam($userId, $tenantId, $clientSteam);
            }

            return ['status' => 'already', 'notice' => '', 'linked_now' => false];
        }
        if ($this->hasSteamId($userSteam) && $userSteam === $clientSteam && !$this->hasSteamId($accountSteam)) {
            if ($this->accounts->assignSteamIdIfEmpty($accountId, $clientSteam)) {
                $account['steam_id'] = $clientSteam;
            }

            return ['status' => 'already', 'notice' => '', 'linked_now' => false];
        }
        if ($this->hasSteamId($accountSteam) && $accountSteam !== $clientSteam) {
            $notice = 'Ce compte est déjà associé à un autre identifiant Steam. L’encadrement a été informé.';
            $this->notifySteamLinkOutcome($account, $chosen, $clientSteam, 'mismatch');

            return ['status' => 'mismatch', 'notice' => $notice, 'linked_now' => false];
        }
        if ($this->hasSteamId($userSteam) && $userSteam !== $clientSteam) {
            $notice = 'Ce compte est déjà associé à un autre identifiant Steam. L’encadrement a été informé.';
            $this->notifySteamLinkOutcome($account, $chosen, $clientSteam, 'mismatch');

            return ['status' => 'mismatch', 'notice' => $notice, 'linked_now' => false];
        }

        $takenAccount = $this->accounts->findBySteamId($clientSteam);
        if ($takenAccount !== null && (int) ($takenAccount['id'] ?? 0) !== $accountId) {
            $notice = 'Cet identifiant Steam est déjà associé à un autre compte. L’encadrement a été informé.';
            $this->notifySteamLinkOutcome($account, $chosen, $clientSteam, 'conflict');

            return ['status' => 'conflict', 'notice' => $notice, 'linked_now' => false];
        }
        $takenUser = $this->users->findBySteamId($clientSteam);
        if ($takenUser !== null && (int) ($takenUser['id'] ?? 0) !== $userId) {
            $takenEmail = strtolower(trim((string) ($takenUser['email'] ?? '')));
            $accountEmail = strtolower(trim((string) ($account['email'] ?? '')));
            if ($takenEmail === '' || $accountEmail === '' || $takenEmail !== $accountEmail) {
                $notice = 'Cet identifiant Steam est déjà associé à un autre compte. L’encadrement a été informé.';
                $this->notifySteamLinkOutcome($account, $chosen, $clientSteam, 'conflict');

                return ['status' => 'conflict', 'notice' => $notice, 'linked_now' => false];
            }
        }

        if (!$this->accounts->assignSteamIdIfEmpty($accountId, $clientSteam)) {
            $notice = 'Cet identifiant Steam est déjà associé à un autre compte. L’encadrement a été informé.';
            $this->notifySteamLinkOutcome($account, $chosen, $clientSteam, 'conflict');

            return ['status' => 'conflict', 'notice' => $notice, 'linked_now' => false];
        }
        $account['steam_id'] = $clientSteam;
        $this->trySetUserSteam($userId, $tenantId, $clientSteam);
        $this->notifySteamLinkOutcome($account, $chosen, $clientSteam, 'linked');

        return [
            'status' => 'linked',
            'notice' => 'Votre identifiant Steam a été associé à votre compte. Un courriel de confirmation vous a été envoyé.',
            'linked_now' => true,
        ];
    }

    private function trySetUserSteam(int $userId, int $tenantId, string $steamId): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->hasSteamId($steamId)) {
            return;
        }
        try {
            $this->users->update($userId, $tenantId, ['steam_id' => $steamId]);
        } catch (\Throwable) {
            // Contrainte ou fiche déjà renseignée : le compte Athena reste associé.
        }
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $chosen
     */
    private function notifySteamLinkOutcome(array $account, array $chosen, string $steamId, string $outcome): void
    {
        $tenantId = (int) ($chosen['tenant_id'] ?? 0);
        $userId = (int) ($chosen['user_id'] ?? 0);
        $email = strtolower(trim((string) ($account['email'] ?? '')));
        $tenant = $tenantId > 0 ? ($this->tenants->findById($tenantId) ?? []) : [];
        $tenantName = trim((string) ($tenant['name'] ?? $chosen['tenant_name'] ?? 'Athena'));
        $user = $userId > 0 ? ($this->users->findById($userId, $tenantId) ?? []) : [];
        $displayName = trim((string) ($user['display_name'] ?? $chosen['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($chosen['callsign'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = $email !== '' ? $email : 'Opérateur';
        }
        $callsign = trim((string) ($user['callsign'] ?? $chosen['callsign'] ?? ''));
        $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
        $safeTenant = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
        $safeSteam = htmlspecialchars($steamId, ENT_QUOTES, 'UTF-8');
        $safeCallsign = htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

        if ($outcome === 'linked') {
            $memberSubject = 'Votre identifiant Steam a été associé — ' . $tenantName;
            $memberHtml = '<p>Bonjour ' . $safeName . ',</p>'
                . '<p>Lors de votre connexion Athena en jeu, votre identifiant Steam a été associé à votre compte pour la communauté <strong>'
                . $safeTenant . '</strong>.</p>'
                . '<p>Identifiant associé : <strong>' . $safeSteam . '</strong></p>'
                . '<p>Les prochaines connexions en jeu pourront utiliser Steam, sans retaper votre e-mail. Si vous n’êtes pas à l’origine de cette connexion, contactez l’encadrement de votre communauté.</p>';
            $memberText = 'Lors de votre connexion Athena en jeu, votre identifiant Steam '
                . $steamId . ' a été associé à votre compte (' . $tenantName . ').';
            $staffSubject = 'Identifiant Steam associé — ' . $displayName . ' — ' . $tenantName;
            $staffHtml = '<p>Un opérateur a associé son identifiant Steam depuis Overwatch.</p>'
                . '<p>Communauté : <strong>' . $safeTenant . '</strong><br>'
                . 'Opérateur : <strong>' . $safeName . '</strong>'
                . ($callsign !== '' ? '<br>Indicatif : <strong>' . $safeCallsign . '</strong>' : '')
                . '<br>Courriel : ' . $safeEmail
                . '<br>Identifiant Steam : <strong>' . $safeSteam . '</strong></p>';
            $staffText = 'Opérateur ' . $displayName
                . ($callsign !== '' ? ' (' . $callsign . ')' : '')
                . ' — ' . $email . ' — identifiant Steam ' . $steamId
                . ' associé depuis le jeu (' . $tenantName . ').';
        } elseif ($outcome === 'conflict') {
            $memberSubject = 'Association Steam impossible — ' . $tenantName;
            $memberHtml = '<p>Bonjour ' . $safeName . ',</p>'
                . '<p>Lors de votre connexion Athena en jeu, l’identifiant Steam de cette session n’a pas pu être associé : il est déjà rattaché à un autre compte.</p>'
                . '<p>Votre session Athena reste ouverte. Contactez l’encadrement si vous pensez qu’il s’agit d’une erreur.</p>';
            $memberText = 'L’identifiant Steam de cette session n’a pas pu être associé : il est déjà rattaché à un autre compte.';
            $staffSubject = 'Association Steam en conflit — ' . $displayName . ' — ' . $tenantName;
            $staffHtml = '<p>Une connexion Athena en jeu n’a pas pu associer l’identifiant Steam : il est déjà rattaché à un autre compte.</p>'
                . '<p>Communauté : <strong>' . $safeTenant . '</strong><br>'
                . 'Opérateur : <strong>' . $safeName . '</strong>'
                . ($callsign !== '' ? '<br>Indicatif : <strong>' . $safeCallsign . '</strong>' : '')
                . '<br>Courriel : ' . $safeEmail
                . '<br>Identifiant Steam refusé : <strong>' . $safeSteam . '</strong></p>';
            $staffText = 'Conflit Steam pour ' . $displayName . ' — ' . $email . ' — identifiant ' . $steamId . ' (' . $tenantName . ').';
        } else {
            $memberSubject = 'Identifiant Steam déjà associé — ' . $tenantName;
            $memberHtml = '<p>Bonjour ' . $safeName . ',</p>'
                . '<p>Lors de votre connexion Athena en jeu, l’identifiant Steam de cette session n’a pas été enregistré : votre compte est déjà associé à un autre identifiant.</p>'
                . '<p>Votre session Athena reste ouverte. Contactez l’encadrement si vous devez le mettre à jour.</p>';
            $memberText = 'Votre compte est déjà associé à un autre identifiant Steam. La session reste ouverte.';
            $staffSubject = 'Identifiant Steam différent en jeu — ' . $displayName . ' — ' . $tenantName;
            $staffHtml = '<p>Un opérateur s’est connecté en jeu avec un identifiant Steam différent de celui déjà enregistré sur son compte.</p>'
                . '<p>Communauté : <strong>' . $safeTenant . '</strong><br>'
                . 'Opérateur : <strong>' . $safeName . '</strong>'
                . ($callsign !== '' ? '<br>Indicatif : <strong>' . $safeCallsign . '</strong>' : '')
                . '<br>Courriel : ' . $safeEmail
                . '<br>Identifiant présenté en jeu : <strong>' . $safeSteam . '</strong></p>'
                . '<p>Le compte n’a pas été modifié.</p>';
            $staffText = 'Steam différent en jeu pour ' . $displayName . ' — ' . $email . ' — présenté : ' . $steamId . ' (' . $tenantName . ').';
        }

        $this->sendSteamLinkMemberEmail($userId, $email, $tenantId, $memberSubject, $memberHtml, $memberText);
        $this->sendSteamLinkStaffEmails($tenantId, $email, $staffSubject, $staffHtml, $staffText);
    }

    private function sendSteamLinkMemberEmail(
        int $userId,
        string $email,
        int $tenantId,
        string $subject,
        string $html,
        string $text
    ): void {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if ($userId > 0
            && !$this->notificationPreferences?->isEmailEventEnabled($userId, EmailEvents::GAME_STEAM_LINKED_MEMBER)
        ) {
            return;
        }
        try {
            $this->email->send(
                EmailEvents::GAME_STEAM_LINKED_MEMBER,
                $email,
                $subject,
                $html,
                $text,
                $tenantId > 0 ? $tenantId : null,
                null,
                ['channel' => 'game_steam_link', 'to' => 'member'],
                null,
                true
            );
        } catch (\Throwable) {
            // La session reste ouverte même si le courriel échoue.
        }
    }

    private function sendSteamLinkStaffEmails(
        int $tenantId,
        string $memberEmail,
        string $subject,
        string $html,
        string $text
    ): void {
        if ($tenantId < 1) {
            return;
        }
        $recipients = $this->users->listGovernanceEmailsForTenant($tenantId);
        foreach ($recipients as $toRaw) {
            $to = strtolower(trim((string) $toRaw));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($memberEmail !== '' && strcasecmp($to, $memberEmail) === 0) {
                continue;
            }
            $staff = $this->users->findByEmail($tenantId, $to);
            $staffId = (int) ($staff['id'] ?? 0);
            if ($staffId > 0
                && !$this->notificationPreferences?->isEmailEventEnabled($staffId, EmailEvents::GAME_STEAM_LINKED_STAFF)
            ) {
                continue;
            }
            try {
                $this->email->send(
                    EmailEvents::GAME_STEAM_LINKED_STAFF,
                    $to,
                    $subject,
                    $html,
                    $text,
                    $tenantId,
                    null,
                    ['channel' => 'game_steam_link', 'to' => 'staff'],
                    null,
                    true
                );
            } catch (\Throwable) {
                // Continuer les autres destinataires.
            }
        }
    }

    /**
     * SteamID64 connu, ou chaîne vide. Jamais null : un identifiant absent ou
     * rejeté (partie solo, placeholder) ne doit pas bloquer e-mail / mot de passe
     * ni la restauration de session.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $account
     */
    private function resolveSteamId(array $body, array $account): string
    {
        $fromClient = SteamId::normalize((string) ($body['steam_id'] ?? $body['steam_uid'] ?? ''));
        if ($this->hasSteamId($fromClient)) {
            return $fromClient;
        }
        $fromAccount = SteamId::normalize(
            isset($account['steam_id']) ? (string) $account['steam_id'] : null
        );

        return $this->hasSteamId($fromAccount) ? $fromAccount : '';
    }

    /** @param array<string, mixed> $body */
    private function carrySteamIdFromSession(array &$body, array $session): void
    {
        if (trim((string) ($body['steam_id'] ?? $body['steam_uid'] ?? '')) !== '') {
            return;
        }
        $fromSession = SteamId::normalize(isset($session['steam_id']) ? (string) $session['steam_id'] : null);
        if ($this->hasSteamId($fromSession)) {
            $body['steam_id'] = $fromSession;
        }
    }

    /** True seulement pour un SteamID64 réel. `null !== ''` est vrai en PHP. */
    private function hasSteamId(mixed $steamId): bool
    {
        return is_string($steamId) && $steamId !== '';
    }

    private function sanitizeDeviceId(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '' || strlen($raw) > 64) {
            return '';
        }
        if (!preg_match('/^[a-f0-9-]{16,64}$/', $raw)) {
            return '';
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{ok: bool, status: int, payload: array<string, mixed>}
     */
    private function fail(string $code, int $status, array $extra = []): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'payload' => array_merge(['authenticated' => false, 'error' => $code], $extra),
        ];
    }
}
