<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Core\Gate;

/**
 * Qui peut composer les raccourcis et la vitrine de tenues du tableau de bord.
 *
 * Périmètre communauté uniquement : un gestionnaire d’organisation n’est pas
 * administrateur de la plateforme. {@see SystemReservedPermissions}.
 */
final class DashboardPinsAccess
{
    public static function canManage(?Gate $gate = null): bool
    {
        $gate ??= Gate::getInstance();

        return $gate->allows('dashboard.pins.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
    }
}
