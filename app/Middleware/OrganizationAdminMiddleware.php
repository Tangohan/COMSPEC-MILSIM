<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class OrganizationAdminMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Authentification requise.');
            return Response::redirect(url('login'));
        }
        $gate = \App\Core\Gate::getInstance();
        if ($gate->deny('admin.organization') && $gate->deny('admin.access')) {
            Session::flash('error', 'Accès réservé aux administrateurs organisationnels.');
            return Response::redirect(url('admin'));
        }
        return $next($request);
    }
}
