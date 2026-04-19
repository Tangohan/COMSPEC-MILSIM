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

/**
 * OTP e-mail après mot de passe pour certains rôles, plus auto-test depuis les préférences.
 */
final class LoginSecurityOtpService
{
    public const TTL_MINUTES = 10;

    public const RESEND_INTERVAL_SEC = 60;

    public function __construct(
        private UserRepository $userRepository,
        private EmailTokenRepository $emailTokenRepository,
        private TenantRepository $tenantRepository,
        private EmailService $emailService,
    ) {}

    public function isMandatoryForUserId(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        $slug = strtolower(trim((string) $this->userRepository->getRoleSlugForUser($userId)));

        return in_array($slug, ['security_admin', 'security_officer', 'tenant_admin', 'community_owner'], true);
    }

    /**
     * Code e-mail après mot de passe : rôles sensibles ou option activée sur le compte.
     *
     * @param array<string, mixed> $user Ligne ou session utilisateur (id, email_login_otp_enabled si présent)
     */
    public function isLoginEmailOtpRequired(array $user): bool
    {
        $uid = (int) ($user['id'] ?? 0);
        if ($this->isMandatoryForUserId($uid)) {
            return true;
        }
        if (!$this->userRepository->hasEmailLoginOtpEnabledColumn()) {
            return false;
        }

        return (int) ($user['email_login_otp_enabled'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed> $user Ligne utilisateur (id, tenant_id, email, display_name, …)
     */
    public function beginLoginChallenge(array $user): Response
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
        $this->emailTokenRepository->deletePendingForUserPurpose($userId, EmailTokenPurpose::LOGIN_SECURITY_OTP);
        $this->emailTokenRepository->create(
            $tenantId,
            $userId,
            EmailTokenPurpose::LOGIN_SECURITY_OTP,
            $tokenHash,
            $nonce,
            $expires,
            ['channel' => 'login_security_otp']
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
            $this->emailTokenRepository->deletePendingForUserPurpose($userId, EmailTokenPurpose::LOGIN_SECURITY_OTP);
            Session::flash('error', 'Impossible d’envoyer le code par e-mail pour le moment.');

            return Response::redirect(url('login'));
        }
        Session::set('pending_login_security_otp', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email_masked' => $this->maskEmailForDisplay($email),
            'token_hash' => $tokenHash,
            'expires_at' => $expires->getTimestamp(),
            'generated_at' => time(),
        ]);
        Session::flash('info', 'Un code vient d’être envoyé sur votre adresse e-mail.');

        return Response::redirect(url('login/otp'));
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
