<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Moderation\ModerationRestrictionResolver;

/**
 * Base : refuse l’accès à un module si celui-ci figure dans les limitations du membre.
 */
abstract class ModerationModuleSanctionMiddleware
{
    abstract protected static function moduleKey(): string;

    public function __invoke(Request $request, callable $next): Response
    {
        $userId = (int) Session::get('user_id');
        $tenantId = (int) Session::get('tenant_id');
        if ($userId < 1 || $tenantId < 1) {
            return $next($request);
        }
        $key = static::moduleKey();
        $resolver = Container::get(ModerationRestrictionResolver::class);
        if (!$resolver->isModuleAllowed($tenantId, $userId, $key)) {
            Session::flash('error', 'L’accès à cette partie du portail vous est restreint pour le moment.');

            return Response::redirect(url('dashboard'));
        }

        return $next($request);
    }
}
