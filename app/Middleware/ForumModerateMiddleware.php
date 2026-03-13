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
        if (!function_exists('can') || !can('forum.moderate')) {
            Session::flash('error', 'Vous n\'avez pas les droits de modération du forum.');
            return Response::redirect(url('forum'));
        }
        return $next($request);
    }
}
