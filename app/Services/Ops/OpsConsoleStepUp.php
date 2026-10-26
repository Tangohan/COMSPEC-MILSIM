<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Auth\LoginSecurityOtpService;
use App\Services\EmailService;

/**
 * Double vérification d'entrée dans la console d'exploitation.
 *
 * Même connecté, un administrateur du site confirme qu'il est bien lui avant d'ouvrir la console :
 * code de son application d'authentification s'il l'a activée, sinon code à six chiffres envoyé
 * sur son adresse. La vérification vaut 30 minutes, pour ce compte et cette session seulement.
 */
final class OpsConsoleStepUp
{
    public const SESSION_KEY = 'ops_console_verified';
    public const PENDING_KEY = 'ops_console_pending_code';
    public const VERIFIED_SECONDS = 1800;
    public const EMAIL_CODE_MINUTES = 10;
    public const MAX_ATTEMPTS = 6;

    public function __construct(
        private UserRepository $users,
        private LoginSecurityOtpService $otp,
        private EmailService $email,
    ) {}

    public function isVerified(int $userId, ?int $now = null): bool
    {
        $now ??= time();
        $state = Session::get(self::SESSION_KEY);

        return is_array($state)
            && (int) ($state['user_id'] ?? 0) === $userId
            && $userId > 0
            && (int) ($state['until'] ?? 0) > $now;
    }

    /** Secondes restantes avant de devoir revérifier (0 si non vérifié). */
    public function remainingSeconds(int $userId, ?int $now = null): int
    {
        $now ??= time();
        if (!$this->isVerified($userId, $now)) {
            return 0;
        }

        return max(0, (int) (Session::get(self::SESSION_KEY)['until'] ?? 0) - $now);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function method(array $user): string
    {
        return $this->otp->isTotpEnabled($user) ? 'totp' : 'email';
    }

    /** @return array{sent_at: int, expires_at: int, email_masked: string}|null */
    public function pendingEmailCode(int $userId): ?array
    {
        $p = Session::get(self::PENDING_KEY);
        if (!is_array($p) || (int) ($p['user_id'] ?? 0) !== $userId || (int) ($p['expires_at'] ?? 0) < time()) {
            return null;
        }

        return ['sent_at' => (int) $p['sent_at'], 'expires_at' => (int) $p['expires_at'], 'email_masked' => (string) ($p['email_masked'] ?? '')];
    }

    /**
     * @param array<string, mixed> $user
     */
    public function sendEmailCode(array $user): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = trim((string) ($user['email'] ?? ''));
        if ($userId < 1 || $email === '') {
            return false;
        }
        $previous = Session::get(self::PENDING_KEY);
        if (is_array($previous) && (int) ($previous['user_id'] ?? 0) === $userId && time() - (int) ($previous['sent_at'] ?? 0) < 45) {
            // Un envoi toutes les 45 secondes au plus : le code précédent reste valable.
            return true;
        }
        $code = (string) random_int(100000, 999999);
        $salt = bin2hex(random_bytes(16));
        $ok = $this->email->sendLoginSecurityOtp(
            $email,
            trim((string) ($user['display_name'] ?? '')) ?: $email,
            'Administration du site',
            $code,
            self::EMAIL_CODE_MINUTES,
            (int) ($user['tenant_id'] ?? 0)
        );
        if (!$ok) {
            return false;
        }
        Session::set(self::PENDING_KEY, [
            'user_id' => $userId,
            'hash' => hash('sha256', $salt . '|' . $code),
            'salt' => $salt,
            'sent_at' => time(),
            'expires_at' => time() + self::EMAIL_CODE_MINUTES * 60,
            'attempts' => 0,
            'email_masked' => self::maskEmail($email),
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: bool, message: string}
     */
    public function verify(array $user, string $code): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($userId < 1 || strlen($code) !== 6) {
            return ['ok' => false, 'message' => 'Saisissez les six chiffres du code.'];
        }
        $attemptsKey = self::SESSION_KEY . '_attempts';
        $attempts = (int) Session::get($attemptsKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Session::forget(self::PENDING_KEY);

            return ['ok' => false, 'message' => 'Trop d’essais : déconnectez-vous puis reconnectez-vous.'];
        }
        Session::set($attemptsKey, $attempts + 1);

        $ok = false;
        if ($this->method($user) === 'totp') {
            $fresh = $this->users->findById($userId, null) ?? $user;
            $secret = $this->otp->decryptUserTotpSecret($fresh);
            $ok = $secret !== null && $this->otp->totpService()->verify($secret, $code);
        } else {
            $p = Session::get(self::PENDING_KEY);
            if (is_array($p) && (int) ($p['user_id'] ?? 0) === $userId && (int) ($p['expires_at'] ?? 0) >= time()) {
                $ok = hash_equals((string) $p['hash'], hash('sha256', (string) $p['salt'] . '|' . $code));
            } else {
                return ['ok' => false, 'message' => 'Code expiré : demandez-en un nouveau.'];
            }
        }
        if (!$ok) {
            return ['ok' => false, 'message' => 'Code incorrect.'];
        }

        Session::forget(self::PENDING_KEY);
        Session::forget($attemptsKey);
        Session::regenerate();
        Session::set(self::SESSION_KEY, ['user_id' => $userId, 'until' => time() + self::VERIFIED_SECONDS, 'at' => time()]);

        return ['ok' => true, 'message' => 'ok'];
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::PENDING_KEY);
    }

    public static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '***';
        }

        return substr($email, 0, 1) . str_repeat('•', max(2, min(6, $at - 1))) . substr($email, $at);
    }
}
