<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Repositories\UserRepository;

/**
 * Compte technique par tenant pour attribuer les actions automatiques en base (audit, moderation_decisions).
 *
 * Usage prévu : expiration quarantaine, futurs jobs forum/tickets, intégrations (Discord), masquage / suppression auto.
 * Email réservé : {@see UserRepository::SYSTEM_MODERATOR_EMAIL} — connexion interdite ({@see AuthService::attempt}).
 */
final class SystemModeratorAccountService
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public function ensureForTenant(int $tenantId): int
    {
        return $this->userRepository->ensureSystemModeratorUser($tenantId);
    }

    /** @return positive-int|null */
    public function getActorIdForTenant(int $tenantId): ?int
    {
        $id = $this->userRepository->ensureSystemModeratorUser($tenantId);

        return $id > 0 ? $id : null;
    }
}
