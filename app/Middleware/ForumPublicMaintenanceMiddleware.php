<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Interrompt les pages membres du forum lorsque l’interrupteur produit est fermé.
 * Les outils internes d’administration du forum ne passent pas par ce filtre.
 */
final class ForumPublicMaintenanceMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (function_exists('forum_product_public_enabled') && forum_product_public_enabled()) {
            return $next($request);
        }

        $path = $request->path();
        if (str_starts_with($path, '/api/')) {
            if (function_exists('forum_api_disabled_response')) {
                $blocked = forum_api_disabled_response(0);
                if ($blocked !== null) {
                    return $blocked;
                }
            }

            return Response::json([
                'success' => false,
                'error' => 'Le forum est temporairement indisponible.',
            ], 503);
        }

        if (function_exists('forum_public_maintenance_response')) {
            return forum_public_maintenance_response();
        }

        return Response::view('forum.maintenance', [
            'title' => 'Le forum est temporairement indisponible',
        ])->setStatusCode(503);
    }
}
