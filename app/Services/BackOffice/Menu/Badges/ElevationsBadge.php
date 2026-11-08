<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\ElevationRequestRepository;
use App\Services\BackOffice\Menu\BadgeProvider;

/** Demandes d'accès élevé ouvertes (en attente ou en examen). */
final class ElevationsBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        return ['count' => (new ElevationRequestRepository())->countOpenForTenant($tenantId)];
    }
}
