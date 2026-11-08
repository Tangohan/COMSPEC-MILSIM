<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

use App\Core\Container;
use App\Services\Platform\FeatureGateService;

/**
 * Accès aux kits de départ de la communauté et à l'assistant de création de tenue (clé de formule `equipment_kits`,
 * Pro et plus par défaut). Consulter et créer ses propres tenues reste ouvert à toutes les formules : seule cette
 * clé ferme les kits et l'assistant. La synchro des tenues avec le jeu garde sa clé `equipment_game_sync`.
 */
final class KitsAccess
{
    public const FEATURE = 'equipment_kits';

    public static function allows(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        try {
            return Container::get(FeatureGateService::class)->allowsWithCatalogDefault($tenantId, self::FEATURE);
        } catch (\Throwable $e) {
            error_log('[equipment-kits] plan check failed: ' . $e->getMessage());

            return false;
        }
    }
}
