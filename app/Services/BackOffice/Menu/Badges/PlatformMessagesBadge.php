<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Repositories\TenantRepository;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\Platform\PlatformBoMessageService;

/** Messages de la plateforme visibles pour la communauté et pas encore ouverts par la personne (rouge si l'un est urgent). */
final class PlatformMessagesBadge implements BadgeProvider
{
    public function badge(int $tenantId, int $userId): ?array
    {
        $tenant = (new TenantRepository())->findById($tenantId);
        if (!is_array($tenant)) {
            return null;
        }

        return (new PlatformBoMessageService())->unreadCount($tenant, $userId);
    }
}
