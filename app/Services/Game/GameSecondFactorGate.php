<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Repositories\UserRepository;
use App\Services\Auth\LoginSecurityOtpService;
use App\Services\Auth\RecoveryCodeService;
use App\Services\Security\FileRateLimiter;
use App\Support\DemoPortalAccounts;
use App\Support\UtcDateTime;

/**
 * Double vérification par application d'authentification pour la connexion en jeu.
 *
 * Le jeu ne sait saisir qu'un code (onglet E-MAIL : RequestOtp puis VerifyOtp). Pour un compte dont
 * l'application est active, ce champ attend le code de l'application (6 chiffres) ou un code de secours
 * (8 caractères) à la place du code e-mail, et aucun e-mail n'est envoyé. Mot de passe seul et Steam seul
 * ne suffisent plus ; Steam reste accepté avec le jeton de poste remis après une connexion vérifiée.
 */
final class GameSecondFactorGate
{
    /** Erreurs de code tolérées par e-mail sur la fenêtre (même plafond que l'étape web). */
    public const MAX_FAILED_ATTEMPTS = LoginSecurityOtpService::MAX_TOTP_ATTEMPTS;

    public const ATTEMPT_WINDOW_SEC = 900;

    /**
     * Sessions jeu ouvertes avant ce correctif (UTC) : un code e-mail suffisait alors aux comptes protégés.
     * Elles ne sont plus reprises pour ces comptes ; le joueur saisit une fois le code de l'application.
     */
    public const SESSIONS_TRUSTED_SINCE_UTC = '2026-11-01 00:00:00';

    public function __construct(
        private UserRepository $users,
        private LoginSecurityOtpService $otp,
        private FileRateLimiter $limiter = new FileRateLimiter(),
    ) {}

    /**
     * Fiches membres de cet e-mail (et des appartenances du compte jeu) dont l'application est active.
     * Comptes de démonstration : jamais protégés (même règle que la connexion au site).
     *
     * @param list<int> $extraUserIds
     * @return list<array<string, mixed>>
     */
    public function protectedUsers(string $email, array $extraUserIds = []): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || DemoPortalAccounts::isDemoEmail($email)) {
            return [];
        }
        $ids = [];
        foreach ($this->users->listUsersForLoginByEmail($email) as $row) {
            $ids[(int) ($row['id'] ?? 0)] = true;
        }
        foreach ($extraUserIds as $id) {
            $ids[(int) $id] = true;
        }
        unset($ids[0]);
        $out = [];
        foreach (array_keys($ids) as $id) {
            $user = $this->users->findById((int) $id, null);
            if (is_array($user) && $this->otp->isTotpEnabled($user)) {
                $out[] = $user;
            }
        }

        return $out;
    }

    public function isThrottled(string $email): bool
    {
        return $this->limiter->attempts($this->limiterKey($email), self::ATTEMPT_WINDOW_SEC) >= self::MAX_FAILED_ATTEMPTS;
    }

    /**
     * Code de l'application (anti-rejeu : un pas de 30 s ne sert qu'une fois) ou code de secours (usage unique).
     * Un échec compte dans le plafond par e-mail.
     *
     * @param list<array<string, mixed>> $protectedUsers
     */
    public function verify(string $email, array $protectedUsers, string $rawCode): bool
    {
        if ($protectedUsers === [] || $this->isThrottled($email)) {
            return false;
        }
        $code = RecoveryCodeService::normalize($rawCode);
        foreach ($protectedUsers as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if (strlen($code) === 6 && ctype_digit($code)) {
                $secret = $this->otp->decryptUserTotpSecret($user);
                if ($secret !== null && $this->otp->verifyTotpCode($userId, $secret, $code)) {
                    $this->limiter->clear($this->limiterKey($email));

                    return true;
                }
            } elseif (strlen($code) === 8 &&$this->otp->recoveryCodes()->consume($userId, $rawCode)) {
                $this->limiter->clear($this->limiterKey($email));

                return true;
            }
        }
        $this->limiter->hit($this->limiterKey($email), self::ATTEMPT_WINDOW_SEC);

        return false;
    }

    /**
     * Session jeu (ou jeton de poste) ouverte après ce correctif et après l'activation de l'application :
     * elle l'a donc été avec le code de l'application ou depuis le site (connexion vérifiée).
     *
     * @param string|null $sessionCreatedAtUtc game_sessions.created_at (connexion MySQL en UTC)
     * @param list<array<string, mixed>> $protectedUsers
     */
    public function sessionOpenedWithFactor(?string $sessionCreatedAtUtc, array $protectedUsers): bool
    {
        $created = UtcDateTime::timestamp($sessionCreatedAtUtc);
        if ($created === null) {
            return false;
        }
        $threshold = (int) UtcDateTime::timestamp(self::SESSIONS_TRUSTED_SINCE_UTC);
        foreach ($protectedUsers as $user) {
            // totp_confirmed_at est écrit par PHP (date(), fuseau du site).
            $confirmed = strtotime(trim((string) ($user['totp_confirmed_at'] ?? '')));
            if ($confirmed !== false && $confirmed > $threshold) {
                $threshold = $confirmed;
            }
        }

        return $created >= $threshold;
    }

    private function limiterKey(string $email): string
    {
        return 'game_totp:' . strtolower(trim($email));
    }
}
