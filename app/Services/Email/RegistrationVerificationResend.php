<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Repositories\EmailTokenRepository;
use App\Services\EmailService;

final class RegistrationVerificationResend
{
    private const COOLDOWN_SECONDS = 90;

    public function __construct(
        private EmailTokenRepository $tokens,
        private EmailService $mailer,
    ) {}

    /** @return array{sent: bool, wait: int} */
    public function send(array $account, int $tenantId, string $organization, bool $requirePendingAccount = false): array
    {
        $email = strtolower(trim((string) ($account['email'] ?? '')));
        $userId = (int) ($account['id'] ?? 0);
        if ($userId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'wait' => 0];
        }

        $purpose = EmailTokenPurpose::REGISTER_CONFIRM;
        $minutes = EmailTokenPurpose::REGISTER_CONFIRM_TTL_MINUTES;
        $token = bin2hex(random_bytes(32));
        $tokenId = 0;
        try {
            $reservation = $this->tokens->reserveResend(
                $tenantId, $userId, $purpose, $email, hash('sha256', $token),
                new \DateTimeImmutable('+' . $minutes . ' minutes'), self::COOLDOWN_SECONDS, $requirePendingAccount
            );
            $tokenId = $reservation['token_id'];
            if ($tokenId === 0) {
                return ['sent' => false, 'wait' => $reservation['wait']];
            }
            $sent = $this->mailer->sendUserRegisterConfirmation(
                $email, (string) ($account['display_name'] ?? 'Membre'), $organization,
                url('verify-email') . '?token=' . rawurlencode($token), $minutes, $tenantId
            );
        } catch (\Throwable) {
            error_log('[verify-email.resend] La préparation ou l’envoi du lien a échoué.');
            $sent = false;
        }

        if ($tokenId > 0) {
            try {
                if ($sent) {
                    $this->tokens->deleteOlderPendingForUserPurpose($userId, $purpose, $tokenId);
                } else {
                    $this->tokens->deleteById($tokenId);
                }
            } catch (\Throwable) {
                error_log('[verify-email.resend] Le nettoyage des liens a échoué.');
            }
        }

        return ['sent' => $sent, 'wait' => 0];
    }
}
