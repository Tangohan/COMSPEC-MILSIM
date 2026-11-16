<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\UnitRepository;
use App\Services\BackOffice\Menu\BadgeProvider;

/**
 * Postes de chef à pourvoir : unités de la communauté sans chef désigné. Même compte que la pastille de l’onglet
 * « Chaîne de commandement » de l’espace Effectifs (UnitRepository::countWithoutCommander).
 */
final class CommandChainVacanciesBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        return ['count' => (new UnitRepository())->countWithoutCommander($tenantId)];
    }
}
