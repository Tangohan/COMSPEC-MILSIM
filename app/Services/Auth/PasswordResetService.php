<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\EmailService;
use App\Services\Identity\UserIdentityMergeRules;
use DateTimeImmutable;

/**
 * Mot de passe oublié : demande par e-mail, lien à usage unique, nouveau mot de passe sur le compte.
 */
final class PasswordResetService
{
    public const TOKEN_TTL_HOURS = 2;

    public function __construct(
        private UserRepository $users,
        private PasswordResetRepository $resets,
        private EmailService $email,
        private AuditService $audit,
    ) {
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function isResettableAccount(?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        if (!empty($user['is_service_account'])) {
            return false;
        }
        $email = UserIdentityMergeRules::normalizeEmail((string) ($user['email'] ?? ''));
        if (!UserIdentityMergeRules::isLiveHumanEmail($email)) {
            return false;
        }
        $status = strtolower(trim((string) ($user['status'] ?? '')));
        if (in_array($status, ['merged', 'deleted'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Crée un lien et envoie le message si le compte existe. Toujours silencieux pour l’appelant
     * (pas de révélation d’existence d’une adresse).
     */
    public function requestLink(string $email): void
    {
        $email = UserIdentityMergeRules::normalizeEmail($email);
        if ($email === '' || !str_contains($email, '@')) {
            return;
        }
        $user = $this->users->findFirstByEmailGlobal($email);
        if (!self::isResettableAccount($user) || $user === null) {
            return;
        }
        $userId = (int) ($user['id'] ?? 0);
        if ($userId < 1) {
            return;
        }
        $this->resets->deleteExpired();
        $this->resets->deleteForUser($userId);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = new DateTimeImmutable('+' . self::TOKEN_TTL_HOURS . ' hours');
        $this->resets->create($userId, $hash, $expires);
        $resetUrl = url('reset-password') . '?token=' . $token;
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $this->email->sendPasswordReset($email, $resetUrl, self::TOKEN_TTL_HOURS, $tenantId > 0 ? $tenantId : null);
        try {
            $this->audit->log(
                AuditAction::AUTH_PASSWORD_RESET_REQUESTED,
                $tenantId,
                $userId,
                'user',
                $userId
            );
        } catch (\Throwable) {
        }
    }

    public function findValidReset(string $plainToken): ?array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || strlen($plainToken) < 32) {
            return null;
        }
        $this->resets->deleteExpired();

        return $this->resets->findValidByToken(hash('sha256', $plainToken));
    }

    /**
     * @return 'ok'|'invalid'|'mismatch'
     */
    public function complete(string $plainToken, string $password, string $confirmation): string
    {
        $reset = $this->findValidReset($plainToken);
        if ($reset === null) {
            return 'invalid';
        }
        if (strlen($password) < 8 || $password !== $confirmation) {
            return 'mismatch';
        }
        $userId = (int) ($reset['user_id'] ?? 0);
        if ($userId < 1) {
            return 'invalid';
        }
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $this->users->updatePasswordHash($userId, $hash);
        $user = $this->users->findById($userId);
        if ($user !== null && ($user['status'] ?? '') === 'pending_verification') {
            $this->users->activatePendingById($userId);
        }
        $this->resets->deleteByToken((string) ($reset['token_hash'] ?? hash('sha256', trim($plainToken))));
        $this->resets->deleteForUser($userId);
        $tenantId = $user !== null ? (int) ($user['tenant_id'] ?? 0) : 0;
        try {
            $this->audit->log(
                AuditAction::AUTH_PASSWORD_RESET_COMPLETED,
                $tenantId,
                $userId,
                'user',
                $userId
            );
        } catch (\Throwable) {
        }

        return 'ok';
    }
}
