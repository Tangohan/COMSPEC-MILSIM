<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;

/**
 * Habilitations du module coopération (pilotage / réponse), partagées par les contrôleurs.
 */
final class CooperationAccess
{
    public static function canManage(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $gate = Gate::getInstance();

        return can('interteam.missions.manage')
            || can('cooperation.missions.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');
    }

    public static function canRespond(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $gate = Gate::getInstance();

        return can('interteam.missions.respond')
            || can('cooperation.missions.respond')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system')
            || self::canManage();
    }

    public static function canCreate(): bool
    {
        return self::canManage() || (function_exists('can') && can('cooperation.missions.create'));
    }
}
