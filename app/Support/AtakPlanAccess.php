<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Response;
use App\Services\Platform\FeatureGateService;

/**
 * Accès plan « carte et liaisons » (feature atak) pour l’API tactique.
 * Le poste web l’applique déjà ; l’API doit refuser la même chose.
 */
final class AtakPlanAccess
{
    public const FEATURE = 'atak';

    public static function allows(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }

        try {
            $gate = Container::get(FeatureGateService::class);
            if (!$gate instanceof FeatureGateService) {
                return false;
            }

            return $gate->allows($tenantId, self::FEATURE);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function deniedJson(): Response
    {
        return Response::json([
            'error' => 'feature_unavailable',
            'message' => 'La carte et les liaisons ne sont pas disponibles pour cette communauté. Contactez votre administrateur.',
        ], 403);
    }
}
