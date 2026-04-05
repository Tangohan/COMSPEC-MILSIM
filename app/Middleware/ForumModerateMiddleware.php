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
        $ok = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
        if (!$ok) {
            Session::flash('error', 'Vous n\'avez pas les droits de modération du forum.');
            return Response::redirect(url('forum'));
        }
        return $next($request);
    }
}
