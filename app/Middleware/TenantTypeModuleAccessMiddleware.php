<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Community\TenantTypeConfig;

/**
 * Vérifie que le module demandé est accessible selon le type de tenant.
 * Redirige vers le tableau de bord si le module n'est pas autorisé.
 */
final class TenantTypeModuleAccessMiddleware
{
    private TenantRepository $tenantRepository;

    public function __construct(?TenantRepository $tenantRepository = null)
    {
        $this->tenantRepository = $tenantRepository ?? new TenantRepository();
    }

    public function handle(Request $request, callable $next): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $next($request);
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return $next($request);
        }

        $tenantType = (string) ($tenant['tenant_type'] ?? 'full');
        if ($tenantType === TenantTypeConfig::TYPE_FULL) {
            return $next($request);
        }

        $uri = trim($request->uri(), '/');
        $module = $this->extractModuleFromUri($uri);

        if ($module !== null && !TenantTypeConfig::moduleAllowed($tenantType, $module)) {
            Session::flash('error', 'Ce module n\'est pas accessible pour votre type de communauté.');

            return Response::redirect(url('dashboard'));
        }

        return $next($request);
    }

    private function extractModuleFromUri(string $uri): ?string
    {
        $segments = explode('/', $uri);
        if (count($segments) < 1) {
            return null;
        }

        $moduleMap = [
            'forum' => 'forum',
            'documents' => 'documents',
            'training' => 'training',
            'courses' => 'training',
            'recruitment' => 'recruitment',
            'operations' => 'operations',
            'missions' => 'operations',
            'atak' => 'atak',
            'cooperation' => 'cooperation',
            'messages' => 'messages',
            'analytics' => 'analytics',
            'personnel' => 'personnel',
        ];

        foreach ($segments as $seg) {
            if (isset($moduleMap[$seg])) {
                return $moduleMap[$seg];
            }
        }

        return null;
    }
}
