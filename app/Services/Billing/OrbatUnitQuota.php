<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Core\Container;
use App\Repositories\UnitRepository;
use App\Services\Platform\FeatureGateService;

/**
 * Plafond d'unités de l'ORBAT par formule (max_orbat_units, 0 = illimité). Les unités déjà créées restent utilisables
 * et les unités archivées ne comptent pas. Ne s'applique qu'aux créations demandées par un membre : la structure de
 * départ d'une nouvelle communauté n'est pas concernée.
 */
final class OrbatUnitQuota
{
    /** Unités encore créables ; null si la formule est illimitée. */
    public static function remaining(int $tenantId): ?int
    {
        $cap = Container::get(FeatureGateService::class)->intWithCatalogDefault($tenantId, 'max_orbat_units');

        return self::remainingFor($cap, Container::get(UnitRepository::class)->countForPlanQuota($tenantId));
    }

    public static function remainingFor(int $cap, int $used): ?int
    {
        return $cap <= 0 ? null : max(0, $cap - $used);
    }

    /** Message à afficher si la formule interdit une nouvelle unité, sinon null. */
    public static function blockMessage(int $tenantId): ?string
    {
        if (self::remaining($tenantId) !== 0) {
            return null;
        }
        $cap = Container::get(FeatureGateService::class)->intWithCatalogDefault($tenantId, 'max_orbat_units');

        return 'Votre formule est limitée à ' . $cap . ' unités dans l’ORBAT. Passez à une formule supérieure pour en ajouter.';
    }
}
