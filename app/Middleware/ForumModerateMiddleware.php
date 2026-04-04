<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class ForumModerateMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $gate = \App\Core\Gate::getInstance();
        $ok = (function_exists('can') && can('forum.moderate'))
            || (function_exists('can') && can('forum.moderate_organization'))
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
        if (!$ok) {
            Session::flash('error', 'Vous n\'avez pas les droits de modération du forum.');
            return Response::redirect(url('forum'));
        }
        return $next($request);
    }
}
