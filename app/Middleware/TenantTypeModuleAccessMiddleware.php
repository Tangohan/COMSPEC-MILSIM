<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Community\TenantTypeConfig;

/**
 * Vérifie que le module demandé est accessible selon le type de communauté.
 * Redirige vers le tableau de bord (ou 403 JSON pour les API) si le module
 * n’est pas inclus dans le profil.
 */
final class TenantTypeModuleAccessMiddleware
{
    private TenantRepository $tenantRepository;

    public function __construct(?TenantRepository $tenantRepository = null)
    {
        $this->tenantRepository = $tenantRepository ?? new TenantRepository();
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $next($request);
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return $next($request);
        }

        $tenantType = TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full'));
        if ($tenantType === TenantTypeConfig::TYPE_FULL) {
            return $next($request);
        }

        $uri = trim($request->path(), '/');
        if (TenantTypeConfig::uriAllowed($tenantType, $uri)) {
            return $next($request);
        }

        $label = TenantTypeConfig::label($tenantType);
        $message = 'Cette fonctionnalité n’est pas incluse dans le profil « ' . $label . ' » de votre communauté.';

        if (str_starts_with($request->path(), '/api/')) {
            return Response::json(['ok' => false, 'error' => $message], 403);
        }

        Session::flash('error', $message);

        return Response::redirect(url('dashboard'));
    }
}
