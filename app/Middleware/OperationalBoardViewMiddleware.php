<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Accès lecture portail au tableau opérationnel.
 */
final class OperationalBoardViewMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $gate = Gate::getInstance();
        if ($gate->allows('operational.board.view')
            || $gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system')) {
            return $next($request);
        }
        Session::flash('error', 'Vous n’avez pas accès à cette vue.');

        return Response::redirect(url('dashboard'));
    }
}
