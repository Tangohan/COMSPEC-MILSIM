<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Moderation\ModerationRestrictionResolver;

/**
 * Après AuthMiddleware : accès forum selon sanctions (lecture / écriture).
 */
final class ForumSanctionMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return $next($request);
        }
        $resolver = Container::get(ModerationRestrictionResolver::class);
        $path = $request->path();
        $isForumArea = str_starts_with($path, '/forum') || str_starts_with($path, '/api/forum');
        if (!$isForumArea) {
            return $next($request);
        }
        if (str_contains($path, '/forum/moderation')) {
            return $next($request);
        }
        if (!$resolver->canReadForum($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas consulter le forum dans l’état actuel de votre compte.');

            return Response::redirect(url('dashboard'));
        }
        if (!$request->isPost()) {
            return $next($request);
        }
        $postOkReadOnly = str_contains($path, '/subscribe') || str_contains($path, '/unsubscribe');
        if ($postOkReadOnly) {
            return $next($request);
        }
        if (!$resolver->canWriteForum($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas publier ou interagir sur le forum pour le moment.');

            return str_starts_with($path, '/api/')
                ? (new Response())->setStatusCode(403)->setBody('Accès restreint.')
                : Response::redirect(url('forum'));
        }

        return $next($request);
    }
}
