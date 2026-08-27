<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SystemAdminMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!Session::get('user_id')) {
            Session::flash('error', 'Connectez-vous pour continuer.');
            return Response::redirect(url('login'));
        }
        $gate = \App\Core\Gate::getInstance();
        if ($gate->deny('admin.system')) {
            Session::flash('error', 'Accès réservé aux super-administrateurs.');
            if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
                return Response::redirect(url('back-office'));
            }
            return Response::redirect(url('dashboard'));
        }
        return $next($request);
    }
}
