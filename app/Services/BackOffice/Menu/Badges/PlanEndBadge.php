<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\TenantRepository;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Support\PlanAccessWindow;

/**
 * Formule de la communauté qui se termine dans les 7 jours, ou terminée depuis moins de 14 jours : pastille rouge « 1 ».
 * Calcul pur sur la ligne tenants déjà lue (PlanAccessWindow), sans autre requête.
 */
final class PlanEndBadge implements BadgeProvider
{
    private const WARNING_DAYS = 7;

    private const LAPSED_DAYS = 14;

    public function badge(int $tenantId, int $userId): ?array
    {
        $tenant = (new TenantRepository())->findById($tenantId);
        if (!is_array($tenant)) {
            return null;
        }
        $now = time();
        $hit = PlanAccessWindow::endingSoon($tenant, self::WARNING_DAYS, $now) !== null;
        if (!$hit) {
            $lapsed = PlanAccessWindow::lapsed($tenant, $now);
            $endedTs = $lapsed !== null ? strtotime($lapsed['ended_at']) : false;
            $hit = $endedTs !== false && $endedTs >= $now - self::LAPSED_DAYS * 86400;
        }

        return ['count' => $hit ? 1 : 0, 'important' => $hit];
    }
}
