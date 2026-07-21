<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;

/**
 * Suppression de compte self-service (RGPD) avec délai de rétractation : le compte est
 * immédiatement restreint (interception dans AuthMiddleware) mais reste accessible en
 * connexion pour permettre l’annulation. Passé le délai, un job cron anonymise le compte.
 */
final class AccountDeletionService
{
    public const GRACE_PERIOD_DAYS = 14;

    public function __construct(
        private UserRepository $users,
        private UserProfileRepository $userProfiles,
        private UserLegalIdentityRepository $userLegalIdentities,
    ) {}

    public function requestDeletion(int $userId, int $tenantId): bool
    {
        $now = date('Y-m-d H:i:s');
        $scheduled = date('Y-m-d H:i:s', strtotime('+' . self::GRACE_PERIOD_DAYS . ' days'));

        return $this->users->requestDeletion($userId, $tenantId, $now, $scheduled);
    }

    public function cancelDeletion(int $userId, int $tenantId): bool
    {
        return $this->users->cancelDeletion($userId, $tenantId);
    }

    /** @return array{ok: bool, summary: string, details: array<string, mixed>} */
    public function anonymizeDueAccounts(): array
    {
        $due = $this->users->listDueForDeletionAnonymization();
        $count = 0;
        $failed = 0;
        foreach ($due as $row) {
            $userId = (int) ($row['id'] ?? 0);
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            if ($userId < 1 || $tenantId < 1) {
                continue;
            }
            try {
                $this->userProfiles->deleteByUserId($userId);
                $this->userLegalIdentities->deleteByUserId($userId);
                $this->users->anonymizeForDeletion($userId, $tenantId);
                $count++;
            } catch (\Throwable $e) {
                $failed++;
                error_log('[account_deletion_anonymize] Échec anonymisation user #' . $userId . ' : ' . $e->getMessage());
            }
        }

        return [
            'ok' => $failed === 0,
            'summary' => "Comptes anonymisés : {$count}" . ($failed > 0 ? " · Échecs : {$failed}" : ''),
            'details' => ['anonymized' => $count, 'failed' => $failed],
        ];
    }
}
