<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\PlatformModuleReleaseRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\Audit\AuditFieldSnapshot;

/**
 * Publication d’une version sur un canal d’environnement + journalisation audit (même sémantique que le formulaire admin).
 */
final class DeploymentChannelReleaseService
{
    public function __construct(
        private PlatformModuleReleaseRepository $releaseRepository,
        private AuditService $auditService,
    ) {}

    /**
     * @throws \Throwable en cas d’échec BDD
     */
    public function publishVersionOnChannel(
        int $moduleId,
        int $channelId,
        int $moduleVersionId,
        ?int $actorUserId,
    ): void {
        $ver = $this->releaseRepository->findVersionById($moduleVersionId);
        if ($ver === null || (int) ($ver['module_id'] ?? 0) !== $moduleId) {
            throw new \InvalidArgumentException('Version incompatible avec cette fonctionnalité.');
        }
        $ch = $this->releaseRepository->findChannelById($channelId);
        if ($ch === null) {
            throw new \InvalidArgumentException('Canal inconnu.');
        }
        $chCode = strtoupper(trim((string) ($ch['code'] ?? '')));
        $prevMap = $this->releaseRepository->findCurrentReleasesByChannelForModule($moduleId);
        $prev = $prevMap[$chCode] ?? null;
        $old = [
            'channel' => $chCode,
            'version' => $prev !== null ? (string) ($prev['version'] ?? '') : null,
            'module_version_id' => $prev !== null ? (int) ($prev['module_version_id'] ?? 0) : null,
        ];
        $new = [
            'channel' => $chCode,
            'version' => (string) ($ver['version'] ?? ''),
            'module_version_id' => $moduleVersionId,
        ];
        $this->releaseRepository->setCurrentReleaseForModuleChannel($moduleId, $channelId, $moduleVersionId, $actorUserId);
        if ($actorUserId !== null) {
            [$o, $n] = AuditFieldSnapshot::diffOnly($old, $new, ['channel', 'version', 'module_version_id']);
            $this->auditService->logChange(
                AuditAction::DEPLOYMENT_RELEASE_SET,
                null,
                $actorUserId,
                'platform_module',
                $moduleId,
                $o,
                $n,
            );
        }
    }
}
