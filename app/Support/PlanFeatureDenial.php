<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;

/**
 * Réponse UI quand une fonctionnalité de formule est absente.
 */
final class PlanFeatureDenial
{
    public static function upgradeView(string $featureKey, string $suggestedPlanLabel = 'Standard ou Pro'): Response
    {
        return Response::view('layout.main', [
            'title' => 'Offre supérieure requise',
            'content' => 'platform.upgrade',
            'feature' => SubscriptionPlanFeaturesCatalog::featureLabel($featureKey),
            'featureKey' => $featureKey,
            'planName' => $suggestedPlanLabel,
        ]);
    }
}
