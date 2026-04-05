<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;

/**
 * Le back-office et modules équivalents nécessitent une communauté réelle (pas le tenant système slug default).
 */
final class NonDefaultTenantContextGuard
{
    public static function redirectIfInvalid(): ?Response
    {
        $gate = \App\Core\Gate::getInstance();
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Aucune communauté active. Sélectionnez une communauté pour continuer.');
            if ($gate->allows('admin.system')) {
                return Response::redirect(url('admin'));
            }
            return Response::redirect(url('communities'));
        }

        $tenant = (new TenantRepository())->findById($tenantId);
        if ($tenant === null) {
            Session::flash('error', 'Communauté introuvable.');
            return Response::redirect(url('dashboard'));
        }
        if (($tenant['slug'] ?? '') === 'default') {
            Session::flash('error', 'Cette section concerne une communauté dédiée. Rejoignez ou créez une communauté, ou changez de contexte depuis le portail.');
            if ($gate->allows('admin.system')) {
                return Response::redirect(url('admin'));
            }
            return Response::redirect(url('communities'));
        }

        return null;
    }
}
