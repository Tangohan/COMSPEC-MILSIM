<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\Audit\AuditFieldSnapshot;

final class IndicatorBlocklistService
{
    public function __construct(
        private BlockedIndicatorRepository $blockedIndicatorRepository,
        private UserRepository $userRepository,
        private AuditService $auditService
    ) {}

    public function isEmailBlockedForTenant(int $tenantId, string $email): bool
    {
        return $this->blockedIndicatorRepository->isEmailBlockedForTenant($tenantId, $email);
    }

    public function isIpBlockedForLogin(?int $tenantId, string $ip): bool
    {
        return $this->blockedIndicatorRepository->isIpBlockedForContext($tenantId, $ip);
    }

    /**
     * @param 'tenant'|'global' $scope
     */
    public function addEmailBlock(
        int $actorUserId,
        string $scope,
        ?int $tenantId,
        string $email,
        ?string $reason,
        ?\DateTimeImmutable $expiresAt,
        ?int $moderationActionId = null
    ): int {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }
        if ($scope === 'tenant' && ($tenantId === null || $tenantId < 1)) {
            throw new \InvalidArgumentException('Organisation requise pour une liste noire locale.');
        }
        $hash = BlockedIndicatorRepository::hashEmail($email);
        $tid = $scope === 'global' ? null : $tenantId;
        $id = $this->blockedIndicatorRepository->add(
            'email',
            $hash,
            $scope,
            $tid,
            $reason,
            $expiresAt,
            $actorUserId,
            $moderationActionId
        );
        $new = [
            'indicator_id' => $id,
            'indicator_type' => 'email',
            'scope' => $scope,
            'tenant_id' => $tid,
        ];
        $this->auditService->logChange(
            AuditAction::MODERATION_ACTION,
            $tenantId ?? 0,
            $actorUserId,
            'blocked_indicator',
            $id,
            [],
            $new,
        );

        return $id;
    }

    /**
     * @param 'tenant'|'global' $scope
     */
    public function addIpBlock(
        int $actorUserId,
        string $scope,
        ?int $tenantId,
        string $ip,
        ?string $reason,
        ?\DateTimeImmutable $expiresAt
    ): int {
        $ip = trim($ip);
        if ($ip === '' || strlen($ip) > 45) {
            throw new \InvalidArgumentException('Adresse IP invalide.');
        }
        if ($scope === 'tenant' && ($tenantId === null || $tenantId < 1)) {
            throw new \InvalidArgumentException('Organisation requise pour une liste noire locale.');
        }
        $hash = BlockedIndicatorRepository::hashIp($ip);
        $tid = $scope === 'global' ? null : $tenantId;
        $id = $this->blockedIndicatorRepository->add(
            'ip',
            $hash,
            $scope,
            $tid,
            $reason,
            $expiresAt,
            $actorUserId,
            null
        );
        $new = [
            'indicator_id' => $id,
            'indicator_type' => 'ip',
            'scope' => $scope,
            'tenant_id' => $tid,
        ];
        $this->auditService->logChange(
            AuditAction::MODERATION_ACTION,
            $tenantId ?? 0,
            $actorUserId,
            'blocked_indicator',
            $id,
            [],
            $new,
        );

        return $id;
    }

    public function revokeIndicator(int $actorUserId, int $indicatorId, ?int $tenantIdForTenantScope): bool
    {
        $row = $this->blockedIndicatorRepository->findById($indicatorId);
        $ok = $this->blockedIndicatorRepository->revoke($indicatorId, $tenantIdForTenantScope);
        if ($ok) {
            $old = [];
            if (is_array($row)) {
                $old = [
                    'indicator_id' => $indicatorId,
                    'indicator_type' => (string) ($row['indicator_type'] ?? ''),
                    'scope' => (string) ($row['scope'] ?? ''),
                    'tenant_id' => $row['tenant_id'] !== null ? (int) $row['tenant_id'] : null,
                ];
            }
            $old = AuditFieldSnapshot::stripSensitive($old);
            [$os, $ns] = AuditFieldSnapshot::encodePair($old, []);
            $this->auditService->log(
                AuditAction::MODERATION_REVOKED,
                $tenantIdForTenantScope ?? 0,
                $actorUserId,
                'blocked_indicator',
                $indicatorId,
                $os,
                $ns,
            );
        }

        return $ok;
    }

    public function syncJoinBlockFromSanction(
        int $tenantId,
        int $targetUserId,
        bool $joinBlocked,
        ?\DateTimeImmutable $expiresAt,
        int $actorUserId,
        int $moderationActionId
    ): void {
        if (!$joinBlocked) {
            return;
        }
        $user = $this->userRepository->findById($targetUserId, $tenantId);
        if (!$user) {
            return;
        }
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return;
        }
        $h = BlockedIndicatorRepository::hashEmail($email);
        if ($this->blockedIndicatorRepository->hasActiveEmailTenant($tenantId, $h)) {
            return;
        }
        $this->addEmailBlock($actorUserId, 'tenant', $tenantId, $email, 'Mesure liée à une sanction (adhésion)', $expiresAt, $moderationActionId);
    }
}
