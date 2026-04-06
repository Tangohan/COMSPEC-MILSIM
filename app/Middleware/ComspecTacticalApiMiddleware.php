<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Support\ComspecApiKeyAuth;

/**
 * Exige une clé API pour les routes tactiques en production (config/tactical_api.php).
 */
final class ComspecTacticalApiMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $path = $request->path();
        $block = ComspecApiKeyAuth::enforceForTacticalPath($path);
        if ($block !== null) {
            return $block;
        }

        return $next($request);
    }
}
