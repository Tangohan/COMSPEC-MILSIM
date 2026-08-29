<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Audit\AuditService;

class AdminAuditService
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function logUserCreated(int $tenantId, int $actorUserId, int $createdUserId, string $email): void
    {
        $this->auditService->log(
            'user_created',
            $tenantId,
            $actorUserId,
            'user',
            $createdUserId,
            null,
            $email
        );
    }

    public function logUserUpdated(int $tenantId, int $actorUserId, int $targetUserId, ?string $oldValue = null, ?string $newValue = null): void
    {
        $this->auditService->log(
            'user_updated',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId,
            $oldValue,
            $newValue
        );
    }

    public function logRoleAssigned(int $tenantId, int $actorUserId, int $targetUserId, ?string $oldRoleId = null, ?string $newRoleId = null): void
    {
        $this->auditService->log(
            'role_assigned',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId,
            $oldRoleId,
            $newRoleId
        );
    }

    public function logUserDeactivated(int $tenantId, int $actorUserId, int $targetUserId): void
    {
        $this->auditService->log(
            'user_deactivated',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId
        );
    }

    public function logUserPurgeRequested(int $tenantId, int $actorUserId, int $targetUserId, int $requestId): void
    {
        $this->auditService->log(
            'user_purge_requested',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId,
            null,
            'purge_request#' . $requestId
        );
    }

    public function logUserLeftCommunity(int $tenantId, int $actorUserId, int $targetUserId): void
    {
        $this->auditService->log(
            'user_left_community',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId
        );
    }

    public function logGroupMemberAdded(int $tenantId, int $actorUserId, int $targetUserId, int $unitId): void
    {
        $this->auditService->log(
            'group_member_added',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId,
            null,
            (string) $unitId
        );
    }

    public function logGroupMemberRemoved(int $tenantId, int $actorUserId, int $targetUserId, int $unitId): void
    {
        $this->auditService->log(
            'group_member_removed',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId,
            (string) $unitId,
            null
        );
    }

    public function logMemberNumberChanged(
        int $tenantId,
        int $actorUserId,
        int $targetUserId,
        ?string $oldValue,
        ?string $newValue,
        ?string $reason = null
    ): void {
        $oldPayload = $oldValue ?? '';
        $newPayload = ($newValue ?? '') . ($reason !== null && trim($reason) !== '' ? ' | motif: ' . trim($reason) : '');
        $this->auditService->log(
            'member_number_changed',
            $tenantId,
            $actorUserId,
            'user',
            $targetUserId,
            $oldPayload !== '' ? $oldPayload : null,
            $newPayload !== '' ? $newPayload : null
        );
    }
}
