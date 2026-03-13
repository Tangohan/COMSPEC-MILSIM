<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class GuestMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (Session::get('user_id')) {
            return Response::redirect(url('dashboard'));
        }
        return $next($request);
    }
}
