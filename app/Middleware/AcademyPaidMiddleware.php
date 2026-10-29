<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Academy\AcademyAccess;

final class AcademyPaidMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $tenant = Container::get(TenantRepository::class)->findById((int) Session::get('tenant_id'));
        if (!$tenant || !AcademyAccess::paid($tenant)) {
            return Response::redirect(url('academy?access=paid'));
        }
        return $next($request)->header('Cache-Control', 'private, no-store');
    }
}
