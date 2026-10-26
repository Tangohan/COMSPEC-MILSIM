<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;

/**
 * « Mettre en avant dans le registre » est inclus dans certaines formules (clé registry_featured).
 * Sans la formule, le réglage de la communauté est ignoré : pas de badge ni de place en tête.
 */
final class RegistryFeatured
{
    public const FEATURE = 'registry_featured';

    /** @var array<string, bool> */
    private static array $cache = [];

    /** @param array<string, mixed> $tenant Ligne tenants (settings en JSON ou décodé). */
    public static function planAllows(array $tenant): bool
    {
        if ((string) ($tenant['subscription_status'] ?? '') === 'past_due'
            && PlanGift::activeFromSettings($tenant['settings'] ?? null) === null) {
            return false;
        }

        return self::slugAllows(PlanGift::effectivePlanSlug($tenant));
    }

    public static function slugAllows(string $planSlug): bool
    {
        if (array_key_exists($planSlug, self::$cache)) {
            return self::$cache[$planSlug];
        }
        $allowed = null;
        try {
            $plan = Container::get(SubscriptionPlanRepository::class)->findBySlug($planSlug);
            $features = $plan ? json_decode((string) ($plan['features_json'] ?? '{}'), true) : null;
            if (is_array($features) && array_key_exists(self::FEATURE, $features)) {
                $allowed = !empty($features[self::FEATURE]);
            }
        } catch (\Throwable) {
        }
        // Formule créée avant l’ajout de la clé : défaut du catalogue.
        $allowed ??= !empty(SubscriptionPlanFeaturesCatalog::defaultsForSlug($planSlug)[self::FEATURE] ?? false);

        return self::$cache[$planSlug] = $allowed;
    }

    /** Pour les tests. */
    public static function resetCache(): void
    {
        self::$cache = [];
    }
}
