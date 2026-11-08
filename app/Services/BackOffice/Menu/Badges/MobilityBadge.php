<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\PersonnelMobilityRequestRepository;
use App\Services\BackOffice\Menu\BadgeProvider;

/** Demandes de mobilité en attente. */
final class MobilityBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        return ['count' => (new PersonnelMobilityRequestRepository())->countPending($tenantId)];
    }
}
