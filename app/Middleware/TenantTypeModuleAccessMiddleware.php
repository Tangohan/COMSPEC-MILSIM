<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Community\TenantTypeConfig;
use Throwable;

/**
 * Vérifie que le module demandé est accessible selon le type de communauté.
 * Redirige vers le tableau de bord (ou 403 JSON pour les API) si le module
 * n’est pas inclus dans le profil.
 *
 * Ne touche pas la BDD tant qu’il n’y a pas de communauté en session.
 * En cas de micro-coupure MySQL : 503 API sans exception non gérée (évite la
 * tempête ERROR_ALERT sur les polls ATAK).
 */
final class TenantTypeModuleAccessMiddleware
{
    private ?TenantRepository $tenantRepository;

    public function __construct(?TenantRepository $tenantRepository = null)
    {
        $this->tenantRepository = $tenantRepository;
    }

    private function tenants(): TenantRepository
    {
        return $this->tenantRepository ??= new TenantRepository();
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $path = $request->path();
        if ($path === '/api/atak/ping' || $path === '/api/health') {
            return $next($request);
        }

        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return $next($request);
        }

        try {
            $tenant = $this->tenants()->findById($tenantId);
        } catch (Throwable $e) {
            if ($this->isDatabaseUnavailable($e)) {
                return $this->unavailableResponse($request, $e);
            }
            throw $e;
        }

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

    private function isDatabaseUnavailable(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return Database::isLostConnection($e)
            || str_contains($msg, 'Database connection failed')
            || str_contains($msg, 'SQLSTATE[HY000] [2002]')
            || str_contains($msg, 'SQLSTATE[HY000] [1045]')
            || str_contains($msg, 'Operation not permitted');
    }

    private function unavailableResponse(Request $request, Throwable $e): Response
    {
        $message = function_exists('athena_error_hint') ? athena_error_hint($e->getMessage()) : '';
        if ($message === '') {
            $message = 'Le hub n’a pas pu s’afficher pour le moment. Réessayez dans quelques instants.';
        }

        if (str_starts_with($request->path(), '/api/')) {
            return Response::json([
                'error' => 'database_unavailable',
                'message' => $message,
            ], 503)->header('Retry-After', '30');
        }

        $reference = (string) (getenv('REQUEST_ID') ?: ($_ENV['REQUEST_ID'] ?? ''));
        $response = Response::view('errors.500', [
            'errorHint' => $message,
            'errorReference' => $reference,
        ]);

        return $response->setStatusCode(503)->header('Retry-After', '30');
    }
}
