<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\PersonnelCorrectionRequestRepository;
use App\Services\BackOffice\Menu\BadgeProvider;

/** Corrections de fiche demandées par les membres, en attente de validation. */
final class CorrectionsBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        return ['count' => (new PersonnelCorrectionRequestRepository())->countPendingForTenant($tenantId)];
    }
}
