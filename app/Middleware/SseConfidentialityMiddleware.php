<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Sse\SseAccessCodeService;

/**
 * Exige l’engagement de confidentialité de la session SSE courante.
 */
final class SseConfidentialityMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $svc = new SseAccessCodeService();
        if (!$svc->hasAcceptedConfidentiality()) {
            return Response::redirect(url('atak/sse/confidentialite'));
        }

        return $next($request);
    }
}
