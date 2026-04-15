<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\PlatformModuleReleaseRepository;

/**
 * Couche plan d’abonnement + contraintes de publication par canal (platform_modules).
 * Si aucune ligne ne correspond à la fonctionnalité, l’accès reste régi uniquement par le plan.
 */
final class PlatformFeatureDeploymentEvaluator
{
    public function __construct(
        private PlatformModuleReleaseRepository $releaseRepository,
        private ModuleReleaseAccessResolver $resolver,
    ) {}

    public function isFeatureAccessibleInCurrentEnvironment(int $tenantId, string $featureCode, ?int $userId): bool
    {
        if (!$this->releaseRepository->schemaReady()) {
            return true;
        }
        $module = $this->releaseRepository->findModuleByCode($featureCode);
        if ($module === null) {
            return true;
        }
        $moduleId = (int) ($module['id'] ?? 0);
        if ($moduleId < 1) {
            return true;
        }
        $releasesByChannel = $this->releaseRepository->findCurrentReleasesByChannelForModule($moduleId);
        if ($releasesByChannel === []) {
            return true;
        }
        $rules = $this->releaseRepository->listActiveModuleAccessRules($moduleId);
        $communityCodes = $userId !== null && $userId > 0
            ? $this->releaseRepository->listUserTesterCommunityCodes($userId)
            : [];
        $communityIds = $userId !== null && $userId > 0
            ? $this->releaseRepository->listUserTesterCommunityIds($userId)
            : [];
        $channel = $this->resolveTargetChannel();
        $decision = $this->resolver->resolve(
            $module,
            $releasesByChannel,
            $rules,
            $communityCodes,
            [],
            [
                'target_channel' => $channel,
                'user_id' => $userId ?? 0,
                'community_ids' => $communityIds,
            ]
        );

        return !empty($decision['allowed']);
    }

    private function resolveTargetChannel(): string
    {
        $override = strtoupper(trim((string) env('DEPLOYMENT_CHANNEL', '')));
        if ($override !== '') {
            return $override;
        }
        $app = strtolower(trim((string) env('APP_ENV', 'production')));

        return match ($app) {
            'local', 'development', 'dev' => 'DEV',
            'testing', 'test' => 'TEST',
            'staging', 'preprod', 'pre-production' => 'PREPROD',
            'internal' => 'INTERNAL',
            default => 'PROD',
        };
    }
}
