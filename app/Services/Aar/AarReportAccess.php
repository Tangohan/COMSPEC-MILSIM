<?php

declare(strict_types=1);

namespace App\Services\Aar;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\ModuleFeatureAccess;

/**
 * Qui lit et qui traite les comptes rendus post-op : une seule règle pour le back-office (/back-office/atak/comptes-rendus)
 * et le JNET (/jnet/comptes-rendus).
 *
 * - Lire : les conditions des routes du back-office, sans la redirection. Gestionnaire de la communauté (admin.organization,
 *   admin.access) ou administrateur de la plateforme (TenantResourceAdminMiddleware), dans une communauté réelle
 *   (NonDefaultTenantContextGuard), avec le module ATAK en lecture (ModuleFeatureAccess::guardAtak('view')).
 * - Traiter (statut, actions) : en plus, le module ATAK en gestion (guardAtak('manage')).
 *
 * Un membre, ou un chef d'unité sans ces droits, ne lit aucun compte rendu post-op : le back-office ne lui en montre pas.
 */
final class AarReportAccess
{
    public static function canRead(): bool
    {
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.system') && !$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            return false;
        }
        if (!ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'view')) {
            return false;
        }

        return self::realCommunity();
    }

    /** Même test que NonDefaultTenantContextGuard, sans le message qu'il pose en session quand il refuse. */
    private static function realCommunity(): bool
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            return false;
        }
        try {
            $tenant = (new TenantRepository())->findById($tenantId);
        } catch (\Throwable) {
            return false;
        }

        return is_array($tenant) && ($tenant['slug'] ?? '') !== 'default';
    }

    public static function canManage(): bool
    {
        return self::canRead() && ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_ATAK, 'manage');
    }
}
