<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Création / modification d’entrées (back-office étendu ou rôle dédié).
 */
final class OperationalBoardEditMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $gate = Gate::getInstance();
        if ($gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system')) {
            return $next($request);
        }
        Session::flash('error', 'Vous n’avez pas les droits pour modifier le tableau opérationnel.');

        return Response::redirect(url('dashboard'));
    }
}
