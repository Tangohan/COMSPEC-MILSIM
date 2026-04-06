<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Moderation\ModerationRestrictionResolver;

/**
 * Blocage de l’envoi de messages internes (tenant) si sanction « messages ».
 */
final class TenantMessagesSanctionMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (!$request->isPost()) {
            return $next($request);
        }
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return $next($request);
        }
        $resolver = Container::get(ModerationRestrictionResolver::class);
        if (!$resolver->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de messages pour le moment.');

            return Response::redirect(url('messages'));
        }

        return $next($request);
    }
}
