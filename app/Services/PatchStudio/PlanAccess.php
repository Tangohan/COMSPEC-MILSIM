<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\TenantRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\PlanGift;

/** Même formule effective que le reste d'ATHENA (offres, essai, expiration et impayés). */
final class PlanAccess
{
    public const FEATURE = 'patch_studio';

    public static function allows(int $tenantId): bool
    {
        if ($tenantId < 1) return false;
        try {
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
            if (!$tenant || !self::eligible($tenant)) return false;
            return Container::get(FeatureGateService::class)->allowsWithCatalogDefault($tenantId, self::FEATURE);
        } catch (\Throwable $e) {
            error_log('Patch Studio plan: ' . $e->getMessage());
            return false;
        }
    }

    public static function eligible(array $tenant): bool
    {
        return PlanGift::rank(\App\Services\Platform\EffectivePlan::slugFor($tenant)) >= PlanGift::rank('pro_plus');
    }

    public static function deniedJson(): Response
    {
        return Response::json([
            'error' => 'feature_unavailable',
            'feature' => self::FEATURE,
            'required_plan' => 'pro_plus',
            'message' => 'ATHENA Patch Studio est réservé aux communautés disposant d’une formule Pro+ minimum.',
        ], 403)->header('Cache-Control', 'private, no-store');
    }
}
