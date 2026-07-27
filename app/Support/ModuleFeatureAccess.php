<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Response;
use App\Core\Session;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Services\Rbac\RolePermissionMatrixService;

/**
 * Contrôle d’accès unifié pour les modules back-office récents (ATAK, opérations, systèmes).
 */
final class ModuleFeatureAccess
{
    public static function allows(string $moduleKey, string $action = 'view'): bool
    {
        $gate = Gate::getInstance();
        $service = new RolePermissionMatrixService();

        return $service->canAccessModuleFeature($gate->permissionSlugs(), $moduleKey, $action);
    }

    public static function denyRedirect(string $fallbackUrl = 'back-office'): ?Response
    {
        Session::flash('error', 'Vous n’avez pas les droits pour accéder à cette section.');

        return Response::redirect(url($fallbackUrl));
    }

    public static function guardOperations(string $action = 'view'): ?Response
    {
        if (self::allows(RolePermissionMatrixCatalog::MODULE_OPERATIONS, $action)) {
            return null;
        }

        return self::denyRedirect();
    }

    public static function guardAtak(string $action = 'view', string $fallbackPath = 'back-office/atak/realisme'): ?Response
    {
        if (self::allows(RolePermissionMatrixCatalog::MODULE_ATAK, $action)) {
            return null;
        }

        return self::denyRedirect($fallbackPath);
    }

    public static function guardSystems(string $action = 'view'): ?Response
    {
        if (self::allows(RolePermissionMatrixCatalog::MODULE_SYSTEMS, $action)) {
            return null;
        }

        return self::denyRedirect('back-office/roles-permissions');
    }
}
