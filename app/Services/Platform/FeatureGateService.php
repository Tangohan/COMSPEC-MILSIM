<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\PlatformUsageRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TenantUsageCounterRepository;
use App\Repositories\UserRepository;

/**
 * Fonctionnalités par plan (JSON subscription_plans.features_json), quotas (limits_json + tenant_usage_counters).
 */
final class FeatureGateService
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private SubscriptionPlanRepository $planRepository,
        private UserRepository $userRepository,
        private TenantUsageCounterRepository $counterRepository,
        private PlatformUsageRepository $usageRepository,
        private CommunityEventRepository $communityEventRepository,
        private ?PlatformFeatureDeploymentEvaluator $deploymentEvaluator = null,
    ) {}

    /**
     * Accès au module (navigation, liste, RSVP) : plan complet, ou gratuit limité avec entrée quota configurée (même si quota épuisé).
     */
    public function allowsLimitedFeatureModule(int $tenantId, string $feature): bool
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return false;
        }
        if (($tenant['subscription_status'] ?? '') === 'past_due') {
            return $feature === 'forum' || $feature === 'documents';
        }
        $planSlug = $this->effectivePlanSlug($tenant);
        $plan = $this->planRepository->findBySlug($planSlug);
        if (!$plan) {
            return $feature === 'forum';
        }
        $features = json_decode((string) ($plan['features_json'] ?? '{}'), true);
        if (!is_array($features)) {
            $features = [];
        }
        $base = !empty($features[$feature]) || $this->quotaConfigForFeature($plan, $feature) !== null;
        if (!$base) {
            return false;
        }

        return $this->applyDeploymentChannelGate($tenantId, $feature);
    }

    /**
     * Action « plein accès » ou quota restant (création, usage intensif). Si quota épuisé sur gratuit limité → false.
     */
    public function allows(int $tenantId, string $feature): bool
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return false;
        }
        if (($tenant['subscription_status'] ?? '') === 'past_due') {
            return $feature === 'forum' || $feature === 'documents';
        }
        $planSlug = $this->effectivePlanSlug($tenant);
        $plan = $this->planRepository->findBySlug($planSlug);
        if (!$plan) {
            return $feature === 'forum';
        }
        $features = json_decode((string) ($plan['features_json'] ?? '{}'), true);
        if (!is_array($features)) {
            $features = [];
        }

        $allowed = !empty($features[$feature]) || $this->hasRemainingQuota($tenantId, $plan, $feature);
        if (!$allowed) {
            return false;
        }

        return $this->applyDeploymentChannelGate($tenantId, $feature);
    }

    private function applyDeploymentChannelGate(int $tenantId, string $feature): bool
    {
        if ($this->deploymentEvaluator === null) {
            return true;
        }
        $uidRaw = Session::get('user_id');
        $uid = $uidRaw !== null && $uidRaw !== '' ? (int) $uidRaw : null;

        return $this->deploymentEvaluator->isFeatureAccessibleInCurrentEnvironment($tenantId, $feature, $uid);
    }

    /**
     * Statut quota pour une feature (UI + analytics). null = pas de quota (accès tout ou rien selon allows).
     *
     * @return array{
     *   mode: 'unlimited'|'limited',
     *   limit?: int,
     *   used?: int,
     *   remaining?: int,
     *   soft_block_threshold?: float,
     *   upgrade_cta?: string,
     *   period_start?: string,
     *   metric_key?: string,
     *   soft_block_message?: string
     * }|null
     */
    public function quotaStatusForFeature(int $tenantId, string $feature): ?array
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return null;
        }
        if (($tenant['subscription_status'] ?? '') === 'past_due') {
            return null;
        }
        $planSlug = $this->effectivePlanSlug($tenant);
        $plan = $this->planRepository->findBySlug($planSlug);
        if (!$plan) {
            return null;
        }
        $features = json_decode((string) ($plan['features_json'] ?? '{}'), true);
        if (!is_array($features)) {
            $features = [];
        }
        if (!empty($features[$feature])) {
            return ['mode' => 'unlimited'];
        }

        $quota = $this->quotaConfigForFeature($plan, $feature);
        if ($quota === null) {
            return null;
        }

        $baseLimit = (int) ($quota['limit'] ?? 0);
        $planSlug = (string) ($plan['slug'] ?? '');
        $limit = $this->effectiveQuotaLimit($baseLimit, $planSlug, $feature);
        if ($limit <= 0) {
            return null;
        }
        $reset = (string) ($quota['reset_period'] ?? 'monthly');
        $periodStart = TenantUsageCounterRepository::periodStartForReset($reset);
        $metricKey = $this->metricKeyFromQuota($quota, $feature);
        $used = $this->usedAmountForQuotaFeature($tenantId, $feature, $quota, $periodStart, $reset, $metricKey);
        $remaining = max(0, $limit - $used);
        $threshold = (float) ($quota['soft_block_threshold'] ?? 0.8);

        return [
            'mode' => 'limited',
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'soft_block_threshold' => $threshold,
            'upgrade_cta' => (string) ($quota['upgrade_cta'] ?? 'platform/upgrade'),
            'period_start' => $periodStart,
            'metric_key' => $metricKey,
            'soft_block_message' => (string) ($quota['soft_block_message'] ?? ''),
        ];
    }

    /**
     * Après une action comptabilisée (ex. création d’événement). Idempotent côté appelant (une fois par action).
     */
    public function recordQuotaUse(int $tenantId, string $feature, ?int $userId): void
    {
        $status = $this->quotaStatusForFeature($tenantId, $feature);
        if ($status === null || ($status['mode'] ?? '') !== 'limited') {
            return;
        }
        $periodStart = (string) ($status['period_start'] ?? '');
        if ($periodStart === '') {
            return;
        }
        $metricKey = (string) ($status['metric_key'] ?? $feature);
        $this->counterRepository->increment($tenantId, $metricKey, $periodStart, 1);
    }

    /**
     * Enregistre le dépassement de quota (tentative bloquée).
     */
    public function recordQuotaLimitReached(int $tenantId, ?int $userId, string $feature): void
    {
        $this->usageRepository->record($tenantId, $userId, 'quota_limit_reached', $feature);
    }

    /**
     * Si le seuil soft-block est atteint, enregistre un événement analytics (appeler depuis la vue / contrôleur).
     */
    public function maybeRecordQuotaSoftBlock(int $tenantId, ?int $userId, string $feature): void
    {
        $status = $this->quotaStatusForFeature($tenantId, $feature);
        if ($status === null || ($status['mode'] ?? '') !== 'limited') {
            return;
        }
        $limit = (int) ($status['limit'] ?? 0);
        $used = (int) ($status['used'] ?? 0);
        if ($limit <= 0) {
            return;
        }
        $ratio = $used / $limit;
        $threshold = (float) ($status['soft_block_threshold'] ?? 0.8);
        if ($ratio < $threshold) {
            return;
        }
        Session::start();
        $dedup = 'quota_soft_block_' . $tenantId . '_' . $feature . '_' . ($status['period_start'] ?? '');
        if (Session::get($dedup)) {
            return;
        }
        Session::set($dedup, true);
        $this->usageRepository->record($tenantId, $userId, 'quota_soft_block', $feature);
    }

    /**
     * Plan factuel pour les fonctionnalités : abonnement Stripe actif prime sur l’essai fondateur Pro.
     */
    public function effectivePlanSlug(array $tenant): string
    {
        $status = (string) ($tenant['subscription_status'] ?? 'none');
        if (in_array($status, ['active', 'trialing'], true)) {
            return (string) ($tenant['plan_slug'] ?? 'free');
        }
        $raw = $tenant['settings'] ?? null;
        $decoded = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
        }
        $end = $decoded['founder_trial_ends_at'] ?? null;
        if (is_string($end) && $end !== '') {
            $ts = strtotime($end);
            if ($ts !== false && $ts > time()) {
                return 'pro';
            }
        }

        return (string) ($tenant['plan_slug'] ?? 'free');
    }

    public function maxMembers(int $tenantId): int
    {
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return 0;
        }
        if (($tenant['subscription_status'] ?? '') === 'past_due') {
            $plan = $this->planRepository->findBySlug($tenant['plan_slug'] ?? 'free');
            $features = $plan ? json_decode((string) ($plan['features_json'] ?? '{}'), true) : [];
            if (!is_array($features)) {
                return 50;
            }

            return (int) ($features['max_members'] ?? 50);
        }
        $planSlug = $this->effectivePlanSlug($tenant);
        $plan = $this->planRepository->findBySlug($planSlug);
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

    /** @param array<string, mixed> $plan */
    private function hasRemainingQuota(int $tenantId, array $plan, string $feature): bool
    {
        $quota = $this->quotaConfigForFeature($plan, $feature);
        if ($quota === null) {
            return false;
        }
        $baseLimit = (int) ($quota['limit'] ?? 0);
        $planSlug = (string) ($plan['slug'] ?? '');
        $limit = $this->effectiveQuotaLimit($baseLimit, $planSlug, $feature);
        if ($limit <= 0) {
            return false;
        }
        $reset = (string) ($quota['reset_period'] ?? 'monthly');
        $periodStart = TenantUsageCounterRepository::periodStartForReset($reset);
        $metricKey = $this->metricKeyFromQuota($quota, $feature);
        $used = $this->usedAmountForQuotaFeature($tenantId, $feature, $quota, $periodStart, $reset, $metricKey);

        return $used < $limit;
    }

    /**
     * Surcharge ops : `FREE_EVENTS_PER_MONTH` (entier > 0) pour le plan `free` et la feature `events` — remplace la limite issue du JSON si définie.
     */
    private function effectiveQuotaLimit(int $baseLimit, string $planSlug, string $feature): int
    {
        if ($planSlug !== 'free' || $feature !== 'events') {
            return $baseLimit;
        }
        $n = $this->readEnvPositiveInt('FREE_EVENTS_PER_MONTH');
        if ($n !== null) {
            return $n;
        }

        return $baseLimit;
    }

    private function readEnvPositiveInt(string $key): ?int
    {
        $raw = null;
        if (function_exists('env')) {
            $v = env($key);
            $raw = $v !== null && $v !== '' ? (string) $v : null;
        }
        if ($raw === null || $raw === '') {
            $g = getenv($key);
            $raw = $g !== false && $g !== '' ? (string) $g : null;
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        $n = (int) $raw;

        return $n > 0 ? $n : null;
    }

    /** @param array<string, mixed> $quota */
    private function metricKeyFromQuota(array $quota, string $feature): string
    {
        $m = $quota['metric_key'] ?? null;

        return is_string($m) && $m !== '' ? $m : $feature;
    }

    /**
     * Compteur + réconciliation avec la source métier (événements créés avant compteur ou sans increment).
     *
     * @param array<string, mixed> $quota
     */
    private function usedAmountForQuotaFeature(
        int $tenantId,
        string $feature,
        array $quota,
        string $periodStart,
        string $reset,
        string $metricKey,
    ): int {
        $fromCounter = $this->counterRepository->getAmount($tenantId, $metricKey, $periodStart);
        if ($feature !== 'events') {
            return $fromCounter;
        }

        return $this->reconcileEventsCounter($tenantId, $metricKey, $periodStart, $reset, $fromCounter);
    }

    private function reconcileEventsCounter(
        int $tenantId,
        string $metricKey,
        string $periodStart,
        string $reset,
        int $counterUsed,
    ): int {
        [$startInclusive, $endExclusive] = $this->eventCreationPeriodBounds($periodStart, $reset);
        $dbCount = $this->communityEventRepository->countCreatedInPeriod($tenantId, $startInclusive, $endExclusive);
        $used = max($counterUsed, $dbCount);
        if ($dbCount > $counterUsed) {
            $this->counterRepository->raiseAmountToAtLeast($tenantId, $metricKey, $periodStart, $dbCount);
        }

        return $used;
    }

    /**
     * @return array{0: string, 1: string} Début inclusif et fin exclusive pour filtre `created_at`.
     */
    private function eventCreationPeriodBounds(string $periodStart, string $reset): array
    {
        $tz = TenantUsageCounterRepository::appTimezone();
        $start = new \DateTimeImmutable($periodStart . ' 00:00:00', $tz);
        if ($reset === 'monthly') {
            $end = $start->modify('first day of next month');
        } elseif ($reset === 'weekly') {
            $end = $start->modify('+1 week');
        } elseif ($reset === 'daily') {
            $end = $start->modify('+1 day');
        } else {
            $end = $start->modify('first day of next month');
        }

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /** @param array<string, mixed> $plan */
    private function quotaConfigForFeature(array $plan, string $feature): ?array
    {
        $raw = $plan['limits_json'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $limits = json_decode($raw, true);
        if (!is_array($limits)) {
            return null;
        }
        $quotas = $limits['quotas'] ?? null;
        if (!is_array($quotas)) {
            return null;
        }
        $q = $quotas[$feature] ?? null;

        return is_array($q) ? $q : null;
    }
}
