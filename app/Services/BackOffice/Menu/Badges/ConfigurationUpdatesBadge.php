<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Core\Container;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;

/** Étapes de « Mise à niveau » encore à faire ; pastille rouge si l'une est obligatoire ou bloquante. */
final class ConfigurationUpdatesBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        $summary = Container::get(ConfigurationUpdateService::class)->hubSummary($tenantId);

        return [
            'count' => (int) ($summary['nav_badge'] ?? 0),
            'important' => (int) ($summary['counts']['required'] ?? 0) > 0,
        ];
    }
}
