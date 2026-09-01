<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Core\Container;
use App\Support\MemberIntegrationCatalog;
use Throwable;

/**
 * Point d’entrée unique après création / réparation d’un compte membre.
 * Les échecs n’annulent jamais le métier appelant.
 */
final class MemberIntegrationEntryHook
{
    /**
     * @param array{role_ids?: list<int>, unit_ids?: list<int>} $context
     */
    public static function afterAccountReady(
        int $tenantId,
        int $userId,
        int $actorUserId,
        string $source,
        array $context = []
    ): void {
        if ($tenantId < 1 || $userId < 1) {
            return;
        }
        try {
            $svc = Container::get(MemberIntegrationAutomationService::class);
            $svc->ensureForNewMember($tenantId, $userId, $actorUserId, $source, $context);
        } catch (Throwable) {
        }
    }

    /**
     * @param array{role_ids?: list<int>, unit_ids?: list<int>} $context
     */
    public static function afterRoleOrUnitChange(
        int $tenantId,
        int $userId,
        int $actorUserId,
        array $context = []
    ): void {
        if ($tenantId < 1 || $userId < 1) {
            return;
        }
        try {
            $svc = Container::get(MemberIntegrationAutomationService::class);
            $svc->maybeStartOnAssignmentChange($tenantId, $userId, $actorUserId, $context);
        } catch (Throwable) {
        }
    }
}
