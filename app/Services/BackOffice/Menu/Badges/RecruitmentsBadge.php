<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\EnlistmentRepository;
use App\Services\BackOffice\Menu\BadgeProvider;

/** Candidatures déposées, pas encore instruites. */
final class RecruitmentsBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        $by = (new EnlistmentRepository())->countsByStatusForTenant($tenantId);

        return ['count' => (int) ($by['submitted'] ?? 0)];
    }
}
