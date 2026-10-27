<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailTokenRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Support\DemoPortalAccounts;

/**
 * Second facteur après mot de passe : OTP e-mail et/ou application d’authentification (TOTP).
 */
final class LoginSecurityOtpService
{
    public const TTL_MINUTES = 10;

    /**
     * Durée de l'étape « application d'authentification » : le code TOTP change toutes les
     * 30 s de lui-même, l'étape n'a donc pas besoin d'expirer aussi vite qu'un code e-mail.
     */
    public const TOTP_CHALLENGE_TTL_MINUTES = 30;

    /**
     * Codes e-mail encore acceptés après un renvoi : un premier e-mail arrivé en retard
     * reste valable au lieu de produire « code invalide ou expiré ».
     */
    public const MAX_LIVE_EMAIL_CODES = 3;

    public const RESEND_INTERVAL_SEC = 60;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_TOTP = 'totp';

    public const MAX_TOTP_ATTEMPTS = 8;

    public function __construct(
        private UserRepository $userRepository,
        private EmailTokenRepository $emailTokenRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
        private TotpService $totpService,
        private TotpSecretCipher $totpSecretCipher,
        private RecoveryCodeService $recoveryCodeService = new RecoveryCodeService(),
        private TrustedDeviceService $trustedDeviceService = new TrustedDeviceService(),
    ) {}

    public function recoveryCodes(): RecoveryCodeService
    {
        return $this->recoveryCodeService;
    }

    public function trustedDevices(): TrustedDeviceService
    {
        return $this->trustedDeviceService;
    }

    /**
     * « Se souvenir de cet appareil » est proposé à tous sauf aux administrateurs de la plateforme,
     * à qui le code reste demandé à chaque connexion.
     *
     * @param array<string, mixed> $user
     */
    public function canTrustDevice(array $user): bool
    {
        if (!$this->trustedDeviceService->isAvailable()) {
            return false;
        }
        if ((int) ($user['is_platform_admin'] ?? 0) === 1) {
            return false;
        }

        return !$this->userRepository->emailHasPlatformAdmin((string) ($user['email'] ?? ''));
    }

    /**
     * Second facteur à demander pour cette connexion : exigé et pas d'appareil de confiance valide.
     *
     * @param array<string, mixed> $user
     */
    public function needsChallengeNow(array $user): bool
    {
        if (!$this->isSecondFactorRequired($user)) {
            return false;
        }
        if ($this->canTrustDevice($user) && $this->trustedDeviceService->isTrusted($user)) {
            return false;
        }

        return true;
    }

    /**
     * Code de secours saisi à l'étape de double vérification (téléphone perdu).
     * Compte dans le même plafond de tentatives que les codes à six chiffres.
     *
     * @param array<string, mixed> $pending
     * @return array{ok: bool, message: string, user?: array<string, mixed>, remaining?: int}
     */
    public function verifyPendingRecoveryCode(array $pending, string $code): array
    {
        $userId = (int) ($pending['user_id'] ?? 0);
        $tenantId = (int) ($pending['tenant_id'] ?? 0);
        if (strlen(RecoveryCodeService::normalize($code)) !== 8) {
            return ['ok' => false, 'message' => 'Un code de secours compte 8 caractères, par exemple ABCD-EFGH.'];
        }
        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= self::MAX_TOTP_ATTEMPTS) {
            Session::forget('pending_login_security_otp');

            return ['ok' => false, 'message' => 'Trop de tentatives. Reconnectez-vous.'];
        }
        $pending['attempts'] = $attempts + 1;
        Session::set('pending_login_security_otp', $pending);

        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            Session::forget('pending_login_security_otp');

            return ['ok' => false, 'message' => 'Compte indisponible.'];
        }
        if (!$this->recoveryCodeService->consume($userId, $code)) {
            return ['ok' => false, 'message' => 'Code de secours incorrect ou déjà utilisé.'];
        }
        Session::forget('pending_login_security_otp');

        return ['ok' => true, 'message' => 'ok', 'user' => $user, 'remaining' => $this->recoveryCodeService->remaining($userId)];
    }

    public function isMandatoryForUserId(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $user = $this->userRepository->findById($userId, null);
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if (DemoPortalAccounts::isDemoEmail($email)) {
            return false;
        }
        $slug = strtolower(trim((string) $this->userRepository->getRoleSlugForUser($userId)));

        return in_array($slug, ['security_admin', 'security_officer', 'tenant_admin', 'community_owner'], true);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isTotpEnabled(array $user): bool
    {
        if (!$this->userRepository->hasTotpColumns()) {
            return false;
        }

        return (int) ($user['totp_enabled'] ?? 0) === 1
            && trim((string) ($user['totp_secret'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $user
     */
    public function isEmailOtpEnabled(array $user): bool
    {
        if (!$this->userRepository->hasEmailLoginOtpEnabledColumn()) {
            return false;
        }

        return (int) ($user['email_login_otp_enabled'] ?? 0) === 1;
    }

    /**
     * Un second facteur est exigé (rôle sensible, OTP e-mail ou authenticator).
     *
     * @param array<string, mixed> $user
     */
    public function isSecondFactorRequired(array $user): bool
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '' && (int) ($user['id'] ?? 0) > 0) {
            $row = $this->userRepository->findById((int) $user['id'], null);
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if (is_array($row)) {
                $user = array_merge($user, $row);
            }
        }
        if (DemoPortalAccounts::isDemoEmail($email)) {
            return false;
        }
        $uid = (int) ($user['id'] ?? 0);
        if ($this->isMandatoryForUserId($uid)) {
            return true;
        }
        if ($this->isTotpEnabled($user)) {
            return true;
        }

        return $this->isEmailOtpEnabled($user);
    }

    /**
     * @deprecated Utiliser isSecondFactorRequired()
     * @param array<string, mixed> $user
     */
    public function isLoginEmailOtpRequired(array $user): bool
    {
        return $this->isSecondFactorRequired($user);
    }

    /**
     * Canal préféré pour le défi de connexion.
     *
     * @param array<string, mixed> $user
     */
    public function preferredLoginChannel(array $user): string
    {
        if ($this->isTotpEnabled($user)) {
            return self::CHANNEL_TOTP;
        }

        return self::CHANNEL_EMAIL;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function beginLoginChallenge(array $user, ?string $preferChannel = null): Response
    {
        $channel = $preferChannel;
        if ($channel !== self::CHANNEL_EMAIL && $channel !== self::CHANNEL_TOTP) {
            $channel = $this->preferredLoginChannel($user);
        }
        if ($channel === self::CHANNEL_TOTP && $this->isTotpEnabled($user)) {
            return $this->beginTotpChallenge($user);
        }
        if ($channel === self::CHANNEL_EMAIL) {
            return $this->beginEmailChallenge($user);
        }
        // Fallback : TOTP demandé mais indispo → e-mail ; e-mail impossible → TOTP si possible.
        if ($this->isTotpEnabled($user)) {
            return $this->beginTotpChallenge($user);
        }

        return $this->beginEmailChallenge($user);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function beginTotpChallenge(array $user): Response
    {
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($userId < 1 || $tenantId < 1 || !$this->isTotpEnabled($user)) {
            Session::flash('error', 'Application d’authentification indisponible pour ce compte.');

            return Response::redirect(url('login'));
        }

        Session::set('pending_login_security_otp', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'channel' => self::CHANNEL_TOTP,
            'email_masked' => $this->maskEmailForDisplay(trim((string) ($user['email'] ?? ''))),
            'expires_at' => time() + (self::TOTP_CHALLENGE_TTL_MINUTES * 60),
            'generated_at' => time(),
            'attempts' => 0,
            'can_fallback_email' => $this->canUseEmailFallback($user),
            'can_use_recovery' => $this->recoveryCodeService->remaining($userId) > 0,
            'can_remember' => $this->canTrustDevice($user),
        ]);
        return Response::redirect(url('login/otp'));
    }

    /**
     * @param array<string, mixed> $user
     */
    public function beginEmailChallenge(array $user): Response
    {
        $userId = (int) ($user['id'] ?? 0);
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($userId < 1 || $tenantId < 1) {
            Session::flash('error', 'Compte introuvable pour la vérification par e-mail.');

            return Response::redirect(url('login'));
        }

        $code = (string) random_int(100000, 999999);
        $nonce = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $code . '|' . $nonce);
        $expires = new \DateTimeImmutable('+' . self::TTL_MINUTES . ' minutes');
        // Les codes envoyés juste avant restent valables (e-mail lent puis « Renvoyer »),
        // dans la limite de MAX_LIVE_EMAIL_CODES.
        $tokenId = $this->emailTokenRepository->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::LOGIN_SECURITY_OTP,
            $tokenHash,
            $nonce,
            $expires,
            ['channel' => 'login_security_otp']
        );
        $this->emailTokenRepository->deleteOlderPendingBeyond(
            $userId,
            EmailTokenPurpose::LOGIN_SECURITY_OTP,
            self::MAX_LIVE_EMAIL_CODES
        );

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ($tenant['name'] ?? 'Votre communauté'));
        $email = trim((string) ($user['email'] ?? ''));
        $displayName = trim((string) ($user['display_name'] ?? '')) ?: $email;
        $ok = $this->emailService->sendLoginSecurityOtp(
            $email,
            $displayName,
            $tenantName,
            $code,
            self::TTL_MINUTES,
            $tenantId
        );
        if (!$ok) {
            $this->emailTokenRepository->deleteById($tokenId);
            Session::flash('error', 'Impossible d’envoyer le code par e-mail pour le moment.');

            return Response::redirect(url('login'));
        }
        Session::set('pending_login_security_otp', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'channel' => self::CHANNEL_EMAIL,
            'email_masked' => $this->maskEmailForDisplay($email),
            'token_hash' => $tokenHash,
            'expires_at' => $expires->getTimestamp(),
            'generated_at' => time(),
            'attempts' => 0,
            'can_fallback_totp' => $this->isTotpEnabled($user),
            'can_use_recovery' => $this->recoveryCodeService->remaining($userId) > 0,
            'can_remember' => $this->canTrustDevice($user),
        ]);
        Session::flash('info', 'Un code vient d’être envoyé sur votre adresse e-mail.');

        return Response::redirect(url('login/otp'));
    }

    /**
     * @param array<string, mixed> $pending
     * @return array{ok: bool, message: string, user?: array<string, mixed>}
     */
    public function verifyPendingChallenge(array $pending, string $code): array
    {
        $channel = (string) ($pending['channel'] ?? self::CHANNEL_EMAIL);
        $userId = (int) ($pending['user_id'] ?? 0);
        $tenantId = (int) ($pending['tenant_id'] ?? 0);
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '' || strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'Code invalide.'];
        }

        $attempts = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= self::MAX_TOTP_ATTEMPTS) {
            Session::forget('pending_login_security_otp');

            return ['ok' => false, 'message' => 'Trop de tentatives. Reconnectez-vous.'];
        }

        $pending['attempts'] = $attempts + 1;
        Session::set('pending_login_security_otp', $pending);

        if ($channel === self::CHANNEL_TOTP) {
            return $this->verifyTotpPending($pending, $code, $userId, $tenantId);
        }

        return $this->verifyEmailPending($pending, $code, $userId);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function decryptUserTotpSecret(array $user): ?string
    {
        $enc = trim((string) ($user['totp_secret'] ?? ''));
        if ($enc === '') {
            return null;
        }

        return $this->totpSecretCipher->decrypt($enc);
    }

    public function encryptTotpSecret(string $plainSecret): string
    {
        return $this->totpSecretCipher->encrypt($plainSecret);
    }

    public function totpService(): TotpService
    {
        return $this->totpService;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendMailboxSelfTest(int $userId, int $tenantId): array
    {
        if ($userId < 1 || $tenantId < 1) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $last = $this->emailTokenRepository->getLatestTokenCreatedAtForUserPurpose(
            $userId,
            EmailTokenPurpose::LOGIN_OTP_MAILBOX_SELF_TEST
        );
        if ($last !== null && (time() - $last->getTimestamp()) < self::RESEND_INTERVAL_SEC) {
            return ['ok' => false, 'message' => 'Patientez une minute avant de demander un nouvel envoi.'];
        }

        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            return ['ok' => false, 'message' => 'Compte introuvable.'];
        }
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Votre adresse e-mail de connexion est absente ou invalide. Mettez-la à jour dans les paramètres du compte.'];
        }

        $code = (string) random_int(100000, 999999);
        $nonce = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $code . '|' . $nonce);
        $expires = new \DateTimeImmutable('+' . self::TTL_MINUTES . ' minutes');
        $this->emailTokenRepository->deletePendingForUserPurpose($userId, EmailTokenPurpose::LOGIN_OTP_MAILBOX_SELF_TEST);
        $this->emailTokenRepository->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::LOGIN_OTP_MAILBOX_SELF_TEST,
            $tokenHash,
            $nonce,
            $expires,
            ['channel' => 'login_otp_mailbox_self_test']
        );

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = trim((string) ($tenant['name'] ?? 'Votre communauté'));
        $displayName = trim((string) ($user['display_name'] ?? '')) ?: $email;
        $ok = $this->emailService->sendLoginOtpMailboxSelfTest(
            $email,
            $displayName,
            $tenantName,
            $code,
            self::TTL_MINUTES,
            $tenantId
        );
        if (!$ok) {
            $this->emailTokenRepository->deletePendingForUserPurpose($userId, EmailTokenPurpose::LOGIN_OTP_MAILBOX_SELF_TEST);
            $detail = '';
            if (filter_var((string) \env('APP_DEBUG', ''), FILTER_VALIDATE_BOOLEAN)) {
                $err = trim((string) $this->emailService->getLastSendError());
                if ($err !== '') {
                    $detail = ' ' . $err;
                }
            }

            return ['ok' => false, 'message' => 'L’envoi a échoué. Réessayez plus tard ou contactez l’administration si le problème continue.' . $detail];
        }

        return [
            'ok' => true,
            'message' => 'Un code à six chiffres vient d’être envoyé. Il sert uniquement à vérifier votre boîte de réception ; il ne remplace pas une connexion. Validité environ ' . self::TTL_MINUTES . ' minutes.',
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function canUseEmailFallback(array $user): bool
    {
        if ($this->isEmailOtpEnabled($user)) {
            return true;
        }

        return $this->isMandatoryForUserId((int) ($user['id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $pending
     * @return array{ok: bool, message: string, user?: array<string, mixed>}
     */
    private function verifyTotpPending(array $pending, string $code, int $userId, int $tenantId): array
    {
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user || !$this->isTotpEnabled($user)) {
            Session::forget('pending_login_security_otp');

            return ['ok' => false, 'message' => 'Compte indisponible.'];
        }
        $secret = $this->decryptUserTotpSecret($user);
        if ($secret === null || !$this->totpService->verify($secret, $code)) {
            return ['ok' => false, 'message' => 'Code incorrect.'];
        }
        Session::forget('pending_login_security_otp');

        return ['ok' => true, 'message' => 'ok', 'user' => $user];
    }

    /**
     * @param array<string, mixed> $pending
     * @return array{ok: bool, message: string, user?: array<string, mixed>}
     */
    private function verifyEmailPending(array $pending, string $code, int $userId): array
    {
        $rows = $this->emailTokenRepository->findPendingForUserPurpose($userId, EmailTokenPurpose::LOGIN_SECURITY_OTP);
        if ($rows === []) {
            return ['ok' => false, 'message' => 'Code invalide ou expiré.'];
        }
        $match = null;
        foreach ($rows as $row) {
            $candidateHash = hash('sha256', $code . '|' . (string) ($row['nonce'] ?? ''));
            if (hash_equals((string) ($row['token_hash'] ?? ''), $candidateHash)) {
                $match = $row;
                break;
            }
        }
        if ($match === null) {
            return ['ok' => false, 'message' => 'Code incorrect.'];
        }
        $this->emailTokenRepository->markConsumed((int) $match['id']);
        $this->emailTokenRepository->deletePendingForUserPurpose($userId, EmailTokenPurpose::LOGIN_SECURITY_OTP);
        Session::forget('pending_login_security_otp');
        $tenantId = (int) ($pending['tenant_id'] ?? 0);
        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            return ['ok' => false, 'message' => 'Compte indisponible.'];
        }

        return ['ok' => true, 'message' => 'ok', 'user' => $user];
    }

    private function maskEmailForDisplay(string $email): string
    {
        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return $email === '' ? '—' : $email;
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $prefix = substr($local, 0, min(2, strlen($local)));

        return $prefix . '•••@' . $domain;
    }
}
