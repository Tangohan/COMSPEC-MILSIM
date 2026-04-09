<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\ModerationRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

final class ModerationService
{
    public function __construct(
        private ModerationRepository $repository,
        private AuditService $auditService,
        private IndicatorBlocklistService $indicatorBlocklistService,
        private BlockedIndicatorRepository $blockedIndicatorRepository
    ) {}

    public function isAccessBlocked(int $tenantId, int $userId): bool
    {
        return $this->repository->hasActiveAccessBlock($tenantId, $userId);
    }

    /**
     * @param array<string, mixed> $restrictions
     * @param 'tenant'|'platform' $sanctionScope tenant = niveau organisation ; platform = niveau site (admin)
     */
    public function applySanction(
        int $tenantId,
        int $actorUserId,
        int $targetUserId,
        string $actionType,
        ?string $reason,
        ?\DateTimeImmutable $expiresAt,
        array $restrictions = [],
        string $sanctionScope = 'tenant'
    ): int {
        $allowed = ['warn', 'mute', 'suspend', 'ban'];
        if (!in_array($actionType, $allowed, true)) {
            throw new \InvalidArgumentException('Type de sanction invalide.');
        }
        $scope = $sanctionScope === 'platform' ? 'platform' : 'tenant';
        $restrictionsJson = $restrictions === [] ? null : json_encode($restrictions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($restrictionsJson === false) {
            $restrictionsJson = null;
        }
        $caseId = $this->repository->createCase($tenantId, $targetUserId, $actorUserId);
        $id = $this->repository->createAction(
            $caseId,
            $tenantId,
            $targetUserId,
            $actorUserId,
            $actionType,
            $reason,
            $expiresAt,
            $restrictionsJson,
            $scope
        );
        $auditPayload = [
            'type' => $actionType,
            'target_user_id' => $targetUserId,
            'scope' => $scope,
            'expires_at' => $expiresAt?->format(\DateTimeInterface::ATOM),
            'restrictions' => $restrictions,
        ];
        $this->auditService->log(
            AuditAction::MODERATION_ACTION,
            $tenantId,
            $actorUserId,
            'moderation_action',
            $id,
            null,
            json_encode($auditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null
        );
        if (!empty($restrictions['join_blocked'])) {
            $this->indicatorBlocklistService->syncJoinBlockFromSanction(
                $tenantId,
                $targetUserId,
                true,
                $expiresAt,
                $actorUserId,
                $id
            );
        }

        return $id;
    }

    public function revoke(int $tenantId, int $actionId, int $revokedByUserId): bool
    {
        $ok = $this->repository->revokeAction($tenantId, $actionId, $revokedByUserId);
        if ($ok) {
            $this->blockedIndicatorRepository->revokeByModerationActionId($actionId);
            $this->auditService->log(
                AuditAction::MODERATION_REVOKED,
                $tenantId,
                $revokedByUserId,
                'moderation_action',
                $actionId,
                null,
                json_encode(['scope' => 'any'], JSON_UNESCAPED_UNICODE) ?: null
            );
        }

        return $ok;
    }

    /**
     * @param 'tenant'|'platform' $sanctionScope
     */
    public function revokeForScope(int $tenantId, int $actionId, int $revokedByUserId, string $sanctionScope): bool
    {
        $scope = $sanctionScope === 'platform' ? 'platform' : 'tenant';
        $ok = $this->repository->revokeActionForScope($tenantId, $actionId, $revokedByUserId, $scope);
        if ($ok) {
            $this->blockedIndicatorRepository->revokeByModerationActionId($actionId);
            $this->auditService->log(
                AuditAction::MODERATION_REVOKED,
                $tenantId,
                $revokedByUserId,
                'moderation_action',
                $actionId,
                null,
                json_encode(['scope' => $scope], JSON_UNESCAPED_UNICODE) ?: null
            );
        }

        return $ok;
    }
}
