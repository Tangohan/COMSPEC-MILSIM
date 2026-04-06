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
     */
    public function applySanction(
        int $tenantId,
        int $actorUserId,
        int $targetUserId,
        string $actionType,
        ?string $reason,
        ?\DateTimeImmutable $expiresAt,
        array $restrictions = []
    ): int {
        $allowed = ['warn', 'mute', 'suspend', 'ban'];
        if (!in_array($actionType, $allowed, true)) {
            throw new \InvalidArgumentException('Type de sanction invalide.');
        }
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
            $restrictionsJson
        );
        $this->auditService->log(
            AuditAction::MODERATION_ACTION,
            $tenantId,
            $actorUserId,
            'moderation_action',
            $id,
            null,
            json_encode(['type' => $actionType, 'target' => $targetUserId], JSON_UNESCAPED_UNICODE) ?: null
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
                $actionId
            );
        }

        return $ok;
    }
}
