<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;

/**
 * Fonctionnalités par plan (JSON subscription_plans.features_json) et quotas simples.
 */
final class FeatureGateService
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private SubscriptionPlanRepository $planRepository,
        private UserRepository $userRepository
    ) {}

    public function allows(int $tenantId, string $feature): bool
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return false;
        }
        $planSlug = $tenant['plan_slug'] ?? 'free';
        if (($tenant['subscription_status'] ?? '') === 'past_due') {
            return $feature === 'forum' || $feature === 'documents';
        }
        $plan = $this->planRepository->findBySlug($planSlug);
        if (!$plan) {
            return $feature === 'forum';
        }
        $features = json_decode((string) ($plan['features_json'] ?? '{}'), true);
        if (!is_array($features)) {
            return false;
        }
        return !empty($features[$feature]);
    }

    public function maxMembers(int $tenantId): int
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return 0;
        }
        $plan = $this->planRepository->findBySlug($tenant['plan_slug'] ?? 'free');
        if (!$plan) {
            return 50;
        }
        $features = json_decode((string) ($plan['features_json'] ?? '{}'), true);
        if (!is_array($features)) {
            return 50;
        }
        return (int) ($features['max_members'] ?? 50);
    }

    public function currentMemberCount(int $tenantId): int
    {
        return $this->userRepository->countActiveForTenant($tenantId);
    }

    public function canAddMember(int $tenantId): bool
    {
        return $this->currentMemberCount($tenantId) < $this->maxMembers($tenantId);
    }
}
