<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Services\Security\AccessControlService;

final class AccessControlMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return $next($request);
        }

        $user = Container::get(UserRepository::class)->findById($userId, $tenantId);
        if (!$user) {
            return Response::redirect(url('login'));
        }

        [$resource, $action] = $this->resolveResourceAction($request);
        $acs = Container::get(AccessControlService::class);
        if (!$acs->canAccess($user, $resource, $action)) {
            if (str_starts_with($request->path(), '/api/')) {
                return Response::json(['ok' => false, 'error' => 'Access denied by access policy.'], 403);
            }

            Session::flash('error', 'Accès refusé par les règles de sécurité de la communauté.');

            return Response::redirect(url('dashboard'));
        }

        return $next($request);
    }

    /** @return array{0:string,1:string} */
    private function resolveResourceAction(Request $request): array
    {
        $path = trim($request->path(), '/');
        $segment = strtolower((string) explode('/', $path)[0]);
        $module = match ($segment) {
            'documents' => 'documents',
            'courrier' => 'courrier',
            'formation', 'formations' => 'training',
            'back-office' => 'admin',
            default => 'portal',
        };

        $action = match ($request->method()) {
            'GET' => 'READ',
            'POST' => 'CREATE',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => 'READ',
        };

        return [$module, $action];
    }
}
