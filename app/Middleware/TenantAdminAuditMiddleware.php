<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Tenant\TenantAdminAuditService;
use App\Services\Tenant\TenantContext;
use Throwable;

final class TenantAdminAuditMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $audit = new TenantAdminAuditService();
        $mutating = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        // Fail closed: persist an intent before business code can mutate anything.
        // Modules then enrich this request trail with their entity snapshots.
        if ($mutating) {
            $audit->recordAction('request_started', $request->method() . ' ' . $request->path(), [], 'http');
        }
        try {
            $response = $next($request);
            if ($mutating && TenantContext::isIntervention()) {
                $audit->recordAction('request_completed', $request->method() . ' ' . $request->path(), [
                    'status' => $response->statusCode(),
                ], 'http');
            }
            return $response;
        } catch (Throwable $e) {
            $audit->recordError($e, $request->path(), 'http');
            throw $e;
        }
    }
}
