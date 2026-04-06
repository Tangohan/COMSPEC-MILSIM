<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

final class RequestIdMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $incoming = trim((string) ($request->server['HTTP_X_REQUEST_ID'] ?? ''));
        $rid = $incoming !== '' ? $incoming : bin2hex(random_bytes(8));
        $_ENV['REQUEST_ID'] = $rid;
        putenv('REQUEST_ID=' . $rid);
        $response = $next($request);
        $response->header('X-Request-Id', $rid);

        return $response;
    }
}
